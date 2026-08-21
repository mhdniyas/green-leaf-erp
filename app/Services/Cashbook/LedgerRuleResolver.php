<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use Carbon\Carbon;
use RuntimeException;

/**
 * Resolves which ShopLedgerEntrySetting governs a given shop + entry type
 * on a given business date (versioning rules apply).
 *
 * Precedence:
 *   1. Shop-specific setting version effective on the business date
 *   2. Existing disabled setting -> auto-activates with standard defaults
 *   3. Auto-provisions default setting if known entry type
 *   4. (none found) → hard error, forcing explicit configuration
 */
class LedgerRuleResolver
{
    public function resolve(int $shopId, int $entryTypeId, string $businessDate): ShopLedgerEntrySetting
    {
        $date = Carbon::parse($businessDate)->toDateString();

        $setting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $shopId)
            ->where('entry_type_id', $entryTypeId)
            ->where('enabled', true)
            ->effectiveOn($date)
            ->orderByDesc('version')
            ->first();

        if ($setting) {
            return $setting;
        }

        // Check if an existing setting exists that can be activated
        $existing = ShopLedgerEntrySetting::query()
            ->where('shop_id', $shopId)
            ->where('entry_type_id', $entryTypeId)
            ->orderByDesc('version')
            ->first();

        $entryType = LedgerEntryType::find($entryTypeId);

        if ($existing) {
            $existing->update([
                'enabled' => true,
                'effective_from' => $existing->effective_from ?? '2026-01-01',
                'settlement_behavior' => $this->defaultSettlementBehavior($entryType?->code, $existing->settlement_behavior),
                'petty_behavior' => $this->defaultPettyBehavior($entryType?->code, $existing->petty_behavior),
                'company_pending_behavior' => $this->defaultCompanyPendingBehavior($entryType?->code, $existing->company_pending_behavior),
            ]);

            return $existing->fresh();
        }

        if ($entryType) {
            $isIncome = $entryType->category === 'income';
            $isExpense = $entryType->category === 'expense';
            $isSettlement = $entryType->category === 'settlement';
            $isTransfer = $entryType->category === 'transfer';

            return ShopLedgerEntrySetting::create([
                'shop_id' => $shopId,
                'entry_type_id' => $entryTypeId,
                'version' => 1,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'enabled' => true,
                'default_funding_source' => $this->defaultFundingSource($entryType),
                'allowed_funding_sources' => $this->defaultAllowedFundingSources($entryType),
                'include_in_sales' => $isIncome,
                'include_in_income' => $isIncome,
                'include_in_expense' => $isExpense,
                'include_in_pl' => ! $isSettlement && ! $isTransfer,
                'settlement_behavior' => $this->defaultSettlementBehavior($entryType->code, 'none'),
                'petty_behavior' => $this->defaultPettyBehavior($entryType->code, 'none'),
                'company_pending_behavior' => $this->defaultCompanyPendingBehavior($entryType->code, 'none'),
                'generates_secondary_entry' => false,
                'secondary_entry_type_id' => null,
                'secondary_amount_mode' => 'same_amount',
                'secondary_amount_value' => null,
                'display_order' => (int) ($entryType->display_order ?? 0),
            ]);
        }

        throw new RuntimeException(
            "No ledger configuration found for shop {$shopId}, entry type {$entryTypeId} on {$date}. ".
            'Apply a profile template or add a shop_ledger_entry_settings row before posting this entry.'
        );
    }

    private function defaultSettlementBehavior(?string $code, ?string $current): string
    {
        if (in_array($code, ['shop_paid_company', 'sales_to_company', 'sales_to_petty'], true)) {
            return 'decrease';
        }

        return $current ?: 'none';
    }

    private function defaultPettyBehavior(?string $code, ?string $current): string
    {
        if (in_array($code, ['company_to_petty', 'sales_to_petty', 'bank_to_petty', 'petty_reimbursement'], true)) {
            return 'increase';
        }
        if ($code === 'petty_to_company') {
            return 'decrease';
        }

        return $current ?: 'none';
    }

    private function defaultCompanyPendingBehavior(?string $code, ?string $current): string
    {
        if (in_array($code, ['company_paid_shop', 'petty_reimbursement'], true)) {
            return 'decrease';
        }

        return $current ?: 'none';
    }

    private function defaultFundingSource(LedgerEntryType $entryType): string
    {
        return match ($entryType->code) {
            'company_to_petty', 'company_paid_shop', 'company_paid_vendor', 'petty_reimbursement' => 'company',
            'bank_to_petty' => 'bank',
            'petty_to_company' => 'petty',
            default => $entryType->category === 'expense' ? 'sales' : 'none',
        };
    }

    private function defaultAllowedFundingSources(LedgerEntryType $entryType): array
    {
        return match ($entryType->code) {
            'shop_paid_company' => ['sales', 'company'],
            'company_to_petty', 'company_paid_shop', 'company_paid_vendor', 'petty_reimbursement' => ['company'],
            'bank_to_petty' => ['bank'],
            'petty_to_company' => ['petty'],
            default => $entryType->category === 'expense'
                ? ['sales', 'petty', 'company', 'company_later']
                : ['none', 'sales', 'bank'],
        };
    }
}
