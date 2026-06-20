<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Product;
use App\Models\PurchaseOrderItem;
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

        if ($supplierId === null) {
            return;
        }

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
}
