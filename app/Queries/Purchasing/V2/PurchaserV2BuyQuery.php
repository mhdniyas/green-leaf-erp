<?php

declare(strict_types=1);

namespace App\Queries\Purchasing\V2;

use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Purchasing\VendorPriceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaserV2BuyQuery
{
    public function __construct(
        private readonly VendorPriceService $vendorPriceService,
    ) {}

    /**
     * Get structured data for selected products in the Buy workspace using bounded grouped SQL queries.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, array{
     *     product_id: int,
     *     name: string,
     *     sku: string,
     *     category_id: int,
     *     category_name: string,
     *     base_unit: string,
     *     orderable_units: array<int, array{unit: string, label: string, conversion_to_base: float, is_base: bool}>,
     *     approved_qty: float,
     *     submitted_qty: float,
     *     remaining_qty: float,
     *     default_purchase_qty: float,
     *     unit_price: float,
     *     is_intended: bool,
     *     is_addon: bool
     * }>
     */
    public function getSelectedProducts(
        array $productIds,
        Carbon $date,
        string $grade,
        User $user,
        ?int $cartId = null,
    ): array {
        $productIds = collect($productIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($productIds)) {
            return [];
        }

        $assignedCategoryIds = $user->hasAssignedCategoryFilter() ? $user->assignedCategoryIds() : [];
        $isCategoryFiltered = count($assignedCategoryIds) > 0;
        $dateStr = $date->toDateString();

        // Query 1: Product metadata & units for selected IDs
        $products = Product::query()
            ->with(['category:id,name', 'orderUnits'])
            ->whereIn('id', $productIds)
            ->when($isCategoryFiltered, fn ($q) => $q->whereIn('category_id', $assignedCategoryIds))
            ->get()
            ->keyBy('id');

        if ($products->isEmpty()) {
            return [];
        }

        $validProductIds = $products->keys()->all();

        // Query 2: Grouped approved quantities for selected products on this date & grade
        $approvedQuantities = ShopOrderItem::query()
            ->whereIn('product_id', $validProductIds)
            ->where('product_grade', $grade)
            ->whereHas('order', function ($query) use ($date): void {
                $query->whereDate('business_date', $date)
                    ->where('state', 'approved');
            })
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(approved_qty) as total_approved'))
            ->pluck('total_approved', 'product_id')
            ->map(fn ($qty): float => (float) $qty)
            ->all();

        // Query 3: Grouped submitted quantities for selected products on this date & grade (excluding current draft cart if editing)
        $submittedQuantities = PurchaserCartItem::query()
            ->whereIn('product_id', $validProductIds)
            ->where('grade', $grade)
            ->whereHas('cart', function ($query) use ($date, $grade, $cartId): void {
                $query->whereDate('business_date', $date)
                    ->where('status', 'submitted')
                    ->where('purchase_grade', $grade)
                    ->when($cartId !== null, fn ($q) => $q->whereKeyNot($cartId));
            })
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(quantity) as total_submitted'))
            ->pluck('total_submitted', 'product_id')
            ->map(fn ($qty): float => (float) $qty)
            ->all();

        // Query 4: Grouped price hints
        $priceHints = $this->vendorPriceService->previousPricesForSupplier(null, $validProductIds);

        $results = [];
        foreach ($validProductIds as $productId) {
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }

            $approvedQty = $approvedQuantities[$productId] ?? 0.0;
            $submittedQty = $submittedQuantities[$productId] ?? 0.0;
            $remainingQty = max(0.0, round($approvedQty - $submittedQty, 2));
            $isIntended = $approvedQty > 0.001;
            $isAddon = ! $isIntended;
            $defaultPurchaseQty = $isIntended ? $remainingQty : 1.0;
            $unitPrice = (float) ($priceHints[$productId] ?? $product->vendor_price ?? $product->base_price ?? 0.0);

            $results[] = [
                'product_id' => $productId,
                'name' => (string) $product->name,
                'sku' => (string) ($product->sku ?? ''),
                'category_id' => (int) ($product->category_id ?? 0),
                'category_name' => (string) ($product->category?->name ?? 'Uncategorized'),
                'base_unit' => (string) ($product->unit ?: 'kg'),
                'orderable_units' => $this->resolveOrderableUnits($product),
                'approved_qty' => round($approvedQty, 2),
                'submitted_qty' => round($submittedQty, 2),
                'remaining_qty' => $remainingQty,
                'default_purchase_qty' => round($defaultPurchaseQty, 2),
                'unit_price' => round($unitPrice, 2),
                'is_intended' => $isIntended,
                'is_addon' => $isAddon,
            ];
        }

        return $results;
    }

    /**
     * Get first page of pending intended products (remaining_qty > 0) on the date & grade.
     *
     * @return array<int, array{
     *     product_id: int,
     *     name: string,
     *     sku: string,
     *     category_name: string,
     *     unit: string,
     *     remaining_qty: float,
     *     approved_qty: float,
     *     submitted_qty: float
     * }>
     */
    public function getPendingIntendedProducts(
        Carbon $date,
        string $grade,
        User $user,
        int $limit = 30,
    ): array {
        $assignedCategoryIds = $user->hasAssignedCategoryFilter() ? $user->assignedCategoryIds() : [];
        $isCategoryFiltered = count($assignedCategoryIds) > 0;
        $dateStr = $date->toDateString();

        // 1. Grouped approved demand for the day
        $approvedQuery = ShopOrderItem::query()
            ->join('shop_orders as so', 'so.id', '=', 'shop_order_items.shop_order_id')
            ->join('products as p', 'p.id', '=', 'shop_order_items.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->whereDate('so.business_date', $dateStr)
            ->where('so.state', 'approved')
            ->where('shop_order_items.product_grade', $grade)
            ->where('p.is_active', true)
            ->where('p.show_in_purchaser_order', true)
            ->when($isCategoryFiltered, fn ($q) => $q->whereIn('p.category_id', $assignedCategoryIds))
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.unit', 'c.name')
            ->select([
                'p.id as product_id',
                'p.name as product_name',
                'p.sku',
                'p.unit',
                'c.name as category_name',
                DB::raw('SUM(shop_order_items.approved_qty) as total_approved'),
            ])
            ->orderBy('p.name');

        $approvedRows = $approvedQuery->get();

        if ($approvedRows->isEmpty()) {
            return [];
        }

        $productIds = $approvedRows->pluck('product_id')->all();

        // 2. Grouped submitted quantities for those products
        $submittedQuantities = PurchaserCartItem::query()
            ->whereIn('product_id', $productIds)
            ->where('grade', $grade)
            ->whereHas('cart', function ($query) use ($dateStr, $grade): void {
                $query->whereDate('business_date', $dateStr)
                    ->where('status', 'submitted')
                    ->where('purchase_grade', $grade);
            })
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(quantity) as total_submitted'))
            ->pluck('total_submitted', 'product_id')
            ->map(fn ($qty): float => (float) $qty)
            ->all();

        $items = [];
        foreach ($approvedRows as $row) {
            $productId = (int) $row->product_id;
            $approvedQty = (float) $row->total_approved;
            $submittedQty = (float) ($submittedQuantities[$productId] ?? 0.0);
            $remainingQty = max(0.0, round($approvedQty - $submittedQty, 2));

            // Only return products that still need purchasing
            if ($remainingQty <= 0.001) {
                continue;
            }

            $items[] = [
                'product_id' => $productId,
                'name' => (string) $row->product_name,
                'sku' => (string) ($row->sku ?? ''),
                'category_name' => (string) ($row->category_name ?? 'Uncategorized'),
                'unit' => (string) ($row->unit ?: 'kg'),
                'remaining_qty' => $remainingQty,
                'approved_qty' => round($approvedQty, 2),
                'submitted_qty' => round($submittedQty, 2),
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * Get count of active draft carts for user on date & grade.
     */
    public function getDraftCartsCount(User $user, Carbon $date, string $grade): int
    {
        return PurchaserCart::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->where('purchase_grade', $grade)
            ->count();
    }

    /**
     * Resolve orderable units with conversion multipliers for a product.
     *
     * @return array<int, array{unit: string, label: string, conversion_to_base: float, is_base: bool}>
     */
    private function resolveOrderableUnits(Product $product): array
    {
        $units = $product->relationLoaded('orderUnits')
            ? $product->orderUnits
            : $product->orderUnits()->orderBy('sort_order')->orderBy('id')->get();

        $measurementUnits = $units
            ->filter(fn ($unit): bool => (float) $unit->conversion_to_base > 0)
            ->values();

        if ($measurementUnits->isEmpty()) {
            return [[
                'unit' => (string) ($product->unit ?: 'kg'),
                'label' => strtoupper((string) ($product->unit ?: 'kg')),
                'conversion_to_base' => 1.0,
                'is_base' => true,
            ]];
        }

        return $measurementUnits
            ->map(fn ($unit): array => [
                'unit' => (string) $unit->unit,
                'label' => (string) ($unit->label ?: strtoupper((string) $unit->unit)),
                'conversion_to_base' => (float) $unit->conversion_to_base,
                'is_base' => (bool) $unit->is_base,
            ])
            ->all();
    }
}
