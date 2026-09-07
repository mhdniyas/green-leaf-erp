<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\LedgerDirection;
use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\ShopStaffPayment;
use Illuminate\Support\Carbon;
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

    /**
     * Audit and synchronize staff payments for a given shop against cashbook transactions.
     *
     * @return array{
     *     created: array<int, array{id: int, employee_name: string, amount: float, date: string, type: string}>,
     *     updated: array<int, array{id: int, employee_name: string, amount: float, date: string, type: string, changes: string}>,
     *     matching: array<int, array{id: int, employee_name: string, amount: float, date: string, type: string}>,
     *     orphans: array<int, array{id: int, transaction_id: int, business_date: string, amount: float, category: string, notes: string}>,
     *     mismatched: array<int, array{id: int, employee_name: string, amount: float, date: string, details: string}>
     * }
     */
    public function syncAndAuditShopPayments(int $shopId, ?string $date = null, ?string $month = null, ?int $userId = null): array
    {
        $results = [
            'created' => [],
            'updated' => [],
            'matching' => [],
            'orphans' => [],
            'mismatched' => [],
        ];

        $salaryEntryType = LedgerEntryType::query()->where('code', 'salary')->first();
        if (! $salaryEntryType instanceof LedgerEntryType) {
            return $results;
        }

        $query = ShopStaffPayment::query()
            ->with(['employee'])
            ->where('shop_id', $shopId);

        if (filled($date)) {
            $query->whereDate('paid_on', $date);
        } elseif (filled($month)) {
            $startOfMonth = Carbon::parse($month)->startOfMonth()->toDateString();
            $endOfMonth = Carbon::parse($month)->endOfMonth()->toDateString();
            $query->whereBetween('paid_on', [$startOfMonth, $endOfMonth]);
        }

        $payments = $query->orderBy('id')->get();

        foreach ($payments as $payment) {
            $transaction = ShopLedgerTransaction::query()
                ->where('shop_id', $shopId)
                ->where('entry_type_id', $salaryEntryType->id)
                ->where('reference_type', ShopStaffPayment::class)
                ->where('reference_id', $payment->id)
                ->first();

            $expectedAmount = round((float) $payment->amount, 2);
            $expectedDate = $payment->paid_on?->toDateString();
            $expectedStatus = ($payment->status === 'cancelled' || $expectedAmount <= 0.0)
                ? TransactionStatus::Void->value
                : TransactionStatus::Posted->value;
            $expectedFundingSource = match ((string) $payment->fund_source) {
                'petty_cash', 'petty' => FundingSource::Petty->value,
                'company' => FundingSource::Company->value,
                default => FundingSource::Sales->value,
            };

            $employeeName = $payment->employee?->name ?? 'Staff';
            $paymentType = ucfirst((string) ($payment->payment_type ?? 'salary'));

            if (! $transaction instanceof ShopLedgerTransaction) {
                $synced = $this->syncPayment($payment, $userId);
                if ($synced instanceof ShopLedgerTransaction) {
                    $results['created'][] = [
                        'id' => $payment->id,
                        'employee_name' => $employeeName,
                        'amount' => $expectedAmount,
                        'date' => (string) $expectedDate,
                        'type' => $paymentType,
                    ];
                }
            } else {
                $txAmount = round((float) $transaction->amount, 2);
                $txDate = $transaction->business_date?->toDateString();
                $txStatus = $transaction->status;
                $txSource = $transaction->funding_source;

                $mismatches = [];
                if ($txAmount !== $expectedAmount) {
                    $mismatches[] = "Amount (₹{$txAmount} → ₹{$expectedAmount})";
                }
                if ($txDate !== $expectedDate) {
                    $mismatches[] = "Date ({$txDate} → {$expectedDate})";
                }
                if ($txStatus !== $expectedStatus) {
                    $mismatches[] = "Status ({$txStatus} → {$expectedStatus})";
                }
                if ($txSource !== $expectedFundingSource) {
                    $mismatches[] = "Funding Source ({$txSource} → {$expectedFundingSource})";
                }

                if (! empty($mismatches)) {
                    $detailsStr = implode(', ', $mismatches);
                    $results['mismatched'][] = [
                        'id' => $payment->id,
                        'employee_name' => $employeeName,
                        'amount' => $expectedAmount,
                        'date' => (string) $expectedDate,
                        'details' => $detailsStr,
                    ];

                    $this->syncPayment($payment, $userId);
                    $results['updated'][] = [
                        'id' => $payment->id,
                        'employee_name' => $employeeName,
                        'amount' => $expectedAmount,
                        'date' => (string) $expectedDate,
                        'type' => $paymentType,
                        'changes' => $detailsStr,
                    ];
                } else {
                    $results['matching'][] = [
                        'id' => $payment->id,
                        'employee_name' => $employeeName,
                        'amount' => $expectedAmount,
                        'date' => (string) $expectedDate,
                        'type' => $paymentType,
                    ];
                }
            }
        }

        // Detect Orphan Cashbook Entries (references non-existent ShopStaffPayment)
        $orphanQuery = ShopLedgerTransaction::query()
            ->with(['entryType'])
            ->where('shop_id', $shopId)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('status', '!=', TransactionStatus::Void->value);

        if (filled($date)) {
            $orphanQuery->whereDate('business_date', $date);
        } elseif (filled($month)) {
            $startOfMonth = Carbon::parse($month)->startOfMonth()->toDateString();
            $endOfMonth = Carbon::parse($month)->endOfMonth()->toDateString();
            $orphanQuery->whereBetween('business_date', [$startOfMonth, $endOfMonth]);
        }

        $allStaffPaymentsForShop = ShopStaffPayment::query()->where('shop_id', $shopId)->pluck('id')->all();

        $orphanTransactions = $orphanQuery->get()->filter(function (ShopLedgerTransaction $tx) use ($allStaffPaymentsForShop): bool {
            return ! in_array((int) $tx->reference_id, $allStaffPaymentsForShop, true);
        });

        foreach ($orphanTransactions as $orphanTx) {
            $results['orphans'][] = [
                'id' => $orphanTx->id,
                'transaction_id' => $orphanTx->id,
                'business_date' => $orphanTx->business_date?->toDateString() ?? '',
                'amount' => round((float) $orphanTx->amount, 2),
                'category' => $orphanTx->entryType?->name ?? 'Salary / Advance',
                'notes' => (string) ($orphanTx->notes ?? 'Orphan staff payment record'),
            ];
        }

        return $results;
    }

    public function deleteOrphanTransaction(int $transactionId, int $shopId): bool
    {
        $transaction = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->where('id', $transactionId)
            ->first();

        if (! $transaction instanceof ShopLedgerTransaction) {
            return false;
        }

        $businessDate = $transaction->business_date?->toDateString();
        $transaction->delete();

        if ($businessDate !== null) {
            $this->balanceCalculator->recalculate($shopId, $businessDate);
        }

        return true;
    }
}
