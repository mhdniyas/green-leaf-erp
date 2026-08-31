<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Services\Finance\JournalService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopPaymentLedgerReconciliationService
{
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
    ): Collection {
        if (empty($allocations)) {
            throw ValidationException::withMessages([
                'allocations' => 'Please provide at least one settlement allocation.',
            ]);
        }

        return DB::transaction(function () use ($payment, $allocations, $userId): Collection {
            $payment = ShopInvoicePaymentRequest::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $shopId = (int) $payment->shop_id;

            $alreadyAllocated = (float) ShopPaymentLedgerAllocation::query()
                ->where('payment_request_id', $payment->id)
                ->lockForUpdate()
                ->sum('amount');

            $availableToAllocate = round(max(0, (float) $payment->requested_amount - $alreadyAllocated), 2);

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

                $allocationRecord = ShopPaymentLedgerAllocation::query()->create([
                    'payment_request_id' => $payment->id,
                    'shop_id' => $shopId,
                    'shop_ledger_transaction_id' => $transaction->id,
                    'amount' => $amount,
                    'reconciled_by' => $userId,
                ]);

                $createdAllocations->push($allocationRecord);
            }

            return $createdAllocations;
        }, attempts: 3);
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
