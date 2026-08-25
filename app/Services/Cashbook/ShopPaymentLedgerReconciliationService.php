<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\ShopInvoicePaymentRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopPaymentLedgerReconciliationService
{
    public function __construct(
        private readonly DailyLedgerService $dailyLedgerService,
    ) {}

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
