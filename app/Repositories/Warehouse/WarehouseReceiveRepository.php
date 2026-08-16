<?php

declare(strict_types=1);

namespace App\Repositories\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaserCart;
use App\Models\ShopOrder;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WarehouseReceiveRepository
{
    /**
     * @return Collection<int, GoodsReceived>
     */
    public function pendingGrns(string $date, string $source, ?int $categoryId, string $search): Collection
    {
        return GoodsReceived::query()
            ->select([
                'id',
                'purchase_order_id',
                'purchaser_cart_id',
                'grn_number',
                'status',
                'received_at',
                'created_at',
            ])
            ->where('status', 'pending_approval')
            ->whereDate('received_at', $date)
            ->with([
                'purchaseOrder:id,supplier_id,purchaser_cart_id,po_number',
                'purchaseOrder.supplier:id,name',
                'purchaseOrder.purchaserCart:id,user_id,cart_number',
                'purchaseOrder.purchaserCart.user:id,name',
                'items:id,goods_received_id,product_id,received_qty',
                'items.product:id,category_id,name,sku,unit',
                'items.product.category:id,name',
            ])
            ->when($source !== 'all' && $source !== 'vendor', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($categoryId, function (Builder $query) use ($categoryId): void {
                $query->whereHas('items.product', fn (Builder $productQuery) => $productQuery->where('category_id', $categoryId));
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('grn_number', 'like', "%{$search}%")
                        ->orWhereHas('purchaseOrder.supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('purchaseOrder.purchaserCart.user', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('items.product', function (Builder $productQuery) use ($search): void {
                            $productQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, StockBatch>
     */
    public function pendingBatches(string $date, string $source, ?int $categoryId, string $search): Collection
    {
        return StockBatch::query()
            ->where('warehouse_receive_pending', true)
            ->whereDate('received_at', $date)
            ->with(['product.category'])
            ->when($source !== 'all' && $source !== 'batch', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($categoryId, function (Builder $query) use ($categoryId): void {
                $query->whereHas('product', fn (Builder $productQuery) => $productQuery->where('category_id', $categoryId));
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('product', function (Builder $productQuery) use ($search): void {
                            $productQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, StockBatch>
     */
    public function confirmedBatches(string $date, ?int $warehouseId): Collection
    {
        return StockBatch::query()
            ->where('warehouse_receive_pending', false)
            ->whereDate('received_at', $date)
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->with(['product.category', 'warehouseConfirmedBy', 'warehouse'])
            ->orderBy('warehouse_confirmed_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * @return Collection<int, GoodsReceived>
     */
    public function directPurchaseGrns(string $date): Collection
    {
        return GoodsReceived::query()
            ->select(['id'])
            ->whereDate('received_at', $date)
            ->whereIn('status', ['pending_approval', 'approved'])
            ->whereIn('purchaser_cart_id', PurchaserCart::query()
                ->where('purchase_source', 'green_leaf_direct_purchase')
                ->select('id'))
            ->with('items:id,goods_received_id,product_id')
            ->get();
    }

    /**
     * @return Collection<int, ShopOrder>
     */
    public function pendingDirectPurchaseOrders(string $date, string $source, ?int $categoryId, string $search): Collection
    {
        return ShopOrder::query()
            ->whereDate('business_date', $date)
            ->where('order_source', 'admin_direct_purchase')
            ->where('state', 'approved')
            ->where('delivery_status', 'pending_delivery')
            ->where('is_allocation_completed', false)
            ->with(['items.product.category'])
            ->whereHas('items')
            ->when($source !== 'all' && $source !== 'direct', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($categoryId, function (Builder $query) use ($categoryId): void {
                $query->whereHas('items.product', fn (Builder $productQuery) => $productQuery->where('category_id', $categoryId));
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('items.product', function (Builder $productQuery) use ($search): void {
                            $productQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->get();
    }

    /**
     * @param Collection<int, object> $stockLevels
     * @return array<string, string|null>
     */
    public function latestActivityByStockLevel(Collection $stockLevels, ?int $warehouseId): array
    {
        $productIds = $stockLevels->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $latestBatchByProduct = StockBatch::query()
            ->selectRaw('product_id, MAX(created_at) as latest_activity')
            ->whereIn('product_id', $productIds)
            ->where('status', BatchStatus::Pending)
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->groupBy('product_id')
            ->pluck('latest_activity', 'product_id');

        $latestMovementByProductGrade = StockMovement::query()
            ->selectRaw('product_id, grade, MAX(created_at) as latest_activity')
            ->whereIn('product_id', $productIds)
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->groupBy('product_id', 'grade')
            ->get()
            ->keyBy(fn ($row): string => ((int) $row->product_id).'|'.(($row->grade instanceof \BackedEnum) ? $row->grade->value : (string) $row->grade));

        $latest = [];
        foreach ($stockLevels as $item) {
            $gradeStr = ($item->grade instanceof \BackedEnum) ? $item->grade->value : (string) $item->grade;
            $key = ((int) $item->product_id).'|'.$gradeStr;
            $latest[$key] = $gradeStr === 'Unsorted'
                ? ($latestBatchByProduct[(int) $item->product_id] ?? null)
                : ($latestMovementByProductGrade->get($key)?->latest_activity);
        }

        return $latest;
    }
}
