<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\LedgerDirection;
use App\Enums\Cashbook\SalaryHrTransactionType;
use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopSalaryBridgeSetting;
use App\Models\ShopStaffPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class StaffPaymentCashbookProjectionService
{
    public function __construct(
        private readonly FundingSourceEffectResolver $effectResolver,
        private readonly BalanceCalculator $balanceCalculator,
    ) {}

    /**
     * Resolve the mapped ShopLedgerEntrySetting for a shop and HR payment type using the Salary Settings Bridge.
     * Returns null if unconfigured (does NOT guess a category).
     */
    public function resolveBridgeSetting(int $shopId, string $paymentType): ?ShopLedgerEntrySetting
    {
        $typeEnum = match ($paymentType) {
            'advance', 'salary_advance' => SalaryHrTransactionType::SalaryAdvance,
            'salary_adjustment', 'adjustment' => SalaryHrTransactionType::SalaryAdjustment,
            'advance_recovery', 'recovery' => SalaryHrTransactionType::AdvanceRecovery,
            default => SalaryHrTransactionType::Salary,
        };

        $bridgeSetting = ShopSalaryBridgeSetting::query()
            ->where('shop_id', $shopId)
            ->where('transaction_type', $typeEnum->value)
            ->where('is_enabled', true)
            ->first();

        if ($bridgeSetting && $bridgeSetting->shop_ledger_entry_setting_id) {
            $setting = ShopLedgerEntrySetting::query()
                ->with(['entryType', 'headerGroup'])
                ->where('id', $bridgeSetting->shop_ledger_entry_setting_id)
                ->where('shop_id', $shopId)
                ->where('enabled', true)
                ->first();

            if ($setting) {
                return $setting;
            }
        }

        return ShopLedgerEntrySetting::query()
            ->with(['entryType', 'headerGroup'])
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->whereHas('entryType', function ($q) {
                $q->whereIn('code', ['salary', 'staff_advance', 'salary_advance']);
            })
            ->first();
    }

    /**
     * Synchronize a single ShopStaffPayment to its corresponding Cashbook ShopLedgerTransaction.
     * Idempotent: Locates existing transaction by reference_type and reference_id.
     */
    public function syncPayment(ShopStaffPayment $payment, ?int $userId = null): ?ShopLedgerTransaction
    {
        $payment->refresh();
        $payment->loadMissing(['employee', 'shop']);

        $businessDate = $payment->paid_on?->toDateString() ?? today()->toDateString();
        $amount = round((float) $payment->amount, 2);
        $shouldVoid = $payment->status === 'cancelled' || $amount <= 0.0;

        return DB::transaction(function () use ($payment, $businessDate, $amount, $shouldVoid, $userId): ?ShopLedgerTransaction {
            /** @var ShopLedgerTransaction|null $transaction */
            $transaction = ShopLedgerTransaction::query()
                ->where('reference_type', ShopStaffPayment::class)
                ->where('reference_id', $payment->id)
                ->lockForUpdate()
                ->first();

            // Case A: Payment is cancelled or zero amount
            if ($shouldVoid) {
                if (! $transaction instanceof ShopLedgerTransaction) {
                    return null;
                }

                $txShopId = (int) $transaction->shop_id;
                $txDate = $transaction->business_date?->toDateString() ?? $businessDate;

                $transaction->fill([
                    'status' => TransactionStatus::Void->value,
                    'pl_delta' => 0.0,
                    'settlement_delta' => 0.0,
                    'petty_delta' => 0.0,
                    'company_pending_delta' => 0.0,
                    'voided_by' => $userId ?? $transaction->voided_by,
                    'voided_at' => $transaction->voided_at ?? now(),
                    'void_reason' => 'Payment cancelled or zero amount.',
                ]);
                $transaction->save();

                $this->recalculateBalancesFromDate($txShopId, $txDate);

                return $transaction->fresh('entryType');
            }

            // Case B: Active Payment — resolve appropriate category setting
            $previousShopId = $transaction?->exists ? (int) $transaction->shop_id : null;
            $previousBusinessDate = $transaction?->exists
                ? $transaction->business_date?->toDateString()
                : null;

            // Handle Shop change (Shop A -> Shop B)
            if ($transaction instanceof ShopLedgerTransaction && $previousShopId !== null && $previousShopId !== (int) $payment->shop_id) {
                // Remove old projection from previous shop
                $transaction->delete();
                $this->recalculateBalancesFromDate($previousShopId, $previousBusinessDate ?? $businessDate);
                $transaction = null;
            }

            // Determine entry setting:
            // If existing transaction in same shop, preserve its original entry_type_id!
            if ($transaction instanceof ShopLedgerTransaction) {
                $setting = ShopLedgerEntrySetting::query()
                    ->where('shop_id', (int) $payment->shop_id)
                    ->where('entry_type_id', (int) $transaction->entry_type_id)
                    ->where('enabled', true)
                    ->first()
                    ?? $this->resolveBridgeSetting((int) $payment->shop_id, (string) $payment->payment_type);
            } else {
                // New transaction: resolve from Salary Settings Bridge
                $setting = $this->resolveBridgeSetting((int) $payment->shop_id, (string) $payment->payment_type);
            }

            // If category is not configured, do NOT guess a category — skip safely
            if (! $setting instanceof ShopLedgerEntrySetting) {
                return null;
            }

            if (! $transaction instanceof ShopLedgerTransaction) {
                $transaction = new ShopLedgerTransaction([
                    'shop_id' => (int) $payment->shop_id,
                    'entry_type_id' => $setting->entry_type_id,
                    'reference_type' => ShopStaffPayment::class,
                    'reference_id' => $payment->id,
                    'entered_by' => $userId ?? $payment->paid_by,
                ]);
            }

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
                'shop_id' => (int) $payment->shop_id,
                'business_date' => $businessDate,
                'entry_type_id' => $setting->entry_type_id,
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
                'status' => TransactionStatus::Posted->value,
                'notes' => $notes,
                'voided_by' => null,
                'voided_at' => null,
                'void_reason' => null,
            ]);

            $transaction->save();

            // Recalculate balances
            $this->recalculateBalancesFromDate((int) $payment->shop_id, $businessDate);
            if ($previousBusinessDate !== null && $previousBusinessDate !== $businessDate) {
                $this->recalculateBalancesFromDate((int) $payment->shop_id, $previousBusinessDate);
            }

            return $transaction->fresh('entryType');
        });
    }

    /**
     * Audit and synchronize staff payments for a given shop against cashbook transactions.
     *
     * @return array{
     *     created: array<int, array{id: int, employee_name: string, amount: float, date: string, type: string}>,
     *     updated: array<int, array{id: int, employee_name: string, amount: float, date: string, type: string, changes: string}>,
     *     matching: array<int, array{id: int, employee_name: string, amount: float, date: string, type: string}>,
     *     orphans: array<int, array{id: int, transaction_id: int, business_date: string, amount: float, category: string, notes: string}>,
     *     mismatched: array<int, array{id: int, employee_name: string, amount: float, date: string, details: string}>,
     *     skipped: array<int, array{id: int, employee_name: string, amount: float, date: string, type: string, reason: string}>
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
            'skipped' => [],
        ];

        $query = ShopStaffPayment::query()
            ->with(['employee', 'shop'])
            ->where('shop_id', $shopId);

        if (filled($date)) {
            $query->whereDate('paid_on', $date);
        } elseif (filled($month)) {
            $startOfMonth = Carbon::parse($month)->startOfMonth()->toDateString();
            $endOfMonth = Carbon::parse($month)->endOfMonth()->toDateString();
            $query->whereDate('paid_on', '>=', $startOfMonth)
                ->whereDate('paid_on', '<=', $endOfMonth);
        }

        $payments = $query->orderBy('id')->get();

        foreach ($payments as $payment) {
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

            $transaction = ShopLedgerTransaction::query()
                ->where('shop_id', $shopId)
                ->where('reference_type', ShopStaffPayment::class)
                ->where('reference_id', $payment->id)
                ->first();

            // Case 1: Transaction does not exist yet
            if (! $transaction instanceof ShopLedgerTransaction) {
                $bridgeSetting = $this->resolveBridgeSetting($shopId, (string) $payment->payment_type);
                if (! $bridgeSetting instanceof ShopLedgerEntrySetting) {
                    $results['skipped'][] = [
                        'id' => $payment->id,
                        'employee_name' => $employeeName,
                        'amount' => $expectedAmount,
                        'date' => (string) $expectedDate,
                        'type' => $paymentType,
                        'reason' => "Salary sync skipped: {$payment->shop?->name} → {$paymentType} category is not configured in Salary Settings.",
                    ];

                    continue;
                }

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
                // Case 2: Transaction exists — check if amount, date, status, or funding source changed
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

        // Detect & Clean Orphan Cashbook Entries (where reference_type is ShopStaffPayment but payment no longer exists for this shop)
        $orphanQuery = ShopLedgerTransaction::query()
            ->with(['entryType'])
            ->where('shop_id', $shopId)
            ->where('reference_type', ShopStaffPayment::class)
            ->whereNotNull('reference_id')
            ->whereNotIn('status', [TransactionStatus::Void->value, 'void', 'reversed']);

        if (filled($date)) {
            $orphanQuery->whereDate('business_date', $date);
        } elseif (filled($month)) {
            $startOfMonth = Carbon::parse($month)->startOfMonth()->toDateString();
            $endOfMonth = Carbon::parse($month)->endOfMonth()->toDateString();
            $orphanQuery->whereDate('business_date', '>=', $startOfMonth)
                ->whereDate('business_date', '<=', $endOfMonth);
        }

        $allActiveStaffPaymentsForShop = ShopStaffPayment::query()
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'cancelled')
            ->pluck('id')
            ->all();

        $orphanTransactions = $orphanQuery->get()->filter(function (ShopLedgerTransaction $tx) use ($allActiveStaffPaymentsForShop): bool {
            return ! in_array((int) $tx->reference_id, $allActiveStaffPaymentsForShop, true);
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

            $txDate = $orphanTx->business_date?->toDateString();
            $orphanTx->delete();
            if ($txDate !== null) {
                $this->recalculateBalancesFromDate($shopId, $txDate);
            }
        }

        return $results;
    }

    /**
     * @return array{checked: int, created: int, updated: int, voided: int, unchanged: int, failed: int, skipped: int}
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
            'skipped' => 0,
        ];

        ShopStaffPayment::query()
            ->when($from, fn ($query) => $query->whereDate('paid_on', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('paid_on', '<=', $to))
            ->orderBy('id')
            ->chunkById(200, function ($payments) use (&$summary, $apply, $userId): void {
                foreach ($payments as $payment) {
                    $summary['checked']++;

                    $transaction = ShopLedgerTransaction::query()
                        ->where('shop_id', $payment->shop_id)
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

                    if ($isMissing && ! $this->resolveBridgeSetting((int) $payment->shop_id, (string) $payment->payment_type)) {
                        $summary['skipped']++;

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

        // Clean orphan transactions
        $orphanTxs = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->whereNotNull('reference_id')
            ->whereNotIn('status', [TransactionStatus::Void->value, 'void', 'reversed'])
            ->when($from, fn ($query) => $query->whereDate('business_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('business_date', '<=', $to))
            ->get();

        $allActivePaymentIds = ShopStaffPayment::query()
            ->where('status', '!=', 'cancelled')
            ->pluck('id')
            ->all();

        foreach ($orphanTxs as $orphanTx) {
            if (in_array((int) $orphanTx->reference_id, $allActivePaymentIds, true)) {
                continue;
            }

            $summary['checked']++;
            $summary['voided']++;

            if (! $apply) {
                continue;
            }

            try {
                $txDate = $orphanTx->business_date?->toDateString();
                $txShopId = (int) $orphanTx->shop_id;
                $orphanTx->delete();
                if ($txDate !== null) {
                    $this->recalculateBalancesFromDate($txShopId, $txDate);
                }
            } catch (Throwable) {
                $summary['voided']--;
                $summary['failed']++;
            }
        }

        return $summary;
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
            $this->recalculateBalancesFromDate($shopId, $businessDate);
        }

        return true;
    }

    public function recalculateBalancesFromDate(int $shopId, string $businessDate): void
    {
        $dates = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $shopId)
            ->whereDate('business_date', '>=', $businessDate)
            ->orderBy('business_date')
            ->pluck('business_date')
            ->map(fn ($d): string => $d instanceof CarbonInterface ? $d->toDateString() : (string) $d)
            ->push($businessDate)
            ->unique()
            ->sort()
            ->values();

        foreach ($dates as $date) {
            $this->balanceCalculator->recalculate($shopId, $date);
        }
    }
}
