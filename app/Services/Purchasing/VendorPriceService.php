<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaserCart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VendorPriceService
{
    public function syncPrice(int $productId, float $price, ?int $supplierId = null): void
    {
        if ($price <= 0) {
            return;
        }

        Product::query()
            ->whereKey($productId)
            ->update(['vendor_price' => round($price, 4)]);

        if ($supplierId !== null) {
            DB::table('product_supplier')->updateOrInsert(
                [
                    'product_id' => $productId,
                    'supplier_id' => $supplierId,
                ],
                [
                    'last_price' => round($price, 4),
                    'last_purchased_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        DB::afterCommit(function (): void {
            app(PurchaserReadCacheService::class)->invalidate(['products', 'prices']);
        });
    }

    /**
     * @param  iterable<int, array{product_id: int, unit_price: float|int}>  $lines
     */
    public function syncMany(?int $supplierId, iterable $lines): void
    {
        foreach ($lines as $line) {
            $this->syncPrice(
                productId: (int) $line['product_id'],
                price: (float) $line['unit_price'],
                supplierId: $supplierId,
            );
        }
    }

    /**
     * @param  iterable<int, int>  $productIds
     * @return array<int, float>
     */
    public function previousPricesForSupplier(?int $supplierId, iterable $productIds, ?int $excludePurchaseOrderId = null): array
    {
        $productIds = collect($productIds)
            ->map(fn ($productId): int => (int) $productId)
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $fallbackPrices = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('vendor_price', 'id')
            ->map(fn ($price): float => (float) $price)
            ->all();

        if ($supplierId === null) {
            return $fallbackPrices;
        }

        $supplierPrices = DB::table('product_supplier')
            ->where('supplier_id', $supplierId)
            ->whereIn('product_id', $productIds)
            ->pluck('last_price', 'product_id')
            ->map(fn ($price): float => (float) $price)
            ->all();

        $missingProductIds = $productIds
            ->reject(fn (int $productId): bool => array_key_exists($productId, $supplierPrices))
            ->values();

        if ($missingProductIds->isEmpty()) {
            return $supplierPrices;
        }

        $historicalPrices = PurchaseOrderItem::query()
            ->select('purchase_order_items.product_id', 'purchase_order_items.unit_price')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.supplier_id', $supplierId)
            ->whereIn('purchase_order_items.product_id', $missingProductIds)
            ->when($excludePurchaseOrderId, fn ($query) => $query->where('purchase_orders.id', '!=', $excludePurchaseOrderId))
            ->orderByDesc('purchase_orders.order_date')
            ->orderByDesc('purchase_order_items.id')
            ->get()
            ->unique('product_id')
            ->mapWithKeys(fn ($row): array => [(int) $row->product_id => (float) $row->unit_price])
            ->all();

        return $productIds
            ->mapWithKeys(function (int $productId) use ($supplierPrices, $historicalPrices, $fallbackPrices): array {
                $price = $supplierPrices[$productId]
                    ?? $historicalPrices[$productId]
                    ?? $fallbackPrices[$productId]
                    ?? 0.0;

                return [$productId => (float) $price];
            })
            ->all();
    }

    /**
     * @param  Collection<int, PurchaserCart>  $carts
     * @return array<int, array<int, float>>
     */
    public function previousPricesForCarts(Collection $carts): array
    {
        if ($carts->isEmpty()) {
            return [];
        }

        $allProductIds = $carts->flatMap(fn (PurchaserCart $cart) => $cart->items->pluck('product_id'))
            ->filter()
            ->unique()
            ->values();

        if ($allProductIds->isEmpty()) {
            return $carts->mapWithKeys(fn (PurchaserCart $cart) => [(int) $cart->id => []])->all();
        }

        $fallbackPrices = Product::query()
            ->whereIn('id', $allProductIds)
            ->pluck('vendor_price', 'id')
            ->map(fn ($price): float => (float) $price)
            ->all();

        $supplierIds = $carts->pluck('supplier_id')->filter()->unique()->values();

        $supplierPrices = [];
        if ($supplierIds->isNotEmpty()) {
            $rows = DB::table('product_supplier')
                ->whereIn('supplier_id', $supplierIds)
                ->whereIn('product_id', $allProductIds)
                ->select(['supplier_id', 'product_id', 'last_price'])
                ->get();

            foreach ($rows as $row) {
                $supplierPrices[(int) $row->supplier_id][(int) $row->product_id] = (float) $row->last_price;
            }
        }

        $missingPairs = [];
        foreach ($carts as $cart) {
            $supId = $cart->supplier_id;
            if ($supId === null) {
                continue;
            }
            foreach ($cart->items as $item) {
                $pId = (int) $item->product_id;
                if (! isset($supplierPrices[$supId][$pId])) {
                    $missingPairs[$supId][] = $pId;
                }
            }
        }

        $historicalPrices = [];
        if (! empty($missingPairs)) {
            $allMissingSuppliers = array_keys($missingPairs);
            $allMissingProducts = collect($missingPairs)->flatten()->unique()->values();

            $histRows = PurchaseOrderItem::query()
                ->select('purchase_orders.supplier_id', 'purchase_order_items.product_id', 'purchase_order_items.unit_price')
                ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
                ->whereIn('purchase_orders.supplier_id', $allMissingSuppliers)
                ->whereIn('purchase_order_items.product_id', $allMissingProducts)
                ->orderByDesc('purchase_orders.order_date')
                ->orderByDesc('purchase_order_items.id')
                ->get();

            foreach ($histRows as $row) {
                $sId = (int) $row->supplier_id;
                $pId = (int) $row->product_id;
                if (! isset($historicalPrices[$sId][$pId])) {
                    $historicalPrices[$sId][$pId] = (float) $row->unit_price;
                }
            }
        }

        return $carts->mapWithKeys(function (PurchaserCart $cart) use ($supplierPrices, $historicalPrices, $fallbackPrices): array {
            $supId = $cart->supplier_id;
            $hints = [];

            foreach ($cart->items as $item) {
                $pId = (int) $item->product_id;
                if ($supId === null) {
                    $hints[$pId] = $fallbackPrices[$pId] ?? 0.0;
                } else {
                    $hints[$pId] = $supplierPrices[$supId][$pId]
                        ?? $historicalPrices[$supId][$pId]
                        ?? $fallbackPrices[$pId]
                        ?? 0.0;
                }
            }

            return [(int) $cart->id => $hints];
        })->all();
    }
}
