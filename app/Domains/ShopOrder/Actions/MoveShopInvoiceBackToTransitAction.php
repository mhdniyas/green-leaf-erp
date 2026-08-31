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

class MoveShopInvoiceBackToTransitAction
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly ResolveDeliveryReviewAction $resolveDeliveryReviewAction,
        private readonly BalanceCalculator $balanceCalculator,
    ) {}

    public function execute(ShopInvoice $invoice, int $userId, string $reason): ShopInvoice
    {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required to move an invoice back to in transit.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $userId, $cleanReason): ShopInvoice {
            /** @var ShopInvoice $lockedInvoice */
            $lockedInvoice = ShopInvoice::query()
                ->with(['items', 'order.items'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $order = $lockedInvoice->order;
            if (! $order instanceof ShopOrder) {
                throw ValidationException::withMessages([
                    'invoice' => 'Cannot move back to transit: This invoice has no linked shop order.',
                ]);
            }

            /** @var ShopOrder $lockedOrder */
            $lockedOrder = ShopOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->assertSafeToMoveBackToTransit($lockedInvoice, $lockedOrder);

            // Idempotency check: If already in initial in_transit state without check-in
            if (
                $lockedOrder->delivery_status === 'in_transit'
                && in_array($lockedOrder->delivery_review_status, ['not_started', 'none'], true)
                && $lockedOrder->shop_checked_at === null
                && $lockedInvoice->delivery_status === 'pending'
                && $lockedInvoice->status === 'generated'
            ) {
                return $lockedInvoice->fresh(['items', 'order.items']);
            }

            // Snapshot before state for audit preservation
            $before = [
                'order_delivery_status' => (string) $lockedOrder->delivery_status,
                'order_delivery_review_status' => (string) $lockedOrder->delivery_review_status,
                'shop_checked_at' => $lockedOrder->shop_checked_at?->toDateTimeString(),
                'shop_checked_by' => $lockedOrder->shop_checked_by,
                'is_delivered' => (bool) $lockedOrder->is_delivered,
                'invoice_status' => (string) $lockedInvoice->status,
                'invoice_delivery_status' => (string) $lockedInvoice->delivery_status,
                'total_shortage_value' => round((float) $lockedOrder->total_shortage_value, 2),
                'total_excess_value' => round((float) $lockedOrder->total_excess_value, 2),
                'reported_items' => $lockedOrder->items->map(static fn ($item): array => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'requested_qty' => (float) ($item->requested_qty ?? 0),
                    'approved_qty' => (float) ($item->approved_qty ?? 0),
                    'loaded_qty' => (float) ($item->loaded_qty ?? 0),
                    'delivered_qty' => (float) ($item->delivered_qty ?? 0),
                    'shop_reported_received_qty' => (float) ($item->shop_reported_received_qty ?? 0),
                    'discrepancy_type' => (string) ($item->delivery_discrepancy_type ?? 'none'),
                    'discrepancy_note' => $item->delivery_discrepancy_note,
                ])->all(),
            ];

            // 1. Reverse discrepancy inventory adjustments if any were created
            $this->resolveDeliveryReviewAction->reverseInventoryAdjustmentsForApprovedReview($lockedOrder, $userId);

            // 2. Reset ShopOrderItems to loaded/in-transit state
            foreach ($lockedOrder->items as $orderItem) {
                $orderItem->update([
                    'delivered_qty' => null,
                    'shop_reported_received_qty' => null,
                    'shop_verified_at' => null,
                    'shop_verified_by' => null,
                    'shortage_qty' => 0,
                    'excess_qty' => 0,
                    'shortage_value' => 0,
                    'excess_value' => 0,
                    'delivery_discrepancy_type' => 'none',
                    'delivery_discrepancy_note' => null,
                ]);
            }

            // 3. Reset ShopInvoiceItems
            foreach ($lockedInvoice->items as $invoiceItem) {
                $invoiceItem->update([
                    'delivered_qty' => 0,
                    'delivered_price_quantity' => 0,
                    'shortage_qty' => 0,
                    'excess_qty' => 0,
                    'shortage_amount' => 0,
                    'excess_amount' => 0,
                    'final_line_total' => (float) $invoiceItem->line_subtotal,
                ]);
            }

            // 4. Reset ShopInvoice to generated/pending
            $lockedInvoice->update([
                'delivery_status' => 'pending',
                'status' => 'generated',
                'finalized_at' => null,
                'finalized_by' => null,
                'delivery_confirmed_by' => null,
                'delivery_confirmed_at' => null,
                'delivery_discrepancy_note' => null,
                'payment_status' => 'unpaid',
                'delivery_note' => $this->appendActionNote($lockedInvoice->delivery_note, 'Moved back to in transit by admin', $cleanReason),
            ]);

            $recalculatedInvoice = $this->shopInvoiceService->recalculate($lockedInvoice->fresh('items'));

            // 5. Reset ShopOrder to in_transit
            $lockedOrder->update([
                'delivery_status' => 'in_transit',
                'delivery_review_status' => 'not_started',
                'is_allocation_completed' => true,
                'is_delivered' => false,
                'delivered_at' => null,
                'delivered_by' => null,
                'shop_checked_at' => null,
                'shop_checked_by' => null,
                'admin_reviewed_at' => null,
                'admin_reviewed_by' => null,
                'admin_review_note' => null,
                'total_shortage_value' => 0,
                'total_excess_value' => 0,
                'balance_amount' => 0,
                'cash_discrepancy' => 0,
                'delivery_notes' => $this->appendActionNote($lockedOrder->delivery_notes, 'Moved back to in transit by admin', $cleanReason),
            ]);

            // 6. Void any Cashbook ledger transaction if it was created
            $purchaseBillType = LedgerEntryType::query()->where('code', 'purchase_bill')->first();
            if ($purchaseBillType) {
                $ledgerTxn = ShopLedgerTransaction::query()
                    ->where('shop_id', $lockedInvoice->shop_id)
                    ->where('entry_type_id', $purchaseBillType->id)
                    ->where('reference_type', ShopInvoice::class)
                    ->where('reference_id', $lockedInvoice->id)
                    ->where('status', '!=', TransactionStatus::Void->value)
                    ->first();

                if ($ledgerTxn) {
                    $ledgerTxn->update([
                        'status' => TransactionStatus::Void->value,
                        'notes' => trim($ledgerTxn->notes.' - Voided due to invoice moved back to in transit: '.$cleanReason),
                    ]);

                    $this->balanceCalculator->recalculateBalancesFrom(
                        shopId: (int) $lockedInvoice->shop_id,
                        fromDate: $ledgerTxn->transaction_date->toDateString()
                    );
                }
            }

            // 7. Re-sync owned shop balance
            $this->shopInvoiceService->syncOwnedShopBalanceForInvoice($recalculatedInvoice, $userId);

            // 8. Record audit activity log
            $causer = User::query()->find($userId);

            activity('shop_order')
                ->performedOn($lockedOrder)
                ->causedBy($causer)
                ->event('moved_back_to_transit')
                ->withProperties([
                    'before' => $before,
                    'after' => [
                        'delivery_status' => 'in_transit',
                        'delivery_review_status' => 'not_started',
                        'shop_checked_at' => null,
                        'shop_checked_by' => null,
                    ],
                    'reason' => $cleanReason,
                    'source' => 'admin_move_back_to_transit',
                ])
                ->log('moved_back_to_transit');

            activity('shop_invoice')
                ->performedOn($recalculatedInvoice)
                ->causedBy($causer)
                ->event('moved_back_to_transit')
                ->withProperties([
                    'before' => [
                        'status' => $before['invoice_status'],
                        'delivery_status' => $before['invoice_delivery_status'],
                    ],
                    'after' => [
                        'status' => 'generated',
                        'delivery_status' => 'pending',
                        'final_total' => round((float) $recalculatedInvoice->final_total, 2),
                    ],
                    'reason' => $cleanReason,
                    'source' => 'admin_move_back_to_transit',
                ])
                ->log('moved_back_to_transit');

            return $recalculatedInvoice->fresh(['items', 'order.items']);
        });
    }

    private function assertSafeToMoveBackToTransit(ShopInvoice $invoice, ShopOrder $order): void
    {
        if ($invoice->isFinalized() || $invoice->finalized_at !== null || in_array($invoice->status, ['finalized', 'paid', 'payment_pending'], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice is finalized. Please use "Revert Finalization" first before moving back to in transit.',
            ]);
        }

        if ((float) $invoice->paid_amount > 0.0001 || in_array($invoice->payment_status, ['partially_paid', 'paid'], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'Cannot move back to in transit: Payments have already been recorded for this invoice.',
            ]);
        }

        if ($invoice->status === 'cancelled' || $order->state === 'cancelled') {
            throw ValidationException::withMessages([
                'invoice' => 'Cannot move back to in transit: Invoice or order is cancelled.',
            ]);
        }

        if ($invoice->paymentRequests()->whereIn('status', ['approved', 'completed'])->exists()) {
            throw ValidationException::withMessages([
                'invoice' => 'Cannot move back to in transit: Approved payment requests exist for this invoice.',
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
                    'invoice' => 'Cannot move back to in transit: Cashbook ledger allocations exist for this invoice.',
                ]);
            }
        }
    }

    private function appendActionNote(?string $existing, string $label, string $reason): string
    {
        $timestamp = now()->format('d M Y, h:i A');
        $entry = "[{$timestamp}] {$label}: {$reason}";

        return filled($existing) ? "{$existing}\n{$entry}" : $entry;
    }
}
