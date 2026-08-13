<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\LedgerDirection;
use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\ShopInvoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class InvoiceCashbookProjectionService
{
    public function __construct(
        private readonly LedgerRuleResolver $ruleResolver,
        private readonly FundingSourceEffectResolver $effectResolver,
        private readonly BalanceCalculator $balanceCalculator,
    ) {}

    public function syncInvoice(ShopInvoice $invoice, ?int $userId = null): ?ShopLedgerTransaction
    {
        $purchaseBillType = LedgerEntryType::query()
            ->where('code', 'purchase_bill')
            ->where('active', true)
            ->first();

        if (! $purchaseBillType instanceof LedgerEntryType) {
            return null;
        }

        $invoice->refresh();
        $businessDate = $invoice->business_date?->toDateString() ?? today()->toDateString();
        $amount = round((float) $invoice->final_total, 2);
        $shouldVoid = $invoice->status === 'cancelled' || $amount <= 0.0;

        try {
            $setting = $this->ruleResolver->resolve((int) $invoice->shop_id, (int) $purchaseBillType->id, $businessDate);
        } catch (RuntimeException) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $purchaseBillType, $businessDate, $amount, $shouldVoid, $userId, $setting): ShopLedgerTransaction {
            $transaction = ShopLedgerTransaction::query()
                ->where('shop_id', $invoice->shop_id)
                ->where('entry_type_id', $purchaseBillType->id)
                ->where('reference_type', ShopInvoice::class)
                ->where('reference_id', $invoice->id)
                ->lockForUpdate()
                ->first();

            if (! $transaction instanceof ShopLedgerTransaction) {
                $transaction = new ShopLedgerTransaction([
                    'shop_id' => $invoice->shop_id,
                    'entry_type_id' => $purchaseBillType->id,
                    'reference_type' => ShopInvoice::class,
                    'reference_id' => $invoice->id,
                    'entered_by' => $userId,
                ]);
            }

            $previousBusinessDate = $transaction->exists
                ? $transaction->business_date?->toDateString()
                : null;
            $fundingSource = FundingSource::Company;
            $direction = LedgerDirection::Expense;
            $effect = $this->effectResolver->resolve($direction, $fundingSource, $amount, $setting);

            $transaction->fill([
                'business_date' => $businessDate,
                'amount' => $amount,
                'direction' => $direction->value,
                'funding_source' => $fundingSource->value,
                'affects_sales' => $setting->include_in_sales,
                'affects_income' => $setting->include_in_income,
                'affects_expense' => $setting->include_in_expense,
                'affects_pl' => $setting->include_in_pl,
                'pl_delta' => $effect->plDelta,
                'settlement_delta' => $effect->settlementDelta,
                'settlement_direction' => $effect->settlementDirection->value,
                'petty_delta' => $effect->pettyDelta,
                'petty_direction' => $effect->pettyDirection->value,
                'company_pending_delta' => $effect->companyPendingDelta,
                'company_pending_direction' => $effect->companyPendingDirection->value,
                'generated_by_rule' => false,
                'status' => $shouldVoid ? TransactionStatus::Void->value : TransactionStatus::Posted->value,
                'notes' => 'Auto from invoice '.$invoice->invoice_number,
                'voided_by' => $shouldVoid ? ($userId ?? $transaction->voided_by) : null,
                'voided_at' => $shouldVoid ? ($transaction->voided_at ?? now()) : null,
                'void_reason' => $shouldVoid ? 'Invoice cancelled or has no billable total.' : null,
            ]);

            $transaction->save();
            $this->balanceCalculator->recalculate((int) $invoice->shop_id, $businessDate);
            if ($previousBusinessDate !== null && $previousBusinessDate !== $businessDate) {
                $this->balanceCalculator->recalculate((int) $invoice->shop_id, $previousBusinessDate);
            }

            return $transaction->fresh('entryType');
        });
    }

    /**
     * @return array{checked: int, created: int, updated: int, voided: int, unchanged: int, failed: int, mismatches: array<int, array<string, mixed>>}
     */
    public function reconcile(?string $from = null, ?string $to = null, bool $apply = false, ?int $userId = null): array
    {
        $summary = [
            'checked' => 0,
            'created' => 0,
            'updated' => 0,
            'voided' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'mismatches' => [],
        ];

        $purchaseBillType = LedgerEntryType::query()->where('code', 'purchase_bill')->first();
        if (! $purchaseBillType instanceof LedgerEntryType) {
            return $summary;
        }

        ShopInvoice::query()
            ->when($from, fn ($query) => $query->whereDate('business_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('business_date', '<=', $to))
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use (&$summary, $purchaseBillType, $apply, $userId): void {
                foreach ($invoices as $invoice) {
                    $summary['checked']++;

                    $transaction = ShopLedgerTransaction::query()
                        ->where('shop_id', $invoice->shop_id)
                        ->where('entry_type_id', $purchaseBillType->id)
                        ->where('reference_type', ShopInvoice::class)
                        ->where('reference_id', $invoice->id)
                        ->first();

                    $expectedAmount = round((float) $invoice->final_total, 2);
                    $expectedStatus = ($invoice->status === 'cancelled' || $expectedAmount <= 0.0)
                        ? TransactionStatus::Void->value
                        : TransactionStatus::Posted->value;

                    $isMissing = ! $transaction instanceof ShopLedgerTransaction;
                    $isDifferent = $isMissing
                        || round((float) $transaction->amount, 2) !== $expectedAmount
                        || $transaction->status !== $expectedStatus
                        || $transaction->business_date?->toDateString() !== $invoice->business_date?->toDateString();

                    if (! $isDifferent) {
                        $summary['unchanged']++;

                        continue;
                    }

                    $summary['mismatches'][] = [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'shop_id' => $invoice->shop_id,
                        'business_date' => $invoice->business_date?->toDateString(),
                        'invoice_final_total' => $expectedAmount,
                        'cashbook_transaction_id' => $transaction?->id,
                        'cashbook_amount' => $transaction ? round((float) $transaction->amount, 2) : null,
                        'cashbook_status' => $transaction?->status,
                        'expected_status' => $expectedStatus,
                    ];

                    if (! $apply) {
                        continue;
                    }

                    try {
                        $synced = $this->syncInvoice($invoice, $userId);
                        if (! $transaction instanceof ShopLedgerTransaction) {
                            $summary['created']++;
                        } elseif ($synced?->status === TransactionStatus::Void->value) {
                            $summary['voided']++;
                        } else {
                            $summary['updated']++;
                        }
                    } catch (Throwable) {
                        $summary['failed']++;
                    }
                }
            });

        return $summary;
    }
}
