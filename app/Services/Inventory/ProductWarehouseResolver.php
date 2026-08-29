<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;

class ProductWarehouseResolver
{
    /**
     * Canonical category name to warehouse code mappings.
     * Note: Warehouse IDs are never hardcoded; they are resolved dynamically from active warehouse records.
     *
     * @var array<string, string>
     */
    private const CANONICAL_CATEGORY_WAREHOUSE_CODES = [
        'frut' => 'FRT-WH',
        'fruit' => 'FRT-WH',
        'fruits' => 'FRT-WH',
        'c' => 'FRT-WH',
        'stationary' => 'FRT-WH',
        'banana' => 'VEG-WH',
        'veg' => 'VEG-WH',
        'veg 1' => 'VEG-WH',
        'vegetable' => 'VEG-WH',
        'vegetables' => 'VEG-WH',
        'leaf' => 'VEG-WH',
        'english' => 'VEG-WH',
        'english 1' => 'VEG-WH',
        'supply' => 'VEG-WH',
        'hal' => 'VEG-WH',
        'kolkata' => 'VEG-WH',
        'onion' => 'VEG-WH',
        'stationary 1' => 'VEG-WH',
    ];

    /**
     * Resolve default warehouse ID according to priority rules:
     * 1. Explicit warehouse selected by user (if valid and active)
     * 2. Category / default warehouse mapping from category_warehouse pivot
     * 3. Canonical produce-type / domain mapping using active VEG-WH / FRT-WH
     * 4. Otherwise null (unallocated for admin review)
     */
    public function resolve(int|Warehouse|null $explicitWarehouse = null, int|Category|null $category = null, ?string $produceType = null): ?int
    {
        // 1. Explicit warehouse selected by user
        if ($explicitWarehouse !== null) {
            $explicitId = $explicitWarehouse instanceof Warehouse ? (int) $explicitWarehouse->id : (int) $explicitWarehouse;
            if ($explicitId > 0 && Warehouse::where('is_active', true)->where('id', $explicitId)->exists()) {
                return $explicitId;
            }
        }

        $categoryModel = $this->findCategory($category);

        // 2. Category / default warehouse mapping in category_warehouse pivot
        if ($categoryModel !== null) {
            $mappedWarehouse = $categoryModel->warehouses()
                ->where('warehouses.is_active', true)
                ->first(['warehouses.id']);

            if ($mappedWarehouse !== null) {
                return (int) $mappedWarehouse->id;
            }
        }

        // 3. Existing produce-type / domain mapping if canonical
        $canonicalWarehouseId = $this->resolveFromCanonicalDomain($categoryModel, $produceType);
        if ($canonicalWarehouseId !== null) {
            return $canonicalWarehouseId;
        }

        // 4. Otherwise leave unallocated for admin review
        return null;
    }

    /**
     * Recommend a warehouse for an existing product based on its category.
     */
    public function recommendForProduct(Product $product): ?Warehouse
    {
        $warehouseId = $this->resolve(
            explicitWarehouse: null,
            category: $product->category ?? $product->category_id
        );

        if ($warehouseId === null) {
            return null;
        }

        return Warehouse::where('is_active', true)->find($warehouseId);
    }

    /**
     * Safely assign recommended warehouses to all unallocated products.
     * Only allocates products where a confident recommendation is resolved.
     *
     * @param  array<int>|null  $productIds  Specific product IDs to assign, or null for all unallocated
     * @return array{assigned_count: int, unallocated_count: int, assigned_products: array<int, array{id: int, name: string, warehouse_id: int, warehouse_name: string}>}
     */
    public function assignRecommendedForUnallocated(?array $productIds = null): array
    {
        $query = Product::whereNull('default_warehouse_id')->with('category');
        if (! empty($productIds)) {
            $query->whereIn('id', $productIds);
        }

        $unallocated = $query->get();
        $assignedCount = 0;
        $assignedProducts = [];

        foreach ($unallocated as $product) {
            $recommendedWarehouse = $this->recommendForProduct($product);
            if ($recommendedWarehouse !== null) {
                $product->update(['default_warehouse_id' => $recommendedWarehouse->id]);
                $assignedCount++;
                $assignedProducts[] = [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'warehouse_id' => (int) $recommendedWarehouse->id,
                    'warehouse_name' => $recommendedWarehouse->name,
                ];
            }
        }

        $remainingUnallocated = Product::whereNull('default_warehouse_id')->count();

        return [
            'assigned_count' => $assignedCount,
            'unallocated_count' => $remainingUnallocated,
            'assigned_products' => $assignedProducts,
        ];
    }

    private function findCategory(int|Category|null $category): ?Category
    {
        if ($category instanceof Category) {
            return $category;
        }

        if (is_numeric($category) && (int) $category > 0) {
            return Category::with('warehouses')->find((int) $category);
        }

        return null;
    }

    private function resolveFromCanonicalDomain(?Category $category, ?string $produceType): ?int
    {
        $canonicalCode = null;

        if ($produceType !== null) {
            $normalizedProduce = strtolower(trim($produceType));
            if (in_array($normalizedProduce, ['veg', 'vegetable', 'vegetables'], true)) {
                $canonicalCode = 'VEG-WH';
            } elseif (in_array($normalizedProduce, ['fruit', 'fruits'], true)) {
                $canonicalCode = 'FRT-WH';
            }
        }

        if ($canonicalCode === null && $category !== null && filled($category->name)) {
            $categoryKey = strtolower(trim($category->name));
            $canonicalCode = self::CANONICAL_CATEGORY_WAREHOUSE_CODES[$categoryKey] ?? null;
        }

        if ($canonicalCode !== null) {
            $warehouse = Warehouse::where('code', $canonicalCode)
                ->where('is_active', true)
                ->first(['id']);

            if ($warehouse !== null) {
                return (int) $warehouse->id;
            }
        }

        return null;
    }
}
