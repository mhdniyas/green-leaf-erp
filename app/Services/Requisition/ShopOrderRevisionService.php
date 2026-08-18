<?php

declare(strict_types=1);

namespace App\Services\Requisition;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Purchasing\POStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOrderRevision;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\PurchaseOrderCreatedNotification;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ShopOrderRevisionService
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
    ) {}

    /**
     * @param  array<int, array{product: Product, quantity: float}>  $items
     */
    public function createApprovedOrderRevision(
        ShopOrder $order,
        array $items,
        User $requester,
        ?string $reason = null
    ): ?ShopOrderRevision {
        if ($order->isFinanciallyLocked()) {
            throw ValidationException::withMessages([
                'items' => 'This order is linked to a finalized shop invoice. Create an adjustment request instead of changing the original order.',
            ]);
        }

        $baselineQuantities = $order->items()
            ->get()
            ->groupBy('product_id')
            ->map(fn ($items): float => (float) $items->sum(fn (ShopOrderItem $item): float => (float) ($item->approved_qty ?? $item->requested_qty ?? 0.00)));
        $incomingQuantities = collect($items)
            ->groupBy(fn (array $item): int => (int) $item['product']->id)
            ->map(fn ($productItems): float => (float) $productItems->sum(fn (array $item): float => (float) $item['quantity']));

        $changedProducts = collect($baselineQuantities->keys())
            ->merge($incomingQuantities->keys())
            ->unique()
            ->values()
            ->map(function (int $productId) use ($baselineQuantities, $incomingQuantities): ?array {
                $oldQuantity = (float) ($baselineQuantities->get($productId) ?? 0.00);
                $newQuantity = (float) ($incomingQuantities->get($productId) ?? 0.00);

                if (abs($oldQuantity - $newQuantity) < 0.0001) {
                    return null;
                }

                return [
                    'product_id' => $productId,
                    'old_requested_qty' => round($oldQuantity, 2),
                    'new_requested_qty' => round($newQuantity, 2),
                    'delta_qty' => round($newQuantity - $oldQuantity, 2),
                ];
            })
            ->filter()
            ->values();

        if ($changedProducts->isEmpty()) {
            return null;
        }

        if ($this->linkedPurchaseOrdersQuery($order, $changedProducts->pluck('product_id')->all())->whereHas('goodsReceiveds')->exists()) {
            throw ValidationException::withMessages([
                'items' => 'This order can no longer be updated because goods receipt has already started for its purchase order.',
            ]);
        }

        $order->revisions()
            ->where('status', 'pending')
            ->update(['status' => 'superseded']);

        $revision = $order->revisions()->create([
            'revision_no' => $order->nextRevisionNumber(),
            'status' => 'pending',
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : 'Shop incharge requested quantity changes.',
            'requested_by' => $requester->id,
        ]);

        $revision->items()->createMany($changedProducts->all());

        $order->update([
            'state' => 'update_requested',
            'update_reason' => $revision->reason,
            'latest_revision_no' => $revision->revision_no,
            'has_pending_revision' => true,
            'submitted_at' => now(),
        ]);

        return $revision->load(['items.product', 'requestedBy']);
    }

    /**
     * @param  array<int|string, mixed>  $shopQuantities
     * @param  array<int|string, mixed>  $fulfillmentTypes
     * @param  array<int|string, mixed>  $suppliers
     */
    public function applyPendingRevision(
        ShopOrder $order,
        User $reviewer,
        array $shopQuantities = [],
        array $fulfillmentTypes = [],
        array $suppliers = [],
        ?string $managerNote = null
    ): ?ShopOrderRevision {
        /** @var ShopOrderRevision|null $revision */
        $revision = $order->latestPendingRevision()
            ->with(['items.product', 'shopOrder.items'])
            ->first();

        if (! $revision) {
            return null;
        }

        if ($order->isFinanciallyLocked()) {
            $revision->update([
                'status' => 'blocked',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'manager_note' => $managerNote,
            ]);

            $order->update([
                'state' => 'approved',
                'update_reason' => null,
                'has_pending_revision' => false,
                'manager_note' => $managerNote,
            ]);

            return $revision->fresh(['items.product', 'shopOrder']);
        }

        $changedProductIds = $revision->items->pluck('product_id')->all();

        if ($this->linkedPurchaseOrdersQuery($order, $changedProductIds)->whereHas('goodsReceiveds')->exists()) {
            $revision->update([
                'status' => 'blocked',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'manager_note' => $managerNote,
            ]);

            $order->update([
                'state' => 'approved',
                'update_reason' => null,
                'has_pending_revision' => false,
                'manager_note' => $managerNote,
            ]);

            return $revision->fresh(['items.product', 'shopOrder']);
        }

        $existingItems = $order->items()->with('product')->get()->groupBy('product_id');
        $wasApplied = false;

        foreach ($revision->items as $revisionItem) {
            $productId = (int) $revisionItem->product_id;
            $finalQuantity = array_key_exists($productId, $shopQuantities)
                ? (float) $shopQuantities[$productId]
                : (float) $revisionItem->new_requested_qty;
            $finalQuantity = round(max(0.0, $finalQuantity), 2);

            $existingProductItems = $existingItems->get($productId, collect());
            /** @var ShopOrderItem|null $existingItem */
            $existingItem = $existingProductItems->first();
            $fulfillmentType = (string) ($fulfillmentTypes[$productId] ?? $existingItem?->fulfillment_type ?? 'warehouse');
            $product = $revisionItem->product ?? Product::findOrFail($productId);

            if ($finalQuantity > 0) {
                $payload = $this->lockedPricePayload($order, $product, $finalQuantity);

                if ($existingItem) {
                    $existingItem->update([
                        'requested_qty' => $finalQuantity,
                        'approved_qty' => $finalQuantity,
                        'unit' => $product->unit,
                        'requested_product_unit_id' => null,
                        'requested_unit' => $product->unit,
                        'requested_unit_label' => strtoupper((string) $product->unit),
                        'requested_unit_quantity' => $finalQuantity,
                        'requested_unit_conversion_to_base' => 1,
                        'fulfillment_type' => $fulfillmentType,
                        ...$payload,
                    ]);
                    $extraItemIds = $existingProductItems->skip(1)->pluck('id')->all();
                    if ($extraItemIds !== []) {
                        $order->items()->whereIn('id', $extraItemIds)->delete();
                    }
                } else {
                    $existingItem = $order->items()->create([
                        'product_id' => $product->id,
                        'requested_qty' => $finalQuantity,
                        'approved_qty' => $finalQuantity,
                        'unit' => $product->unit,
                        'requested_unit' => $product->unit,
                        'requested_unit_label' => strtoupper((string) $product->unit),
                        'requested_unit_quantity' => $finalQuantity,
                        'requested_unit_conversion_to_base' => 1,
                        'fulfillment_type' => $fulfillmentType,
                        ...$payload,
                    ]);
                }
            } elseif ($existingItem) {
                $order->items()->whereIn('id', $existingProductItems->pluck('id')->all())->delete();
            }

            if (abs((float) $revisionItem->old_requested_qty - $finalQuantity) > 0.0001) {
                $wasApplied = true;
            }

            $revisionItem->update([
                'final_approved_qty' => $finalQuantity,
            ]);
        }

        $order->update([
            'state' => 'approved',
            'update_reason' => null,
            'has_pending_revision' => false,
            'manager_note' => $managerNote,
        ]);

        $revision->update([
            'status' => $wasApplied ? 'applied' : 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'manager_note' => $managerNote,
        ]);

        if ($this->linkedPurchaseOrdersQuery($order, $changedProductIds)->exists()) {
            $this->syncChangedProductsToPurchaseOrders($order, $changedProductIds, $fulfillmentTypes, $suppliers, $reviewer);
        }

        return $revision->fresh(['items.product', 'shopOrder']);
    }

    /**
     * @param  array<int>  $productIds
     * @param  array<int|string, mixed>  $fulfillmentTypes
     * @param  array<int|string, mixed>  $suppliers
     */
    public function syncChangedProductsToPurchaseOrders(
        ShopOrder $order,
        array $productIds,
        array $fulfillmentTypes,
        array $suppliers,
        User $reviewer
    ): void {
        foreach (array_unique($productIds) as $productId) {
            $this->syncSingleProductToPurchaseOrders($order, (int) $productId, $fulfillmentTypes, $suppliers, $reviewer);
        }

        $this->deleteEmptyGeneratedPurchaseOrders($order);
    }

    /**
     * @param  array<int|string, mixed>  $fulfillmentTypes
     * @param  array<int|string, mixed>  $suppliers
     */
    private function syncSingleProductToPurchaseOrders(
        ShopOrder $order,
        int $productId,
        array $fulfillmentTypes,
        array $suppliers,
        User $reviewer
    ): void {
        $linkedGeneratedPos = $this->linkedPurchaseOrdersQuery($order, [$productId])->get();
        $existingPoItem = PurchaseOrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('purchaseOrder', function (Builder $query) use ($order): void {
                $query->whereDate('order_date', $order->business_date)
                    ->where(function (Builder $subQuery): void {
                        $subQuery->where('notes', 'Auto-generated from Approved Requisitions Board')
                            ->orWhere('notes', 'Auto-generated from Requisitions System');
                    });
            })
            ->with('purchaseOrder')
            ->latest('id')
            ->first();

        PurchaseOrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('purchaseOrder', function (Builder $query) use ($order): void {
                $query->whereDate('order_date', $order->business_date)
                    ->where(function (Builder $subQuery): void {
                        $subQuery->where('notes', 'Auto-generated from Approved Requisitions Board')
                            ->orWhere('notes', 'Auto-generated from Requisitions System');
                    });
            })
            ->delete();

        $finalQuantity = (float) ShopOrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', function (Builder $query) use ($order): void {
                $query->whereDate('business_date', $order->business_date)
                    ->where('state', 'approved');
            })
            ->sum('approved_qty');

        if ($finalQuantity <= 0.0) {
            return;
        }

        $supplierId = isset($suppliers[$productId]) && (int) $suppliers[$productId] > 0
            ? (int) $suppliers[$productId]
            : ($existingPoItem?->purchaseOrder?->supplier_id ?? null);

        if (! $supplierId) {
            $supplierId = Supplier::defaultPurchase()->value('id');
        }

        if (! $supplierId) {
            $supplierId = PurchaseOrderItem::query()
                ->where('product_id', $productId)
                ->with('purchaseOrder')
                ->latest('id')
                ->get()
                ->first(fn (PurchaseOrderItem $item) => $item->purchaseOrder !== null)
                ?->purchaseOrder
                ?->supplier_id;
        }

        if (! $supplierId) {
            $supplierId = Supplier::query()->where('category', 'own_purchase')->value('id') ?? Supplier::query()->value('id');
        }

        $fulfillmentType = (string) ($fulfillmentTypes[$productId]
            ?? ShopOrderItem::query()
                ->where('product_id', $productId)
                ->whereHas('order', function (Builder $query) use ($order): void {
                    $query->whereDate('business_date', $order->business_date)
                        ->where('state', 'approved');
                })
                ->value('fulfillment_type')
            ?? $existingPoItem?->purchaseOrder?->fulfillment_type
            ?? 'warehouse');

        /** @var PurchaseOrder|null $purchaseOrder */
        $purchaseOrder = PurchaseOrder::query()
            ->whereDate('order_date', $order->business_date)
            ->where('supplier_id', $supplierId)
            ->where('fulfillment_type', $fulfillmentType)
            ->first();

        $createdPurchaseOrder = false;
        if (! $purchaseOrder) {
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $supplierId,
                'po_number' => $this->generatePurchaseOrderNumber($order),
                'status' => POStatus::Approved,
                'order_date' => $order->business_date,
                'created_by' => $reviewer->id,
                'fulfillment_type' => $fulfillmentType,
                'notes' => 'Auto-generated from Requisitions System',
            ]);
            $createdPurchaseOrder = true;
        }

        $lastPrice = PurchaseOrderItem::query()
            ->where('product_id', $productId)
            ->latest('id')
            ->value('unit_price') ?? 1.00;

        $purchaseOrder->items()->create([
            'product_id' => $productId,
            'quantity' => round($finalQuantity, 2),
            'unit_price' => $lastPrice,
        ]);

        if ($createdPurchaseOrder) {
            $this->notifyPurchaseManagers(new PurchaseOrderCreatedNotification($purchaseOrder));
        }
    }

    /**
     * @param  array<int>  $productIds
     * @return Builder<PurchaseOrder>
     */
    public function linkedPurchaseOrdersQuery(ShopOrder $order, array $productIds): Builder
    {
        return PurchaseOrder::query()
            ->whereDate('order_date', $order->business_date)
            ->where(function (Builder $query): void {
                $query->where('notes', 'Auto-generated from Approved Requisitions Board')
                    ->orWhere('notes', 'Auto-generated from Requisitions System');
            })
            ->whereHas('items', function (Builder $query) use ($productIds): void {
                $query->whereIn('product_id', $productIds);
            });
    }

    public function notifyPurchaseManagers(object $notification): void
    {
        User::query()
            ->role('purchase')
            ->get()
            ->each(fn (User $user): User => tap($user)->notify($notification));
    }

    private function deleteEmptyGeneratedPurchaseOrders(ShopOrder $order): void
    {
        PurchaseOrder::query()
            ->whereDate('order_date', $order->business_date)
            ->where(function (Builder $query): void {
                $query->where('notes', 'Auto-generated from Approved Requisitions Board')
                    ->orWhere('notes', 'Auto-generated from Requisitions System');
            })
            ->get()
            ->each(function (PurchaseOrder $purchaseOrder): void {
                if (! $purchaseOrder->items()->exists() && ! $purchaseOrder->goodsReceiveds()->exists()) {
                    $purchaseOrder->forceDelete();
                }
            });
    }

    private function generatePurchaseOrderNumber(ShopOrder $order): string
    {
        $dateString = $order->business_date->format('Ymd');

        do {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $poNumber = "PO-{$dateString}-{$suffix}";
        } while (PurchaseOrder::where('po_number', $poNumber)->exists());

        return $poNumber;
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedPricePayload(ShopOrder $order, Product $product, float $quantity): array
    {
        $price = $this->priceBoardService->sellingPriceFor($product, $order->shop, ProductGrade::GradeA);

        return [
            'product_grade' => ProductGrade::GradeA->value,
            'locked_price_group_id' => $price['group']->id,
            'locked_selling_price' => $price['price'],
            'locked_price_source' => $price['source'],
            'line_total' => round($quantity * $price['price'], 2),
        ];
    }
}
