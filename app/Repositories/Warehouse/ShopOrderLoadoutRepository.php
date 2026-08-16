<?php

declare(strict_types=1);

namespace App\Repositories\Warehouse;

use App\Models\ShopOrder;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ShopOrderLoadoutRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return ShopOrder::class;
    }

    /**
     * @param array<int, int>|null $categoryIds
     * @return Collection<int, ShopOrder>
     */
    public function loadoutOrders(
        ?string $date,
        ?int $shopId,
        string $source,
        ?array $categoryIds,
        ?int $warehouseId,
        string $search,
        bool $loadItems = false,
    ): Collection {
        $query = $this->baseLoadoutQuery($date, $shopId, $source, $categoryIds, $warehouseId, $search)
            ->select([
                'id',
                'shop_id',
                'order_number',
                'business_date',
                'delivery_status',
                'order_source',
                'created_at',
                'updated_at',
            ])
            ->with(['shop:id,name,code,warehouse_tag'])
            ->withCount([
                'items as total_count',
                'items as loaded_count' => fn (Builder $query) => $query->where('sorting_status', 'loaded'),
            ]);

        if ($loadItems) {
            $query->with([
                'items:id,shop_order_id,product_id,sorting_status,approved_qty,requested_qty,loaded_qty',
                'items.product:id,category_id,name,sku,unit',
                'items.product.category:id,name',
            ]);
        }

        return $query
            ->orderBy('business_date', 'desc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * @param array<int, int>|null $categoryIds
     */
    private function baseLoadoutQuery(
        ?string $date,
        ?int $shopId,
        string $source,
        ?array $categoryIds,
        ?int $warehouseId,
        string $search,
    ): Builder {
        return $this->query()
            ->whereIn('delivery_status', [
                'pending_delivery',
                'ready_for_dispatch',
                'in_transit',
                'delivered',
                'pending_approval',
                'partially_delivered',
                'delivery_issue',
            ])
            ->whereHas('items')
            ->when($date, fn (Builder $query) => $query->whereDate('business_date', $date))
            ->when($shopId, fn (Builder $query) => $query->where('shop_id', $shopId))
            ->when($source === 'all', fn (Builder $query) => $query->where('order_source', '!=', 'admin_direct_purchase'))
            ->when($source === 'shop', fn (Builder $query) => $query->where('order_source', 'shop_owner'))
            ->when($source === 'direct', fn (Builder $query) => $query->whereIn('order_source', ['admin_direct_purchase', 'direct_sale']))
            ->when($categoryIds !== null && $categoryIds !== [], function (Builder $query) use ($categoryIds): void {
                $query->whereHas('items.product', fn (Builder $productQuery) => $productQuery->whereIn('category_id', $categoryIds));
            })
            ->when($warehouseId, function (Builder $query) use ($warehouseId): void {
                $query->whereHas('items.product', fn (Builder $productQuery) => $productQuery->where('default_warehouse_id', $warehouseId));
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('shop', function (Builder $shopQuery) use ($search): void {
                            $shopQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%")
                                ->orWhere('warehouse_tag', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.product', function (Builder $productQuery) use ($search): void {
                            $productQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                        });
                });
            });
    }
}
