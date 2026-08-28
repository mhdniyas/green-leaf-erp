<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class WarehouseReceiptReadScope
{
    public function __construct(private readonly WarehouseReceiptStateResolver $receiptState) {}

    /** @return array<int, int>|null Null means explicitly authorized all-warehouse access. */
    public function warehouseIds(User $user, ?int $selected = null): ?array
    {
        $all = $user->hasRole('admin') || $user->hasAllWarehouseAccess();
        if ($selected !== null) {
            abort_unless($all || $user->canAccessWarehouse($selected), 403, 'Unauthorized warehouse access.');

            return [$selected];
        }

        return $all ? null : $user->warehouses()->pluck('warehouses.id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Keep whole-record payloads within scope. Mixed-warehouse receipts require access
     * to every warehouse; product defaults never override an assigned warehouse.
     *
     * @param  array<int, int>|null  $ids
     */
    public function receipts(Builder $query, ?array $ids): Builder
    {
        if ($ids === null) {
            return $query;
        }
        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $effective = 'COALESCE(stock_batches.warehouse_id, goods_received.warehouse_id, (SELECT products.default_warehouse_id FROM products WHERE products.id = stock_batches.product_id))';
        $foreignBatches = $this->receiptState->batches()->whereRaw("({$effective} IS NULL OR {$effective} NOT IN ({$placeholders}))", $ids);

        return $query->where(function (Builder $scope) use ($ids, $foreignBatches): void {
            $scope->where(function (Builder $withBatches) use ($foreignBatches): void {
                $withBatches->whereExists($this->receiptState->batches()->selectRaw('1')->toBase())
                    ->whereNotExists($foreignBatches->selectRaw('1')->toBase());
            })->orWhere(function (Builder $withoutBatches) use ($ids): void {
                $withoutBatches->whereNotExists($this->receiptState->batches()->selectRaw('1')->toBase())
                    ->where(function (Builder $ownership) use ($ids): void {
                        $ownership->whereIn('goods_received.warehouse_id', $ids)
                            ->orWhere(function (Builder $unassigned) use ($ids): void {
                                $unassigned->whereNull('goods_received.warehouse_id');
                                $this->productItems($unassigned, $ids);
                            });
                    });
            });
        });
    }

    /** @param array<int, int>|null $ids */
    public function orders(Builder $query, ?array $ids): Builder
    {
        if ($ids === null) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($ids): void {
            $scope->where(function (Builder $withReceipts) use ($ids): void {
                $withReceipts->whereHas('goodsReceiveds')
                    ->whereDoesntHave('goodsReceiveds', fn (Builder $receipt) => $receipt->whereNot(fn (Builder $allowed) => $this->receipts($allowed, $ids)));
            })->orWhere(function (Builder $withoutReceipts) use ($ids): void {
                $withoutReceipts->whereDoesntHave('goodsReceiveds');
                $this->productItems($withoutReceipts, $ids);
            });
        });
    }

    /** @param array<int, int> $ids */
    public function productItems(Builder $query, array $ids): Builder
    {
        return $query->whereHas('items')
            ->whereDoesntHave('items', fn (Builder $item) => $item->whereDoesntHave('product', fn (Builder $product) => $product->whereIn('default_warehouse_id', $ids)));
    }

    /** @param array<int, int>|null $ids */
    public function batches(Builder $query, ?array $ids): Builder
    {
        if ($ids === null) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($ids): void {
            $scope->whereIn('warehouse_id', $ids)->orWhere(function (Builder $unassigned) use ($ids): void {
                $unassigned->whereNull('warehouse_id')->whereHas('product', fn (Builder $product) => $product->whereIn('default_warehouse_id', $ids));
            });
        });
    }
}
