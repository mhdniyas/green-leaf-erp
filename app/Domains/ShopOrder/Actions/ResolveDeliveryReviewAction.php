<?php

declare(strict_types=1);

namespace App\Domains\ShopOrder\Actions;

use App\Enums\Inventory\StockMovementType;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\NegativeStockCreatedNotification;
use App\Notifications\ShopDeliveryReviewSubmittedNotification;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Inventory\StockLedgerService;
use App\Services\ShopInvoices\ShopInvoiceIntegrityValidator;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveDeliveryReviewAction
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly StockLedgerService $stockLedgerService,
        private readonly ShopInvoiceIntegrityValidator $shopInvoiceIntegrityValidator,
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
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

            $invoice = $lockedOrder->invoice;

            if (! $invoice instanceof ShopInvoice) {
                throw ValidationException::withMessages([
                    'invoice' => 'Delivery verification is disabled until the approved daily invoice is generated.',
                ]);
            }

            $this->shopInvoiceIntegrityValidator->assertMatchesApprovedDailyPrices($invoice);

            foreach ($lockedOrder->items as $item) {
                $expectedQty = $item->loaded_qty !== null
                    ? round((float) $item->loaded_qty, 2)
                    : round((float) $item->approved_qty, 2);
                $reportedReceivedQty = round((float) Arr::get($reportedDeliveredQuantities, $item->id, 0), 2);

                if ($reportedReceivedQty < 0) {
                    $productName = $item->product?->name ?? 'this item';

                    throw ValidationException::withMessages([
                        "delivered_qty.{$item->id}" => "Received quantity for {$productName} cannot be negative.",
                    ]);
                }

                $item->update([
                    'shop_reported_received_qty' => $reportedReceivedQty,
                    'shop_reported_missing_qty' => round(max(0, $expectedQty - $reportedReceivedQty), 2),
                    'shop_reported_excess_qty' => round(max(0, $reportedReceivedQty - $expectedQty), 2),
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

            $lockedOrder = $lockedOrder->fresh(['shop', 'items.product', 'invoice.items']);
            $this->notifyAdminsAboutDeliveryReview($lockedOrder);

            return $lockedOrder;
        });
    }

    /**
     * @param  array<int|string, mixed>  $approvedDeliveredQuantities
     * @param  array<int|string, mixed>  $itemReviewNotes
     * @param  array<int|string, string>  $itemInventoryActions
     * @param  array<int|string, string>  $deliveryDiscrepancyTypes
     * @param  array<int|string, string>  $deliveryDiscrepancyNotes
     */
    public function approve(
        ShopOrder $order,
        array $approvedDeliveredQuantities,
        array $itemReviewNotes,
        array $itemInventoryActions,
        array $deliveryDiscrepancyTypes,
        array $deliveryDiscrepancyNotes,
        int $userId,
        ?string $reviewNote
    ): ShopOrder {
        return DB::transaction(function () use (
            $order,
            $approvedDeliveredQuantities,
            $itemReviewNotes,
            $itemInventoryActions,
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

            $invoice = $lockedOrder->invoice;

            if (! $invoice instanceof ShopInvoice) {
                throw ValidationException::withMessages([
                    'invoice' => 'Delivery approval is disabled until the approved daily invoice is generated.',
                ]);
            }

            $invoice = ShopInvoice::query()->with('items')->lockForUpdate()->findOrFail($invoice->id);
            $this->shopInvoiceIntegrityValidator->assertMatchesApprovedDailyPrices($invoice);
            $invoiceItemsByProductId = $invoice->items->keyBy(fn ($invoiceItem) => (int) $invoiceItem->product_id);

            $hasShortage = false;
            $hasDeliveryAdjustment = false;
            foreach ($lockedOrder->items as $item) {
                $expectedQty = $item->loaded_qty !== null
                    ? round((float) $item->loaded_qty, 2)
                    : round((float) $item->approved_qty, 2);
                $deliveredQty = round((float) Arr::get($approvedDeliveredQuantities, $item->id, $item->shop_reported_received_qty ?? 0), 2);

                if ($deliveredQty < 0) {
                    $productName = $item->product?->name ?? 'this item';

                    throw ValidationException::withMessages([
                        "approved_delivered_qty.{$item->id}" => "Approved delivered quantity for {$productName} cannot be negative.",
                    ]);
                }

                $shortageQty = round(max(0, $expectedQty - $deliveredQty), 2);
                $excessQty = round(max(0, $deliveredQty - $expectedQty), 2);
                $hasShortage = $hasShortage || $shortageQty > 0.0;
                $hasDeliveryAdjustment = $hasDeliveryAdjustment || $shortageQty > 0.0 || $excessQty > 0.0;
                $discrepancyType = $excessQty > 0.0
                    ? 'excess'
                    : ($shortageQty > 0.0
                    ? Arr::get($deliveryDiscrepancyTypes, $item->id, 'wastage')
                    : 'none');
                $discrepancyNote = Arr::get($deliveryDiscrepancyNotes, $item->id);
                $itemReviewNote = trim((string) Arr::get($itemReviewNotes, $item->id, ''));
                $inventoryAction = (string) Arr::get(
                    $itemInventoryActions,
                    $item->id,
                    $excessQty > 0.0 ? 'deduct_extra' : 'none',
                );
                $shortageValue = round($shortageQty * (float) $item->unit_cost, 2);
                $excessValue = round($excessQty * (float) $item->unit_cost, 2);

                if ($shortageQty > 0.0 && $inventoryAction === 'add_back') {
                    $this->addShortageBackToInventory($lockedOrder, $item, $shortageQty, $userId);
                }

                if ($excessQty > 0.0 && $inventoryAction === 'deduct_extra') {
                    $availableStock = $this->stockLedgerService->availableStockForProduct((int) $item->product_id);
                    $this->stockLedgerService->consumeStockForProductAllowingNegative(
                        (int) $item->product_id,
                        $excessQty,
                        $userId,
                        StockMovementType::Out,
                        "Delivery excess approved - Order: {$lockedOrder->order_number}; Item: {$item->id}",
                        (int) $item->id,
                    );

                    if ($excessQty > $availableStock + 0.001) {
                        $this->notifyAdminsAboutNegativeStock(
                            $item->product?->name ?? 'Unknown product',
                            $lockedOrder->order_number,
                            round($excessQty - max(0.0, $availableStock), 2),
                            (string) $item->unit,
                        );
                    }
                }

                $item->update([
                    'delivered_qty' => $deliveredQty,
                    'shortage_qty' => $shortageQty,
                    'excess_qty' => $excessQty,
                    'shortage_value' => $shortageValue,
                    'excess_value' => $excessValue,
                    'delivery_discrepancy_type' => $discrepancyType,
                    'delivery_discrepancy_note' => $discrepancyNote,
                    'notes' => $itemReviewNote !== ''
                        ? $this->appendReviewNote($item->notes, 'Delivery review', $itemReviewNote)
                        : $item->notes,
                ]);
            }

            $lockedOrder->items
                ->groupBy(fn ($item) => (int) $item->product_id)
                ->each(function ($items, int $productId) use ($invoiceItemsByProductId): void {
                    $invoiceItem = $invoiceItemsByProductId->get($productId);

                    if (! $invoiceItem) {
                        return;
                    }

                    $deliveredQty = round((float) $items->sum('delivered_qty'), 2);
                    $shortageQty = round((float) $items->sum('shortage_qty'), 2);
                    $excessQty = round((float) $items->sum('excess_qty'), 2);
                    $shortageAmount = round($shortageQty * (float) $invoiceItem->unit_price, 2);
                    $excessAmount = round($excessQty * (float) $invoiceItem->unit_price, 2);

                    $invoiceItem->update([
                        'delivered_qty' => $deliveredQty,
                        'shortage_qty' => $shortageQty,
                        'excess_qty' => $excessQty,
                        'shortage_amount' => $shortageAmount,
                        'excess_amount' => $excessAmount,
                        'final_line_total' => round((float) $invoiceItem->line_subtotal - $shortageAmount + $excessAmount, 2),
                    ]);
                });

            $invoice->update([
                'delivery_status' => $hasDeliveryAdjustment ? 'approved_after_discrepancy' : 'received_full',
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
                'total_excess_value' => $invoice->excess_total,
                'balance_amount' => $invoice->balance_amount,
                'payment_status' => $invoice->payment_status,
                'cash_discrepancy' => round((float) $invoice->final_total - (float) $lockedOrder->cash_collected, 2),
            ]);

            $invoice->loadMissing('shop');

            if ($invoice->shop?->isOwnedAccountingEnabled() && $invoice->business_date) {
                $this->ownedShopAccountingService->syncStoredClosingBalancesFromDate(
                    $invoice->shop,
                    $invoice->business_date,
                    $userId,
                );
            }

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
                    'shop_reported_excess_qty' => 0,
                    'shop_reported_damaged_qty' => 0,
                    'shop_reported_returned_qty' => 0,
                    'shop_verified_by' => null,
                    'shop_verified_at' => null,
                    'shop_verification_note' => null,
                    'delivered_qty' => null,
                    'shortage_qty' => 0,
                    'excess_qty' => 0,
                    'shortage_value' => 0,
                    'excess_value' => 0,
                ]);
            }

            if ($lockedOrder->invoice) {
                foreach ($lockedOrder->invoice->items as $invoiceItem) {
                    $invoiceItem->update([
                        'delivered_qty' => 0,
                        'shortage_qty' => 0,
                        'excess_qty' => 0,
                        'shortage_amount' => 0,
                        'excess_amount' => 0,
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

    private function addShortageBackToInventory(
        ShopOrder $order,
        object $item,
        float $shortageQty,
        int $userId
    ): void {
        $remainingQty = $shortageQty;
        $sourceMovements = StockMovement::query()
            ->where('product_id', $item->product_id)
            ->where('type', StockMovementType::Out->value)
            ->where('notes', 'like', "%Order: {$order->order_number}%")
            ->oldest('id')
            ->get();

        foreach ($sourceMovements as $sourceMovement) {
            if ($remainingQty <= 0.001) {
                break;
            }

            $reversalQty = min($remainingQty, (float) $sourceMovement->quantity);

            StockMovement::query()->create([
                'batch_id' => $sourceMovement->batch_id,
                'product_id' => $item->product_id,
                'warehouse_id' => $sourceMovement->warehouse_id,
                'shop_order_item_id' => $item->id,
                'created_by' => $userId,
                'grade' => $sourceMovement->grade,
                'type' => StockMovementType::SaleReversal,
                'quantity' => $reversalQty,
                'cost_per_unit' => $sourceMovement->cost_per_unit,
                'notes' => "Delivery shortage added back to inventory - Order: {$order->order_number}; Item: {$item->id}",
            ]);

            $remainingQty = round($remainingQty - $reversalQty, 3);
        }

        if ($remainingQty > 0.001) {
            throw ValidationException::withMessages([
                "approved_delivered_qty.{$item->id}" => 'Short quantity cannot be added back because the original loadout stock movement was not found.',
            ]);
        }
    }

    private function notifyAdminsAboutNegativeStock(string $productName, string $orderNumber, float $negativeQty, string $unit): void
    {
        User::role('admin')
            ->get()
            ->each
            ->notify(new NegativeStockCreatedNotification($productName, $orderNumber, $negativeQty, $unit));
    }

    private function notifyAdminsAboutDeliveryReview(ShopOrder $order): void
    {
        User::role('admin')
            ->get()
            ->each
            ->notify(new ShopDeliveryReviewSubmittedNotification($order));
    }
}
