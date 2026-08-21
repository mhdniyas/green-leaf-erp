<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\LedgerDirection;
use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionGenerator
{
    public function __construct(
        private readonly LedgerRuleResolver $ruleResolver,
        private readonly FundingSourceEffectResolver $effectResolver,
    ) {}

    /**
     * Record a single operator-entered ledger line, plus any secondary entry
     * it's configured to generate (e.g. Income CP → Expense CP).
     *
     * $input keys:
     *   shop_id, business_date, entry_type_code, amount,
     *   funding_source (optional override of the default),
     *   reference_type, reference_id, notes, entered_by
     */
    public function record(array $input): ShopLedgerTransaction
    {
        return DB::transaction(function () use ($input) {
            $entryType = LedgerEntryType::where('code', $input['entry_type_code'])
                ->where('active', true)
                ->first();

            if (! $entryType) {
                $entryType = LedgerEntryType::where('code', $input['entry_type_code'])->firstOrFail();
            }

            $setting = $this->ruleResolver->resolve(
                (int) $input['shop_id'],
                $entryType->id,
                $input['business_date']
            );

            $fundingSourceInput = $input['funding_source'] ?? null;
            if ($fundingSourceInput && $fundingSourceInput !== 'none') {
                $fundingSource = FundingSource::tryFrom((string) $fundingSourceInput) ?? FundingSource::None;
            } else {
                $fundingSource = FundingSource::tryFrom((string) ($setting->default_funding_source ?? 'none')) ?? FundingSource::None;
            }

            $this->assertFundingSourceAllowed($fundingSource, $setting);

            $direction = LedgerDirection::tryFrom((string) $entryType->category) ?? LedgerDirection::Expense;
            $amount = round((float) $input['amount'], 2);

            $effect = $this->effectResolver->resolve($direction, $fundingSource, $amount, $setting);

            $transaction = ShopLedgerTransaction::create([
                'shop_id' => $input['shop_id'],
                'business_date' => $input['business_date'],
                'entry_type_id' => $entryType->id,
                'amount' => $amount,
                'direction' => $direction->value,
                'funding_source' => $fundingSource->value,
                'affects_sales' => (bool) $setting->include_in_sales,
                'affects_income' => (bool) $setting->include_in_income,
                'affects_expense' => (bool) $setting->include_in_expense,
                'affects_pl' => (bool) $setting->include_in_pl,
                'pl_delta' => $effect->plDelta,
                'settlement_delta' => $effect->settlementDelta,
                'settlement_direction' => $effect->settlementDirection->value,
                'petty_delta' => $effect->pettyDelta,
                'petty_direction' => $effect->pettyDirection->value,
                'company_pending_delta' => $effect->companyPendingDelta,
                'company_pending_direction' => $effect->companyPendingDirection->value,
                'generated_by_rule' => false,
                'status' => TransactionStatus::Posted->value,
                'reference_type' => $input['reference_type'] ?? null,
                'reference_id' => $input['reference_id'] ?? null,
                'notes' => $input['notes'] ?? null,
                'entered_by' => $input['entered_by'] ?? null,
            ]);

            if ($setting->generates_secondary_entry && $setting->secondary_entry_type_id) {
                $this->generateSecondaryEntry($transaction, $setting);
            }

            return $transaction->fresh();
        });
    }

    /**
     * An income entry can auto-generate a linked expense so the pair nets to
     * zero on P/L, without a second manual entry ever being required.
     * The child is fully derived — never independently editable — and its
     * amount tracks the parent.
     */
    private function generateSecondaryEntry(ShopLedgerTransaction $parent, ShopLedgerEntrySetting $parentSetting): void
    {
        $secondaryType = LedgerEntryType::findOrFail($parentSetting->secondary_entry_type_id);

        $secondaryAmount = $parentSetting->secondary_amount_mode === 'percentage'
            ? round($parent->amount * ((float) $parentSetting->secondary_amount_value / 100), 2)
            : (float) $parent->amount;

        ShopLedgerTransaction::create([
            'shop_id' => $parent->shop_id,
            'business_date' => $parent->business_date,
            'entry_type_id' => $secondaryType->id,
            'amount' => $secondaryAmount,
            'direction' => LedgerDirection::Expense->value,
            'funding_source' => FundingSource::None->value,
            'affects_sales' => false,
            'affects_income' => false,
            'affects_expense' => true,
            'affects_pl' => true,
            'pl_delta' => -$secondaryAmount,
            'settlement_delta' => 0,
            'settlement_direction' => 'none',
            'petty_delta' => 0,
            'petty_direction' => 'none',
            'company_pending_delta' => 0,
            'company_pending_direction' => 'none',
            'generated_by_rule' => true,
            'parent_transaction_id' => $parent->id,
            'status' => TransactionStatus::Posted->value,
            'entered_by' => $parent->entered_by,
        ]);
    }

    /**
     * Update an entry with double-entry recalculation for amount, funding source, and notes.
     */
    public function updateEntry(ShopLedgerTransaction $transaction, float $newAmount, ?string $newFundingSource = null, ?string $notes = null, ?int $updatedBy = null): ShopLedgerTransaction
    {
        if ($transaction->generated_by_rule) {
            throw new RuntimeException('Generated entries cannot be edited directly; edit the parent transaction instead.');
        }

        return DB::transaction(function () use ($transaction, $newAmount, $newFundingSource, $notes) {
            $newAmount = round($newAmount, 2);
            $setting = $this->ruleResolver->resolve($transaction->shop_id, $transaction->entry_type_id, $transaction->business_date->toDateString());

            $sourceStr = $newFundingSource && $newFundingSource !== 'none'
                ? $newFundingSource
                : ($transaction->funding_source ?: ($setting->default_funding_source ?: 'none'));

            $source = FundingSource::tryFrom((string) $sourceStr) ?? FundingSource::None;
            $this->assertFundingSourceAllowed($source, $setting);

            $direction = LedgerDirection::tryFrom((string) $transaction->direction) ?? LedgerDirection::from($transaction->entryType?->category ?? 'expense');
            $effect = $this->effectResolver->resolve($direction, $source, $newAmount, $setting);

            $updatePayload = [
                'amount' => $newAmount,
                'funding_source' => $source->value,
                'pl_delta' => $effect->plDelta,
                'settlement_delta' => $effect->settlementDelta,
                'settlement_direction' => $effect->settlementDirection->value,
                'petty_delta' => $effect->pettyDelta,
                'petty_direction' => $effect->pettyDirection->value,
                'company_pending_delta' => $effect->companyPendingDelta,
                'company_pending_direction' => $effect->companyPendingDirection->value,
            ];

            if ($notes !== null) {
                $updatePayload['notes'] = $notes ?: null;
            }

            $transaction->update($updatePayload);

            foreach ($transaction->children as $child) {
                $childSetting = $this->ruleResolver->resolve($child->shop_id, $child->entry_type_id, $child->business_date->toDateString());
                $childAmount = $childSetting->secondary_amount_mode === 'percentage'
                    ? round($newAmount * ((float) $childSetting->secondary_amount_value / 100), 2)
                    : $newAmount;

                $child->update(['amount' => $childAmount, 'pl_delta' => -$childAmount]);
            }

            return $transaction->fresh('children');
        });
    }

    /**
     * Backwards-compatible wrapper for updating transaction amount.
     */
    public function updateAmount(ShopLedgerTransaction $transaction, float $newAmount, ?int $updatedBy = null): ShopLedgerTransaction
    {
        return $this->updateEntry($transaction, $newAmount, null, null, $updatedBy);
    }

    /**
     * Never hard-delete. Void the transaction and cascade the void to any
     * generated children so reports stay auditable and internally consistent.
     */
    public function void(ShopLedgerTransaction $transaction, int $voidedBy, string $reason): ShopLedgerTransaction
    {
        return DB::transaction(function () use ($transaction, $voidedBy, $reason) {
            $transaction->update([
                'status' => TransactionStatus::Void->value,
                'voided_by' => $voidedBy,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            foreach ($transaction->children()->where('status', '!=', TransactionStatus::Void->value)->get() as $child) {
                $this->void($child, $voidedBy, "Parent transaction #{$transaction->id} voided");
            }

            return $transaction->fresh();
        });
    }

    private function assertFundingSourceAllowed(FundingSource $source, ShopLedgerEntrySetting $setting): void
    {
        if ($source === FundingSource::None) {
            return;
        }

        $allowed = $setting->allowed_funding_sources ?? [];
        if (! empty($allowed) && ! in_array($source->value, $allowed, true)) {
            if ($setting->default_funding_source === $source->value) {
                return;
            }
            throw new RuntimeException("Funding source [{$source->value}] is not allowed for this entry type on this shop.");
        }
    }
}
