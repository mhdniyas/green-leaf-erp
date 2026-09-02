<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Finance\JournalService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopPaymentLedgerReconciliationService
{
    private const ALLOCATION_TOLERANCE = 0.01;

    public function __construct(
        private readonly DailyLedgerService $dailyLedgerService,
        private readonly JournalService $journalService,
        private readonly CompanyMoneyPositionService $moneyPositionService,
    ) {}

    /**
     * Get open daily settlements for a shop based on authoritative Company Payable calculations.
     *
     * @return Collection<int, array{
     *     id: int,
     *     business_date: string,
     *     formatted_date: string,
     *     company_payable: float,
     *     gross_sales: float,
     *     deductions: float,
     *     already_allocated: float,
     *     remaining_due: float,
     *     remaining_amount: float,
     *     entry_name: string,
     * }>
     */
    public function getOpenDailySettlements(int $shopId, ?string $month = null): Collection
    {
        $query = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereNotIn('status', ['void', 'voided', 'reversed']);

        if ($month) {
            $monthStart = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
            $monthEnd = Carbon::parse($month.'-01')->endOfMonth()->toDateString();
            $query->whereBetween('business_date', [$monthStart, $monthEnd]);
        }

        $dates = $query->distinct()
            ->pluck('business_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->sort()
            ->values();

        $openSettlements = collect();

        foreach ($dates as $dateStr) {
            // Canonical Company Payable calculated through existing settlement engine
            $daySummary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($shopId, $dateStr);
            $settlementSummary = $daySummary['settlement_summary'] ?? [];
            $canonicalCompanyPayable = round((float) ($settlementSummary['expected_payable'] ?? 0.0), 2);
            $grossSales = round((float) ($settlementSummary['gross_sales'] ?? 0.0), 2);
            $deductions = round((float) ($settlementSummary['settlement_deductions'] ?? 0.0), 2);

            if ($canonicalCompanyPayable <= 0.0) {
                continue;
            }

            // Confirmed allocations already linked to this day
            $alreadyAllocated = round((float) ShopPaymentLedgerAllocation::query()
                ->where('shop_id', $shopId)
                ->whereHas('ledgerTransaction', fn ($q) => $q->whereDate('business_date', $dateStr))
                ->sum('amount'), 2);

            $remainingDue = round(max(0, $canonicalCompanyPayable - $alreadyAllocated), 2);

            // Find representative transaction for this business date
            $representativeTx = ShopLedgerTransaction::query()
                ->where('shop_id', $shopId)
                ->whereDate('business_date', $dateStr)
                ->whereNotIn('status', ['void', 'voided', 'reversed'])
                ->where(function ($q) {
                    $q->where('settlement_delta', '>', 0)
                        ->orWhere('direction', 'income')
                        ->orWhere('affects_sales', true);
                })
                ->oldest('id')
                ->first() ?? ShopLedgerTransaction::query()
                ->where('shop_id', $shopId)
                ->whereDate('business_date', $dateStr)
                ->whereNotIn('status', ['void', 'voided', 'reversed'])
                ->oldest('id')
                ->first();

            if ($representativeTx) {
                $openSettlements->push([
                    'id' => (int) $representativeTx->id,
                    'business_date' => $dateStr,
                    'formatted_date' => Carbon::parse($dateStr)->format('d M Y'),
                    'company_payable' => $canonicalCompanyPayable,
                    'gross_sales' => $grossSales,
                    'deductions' => $deductions,
                    'already_allocated' => $alreadyAllocated,
                    'remaining_due' => $remainingDue,
                    'remaining_amount' => $remainingDue,
                    'entry_name' => 'Company Payable',
                ]);
            }
        }

        return $openSettlements;
    }

    /**
     * Record actual payment received from a shop into a designated Company Account.
     *
     * @param  array{
     *     amount: float|int|string,
     *     payment_date: string,
     *     payment_method: string,
     *     company_account_id: int|string,
     *     payment_reference?: ?string,
     *     notes?: ?string,
     *     cheque_bank_name?: ?string,
     *     cheque_date?: ?string,
     * }  $data
     */
    public function recordReceivedPayment(Shop $shop, array $data, int $userId): ShopInvoicePaymentRequest
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0.0) {
            throw ValidationException::withMessages([
                'amount' => 'Received payment amount must be greater than zero.',
            ]);
        }

        $paymentMethod = (string) $data['payment_method'];
        $isCheque = $paymentMethod === 'cheque';
        $companyAccountId = (int) $data['company_account_id'];

        return DB::transaction(function () use ($shop, $data, $amount, $paymentMethod, $isCheque, $companyAccountId, $userId): ShopInvoicePaymentRequest {
            $shop = Shop::query()->whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $account = CompanyAccount::query()
                ->whereKey($companyAccountId)
                ->where('enabled', true)
                ->lockForUpdate()
                ->first();

            if (! $account instanceof CompanyAccount) {
                throw ValidationException::withMessages([
                    'company_account_id' => 'Selected destination company account is invalid or disabled.',
                ]);
            }

            $reference = filled($data['payment_reference'] ?? null) ? trim((string) $data['payment_reference']) : null;
            $notes = filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null;
            $paymentDate = (string) $data['payment_date'];

            $payment = ShopInvoicePaymentRequest::query()->create([
                'shop_id' => $shop->id,
                'requested_by' => $userId,
                'submission_uuid' => (string) Str::uuid(),
                'request_type' => 'shop_cashbook',
                'payment_method' => $paymentMethod,
                'payment_reference' => $reference,
                'payment_date' => $paymentDate,
                'requested_amount' => $amount,
                'approved_amount' => $isCheque ? null : $amount,
                'reconciled_amount' => $isCheque ? 0.00 : $amount,
                'floating_amount' => $isCheque ? $amount : 0.00,
                'status' => $isCheque ? 'pending' : 'approved',
                'reconciliation_status' => $isCheque ? 'floating' : 'reconciled',
                'cheque_status' => $isCheque ? 'pending' : null,
                'cheque_bank_name' => $isCheque ? ($data['cheque_bank_name'] ?? null) : null,
                'cheque_date' => $isCheque ? ($data['cheque_date'] ?? null) : null,
                'shop_note' => $notes,
                'admin_note' => $notes,
                'notes' => $notes,
                'created_by' => $userId,
                'updated_by' => $userId,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'last_reconciled_at' => $isCheque ? null : now(),
            ]);

            if ($isCheque) {
                return $payment;
            }

            $statementEntry = CompanyAccountStatementEntry::query()->create([
                'company_account_id' => $account->id,
                'transaction_date' => $paymentDate,
                'value_date' => $paymentDate,
                'entry_type' => 'shop_collection',
                'direction' => 'in',
                'amount' => $amount,
                'reference' => $reference,
                'notes' => 'Shop payment received from '.$shop->name.($notes ? ' — '.$notes : ''),
                'status' => 'reconciled',
                'is_finalized' => true,
                'source_type' => ShopInvoicePaymentRequest::class,
                'source_id' => $payment->id,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
            ]);

            $account->increment('current_balance', $amount);

            CompanyPaymentReconciliation::query()->create([
                'payment_request_id' => $payment->id,
                'shop_id' => $shop->id,
                'company_account_id' => $account->id,
                'statement_entry_id' => $statementEntry->id,
                'statement_amount' => $amount,
                'cleared_amount' => $amount,
                'difference_amount' => 0.00,
                'difference_action' => 'none',
                'status' => 'approved',
                'is_finalized' => true,
                'finalized_at' => now(),
                'admin_note' => $notes,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
            ]);

            $this->journalService->recordShopPaymentRequest($payment, $userId);

            return $payment;
        }, attempts: 3);
    }

    /**
     * Manually allocate a payment against one or more shop settlement transactions.
     *
     * @param  array<int, array{ledger_transaction_id: int, amount: float|int|string}>  $allocations
     */
    public function allocatePayment(
        ShopInvoicePaymentRequest $payment,
        array $allocations,
        int $userId,
        ?string $batchUuid = null,
    ): Collection {
        if (empty($allocations)) {
            throw ValidationException::withMessages([
                'allocations' => 'Please provide at least one settlement allocation.',
            ]);
        }

        return DB::transaction(function () use ($payment, $allocations, $userId, $batchUuid): Collection {
            $payment = ShopInvoicePaymentRequest::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $shopId = (int) $payment->shop_id;
            $paymentAmount = $this->resolvePaymentAmount($payment);

            $alreadyAllocated = (float) ShopPaymentLedgerAllocation::query()
                ->where('payment_request_id', $payment->id)
                ->lockForUpdate()
                ->sum('amount');

            $availableToAllocate = round(max(0, $paymentAmount - $alreadyAllocated), 2);

            $requestedTotal = 0.0;
            $transactionIds = [];
            foreach ($allocations as $alloc) {
                $amount = round((float) ($alloc['amount'] ?? 0), 2);
                if ($amount <= 0.0) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Allocation amounts must be greater than zero.',
                    ]);
                }
                $requestedTotal += $amount;
                $transactionIds[] = (int) $alloc['ledger_transaction_id'];
            }

            $requestedTotal = round($requestedTotal, 2);
            if ($requestedTotal > $availableToAllocate) {
                throw ValidationException::withMessages([
                    'allocations' => "Total allocation amount (₹{$requestedTotal}) cannot exceed available unallocated payment (₹{$availableToAllocate}).",
                ]);
            }

            $transactions = ShopLedgerTransaction::query()
                ->with('entryType')
                ->whereIn('id', array_unique($transactionIds))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($transactions->count() !== count(array_unique($transactionIds))) {
                throw ValidationException::withMessages([
                    'allocations' => 'One or more selected settlement transactions no longer exist.',
                ]);
            }

            $createdAllocations = collect();

            foreach ($allocations as $alloc) {
                $txId = (int) $alloc['ledger_transaction_id'];
                $amount = round((float) $alloc['amount'], 2);
                $transaction = $transactions->get($txId);

                if (! $transaction instanceof ShopLedgerTransaction || (int) $transaction->shop_id !== $shopId) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Selected settlement transactions must belong to the same shop as the payment.',
                    ]);
                }

                if (in_array($transaction->status, ['void', 'voided', 'reversed'], true)) {
                    throw ValidationException::withMessages([
                        'allocations' => "Transaction on {$transaction->business_date?->format('d M Y')} is voided and cannot be allocated.",
                    ]);
                }

                $businessDateStr = $transaction->business_date?->toDateString();
                $daySummary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($shopId, $businessDateStr);
                $canonicalCompanyPayable = round((float) ($daySummary['settlement_summary']['expected_payable'] ?? 0.0), 2);

                if ($canonicalCompanyPayable <= 0.0) {
                    throw ValidationException::withMessages([
                        'allocations' => "Settlement on {$transaction->business_date?->format('d M Y')} has no company payable obligation.",
                    ]);
                }

                $alreadyAllocatedForDay = round((float) ShopPaymentLedgerAllocation::query()
                    ->where('shop_id', $shopId)
                    ->whereHas('ledgerTransaction', fn ($q) => $q->whereDate('business_date', $businessDateStr))
                    ->lockForUpdate()
                    ->sum('amount'), 2);

                $dayRemainingDue = round(max(0, $canonicalCompanyPayable - $alreadyAllocatedForDay), 2);

                if ($amount > $dayRemainingDue) {
                    $dateFormatted = $transaction->business_date?->format('d M Y');
                    throw ValidationException::withMessages([
                        'allocations' => "Allocation amount ₹{$amount} exceeds remaining company payable due ₹{$dayRemainingDue} for {$dateFormatted}.",
                    ]);
                }

                $existingAlloc = ShopPaymentLedgerAllocation::query()
                    ->where('payment_request_id', $payment->id)
                    ->where('shop_ledger_transaction_id', $transaction->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingAlloc instanceof ShopPaymentLedgerAllocation) {
                    $existingAlloc->update([
                        'amount' => round((float) $existingAlloc->amount + $amount, 2),
                        'reconciled_by' => $userId,
                        'batch_uuid' => $batchUuid ?? $existingAlloc->batch_uuid,
                    ]);
                    $allocationRecord = $existingAlloc->fresh();
                } else {
                    $allocationRecord = ShopPaymentLedgerAllocation::query()->create([
                        'payment_request_id' => $payment->id,
                        'shop_id' => $shopId,
                        'shop_ledger_transaction_id' => $transaction->id,
                        'amount' => $amount,
                        'reconciled_by' => $userId,
                        'batch_uuid' => $batchUuid,
                    ]);
                }

                $createdAllocations->push($allocationRecord);
            }

            return $createdAllocations;
        }, attempts: 3);
    }

    /**
     * Determine canonical payment amount used for allocation integrity and limits.
     */
    public function resolvePaymentAmount(ShopInvoicePaymentRequest $payment): float
    {
        $reconciledAmount = round((float) ($payment->reconciled_amount ?? 0), 2);
        $approvedAmount = round((float) ($payment->approved_amount ?? 0), 2);
        $requestedAmount = round((float) $payment->requested_amount, 2);

        if ($reconciledAmount > self::ALLOCATION_TOLERANCE) {
            return $reconciledAmount;
        }

        if ($approvedAmount > self::ALLOCATION_TOLERANCE) {
            return $approvedAmount;
        }

        return $requestedAmount;
    }

    /**
     * @return array{
     *   total_amount: float,
     *   actual_allocated: float,
     *   actual_remaining: float,
     *   over_allocated_amount: float,
     *   duplicate_allocation_count: int,
     *   integrity_flag: string,
     *   stored_status: string,
     *   actual_status: string,
     *   has_error: bool
     * }
     */
    public function allocationIntegritySnapshot(ShopInvoicePaymentRequest $payment): array
    {
        $totalAmount = $this->resolvePaymentAmount($payment);

        $allocations = $payment->relationLoaded('ledgerAllocations')
            ? $payment->ledgerAllocations
            : $payment->ledgerAllocations()->get(['id', 'shop_ledger_transaction_id', 'amount']);

        $actualAllocated = round((float) $allocations->sum(fn (ShopPaymentLedgerAllocation $allocation): float => (float) $allocation->amount), 2);
        $actualRemaining = round($totalAmount - $actualAllocated, 2);
        $overAllocatedAmount = round(max(0, $actualAllocated - $totalAmount), 2);

        $duplicateAllocationCount = (int) $allocations
            ->groupBy(fn (ShopPaymentLedgerAllocation $allocation): int => (int) $allocation->shop_ledger_transaction_id)
            ->sum(fn (Collection $group): int => max(0, $group->count() - 1));

        $storedStatus = match (true) {
            $payment->cheque_status === 'pending' => 'PENDING_CHEQUE',
            round((float) $payment->requested_amount, 2) <= self::ALLOCATION_TOLERANCE => 'OK',
            $actualAllocated <= self::ALLOCATION_TOLERANCE => 'OK',
            $actualAllocated + self::ALLOCATION_TOLERANCE >= round((float) $payment->requested_amount, 2) => 'FULLY_ALLOCATED',
            default => 'PARTIALLY_ALLOCATED',
        };

        $actualStatus = match (true) {
            $actualAllocated <= self::ALLOCATION_TOLERANCE => 'OK',
            $actualRemaining <= self::ALLOCATION_TOLERANCE => 'FULLY_ALLOCATED',
            default => 'PARTIALLY_ALLOCATED',
        };

        $integrityFlag = $actualStatus;

        if ($duplicateAllocationCount > 0) {
            $integrityFlag = 'DUPLICATE_ALLOCATION';
        } elseif ($overAllocatedAmount > self::ALLOCATION_TOLERANCE) {
            $integrityFlag = 'OVER_ALLOCATED';
        } elseif ($storedStatus !== 'PENDING_CHEQUE' && $storedStatus !== $actualStatus) {
            $integrityFlag = 'ALLOCATION_ERROR';
        }

        return [
            'total_amount' => $totalAmount,
            'actual_allocated' => $actualAllocated,
            'actual_remaining' => $actualRemaining,
            'over_allocated_amount' => $overAllocatedAmount,
            'duplicate_allocation_count' => $duplicateAllocationCount,
            'integrity_flag' => $integrityFlag,
            'stored_status' => $storedStatus,
            'actual_status' => $actualStatus,
            'has_error' => in_array($integrityFlag, ['ALLOCATION_ERROR', 'OVER_ALLOCATED', 'DUPLICATE_ALLOCATION'], true),
        ];
    }

    /**
     * Remove all allocations for a payment in one transaction.
     */
    public function clearPaymentAllocations(ShopInvoicePaymentRequest $payment, int $userId): int
    {
        return DB::transaction(function () use ($payment): int {
            $lockedPayment = ShopInvoicePaymentRequest::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $allocations = ShopPaymentLedgerAllocation::query()
                ->where('payment_request_id', $lockedPayment->id)
                ->where('shop_id', (int) $lockedPayment->shop_id)
                ->lockForUpdate()
                ->get();

            $allocationIds = $allocations->pluck('id')->all();
            if ($allocationIds === []) {
                return 0;
            }

            return (int) ShopPaymentLedgerAllocation::query()
                ->whereIn('id', $allocationIds)
                ->delete();
        }, attempts: 3);
    }

    /**
     * Remove all allocations for an entire shop in one transaction.
     * Deletes ONLY ShopPaymentLedgerAllocation rows for the shop.
     * Leaves ShopInvoicePaymentRequest, received payment records, bank reconciliations,
     * shop_paid_company ledger receipts, and settlement obligations 100% untouched.
     */
    public function clearAllShopPaymentAllocations(Shop|ShopLedgerProfile|int $shop, int $userId, ?string $reason = null): int
    {
        $shopModel = match (true) {
            $shop instanceof Shop => $shop,
            $shop instanceof ShopLedgerProfile => Shop::query()->where('id', $shop->shop_id)->first() ?? $shop,
            default => Shop::query()->where('id', $shop)->first(),
        };

        $shopId = match (true) {
            $shop instanceof Shop => (int) $shop->id,
            $shop instanceof ShopLedgerProfile => (int) $shop->shop_id,
            default => (int) $shop,
        };

        return DB::transaction(function () use ($shopModel, $shopId, $userId, $reason): int {
            // 1. Lock allocation rows for target shop
            $allocations = ShopPaymentLedgerAllocation::query()
                ->where('shop_id', $shopId)
                ->lockForUpdate()
                ->get();

            $allocationIds = $allocations->pluck('id')->all();
            if ($allocationIds === []) {
                return 0;
            }

            // 2. Capture allocation metrics
            $paymentIds = $allocations->pluck('payment_request_id')->unique()->filter()->values()->all();
            $billIds = $allocations->pluck('shop_ledger_transaction_id')->unique()->filter()->values()->all();
            $totalReleased = round((float) $allocations->sum('amount'), 2);

            // 3. Lock affected ShopInvoicePaymentRequest rows
            if (! empty($paymentIds)) {
                ShopInvoicePaymentRequest::query()
                    ->whereIn('id', $paymentIds)
                    ->lockForUpdate()
                    ->get();
            }

            // 4. Lock affected ShopLedgerTransaction rows
            if (! empty($billIds)) {
                ShopLedgerTransaction::query()
                    ->whereIn('id', $billIds)
                    ->lockForUpdate()
                    ->get();
            }

            // 5. Delete ONLY ShopPaymentLedgerAllocation rows for target shop
            $clearedCount = (int) ShopPaymentLedgerAllocation::query()
                ->whereIn('id', $allocationIds)
                ->delete();

            // 6. Verify allocations for this scope are now zero
            $remainingAllocationsCount = ShopPaymentLedgerAllocation::query()
                ->where('shop_id', $shopId)
                ->count();

            if ($remainingAllocationsCount !== 0) {
                throw new \RuntimeException('Allocation clear verification failed: allocations remain for scope.');
            }

            // 7. Verify original payment rows still exist
            if (! empty($paymentIds)) {
                $remainingPaymentCount = ShopInvoicePaymentRequest::query()
                    ->whereIn('id', $paymentIds)
                    ->count();

                if ($remainingPaymentCount !== count($paymentIds)) {
                    throw new \RuntimeException('Allocation clear verification failed: original payment records were affected.');
                }
            }

            // 8. Server diagnostic log
            Log::info('Shop payment allocations cleared for shop', [
                'shop_id' => $shopId,
                'admin_user_id' => $userId,
                'action' => 'clear_all_shop_payment_allocations',
                'allocation_ids' => $allocationIds,
                'payment_request_ids' => $paymentIds,
                'shop_ledger_transaction_ids' => $billIds,
                'allocation_count' => $clearedCount,
                'total_released_amount' => $totalReleased,
                'reason' => $reason,
                'timestamp' => now()->toIso8601String(),
            ]);

            // 9. Durable application activity log
            $actor = $userId > 0 ? User::find($userId) : auth()->user();
            $activity = activity('shop_cashbook.clear_all_allocations')
                ->withProperties([
                    'action' => 'clear_all_shop_payment_allocations',
                    'shop_id' => $shopId,
                    'shop_name' => $shopModel instanceof Shop ? $shopModel->name : null,
                    'shop_code' => $shopModel instanceof Shop ? $shopModel->code : null,
                    'admin_user_id' => $userId,
                    'allocation_ids' => $allocationIds,
                    'payment_request_ids' => $paymentIds,
                    'shop_ledger_transaction_ids' => $billIds,
                    'allocation_count' => $clearedCount,
                    'total_released_amount' => $totalReleased,
                    'reason' => $reason,
                    'timestamp' => now()->toIso8601String(),
                ]);

            if ($shopModel) {
                $activity->performedOn($shopModel);
            }
            if ($actor) {
                $activity->causedBy($actor);
            }
            $activity->log('shop_cashbook.clear_all_allocations');

            return $clearedCount;
        }, attempts: 3);
    }

    /**
     * Clear payment allocations and rebuild using current auto-allocation rules.
     *
     * @return array{cleared_count: int, created_count: int, allocated_total: float, remaining_amount: float}
     */
    public function clearAndReallocatePayment(ShopInvoicePaymentRequest $payment, int $userId): array
    {
        return DB::transaction(function () use ($payment, $userId): array {
            $lockedPayment = ShopInvoicePaymentRequest::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $shopId = (int) $lockedPayment->shop_id;

            $existingAllocations = ShopPaymentLedgerAllocation::query()
                ->where('payment_request_id', $lockedPayment->id)
                ->where('shop_id', $shopId)
                ->lockForUpdate()
                ->get();

            $clearedCount = 0;
            if ($existingAllocations->isNotEmpty()) {
                $clearedCount = (int) ShopPaymentLedgerAllocation::query()
                    ->whereIn('id', $existingAllocations->pluck('id')->all())
                    ->delete();
            }

            $plan = $this->buildAutoAllocationPlanForPayment($lockedPayment, $shopId);
            if ($plan === []) {
                $remainingAmount = round(max(0, $this->resolvePaymentAmount($lockedPayment)), 2);

                return [
                    'cleared_count' => $clearedCount,
                    'created_count' => 0,
                    'allocated_total' => 0.0,
                    'remaining_amount' => $remainingAmount,
                ];
            }

            $createdAllocations = $this->allocatePayment($lockedPayment, $plan, $userId);
            $allocatedTotal = round((float) $createdAllocations->sum('amount'), 2);
            $remainingAmount = round(max(0, $this->resolvePaymentAmount($lockedPayment) - $allocatedTotal), 2);

            return [
                'cleared_count' => $clearedCount,
                'created_count' => $createdAllocations->count(),
                'allocated_total' => $allocatedTotal,
                'remaining_amount' => $remainingAmount,
            ];
        }, attempts: 3);
    }

    /**
     * @return array<int, array{ledger_transaction_id: int, amount: float}>
     */
    private function buildAutoAllocationPlanForPayment(ShopInvoicePaymentRequest $payment, int $shopId): array
    {
        $payment = ShopInvoicePaymentRequest::query()
            ->whereKey($payment->id)
            ->with('ledgerAllocations:id,payment_request_id,amount')
            ->lockForUpdate()
            ->firstOrFail();

        $paymentSnapshot = $this->allocationIntegritySnapshot($payment);
        $remaining = round(max(0, $paymentSnapshot['actual_remaining']), 2);

        if ($remaining <= self::ALLOCATION_TOLERANCE) {
            return [];
        }

        $openSettlements = $this->getOpenDailySettlements($shopId)
            ->filter(fn (array $settlement): bool => (float) $settlement['remaining_due'] > self::ALLOCATION_TOLERANCE)
            ->sortBy([['business_date', 'asc'], ['id', 'asc']])
            ->values();

        $allocationPlan = [];

        foreach ($openSettlements as $settlement) {
            if ($remaining <= self::ALLOCATION_TOLERANCE) {
                break;
            }

            $settlementRemaining = round((float) ($settlement['remaining_due'] ?? 0.0), 2);
            if ($settlementRemaining <= self::ALLOCATION_TOLERANCE) {
                continue;
            }

            $amount = round(min($remaining, $settlementRemaining), 2);
            if ($amount <= self::ALLOCATION_TOLERANCE) {
                continue;
            }

            $allocationPlan[] = [
                'ledger_transaction_id' => (int) $settlement['id'],
                'amount' => $amount,
            ];

            $remaining = round($remaining - $amount, 2);
        }

        return $allocationPlan;
    }

    /**
     * Remove / reverse an existing allocation.
     */
    public function removeAllocation(ShopPaymentLedgerAllocation $allocation, int $userId): void
    {
        DB::transaction(function () use ($allocation): void {
            $allocation = ShopPaymentLedgerAllocation::query()
                ->whereKey($allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $allocation->delete();
        }, attempts: 3);
    }

    /**
     * @param  array<int, array{ledger_transaction_id: int, amount: float}>  $allocations
     */
    public function reconcile(
        ShopInvoicePaymentRequest $paymentRequest,
        int $shopId,
        array $allocations,
        int $userId,
    ): Collection {
        return DB::transaction(function () use ($paymentRequest, $shopId, $allocations, $userId): Collection {
            $paymentRequest = ShopInvoicePaymentRequest::query()
                ->whereKey($paymentRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertFinalizedPayment($paymentRequest, $shopId);

            $existingAllocations = ShopPaymentLedgerAllocation::query()
                ->where('payment_request_id', $paymentRequest->id)
                ->lockForUpdate()
                ->get();

            if ($existingAllocations->isNotEmpty()) {
                return $existingAllocations;
            }

            $linkedSettlements = ShopLedgerTransaction::query()
                ->with('entryType:id,code')
                ->where('reference_type', ShopInvoicePaymentRequest::class)
                ->where('reference_id', $paymentRequest->id)
                ->lockForUpdate()
                ->get()
                ->filter(fn (ShopLedgerTransaction $transaction): bool => $transaction->entryType?->code === 'shop_paid_company');

            if ($linkedSettlements->isNotEmpty()) {
                if ($linkedSettlements->contains(fn (ShopLedgerTransaction $transaction): bool => (int) $transaction->shop_id !== $shopId)) {
                    throw ValidationException::withMessages([
                        'payment_ref' => 'This payment already has a linked settlement for a different shop.',
                    ]);
                }

                throw ValidationException::withMessages([
                    'payment_ref' => 'This payment already has a linked shop settlement.',
                ]);
            }

            $transactionIds = collect($allocations)
                ->pluck('ledger_transaction_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            $transactions = ShopLedgerTransaction::query()
                ->with('entryType')
                ->whereIn('id', $transactionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($transactions->count() !== count($transactionIds)) {
                throw ValidationException::withMessages([
                    'allocations' => 'One or more selected shop transactions no longer exist.',
                ]);
            }

            $alreadyReconciled = ShopPaymentLedgerAllocation::query()
                ->whereIn('shop_ledger_transaction_id', $transactionIds)
                ->lockForUpdate()
                ->selectRaw('shop_ledger_transaction_id, COALESCE(SUM(amount), 0) as amount')
                ->groupBy('shop_ledger_transaction_id')
                ->pluck('amount', 'shop_ledger_transaction_id');

            $netAmount = 0.0;
            foreach ($allocations as $allocation) {
                $transaction = $transactions->get($allocation['ledger_transaction_id']);
                $amount = round((float) $allocation['amount'], 2);
                $this->assertEligibleTransaction($transaction, $shopId, $amount);

                $openAmount = round(
                    abs((float) $transaction->settlement_delta) - (float) ($alreadyReconciled->get($transaction->id) ?? 0),
                    2,
                );

                if ($amount > $openAmount) {
                    throw ValidationException::withMessages([
                        'allocations' => "{$transaction->entryType?->name} on {$transaction->business_date?->format('d M Y')} has only ₹".number_format(max(0, $openAmount), 2).' open.',
                    ]);
                }

                $netAmount += (float) $transaction->settlement_delta > 0 ? $amount : -$amount;
            }

            $netAmount = round($netAmount, 2);
            $paymentAmount = round((float) $paymentRequest->reconciled_amount, 2);
            if ($netAmount !== $paymentAmount) {
                throw ValidationException::withMessages([
                    'allocations' => 'Selected shop credits minus debits must exactly equal the finalized payment amount.',
                ]);
            }

            foreach ($allocations as $allocation) {
                ShopPaymentLedgerAllocation::query()->create([
                    'payment_request_id' => $paymentRequest->id,
                    'shop_id' => $shopId,
                    'shop_ledger_transaction_id' => $allocation['ledger_transaction_id'],
                    'amount' => round((float) $allocation['amount'], 2),
                    'reconciled_by' => $userId,
                ]);
            }

            $this->dailyLedgerService->recordEntry([
                'shop_id' => $shopId,
                'business_date' => $paymentRequest->payment_date?->toDateString() ?? today()->toDateString(),
                'entry_type_code' => 'shop_paid_company',
                'amount' => $paymentAmount,
                'funding_source' => 'sales',
                'reference_type' => ShopInvoicePaymentRequest::class,
                'reference_id' => $paymentRequest->id,
                'notes' => 'Finalized company receipt: '.($paymentRequest->payment_reference ?: 'Shop payment'),
                'entered_by' => $userId,
            ]);

            return ShopPaymentLedgerAllocation::query()
                ->where('payment_request_id', $paymentRequest->id)
                ->with('ledgerTransaction.entryType')
                ->get();
        }, attempts: 3);
    }

    private function assertFinalizedPayment(ShopInvoicePaymentRequest $paymentRequest, int $shopId): void
    {
        if ((int) $paymentRequest->shop_id !== $shopId) {
            throw ValidationException::withMessages([
                'payment_ref' => 'This payment belongs to a different shop.',
            ]);
        }

        if ($paymentRequest->allocations()->exists()) {
            throw ValidationException::withMessages([
                'payment_ref' => 'This payment was processed through the legacy invoice-allocation flow and cannot be reconciled through the new Shop Settlement flow.',
            ]);
        }

        if ($paymentRequest->reconciliation_status !== 'reconciled'
            || ! $paymentRequest->reconciliations()->where('is_finalized', true)->exists()) {
            throw ValidationException::withMessages([
                'payment_ref' => 'Shop transactions can be reconciled only after the company payment is finalized.',
            ]);
        }
    }

    private function assertEligibleTransaction(?ShopLedgerTransaction $transaction, int $shopId, float $amount): void
    {
        if (! $transaction instanceof ShopLedgerTransaction || (int) $transaction->shop_id !== $shopId) {
            throw ValidationException::withMessages([
                'allocations' => 'Selected transactions must belong to the same shop as the payment.',
            ]);
        }

        if ($transaction->status === 'void' || $transaction->status === 'voided') {
            throw ValidationException::withMessages([
                'allocations' => 'Voided shop transactions cannot be reconciled.',
            ]);
        }

        if ((float) $transaction->settlement_delta === 0.0
            || $transaction->entryType?->code === 'shop_paid_company') {
            throw ValidationException::withMessages([
                'allocations' => 'Selected transactions must have an open settlement effect.',
            ]);
        }

        if ($amount <= 0.0) {
            throw ValidationException::withMessages([
                'allocations' => 'Allocation amounts must be greater than zero.',
            ]);
        }
    }
}
