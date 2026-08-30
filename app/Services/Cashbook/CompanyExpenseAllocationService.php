<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyExpenseLedgerAllocation;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\ShopInvoicePaymentRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyExpenseAllocationService
{
    /**
     * Allocate a verified company payment or statement entry across shop expense obligations.
     *
     * @param  array<int, array{ledger_transaction_id: int, amount: float}>  $allocations
     * @return Collection<int, CompanyExpenseLedgerAllocation>
     */
    public function allocate(
        CompanyAccountStatementEntry|ShopInvoicePaymentRequest $payment,
        int $shopId,
        array $allocations,
        int $userId,
        ?string $allocationDate = null,
        ?string $notes = null
    ): Collection {
        return DB::transaction(function () use ($payment, $shopId, $allocations, $userId, $allocationDate, $notes): Collection {
            $isStatement = $payment instanceof CompanyAccountStatementEntry;
            $paymentAmount = (float) $payment->amount;

            if ($isStatement && (! $payment->is_finalized || $payment->status !== 'reconciled')) {
                throw ValidationException::withMessages([
                    'payment' => 'Only verified and finalized company statement entries can be allocated.',
                ]);
            }

            $existingAllocated = (float) CompanyExpenseLedgerAllocation::query()
                ->where('status', 'active')
                ->where(function ($q) use ($isStatement, $payment): void {
                    if ($isStatement) {
                        $q->where('company_statement_entry_id', $payment->id);
                    } else {
                        $q->where('payment_request_id', $payment->id);
                    }
                })
                ->sum('allocated_amount');

            $availableToAllocate = round($paymentAmount - $existingAllocated, 2);

            $requestedTotal = round((float) collect($allocations)->sum('amount'), 2);
            if ($requestedTotal > $availableToAllocate) {
                throw ValidationException::withMessages([
                    'allocations' => "Total allocations (₹{$requestedTotal}) exceed available payment amount (₹{$availableToAllocate}).",
                ]);
            }

            $txIds = collect($allocations)->pluck('ledger_transaction_id')->map(fn ($id) => (int) $id)->all();
            $transactions = ShopLedgerTransaction::query()
                ->whereIn('id', $txIds)
                ->where('shop_id', $shopId)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($transactions->count() !== count($txIds)) {
                throw ValidationException::withMessages([
                    'allocations' => 'One or more selected shop expense obligations do not exist or belong to a different shop.',
                ]);
            }

            $created = collect();
            $effectiveDate = $allocationDate ?? ($isStatement ? $payment->transaction_date?->toDateString() : $payment->payment_date?->toDateString()) ?? today()->toDateString();

            foreach ($allocations as $item) {
                $txId = (int) $item['ledger_transaction_id'];
                $amount = round((float) $item['amount'], 2);
                $tx = $transactions->get($txId);

                $coverage = $this->getObligationCoverage($tx);
                if ($amount > $coverage['remaining_amount']) {
                    throw ValidationException::withMessages([
                        'allocations' => "Allocation amount ₹{$amount} exceeds remaining payable amount ₹{$coverage['remaining_amount']} for transaction #{$txId}.",
                    ]);
                }

                $record = CompanyExpenseLedgerAllocation::create([
                    'company_statement_entry_id' => $isStatement ? $payment->id : null,
                    'payment_request_id' => $isStatement ? null : $payment->id,
                    'shop_id' => $shopId,
                    'shop_ledger_transaction_id' => $txId,
                    'allocated_amount' => $amount,
                    'allocation_date' => $effectiveDate,
                    'status' => 'active',
                    'notes' => $notes,
                    'allocated_by' => $userId,
                ]);

                $created->push($record);
            }

            return $created;
        });
    }

    /**
     * Calculate informational coverage of a shop ledger expense obligation.
     *
     * @return array{payable_amount: float, covered_amount: float, remaining_amount: float, status: string, is_reimbursable: bool}
     */
    public function getObligationCoverage(ShopLedgerTransaction $transaction): array
    {
        $isPayableByCompany = in_array((string) $transaction->funding_source, ['company_later'], true)
            || (float) $transaction->company_pending_delta > 0;

        $grossPayable = $isPayableByCompany ? (float) $transaction->amount : 0.0;

        $coveredAmount = (float) CompanyExpenseLedgerAllocation::query()
            ->where('shop_ledger_transaction_id', $transaction->id)
            ->where('status', 'active')
            ->sum('allocated_amount');

        $remaining = max(0.0, round($grossPayable - $coveredAmount, 2));

        $status = match (true) {
            ! $isPayableByCompany => 'Non-Payable',
            $coveredAmount <= 0.0 => 'Uncovered',
            $remaining <= 0.0 => 'Covered',
            default => 'Partially covered',
        };

        return [
            'payable_amount' => round($grossPayable, 2),
            'covered_amount' => round($coveredAmount, 2),
            'remaining_amount' => $remaining,
            'status' => $status,
            'is_reimbursable' => $isPayableByCompany,
        ];
    }

    /**
     * Soft-reverse an active allocation preserving complete audit history.
     */
    public function reverseAllocation(
        CompanyExpenseLedgerAllocation $allocation,
        int $userId,
        string $reason
    ): CompanyExpenseLedgerAllocation {
        return DB::transaction(function () use ($allocation, $userId, $reason): CompanyExpenseLedgerAllocation {
            $allocation = CompanyExpenseLedgerAllocation::query()
                ->whereKey($allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($allocation->status === 'reversed') {
                return $allocation;
            }

            $allocation->update([
                'status' => 'reversed',
                'reversed_by' => $userId,
                'reversed_at' => Carbon::now(),
                'reversal_reason' => $reason,
            ]);

            return $allocation->fresh();
        });
    }
}
