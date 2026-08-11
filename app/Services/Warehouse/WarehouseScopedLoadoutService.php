<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOrderLoadoutState;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseScopedLoadoutService
{
    public function __construct(
        private readonly StockLedgerService $stockLedgerService,
        private readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function orders(User $user, Warehouse $warehouse, array $filters): array
    {
        $this->ensureAccess($user, $warehouse);

        $date = (string) ($filters['date'] ?? $this->businessDayService->operationalDate()->toDateString());
        $search = trim((string) ($filters['search'] ?? ''));
        $source = (string) ($filters['source'] ?? 'all');
        $warehouseItems = fn (Builder $query): Builder => $this->scopeItems($query, $warehouse);

        $orders = ShopOrder::query()
            ->whereIn('delivery_status', $this->visibleDeliveryStatuses())
            ->whereDate('business_date', $date)
            ->when(isset($filters['shop_id']), fn (Builder $query) => $query->where('shop_id', (int) $filters['shop_id']))
            ->when($source === 'all', fn (Builder $query) => $query->where('order_source', '!=', 'admin_direct_purchase'))
            ->when($source === 'shop', fn (Builder $query) => $query->where('order_source', 'shop_owner'))
            ->when($source === 'direct', fn (Builder $query) => $query->whereIn('order_source', ['admin_direct_purchase', 'direct_sale']))
            ->whereHas('items', $warehouseItems)
            ->when($search !== '', function (Builder $query) use ($search, $warehouse): void {
                $query->where(function (Builder $searchQuery) use ($search, $warehouse): void {
                    $searchQuery
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('shop', function (Builder $shopQuery) use ($search): void {
                            $shopQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%")
                                ->orWhere('warehouse_tag', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items', function (Builder $itemQuery) use ($search, $warehouse): void {
                            $this->scopeItems($itemQuery, $warehouse)
                                ->whereHas('product', function (Builder $productQuery) use ($search, $warehouse): void {
                                    $productQuery->where('default_warehouse_id', $warehouse->id)
                                        ->where(function (Builder $textQuery) use ($search): void {
                                            $textQuery->where('name', 'like', "%{$search}%")
                                                ->orWhere('sku', 'like', "%{$search}%");
                                        });
                                });
                        });
                });
            })
            ->with('shop:id,name,code,warehouse_tag')
            ->withCount([
                'items as warehouse_item_count' => $warehouseItems,
                'items as warehouse_loaded_count' => fn (Builder $query): Builder => $warehouseItems($query)->where('sorting_status', 'loaded'),
                'items as warehouse_not_available_count' => fn (Builder $query): Builder => $warehouseItems($query)->where('sorting_status', 'not_available'),
            ])
            ->orderByDesc('business_date')
            ->orderBy('created_at')
            ->get();

        return [
            'selected_date' => $date,
            'warehouse' => $this->warehouseData($warehouse),
            'orders' => $orders->map(function (ShopOrder $order): array {
                $total = (int) $order->warehouse_item_count;
                $loaded = (int) $order->warehouse_loaded_count;
                $notAvailable = (int) $order->warehouse_not_available_count;

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'business_date' => $order->business_date->toDateString(),
                    'delivery_status' => $order->delivery_status,
                    'order_source' => $order->order_source,
                    'display_name' => $order->loadoutDisplayName(),
                    'shop' => $this->shopData($order),
                    'warehouse_item_count' => $total,
                    'warehouse_loaded_count' => $loaded,
                    'warehouse_not_available_count' => $notAvailable,
                    'warehouse_pending_count' => max(0, $total - $loaded - $notAvailable),
                    'warehouse_progress_percentage' => $total > 0
                        ? round((($loaded + $notAvailable) / $total) * 100)
                        : 0,
                    'can_edit' => $this->canEdit($order),
                ];
            })->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(User $user, Warehouse $warehouse, ShopOrder $order): array
    {
        $this->ensureAccess($user, $warehouse);

        $order->load('shop:id,name,code,warehouse_tag');
        $items = $this->warehouseItems($order, $warehouse);
        if ($items->isEmpty()) {
            abort(404, 'This order has no items for the selected warehouse.');
        }

        return $this->detailData($warehouse, $order, $items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $submittedItems
     * @return array<string, mixed>
     */
    public function save(User $user, Warehouse $warehouse, ShopOrder $order, array $submittedItems): array
    {
        $this->ensureAccess($user, $warehouse);
        if (! $this->canEdit($order)) {
            throw ValidationException::withMessages([
                'order' => ['This order is not currently editable for loadout.'],
            ]);
        }

        $submittedById = collect($submittedItems)->keyBy(fn (array $item): int => (int) $item['requisition_item_id']);
        $itemIds = $submittedById->keys()->map(fn ($id): int => (int) $id)->sort()->values();

        DB::transaction(function () use ($user, $warehouse, $order, $submittedById, $itemIds): void {
            $items = ShopOrderItem::query()
                ->whereIn('id', $itemIds)
                ->with('product:id,default_warehouse_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->validateItemScope($items, $itemIds, $warehouse, $order);

            foreach ($items as $item) {
                $input = $submittedById->get($item->id);
                $isNotAvailable = (bool) $input['is_not_available'];
                $loadedQty = $isNotAvailable ? 0.0 : round((float) ($input['loaded_qty'] ?? 0), 2);
                $noteWasSubmitted = array_key_exists('note', $input);
                $note = $noteWasSubmitted ? $input['note'] : $item->loadout_discrepancy_note;

                $attributes = [
                    'loaded_qty' => $isNotAvailable || $loadedQty > 0 ? $loadedQty : null,
                    'loaded_order_unit_qty' => $isNotAvailable
                        ? 0.0
                        : (array_key_exists('loaded_order_unit_qty', $input)
                            ? $input['loaded_order_unit_qty']
                            : $item->loaded_order_unit_qty),
                    'loadout_discrepancy_type' => $isNotAvailable ? 'not_available' : 'none',
                    'loadout_discrepancy_note' => $isNotAvailable && blank($note)
                        ? 'Marked as Not Available by warehouse'
                        : $note,
                    'sorting_status' => $isNotAvailable ? 'not_available' : ($loadedQty > 0 ? 'loaded' : 'allocated'),
                    'is_sorted' => $isNotAvailable || $loadedQty > 0,
                    'sorted_at' => $isNotAvailable || $loadedQty > 0 ? now() : null,
                    'sorted_by' => $isNotAvailable || $loadedQty > 0 ? $user->id : null,
                    'actual_weight' => ! $isNotAvailable && $loadedQty > 0 ? $loadedQty : null,
                    'excess_qty' => $isNotAvailable ? 0.0 : max(0.0, round($loadedQty - (float) $item->approved_qty, 2)),
                    'excess_value' => $isNotAvailable
                        ? 0.0
                        : round(max(0.0, $loadedQty - (float) $item->approved_qty) * (float) $item->locked_selling_price, 2),
                ];

                $item->update($attributes);
            }

            $state = ShopOrderLoadoutState::query()->firstOrCreate(
                ['shop_order_id' => $order->id, 'warehouse_id' => $warehouse->id],
                ['started_at' => now()]
            );
            if ($state->started_at === null) {
                $state->forceFill(['started_at' => now()])->save();
            }
        });

        return $this->detail($user, $warehouse, $order->fresh());
    }

    private function ensureAccess(User $user, Warehouse $warehouse): void
    {
        abort_unless($user->canAccessWarehouse($warehouse), 403, 'Unauthorized warehouse access.');
    }

    private function scopeItems(Builder $query, Warehouse $warehouse): Builder
    {
        return $query->whereHas('product', fn (Builder $productQuery): Builder => $productQuery
            ->where('default_warehouse_id', $warehouse->id)
            ->where('is_active', true));
    }

    /** @return Collection<int, ShopOrderItem> */
    private function warehouseItems(ShopOrder $order, Warehouse $warehouse): Collection
    {
        return $this->scopeItems(
            ShopOrderItem::query()->where('shop_order_id', $order->id),
            $warehouse
        )->with(['product.category', 'product.orderUnits', 'product.defaultWarehouse'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, ShopOrderItem>  $items
     * @return array<string, mixed>
     */
    private function detailData(Warehouse $warehouse, ShopOrder $order, Collection $items): array
    {
        $state = ShopOrderLoadoutState::query()
            ->where('shop_order_id', $order->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        $hasStarted = $state?->started_at !== null || $items->contains(fn (ShopOrderItem $item): bool => $item->sorting_status !== 'allocated'
            || (float) ($item->loaded_qty ?? 0) > 0
            || filled($item->loadout_discrepancy_note));
        $loaded = $items->where('sorting_status', 'loaded')->count();
        $notAvailable = $items->where('sorting_status', 'not_available')->count();
        $total = $items->count();

        return [
            'warehouse' => $this->warehouseData($warehouse),
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'business_date' => $order->business_date->toDateString(),
                'delivery_status' => $order->delivery_status,
                'display_name' => $order->loadoutDisplayName(),
                'shop' => $this->shopData($order),
            ],
            'items' => $items->map(fn (ShopOrderItem $item): array => $this->itemData($item, $warehouse))->values(),
            'counts' => [
                'total' => $total,
                'loaded' => $loaded,
                'not_available' => $notAvailable,
                'pending' => max(0, $total - $loaded - $notAvailable),
            ],
            'can_edit' => $this->canEdit($order),
            'has_loadout_started' => $hasStarted,
            'loadout_started_at' => $state?->started_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function itemData(ShopOrderItem $item, Warehouse $warehouse): array
    {
        $product = $item->product;
        $requestedUnit = strtolower((string) ($item->requested_unit ?? ''));
        $baseUnit = strtolower((string) ($product?->unit ?? $item->unit ?? 'kg'));
        $hasSecondaryUnit = $requestedUnit !== '' && $requestedUnit !== $baseUnit;
        $conversion = (float) ($item->requested_unit_conversion_to_base ?? 1.0);
        $approved = (float) ($item->approved_qty ?? $item->requested_qty ?? 0);

        return [
            'requisition_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $product?->name ?? 'Unknown Product',
            'product_sku' => $product?->sku ?? '',
            'warehouse_id' => $warehouse->id,
            'category_name' => $product?->category?->name ?? 'General',
            'unit' => $product?->unit ?? $item->unit ?? 'KG',
            'product_grade' => $item->product_grade ?? 'A',
            'approved_qty' => $approved,
            'loaded_qty' => (float) ($item->loaded_qty ?? 0),
            'loaded_order_unit_qty' => $item->loaded_order_unit_qty !== null
                ? (float) $item->loaded_order_unit_qty
                : null,
            'default_loaded_qty' => $approved,
            'default_loaded_order_unit_qty' => $hasSecondaryUnit && $conversion > 0
                ? round($approved / $conversion, 2)
                : null,
            'requested_unit_label' => $item->requested_unit_label ?? strtoupper((string) $item->requested_unit),
            'requested_unit_conversion_to_base' => $conversion,
            'has_secondary_unit' => $hasSecondaryUnit,
            'use_dual_measurement_inputs' => $hasSecondaryUnit && ($product?->orderUnits?->count() ?? 0) > 1,
            'sorting_status' => $item->sorting_status,
            'is_not_available' => $item->sorting_status === 'not_available',
            'note' => $item->loadout_discrepancy_note,
            'available_stock' => $this->stockLedgerService->availableSortedStockForProduct($item->product_id, $warehouse->id)
                + (float) ($item->loaded_qty ?? 0),
        ];
    }

    /**
     * @param  Collection<int, ShopOrderItem>  $items
     * @param  Collection<int, int>  $submittedIds
     */
    private function validateItemScope(Collection $items, Collection $submittedIds, Warehouse $warehouse, ShopOrder $order): void
    {
        $foundIds = $items->pluck('id')->map(fn ($id): int => (int) $id);
        if ($foundIds->count() !== $submittedIds->count()) {
            throw ValidationException::withMessages(['items' => ['One or more loadout items were not found.']]);
        }

        if ($items->contains(fn (ShopOrderItem $item): bool => $item->shop_order_id !== $order->id)) {
            throw ValidationException::withMessages(['items' => ['An item does not belong to the requested order.']]);
        }

        if ($items->contains(fn (ShopOrderItem $item): bool => $item->product?->default_warehouse_id !== $warehouse->id)) {
            throw ValidationException::withMessages(['items' => ['An item does not belong to the requested warehouse.']]);
        }
    }

    private function canEdit(ShopOrder $order): bool
    {
        return in_array($order->delivery_status, ['pending_delivery', 'ready_for_dispatch'], true);
    }

    /** @return array<int, string> */
    private function visibleDeliveryStatuses(): array
    {
        return [
            'pending_delivery', 'ready_for_dispatch', 'in_transit', 'delivered',
            'pending_approval', 'partially_delivered', 'delivery_issue',
        ];
    }

    /** @return array{id:int, name:string, code:string} */
    private function warehouseData(Warehouse $warehouse): array
    {
        return ['id' => $warehouse->id, 'name' => $warehouse->name, 'code' => $warehouse->code];
    }

    /** @return array<string, mixed>|null */
    private function shopData(ShopOrder $order): ?array
    {
        return $order->shop ? [
            'id' => $order->shop->id,
            'name' => $order->shop->name,
            'code' => $order->shop->code,
            'warehouse_tag' => $order->shop->warehouse_tag,
        ] : null;
    }
}
