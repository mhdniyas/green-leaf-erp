<?php

declare(strict_types=1);

namespace App\Domains\ShopOrder\Actions;

use App\DTOs\Inventory\WastageEntryData;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\StockMovement;
use App\Services\Inventory\StockLedgerService;
use App\Services\Inventory\WastageService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveDeliveryReviewAction
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly StockLedgerService $stockLedgerService,
        private readonly WastageService $wastageService,
    ) {}

    /**
     * @param  array<int, float>  $reportedDeliveredQuantities
     */
    public function submit(
        ShopOrder $order,
        array $reportedDeliveredQuantities,
        int $userId,
        ?string $deliveryNote
    ): ShopOrder {
        return DB::transaction(function () use ($order, $reportedDeliveredQuantities, $userId, $deliveryNote): ShopOrder {
            /** @var ShopOrder $lockedOrder */
            $lockedOrder = ShopOrder::query()
                ->with(['items.product', 'invoice.items'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->assertCurrentState($lockedOrder, ['in_transit'], ['not_started', 'correction_requested']);

            $invoice = $lockedOrder->invoice ?? $this->shopInvoiceService->synchronizeOrderInvoice($lockedOrder, $userId);

            foreach ($lockedOrder->items as $item) {
                $expectedQty = $item->loaded_qty !== null
                    ? round((float) $item->loaded_qty, 2)
                    : round((float) $item->approved_qty, 2);
                $reportedReceivedQty = round((float) Arr::get($reportedDeliveredQuantities, $item->id, 0), 2);

                if ($reportedReceivedQty < 0 || $reportedReceivedQty > $expectedQty) {
                    $productName = $item->product?->name ?? 'this item';

                    throw ValidationException::withMessages([
                        "delivered_qty.{$item->id}" => "Received quantity for {$productName} must be between 0 and {$expectedQty}.",
                    ]);
                }

                $item->update([
                    'shop_reported_received_qty' => $reportedReceivedQty,
                    'shop_reported_missing_qty' => round(max(0, $expectedQty - $reportedReceivedQty), 2),
                    'shop_reported_damaged_qty' => 0,
                    'shop_reported_returned_qty' => 0,
                ]);
            }

            $invoice->update([
                'delivery_status' => 'awaiting_review',
                'status' => 'delivery_review',
                'delivery_note' => $deliveryNote,
                'delivery_confirmed_by' => $userId,
                'delivery_confirmed_at' => now(),
            ]);

            $lockedOrder->update([
                'delivery_status' => 'pending_approval',
                'delivery_review_status' => 'pending',
                'delivery_notes' => $deliveryNote,
                'shop_checked_by' => $userId,
                'shop_checked_at' => now(),
                'admin_reviewed_by' => null,
                'admin_reviewed_at' => null,
                'admin_review_note' => null,
                'is_delivered' => false,
                'delivered_at' => null,
                'delivered_by' => $userId,
            ]);

            return $lockedOrder->fresh(['items.product', 'invoice.items']);
        });
    }

    /**
     * @param  array<int|string, mixed>  $approvedDeliveredQuantities
     * @param  array<int|string, mixed>  $itemReviewNotes
     * @param  array<int|string, string>  $deliveryDiscrepancyTypes
     * @param  array<int|string, string>  $deliveryDiscrepancyNotes
     */
    public function approve(
        ShopOrder $order,
        array $approvedDeliveredQuantities,
        array $itemReviewNotes,
        array $deliveryDiscrepancyTypes,
        array $deliveryDiscrepancyNotes,
        int $userId,
        ?string $reviewNote
    ): ShopOrder {
        return DB::transaction(function () use (
            $order,
            $approvedDeliveredQuantities,
            $itemReviewNotes,
            $deliveryDiscrepancyTypes,
            $deliveryDiscrepancyNotes,
            $userId,
            $reviewNote
        ): ShopOrder {
            /** @var ShopOrder $lockedOrder */
            $lockedOrder = ShopOrder::query()
                ->with(['items.product', 'invoice.items'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->assertCurrentState($lockedOrder, ['pending_approval'], ['pending']);

            $invoice = $lockedOrder->invoice ?? $this->shopInvoiceService->synchronizeOrderInvoice($lockedOrder, $userId);
            /** @var ShopInvoice $invoice */
            $invoice = ShopInvoice::query()->with('items')->lockForUpdate()->findOrFail($invoice->id);
            $invoiceItemsByOrderItemId = $invoice->items->keyBy(fn ($invoiceItem) => (int) $invoiceItem->shop_order_item_id);

            $hasShortage = false;
            foreach ($lockedOrder->items as $item) {
                $expectedQty = $item->loaded_qty !== null
                    ? round((float) $item->loaded_qty, 2)
                    : round((float) $item->approved_qty, 2);
                $deliveredQty = round((float) Arr::get($approvedDeliveredQuantities, $item->id, $item->shop_reported_received_qty ?? 0), 2);

                if ($deliveredQty < 0 || $deliveredQty > $expectedQty) {
                    $productName = $item->product?->name ?? 'this item';

                    throw ValidationException::withMessages([
                        "approved_delivered_qty.{$item->id}" => "Approved delivered quantity for {$productName} must be between 0 and {$expectedQty}.",
                    ]);
                }

                $shortageQty = round(max(0, $expectedQty - $deliveredQty), 2);
                $hasShortage = $hasShortage || $shortageQty > 0.0;
                $discrepancyType = $shortageQty > 0.0
                    ? Arr::get($deliveryDiscrepancyTypes, $item->id, 'wastage')
                    : 'none';
                $discrepancyNote = Arr::get($deliveryDiscrepancyNotes, $item->id);
                $itemReviewNote = trim((string) Arr::get($itemReviewNotes, $item->id, ''));
                $shortageValue = round($shortageQty * (float) $item->unit_cost, 2);

                $item->update([
                    'delivered_qty' => $deliveredQty,
                    'shortage_qty' => $shortageQty,
                    'shortage_value' => $shortageValue,
                    'delivery_discrepancy_type' => $discrepancyType,
                    'delivery_discrepancy_note' => $discrepancyNote,
                    'notes' => $itemReviewNote !== ''
                        ? $this->appendReviewNote($item->notes, 'Delivery review', $itemReviewNote)
                        : $item->notes,
                ]);

                $invoiceItem = $invoiceItemsByOrderItemId->get((int) $item->id);
                if ($invoiceItem) {
                    $shortageAmount = round($shortageQty * (float) $invoiceItem->unit_price, 2);

                    $invoiceItem->update([
                        'delivered_qty' => $deliveredQty,
                        'shortage_qty' => $shortageQty,
                        'shortage_amount' => $shortageAmount,
                        'final_line_total' => round((float) $invoiceItem->line_subtotal - $shortageAmount, 2),
                    ]);
                }

                if ($shortageQty > 0.0) {
                    $this->recordApprovedShortage($lockedOrder, $item, $shortageQty, $userId, $discrepancyType, $discrepancyNote);
                }
            }

            $invoice->update([
                'delivery_status' => $hasShortage ? 'approved_after_discrepancy' : 'received_full',
                'delivery_note' => $this->appendReviewNote($invoice->delivery_note, 'Delivery approved', $reviewNote),
                'delivery_confirmed_by' => $invoice->delivery_confirmed_by ?? $lockedOrder->shop_checked_by ?? $userId,
                'delivery_confirmed_at' => $invoice->delivery_confirmed_at ?? now(),
            ]);

            $invoice = $this->shopInvoiceService->recalculate($invoice->fresh('items'));

            $lockedOrder->update([
                'delivery_status' => $hasShortage ? 'partially_delivered' : 'delivered',
                'delivery_review_status' => 'approved',
                'is_delivered' => true,
                'delivered_at' => now(),
                'delivered_by' => $lockedOrder->delivered_by ?? $lockedOrder->shop_checked_by ?? $userId,
                'delivery_notes' => $this->appendReviewNote($lockedOrder->delivery_notes, 'Delivery approved', $reviewNote),
                'admin_reviewed_by' => $userId,
                'admin_reviewed_at' => now(),
                'admin_review_note' => $reviewNote,
                'total_shortage_value' => $invoice->shortage_total,
                'balance_amount' => $invoice->balance_amount,
                'payment_status' => $invoice->payment_status,
                'cash_discrepancy' => round((float) $invoice->final_total - (float) $lockedOrder->cash_collected, 2),
            ]);

            return $lockedOrder->fresh(['items.product', 'invoice.items']);
        });
    }

    public function reject(ShopOrder $order, int $userId, ?string $reviewNote): ShopOrder
    {
        return DB::transaction(function () use ($order, $userId, $reviewNote): ShopOrder {
            /** @var ShopOrder $lockedOrder */
            $lockedOrder = ShopOrder::query()
                ->with(['items', 'invoice.items'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->assertCurrentState($lockedOrder, ['pending_approval'], ['pending']);

            foreach ($lockedOrder->items as $item) {
                $item->update([
                    'shop_reported_received_qty' => null,
                    'shop_reported_missing_qty' => 0,
                    'shop_reported_damaged_qty' => 0,
                    'shop_reported_returned_qty' => 0,
                    'delivered_qty' => null,
                    'shortage_qty' => 0,
                    'shortage_value' => 0,
                ]);
            }

            if ($lockedOrder->invoice) {
                foreach ($lockedOrder->invoice->items as $invoiceItem) {
                    $invoiceItem->update([
                        'delivered_qty' => 0,
                        'shortage_qty' => 0,
                        'shortage_amount' => 0,
                        'final_line_total' => (float) $invoiceItem->line_subtotal,
                    ]);
                }

                $lockedOrder->invoice->update([
                    'delivery_status' => 'pending',
                    'status' => 'generated',
                    'delivery_note' => $this->appendReviewNote($lockedOrder->invoice->delivery_note, 'Delivery correction requested', $reviewNote),
                    'delivery_confirmed_by' => null,
                    'delivery_confirmed_at' => null,
                ]);

                $this->shopInvoiceService->recalculate($lockedOrder->invoice->fresh('items'));
            }

            $lockedOrder->update([
                'delivery_status' => 'in_transit',
                'delivery_review_status' => 'correction_requested',
                'is_delivered' => false,
                'delivered_at' => null,
                'delivered_by' => null,
                'delivery_notes' => $this->appendReviewNote($lockedOrder->delivery_notes, 'Delivery correction requested', $reviewNote),
                'admin_reviewed_by' => $userId,
                'admin_reviewed_at' => now(),
                'admin_review_note' => $reviewNote,
                'total_shortage_value' => 0,
                'balance_amount' => 0,
                'cash_discrepancy' => 0,
            ]);

            return $lockedOrder->fresh(['items', 'invoice.items']);
        });
    }

    /**
     * @param  array<int, string>  $allowedDeliveryStatuses
     * @param  array<int, string>  $allowedReviewStatuses
     */
    private function assertCurrentState(ShopOrder $order, array $allowedDeliveryStatuses, array $allowedReviewStatuses): void
    {
        if (! in_array($order->delivery_status, $allowedDeliveryStatuses, true)
            || ! in_array($order->delivery_review_status, $allowedReviewStatuses, true)) {
            throw ValidationException::withMessages([
                'order' => 'This delivery is no longer in a reviewable state.',
            ]);
        }
    }

    private function appendReviewNote(?string $existing, string $label, ?string $note): string
    {
        $message = trim($label.($note ? ': '.$note : ''));

        return trim(implode("\n", array_filter([
            $existing,
            '['.now()->format('d M Y H:i').'] '.$message,
        ])));
    }

    private function recordApprovedShortage(
        ShopOrder $order,
        object $item,
        float $shortageQty,
        int $userId,
        string $discrepancyType,
        ?string $discrepancyNote
    ): void {
        if ($discrepancyType === 'wastage') {
            $notes = "Delivery shortage discrepancy (approved): {$order->order_number}";

            $this->stockLedgerService->consumeSortedStockForProduct(
                $item->product_id,
                $shortageQty,
                $userId,
                StockMovementType::Wastage,
                $notes
            );

            $movements = StockMovement::query()
                ->where('product_id', $item->product_id)
                ->where('type', StockMovementType::Wastage->value)
                ->where('created_by', $userId)
                ->where('notes', $notes)
                ->get();

            foreach ($movements as $movement) {
                $this->wastageService->record(new WastageEntryData(
                    productId: $movement->product_id,
                    batchId: $movement->batch_id,
                    grade: $movement->grade instanceof ProductGrade ? $movement->grade->value : (string) $movement->grade,
                    quantity: (float) $movement->quantity,
                    costPerKg: (float) $movement->cost_per_unit,
                    reason: WastageReason::TransitDamage,
                    wastageDate: now()->toDateString(),
                    notes: 'Delivery discrepancy wastage: '.($discrepancyNote ?? 'Order delivery discrepancy'),
                ), $userId);
            }

            return;
        }

        $this->stockLedgerService->consumeSortedStockForProduct(
            $item->product_id,
            $shortageQty,
            $userId,
            StockMovementType::Adjustment,
            "Delivery shortage discrepancy other (approved): {$order->order_number}"
        );
    }
}
