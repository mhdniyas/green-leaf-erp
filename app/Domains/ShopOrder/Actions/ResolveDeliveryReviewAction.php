<?php

declare(strict_types=1);

namespace App\Domains\ShopOrder\Actions;

use App\DTOs\Inventory\WastageEntryData;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WastageEntry;
use App\Notifications\NegativeStockCreatedNotification;
use App\Notifications\ShopDeliveryReviewSubmittedNotification;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Inventory\WastageService;
use App\Services\ShopInvoices\ShopInvoiceIntegrityValidator;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveDeliveryReviewAction
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly StockLedgerService $stockLedgerService,
        private readonly ShopInvoiceIntegrityValidator $shopInvoiceIntegrityValidator,
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
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

            if ($lockedOrder->delivery_review_status === 'approved'
                && in_array($lockedOrder->delivery_status, ['delivered', 'partially_delivered'], true)) {
                return $lockedOrder;
            }

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

            $beforeSubtotal = round((float) $invoice->subtotal, 2);
            $beforeDiscount = round((float) $invoice->discount_total, 2);
            $beforeFinalTotal = round((float) $invoice->final_total, 2);
            $productChanges = [];
            $inventoryResolutions = [];

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

                $beforeDeliveredQty = (float) ($item->shop_reported_received_qty ?? $item->delivered_qty ?? $expectedQty);
                $itemUnitPrice = (float) ($invoiceItemsByProductId->get((int) $item->product_id)?->unit_price ?? $item->locked_selling_price ?? $item->unit_price);
                if (abs($deliveredQty - $beforeDeliveredQty) > 0.0001 || abs($deliveredQty - $expectedQty) > 0.0001) {
                    $productChanges[] = [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->name ?? 'Product #'.$item->product_id,
                        'unit' => (string) $item->unit,
                        'loaded_qty' => $expectedQty,
                        'before_qty' => $beforeDeliveredQty,
                        'final_qty' => $deliveredQty,
                        'before_price' => $itemUnitPrice,
                        'final_price' => $itemUnitPrice,
                    ];
                }

                if ($shortageQty > 0.0 || $excessQty > 0.0) {
                    $inventoryResolutions[] = [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->name ?? 'Product #'.$item->product_id,
                        'unit' => (string) $item->unit,
                        'loaded_qty' => $expectedQty,
                        'final_qty' => $deliveredQty,
                        'difference_qty' => $excessQty > 0.0 ? $excessQty : -$shortageQty,
                        'reason' => $discrepancyType,
                        'resolution' => $inventoryAction,
                        'resolution_qty' => $excessQty > 0.0 ? $excessQty : $shortageQty,
                        'note' => $discrepancyNote,
                    ];
                }

                if ($shortageQty > 0.0 && in_array($inventoryAction, ['add_back', 'return_to_warehouse'], true)) {
                    $this->addShortageBackToInventory($lockedOrder, $item, $shortageQty, $userId);
                }

                if ($shortageQty > 0.0 && $inventoryAction === 'wastage') {
                    $this->recordWastageForShortage($lockedOrder, $item, $shortageQty, $discrepancyType, $discrepancyNote, $userId);
                }

                if ($excessQty > 0.0 && $inventoryAction === 'deduct_extra') {
                    $orderNumber = (string) $lockedOrder->order_number;
                    $notePrefix = "Delivery excess approved - Order: {$orderNumber}; Item: {$item->id}";

                    $alreadyExists = StockMovement::query()
                        ->where('shop_order_item_id', $item->id)
                        ->where('type', StockMovementType::Out->value)
                        ->where('notes', 'like', "{$notePrefix}%")
                        ->exists();

                    if (! $alreadyExists) {
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
                }

                $item->update([
                    'delivered_qty' => $deliveredQty,
                    'shortage_qty' => $shortageQty,
                    'excess_qty' => $excessQty,
                    'shortage_value' => $shortageValue,
                    'excess_value' => $excessValue,
                    'delivery_discrepancy_type' => $discrepancyType,
                    'delivery_discrepancy_note' => $discrepancyNote,
                    'locked_selling_price' => $itemUnitPrice,
                    'line_total' => round($deliveredQty * $itemUnitPrice, 2),
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

                    $adjustment = $this->shopInvoiceService->calculateDeliveryAdjustmentForInvoiceItem(
                        $invoiceItem,
                        $deliveredQty,
                        $shortageQty,
                        $excessQty,
                        $items instanceof Collection ? $items->values() : collect($items)->values(),
                    );

                    $invoiceItem->update($adjustment);

                    $unitPrice = (float) $invoiceItem->unit_price;
                    foreach ($items as $orderItem) {
                        $orderItem->update([
                            'locked_selling_price' => $unitPrice,
                            'line_total' => round((float) ($orderItem->delivered_qty ?? 0) * $unitPrice, 2),
                            'shortage_value' => round((float) ($orderItem->shortage_qty ?? 0) * $unitPrice, 2),
                            'excess_value' => round((float) ($orderItem->excess_qty ?? 0) * $unitPrice, 2),
                        ]);
                    }
                });

            $summaryLines = [];
            foreach ($productChanges as $change) {
                if ($change['loaded_qty'] != $change['final_qty']) {
                    $summaryLines[] = "{$change['product_name']}: Qty: {$change['loaded_qty']} {$change['unit']} → {$change['final_qty']} {$change['unit']}";
                }
            }
            foreach ($inventoryResolutions as $res) {
                $reasonLabel = match ($res['reason']) {
                    'wastage_damage' => 'Wastage / Damage',
                    'loadout_mistake' => 'Loadout Mistake',
                    'delivery_mistake', 'shop_delivery_mistake' => 'Shop Order / Delivery Mistake',
                    default => ucfirst(str_replace('_', ' ', (string) $res['reason'])),
                };
                $resolutionLabel = match ($res['resolution']) {
                    'return_to_warehouse', 'add_back' => 'returned to warehouse',
                    'wastage' => 'recorded as wastage',
                    'deduct_extra' => 'deducted extra from warehouse',
                    'already_accounted' => 'already accounted (no stock adjustment)',
                    default => str_replace('_', ' ', (string) $res['resolution']),
                };
                $summaryLines[] = "Reason: {$reasonLabel} | Inventory: {$res['resolution_qty']} {$res['unit']} {$resolutionLabel}";
            }
            if ($beforeDiscount != (float) $invoice->discount_total) {
                $summaryLines[] = "Discount: ₹{$beforeDiscount} → ₹{$invoice->discount_total}";
            }
            if ($beforeFinalTotal != (float) $invoice->final_total) {
                $summaryLines[] = "Final Total: ₹{$beforeFinalTotal} → ₹{$invoice->final_total}";
            }
            if ($reviewNote) {
                $summaryLines[] = "Note: \"{$reviewNote}\"";
            }
            $autoChangeSummary = implode("\n", $summaryLines);

            $invoice->update([
                'delivery_status' => $hasDeliveryAdjustment ? 'approved_after_discrepancy' : 'received_full',
                'delivery_note' => $this->appendReviewNote($invoice->delivery_note, 'Delivery approved', $reviewNote),
                'delivery_confirmed_by' => $invoice->delivery_confirmed_by ?? $lockedOrder->shop_checked_by ?? $userId,
                'delivery_confirmed_at' => $invoice->delivery_confirmed_at ?? now(),
            ]);

            $invoice = $this->shopInvoiceService->recalculate($invoice->fresh('items'));

            $actor = User::query()->find($userId);
            $actorRole = $actor?->roles->pluck('name')->first() ?? ($actor?->is_admin ? 'admin' : 'user');
            $isAdmin = (bool) ($actor?->hasRole('admin') || $actor?->hasRole('purchase'));
            $isAdminOnBehalf = ! $lockedOrder->shop_checked_at || ($lockedOrder->shop_checked_by === $userId && $isAdmin);

            activity('shop_invoice')
                ->performedOn($invoice)
                ->causedBy($userId)
                ->event('delivery_finalized')
                ->withProperties([
                    'source' => 'admin_delivery_review_finalized',
                    'shop_id' => $invoice->shop_id,
                    'shop_name' => $invoice->shop?->name,
                    'business_date' => $invoice->business_date?->toDateString(),
                    'actor' => [
                        'id' => $userId,
                        'name' => $actor?->name,
                        'role' => $actorRole,
                    ],
                    'is_admin_on_behalf' => $isAdminOnBehalf,
                    'overall_note' => $reviewNote,
                    'auto_change_summary' => $autoChangeSummary,
                    'product_changes' => $productChanges,
                    'inventory_resolutions' => $inventoryResolutions,
                    'totals' => [
                        'before_subtotal' => $beforeSubtotal,
                        'final_subtotal' => (float) $invoice->subtotal,
                        'before_discount' => $beforeDiscount,
                        'final_discount' => (float) $invoice->discount_total,
                        'before_final_total' => $beforeFinalTotal,
                        'final_total' => (float) $invoice->final_total,
                    ],
                    'finalization' => [
                        'final_amount' => (float) $invoice->final_total,
                        'finalized_by' => $userId,
                        'finalized_at' => now()->toDateTimeString(),
                    ],
                ])
                ->log($isAdminOnBehalf ? 'Finalized by Admin on behalf of Shop' : 'Finalized delivery review');

            $invoice = $this->shopInvoiceService->markFinalized(
                $invoice,
                $userId,
                'admin_delivery_review_finalized',
                $isAdminOnBehalf ? 'Finalized by Admin on behalf of Shop' : 'Admin finalized delivery review.'
            );

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

    public function revertApprovalForAdminEdit(ShopOrder $order, int $userId, ?string $reviewNote): ShopOrder
    {
        return DB::transaction(function () use ($order, $userId, $reviewNote): ShopOrder {
            /** @var ShopOrder $lockedOrder */
            $lockedOrder = ShopOrder::query()
                ->with(['items.product', 'invoice.items'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->assertCurrentState($lockedOrder, ['delivered', 'partially_delivered'], ['approved']);

            $invoice = $lockedOrder->invoice;

            if (! $invoice instanceof ShopInvoice) {
                throw ValidationException::withMessages([
                    'invoice' => 'Cannot revert approval because the shop invoice is missing.',
                ]);
            }

            if (
                (float) $invoice->paid_amount > 0.0
                || $invoice->payment_approved_at !== null
                || in_array((string) $invoice->payment_status, ['partially_paid', 'paid'], true)
            ) {
                throw ValidationException::withMessages([
                    'invoice' => 'Approval cannot be reverted after payment activity has started.',
                ]);
            }

            $this->reverseInventoryAdjustmentsForApprovedReview($lockedOrder, $userId);

            foreach ($lockedOrder->items as $item) {
                $item->update([
                    'delivered_qty' => null,
                    'shortage_qty' => 0,
                    'excess_qty' => 0,
                    'shortage_value' => 0,
                    'excess_value' => 0,
                    'delivery_discrepancy_type' => 'none',
                    'delivery_discrepancy_note' => null,
                ]);
            }

            $invoice = ShopInvoice::query()->with('items')->lockForUpdate()->findOrFail($invoice->id);

            foreach ($invoice->items as $invoiceItem) {
                $invoiceItem->update([
                    'delivered_qty' => 0,
                    'shortage_qty' => 0,
                    'excess_qty' => 0,
                    'shortage_amount' => 0,
                    'excess_amount' => 0,
                    'final_line_total' => (float) $invoiceItem->line_subtotal,
                ]);
            }

            $invoice->update([
                'delivery_status' => 'awaiting_review',
                'status' => 'delivery_review',
                'discount_total' => 0,
                'discount_note' => null,
                'discount_approved_by' => null,
                'discount_approved_at' => null,
                'delivery_note' => $this->appendReviewNote($invoice->delivery_note, 'Delivery approval reverted', $reviewNote),
            ]);

            $invoice = $this->shopInvoiceService->recalculate($invoice->fresh('items'));

            $lockedOrder->update([
                'delivery_status' => 'pending_approval',
                'delivery_review_status' => 'pending',
                'is_delivered' => false,
                'delivered_at' => null,
                'delivered_by' => $lockedOrder->shop_checked_by,
                'delivery_notes' => $this->appendReviewNote($lockedOrder->delivery_notes, 'Delivery approval reverted', $reviewNote),
                'admin_reviewed_by' => $userId,
                'admin_reviewed_at' => now(),
                'admin_review_note' => $reviewNote,
                'total_shortage_value' => 0,
                'total_excess_value' => 0,
                'balance_amount' => $invoice->balance_amount,
                'payment_status' => $invoice->payment_status,
                'cash_discrepancy' => round((float) $invoice->final_total - (float) $lockedOrder->cash_collected, 2),
            ]);

            return $lockedOrder->fresh(['items.product', 'invoice.items']);
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
        $orderNumber = (string) $order->order_number;
        $notePrefix = "Delivery shortage added back to inventory - Order: {$orderNumber}; Item: {$item->id}";

        $alreadyExists = StockMovement::query()
            ->where('shop_order_item_id', $item->id)
            ->where('type', StockMovementType::SaleReversal->value)
            ->where('notes', 'like', "{$notePrefix}%")
            ->exists();

        if ($alreadyExists) {
            return;
        }

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

    private function recordWastageForShortage(
        ShopOrder $order,
        object $item,
        float $shortageQty,
        string $discrepancyType,
        ?string $discrepancyNote,
        int $userId
    ): void {
        $orderNumber = (string) $order->order_number;
        $notePrefix = "Delivery review wastage - Order: {$orderNumber}; Item: {$item->id}";

        $alreadyExists = WastageEntry::query()
            ->where('notes', 'like', "{$notePrefix}%")
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $sourceMovement = StockMovement::query()
            ->where('product_id', $item->product_id)
            ->where('type', StockMovementType::Out->value)
            ->where('notes', 'like', "%Order: {$orderNumber}%")
            ->oldest('id')
            ->first();

        $batchId = $sourceMovement?->batch_id ?? (is_numeric($item->batch_id ?? null) ? (int) $item->batch_id : null);
        $grade = (string) ($sourceMovement?->grade?->value ?? (is_object($item->product_grade ?? null) ? $item->product_grade->value : ($item->product_grade ?? 'A')));

        $wastageReason = match ($discrepancyType) {
            'transit_damage', 'wastage_damage', 'wastage' => WastageReason::TransitDamage,
            'rotten' => WastageReason::Rotten,
            'expired' => WastageReason::Expired,
            'unsold' => WastageReason::Unsold,
            'sorting_damage', 'loadout_mistake' => WastageReason::SortingDamage,
            default => WastageReason::TransitDamage,
        };

        $notes = $notePrefix."; Reason: {$discrepancyType}".($discrepancyNote ? " - Note: {$discrepancyNote}" : '');

        $this->wastageService->record(new WastageEntryData(
            productId: (int) $item->product_id,
            batchId: $batchId,
            grade: $grade,
            quantity: $shortageQty,
            costPerKg: (float) ($item->unit_cost ?: ($sourceMovement?->cost_per_unit ?? 0)),
            reason: $wastageReason,
            wastageDate: $order->business_date ? $order->business_date->toDateString() : today()->toDateString(),
            notes: $notes,
        ), $userId);
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

    public function reverseInventoryAdjustmentsForApprovedReview(ShopOrder $order, int $userId): void
    {
        $orderNumber = (string) $order->order_number;
        $itemIds = $order->items->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        if ($itemIds === []) {
            return;
        }

        $movements = StockMovement::query()
            ->whereIn('shop_order_item_id', $itemIds)
            ->where(function ($query) use ($orderNumber): void {
                $query
                    ->where('notes', 'like', "Delivery shortage added back to inventory - Order: {$orderNumber}; Item:%")
                    ->orWhere('notes', 'like', "Delivery excess approved - Order: {$orderNumber}; Item:%");
            })
            ->orderBy('id')
            ->get();

        if ($movements->isEmpty()) {
            return;
        }

        $movementsByItem = $movements->groupBy(fn (StockMovement $movement): int => (int) ($movement->shop_order_item_id ?? 0));

        foreach ($order->items as $item) {
            $itemMovements = $movementsByItem->get((int) $item->id, collect());

            if (! $itemMovements instanceof Collection || $itemMovements->isEmpty()) {
                continue;
            }

            $this->reverseItemInventoryMovements($itemMovements, $orderNumber, (int) $item->id, $userId);
        }
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     */
    private function reverseItemInventoryMovements(Collection $movements, string $orderNumber, int $itemId, int $userId): void
    {
        foreach ($movements as $movement) {
            $reverseType = match ((string) $movement->type->value) {
                StockMovementType::SaleReversal->value => StockMovementType::Out,
                StockMovementType::Out->value => StockMovementType::SaleReversal,
                default => null,
            };

            if (! $reverseType instanceof StockMovementType) {
                continue;
            }

            StockMovement::query()->create([
                'batch_id' => $movement->batch_id,
                'product_id' => $movement->product_id,
                'warehouse_id' => $movement->warehouse_id,
                'shop_order_item_id' => $movement->shop_order_item_id,
                'created_by' => $userId,
                'grade' => $movement->grade,
                'type' => $reverseType,
                'quantity' => (float) $movement->quantity,
                'cost_per_unit' => (float) $movement->cost_per_unit,
                'notes' => "Reverted delivery approval inventory effect - Order: {$orderNumber}; Item: {$itemId}; Source movement: {$movement->id}",
            ]);
        }
    }
}
