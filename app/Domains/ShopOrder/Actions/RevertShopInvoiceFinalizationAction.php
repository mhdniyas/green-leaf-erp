<?php

declare(strict_types=1);

namespace App\Domains\ShopOrder\Actions;

use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevertShopInvoiceFinalizationAction
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly ResolveDeliveryReviewAction $resolveDeliveryReviewAction,
        private readonly BalanceCalculator $balanceCalculator,
    ) {}

    public function execute(ShopInvoice $invoice, User $actor, string $reason): ShopInvoice
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw ValidationException::withMessages([
                'reason' => 'A detailed reason (at least 3 characters) is required to revert invoice finalization.',
            ]);
        }

        if (! $actor->hasRole('admin')) {
            throw ValidationException::withMessages([
                'invoice' => 'Only administrators are authorized to revert invoice finalization.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $actor, $reason): ShopInvoice {
            /** @var ShopInvoice $lockedInvoice */
            $lockedInvoice = ShopInvoice::query()
                ->with(['items.product', 'order.items.product'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            // Idempotency: If already unfinalized, return cleanly
            if ($lockedInvoice->finalized_at === null && $lockedInvoice->status === 'delivery_review') {
                return $lockedInvoice;
            }

            $this->assertSafeForReversal($lockedInvoice);

            $before = [
                'status' => $lockedInvoice->status,
                'delivery_status' => $lockedInvoice->delivery_status,
                'final_total' => round((float) $lockedInvoice->final_total, 2),
                'balance_amount' => round((float) $lockedInvoice->balance_amount, 2),
                'finalized_at' => $lockedInvoice->finalized_at?->toDateTimeString(),
                'finalized_by' => $lockedInvoice->finalized_by,
            ];

            // 1. Revert order state if linked
            if ($order = $lockedInvoice->order) {
                /** @var ShopOrder $lockedOrder */
                $lockedOrder = ShopOrder::query()
                    ->with('items')
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                // Reverse any inventory adjustments from delivery discrepancy resolution
                $this->resolveDeliveryReviewAction->reverseInventoryAdjustmentsForApprovedReview($lockedOrder, (int) $actor->id);

                $lockedOrder->update([
                    'delivery_status' => 'pending_approval',
                    'delivery_review_status' => 'pending',
                    'is_delivered' => false,
                    'delivered_at' => null,
                    'admin_review_note' => 'Finalization reverted by '.$actor->name.': '.$reason,
                ]);
            }

            // 2. Revert invoice state
            $lockedInvoice->forceFill([
                'status' => 'delivery_review',
                'delivery_status' => 'awaiting_review',
                'finalized_at' => null,
                'finalized_by' => null,
                'payment_status' => 'unpaid',
            ])->save();

            // 3. Void cashbook transaction projection
            $this->voidCashbookTransaction($lockedInvoice, (int) $actor->id, $reason);

            // 4. Sync owned shop balance
            $this->shopInvoiceService->syncOwnedShopBalanceForInvoice($lockedInvoice, (int) $actor->id);

            // 5. Activity audit log
            activity('shop_invoice')
                ->performedOn($lockedInvoice)
                ->causedBy($actor)
                ->event('finalization_reverted')
                ->withProperties([
                    'before' => $before,
                    'after' => [
                        'status' => $lockedInvoice->status,
                        'delivery_status' => $lockedInvoice->delivery_status,
                        'final_total' => round((float) $lockedInvoice->final_total, 2),
                        'balance_amount' => round((float) $lockedInvoice->balance_amount, 2),
                        'finalized_at' => null,
                    ],
                    'reason' => $reason,
                    'source' => 'admin_revert_finalization',
                ])
                ->log('Finalization reverted by '.$actor->name.': '.$reason);

            return $lockedInvoice->fresh(['items', 'order']);
        });
    }

    private function assertSafeForReversal(ShopInvoice $invoice): void
    {
        if ((float) $invoice->paid_amount > 0.0001 || in_array((string) $invoice->payment_status, ['partially_paid', 'paid'], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'Cannot revert finalization: Payments have already been recorded against this invoice.',
            ]);
        }

        if ($invoice->paymentRequests()->whereIn('status', ['approved', 'completed'])->exists()) {
            throw ValidationException::withMessages([
                'invoice' => 'Cannot revert finalization: Approved payment requests exist for this invoice.',
            ]);
        }

        $purchaseBillType = LedgerEntryType::query()->where('code', 'purchase_bill')->first();
        if ($purchaseBillType) {
            $hasAllocations = ShopLedgerTransaction::query()
                ->where('shop_id', $invoice->shop_id)
                ->where('entry_type_id', $purchaseBillType->id)
                ->where('reference_type', ShopInvoice::class)
                ->where('reference_id', $invoice->id)
                ->whereHas('paymentLedgerAllocations')
                ->exists();

            if ($hasAllocations) {
                throw ValidationException::withMessages([
                    'invoice' => 'Cannot revert finalization: Cashbook ledger allocations already exist for this invoice.',
                ]);
            }
        }
    }

    private function voidCashbookTransaction(ShopInvoice $invoice, int $userId, string $reason): void
    {
        $purchaseBillType = LedgerEntryType::query()->where('code', 'purchase_bill')->first();
        if (! $purchaseBillType) {
            return;
        }

        $transaction = ShopLedgerTransaction::query()
            ->where('shop_id', $invoice->shop_id)
            ->where('entry_type_id', $purchaseBillType->id)
            ->where('reference_type', ShopInvoice::class)
            ->where('reference_id', $invoice->id)
            ->lockForUpdate()
            ->first();

        if ($transaction instanceof ShopLedgerTransaction) {
            $transaction->update([
                'status' => TransactionStatus::Void->value,
                'voided_by' => $userId,
                'voided_at' => now(),
                'void_reason' => 'Finalization reverted by admin: '.$reason,
            ]);

            $this->balanceCalculator->recalculate((int) $invoice->shop_id, $invoice->business_date->toDateString());
        }
    }
}
