<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\LedgerDirection;
use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\ShopStaffPayment;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class StaffPaymentCashbookProjectionService
{
    public function __construct(
        private readonly LedgerRuleResolver $ruleResolver,
        private readonly FundingSourceEffectResolver $effectResolver,
        private readonly BalanceCalculator $balanceCalculator,
    ) {}

    public function syncPayment(ShopStaffPayment $payment, ?int $userId = null): ?ShopLedgerTransaction
    {
        $salaryEntryType = LedgerEntryType::query()
            ->where('code', 'salary')
            ->where('active', true)
            ->first();

        if (! $salaryEntryType instanceof LedgerEntryType) {
            return null;
        }

        $payment->refresh();
        $payment->loadMissing(['employee', 'shop']);

        $businessDate = $payment->paid_on?->toDateString() ?? today()->toDateString();
        $amount = round((float) $payment->amount, 2);
        $shouldVoid = $payment->status === 'cancelled' || $amount <= 0.0;

        try {
            $setting = $this->ruleResolver->resolve((int) $payment->shop_id, (int) $salaryEntryType->id, $businessDate);
        } catch (RuntimeException) {
            return null;
        }

        return DB::transaction(function () use ($payment, $salaryEntryType, $businessDate, $amount, $shouldVoid, $userId, $setting): ShopLedgerTransaction {
            $transaction = ShopLedgerTransaction::query()
                ->where('shop_id', $payment->shop_id)
                ->where('entry_type_id', $salaryEntryType->id)
                ->where('reference_type', ShopStaffPayment::class)
                ->where('reference_id', $payment->id)
                ->lockForUpdate()
                ->first();

            if (! $transaction instanceof ShopLedgerTransaction) {
                $transaction = new ShopLedgerTransaction([
                    'shop_id' => $payment->shop_id,
                    'entry_type_id' => $salaryEntryType->id,
                    'reference_type' => ShopStaffPayment::class,
                    'reference_id' => $payment->id,
                    'entered_by' => $userId ?? $payment->paid_by,
                ]);
            }

            $previousBusinessDate = $transaction->exists
                ? $transaction->business_date?->toDateString()
                : null;

            $fundingSource = match ((string) $payment->fund_source) {
                'petty_cash', 'petty' => FundingSource::Petty,
                'company' => FundingSource::Company,
                default => FundingSource::Sales,
            };

            $direction = LedgerDirection::Expense;
            $effect = $this->effectResolver->resolve($direction, $fundingSource, $amount, $setting);

            $employeeName = $payment->employee?->name ?? 'Staff';
            $defaultNotes = ($payment->payment_type === 'advance' ? 'Staff Advance: ' : 'Staff Salary: ').$employeeName;
            $notes = filled($payment->notes) ? $payment->notes : $defaultNotes;

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
                'notes' => $notes,
                'voided_by' => $shouldVoid ? ($userId ?? $transaction->voided_by) : null,
                'voided_at' => $shouldVoid ? ($transaction->voided_at ?? now()) : null,
                'void_reason' => $shouldVoid ? 'Payment cancelled or zero amount.' : null,
            ]);

            $transaction->save();
            $this->balanceCalculator->recalculate((int) $payment->shop_id, $businessDate);
            if ($previousBusinessDate !== null && $previousBusinessDate !== $businessDate) {
                $this->balanceCalculator->recalculate((int) $payment->shop_id, $previousBusinessDate);
            }

            return $transaction->fresh('entryType');
        });
    }

    /**
     * @return array{checked: int, created: int, updated: int, voided: int, unchanged: int, failed: int}
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
        ];

        $salaryEntryType = LedgerEntryType::query()->where('code', 'salary')->first();
        if (! $salaryEntryType instanceof LedgerEntryType) {
            return $summary;
        }

        ShopStaffPayment::query()
            ->when($from, fn ($query) => $query->whereDate('paid_on', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('paid_on', '<=', $to))
            ->orderBy('id')
            ->chunkById(200, function ($payments) use (&$summary, $salaryEntryType, $apply, $userId): void {
                foreach ($payments as $payment) {
                    $summary['checked']++;

                    $transaction = ShopLedgerTransaction::query()
                        ->where('shop_id', $payment->shop_id)
                        ->where('entry_type_id', $salaryEntryType->id)
                        ->where('reference_type', ShopStaffPayment::class)
                        ->where('reference_id', $payment->id)
                        ->first();

                    $expectedAmount = round((float) $payment->amount, 2);
                    $expectedStatus = ($payment->status === 'cancelled' || $expectedAmount <= 0.0)
                        ? TransactionStatus::Void->value
                        : TransactionStatus::Posted->value;

                    $expectedFundingSource = match ((string) $payment->fund_source) {
                        'petty_cash', 'petty' => FundingSource::Petty->value,
                        'company' => FundingSource::Company->value,
                        default => FundingSource::Sales->value,
                    };

                    $isMissing = ! $transaction instanceof ShopLedgerTransaction;
                    $isDifferent = $isMissing
                        || round((float) $transaction->amount, 2) !== $expectedAmount
                        || $transaction->status !== $expectedStatus
                        || $transaction->funding_source !== $expectedFundingSource
                        || $transaction->business_date?->toDateString() !== $payment->paid_on?->toDateString();

                    if (! $isDifferent) {
                        $summary['unchanged']++;

                        continue;
                    }

                    if (! $apply) {
                        continue;
                    }

                    try {
                        $synced = $this->syncPayment($payment, $userId);
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
