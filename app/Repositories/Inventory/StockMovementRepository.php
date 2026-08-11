<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StockMovementRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return StockMovement::class;
    }

    public function paginateFiltered(int $perPage = 15, ?int $productId = null): LengthAwarePaginator
    {
        return $this->query()
            ->with(['product', 'batch', 'createdBy'])
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get current stock levels grouped by product_id and grade.
     * Uses toBase() so results are plain stdClass objects — prevents the
     * Eloquent 'grade' enum cast from being applied, keeping grade as a
     * raw string value safe for array-key lookup.
     */
    /** @param array<int, int>|null $categoryIds */
    public function currentStockByProductAndGrade(?string $date = null, ?int $warehouseId = null, ?array $categoryIds = null): Collection
    {
        $driver = (new Product)->getConnection()->getDriverName();
        $positiveMovementTypes = [
            StockMovementType::In->value,
            StockMovementType::SaleReversal->value,
        ];
        $negativeMovementTypes = [
            StockMovementType::Out->value,
            StockMovementType::Wastage->value,
            StockMovementType::Sale->value,
        ];

        $movementsStock = $this->query()
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw(
                'stock_movements.product_id, products.public_uuid as product_route_key, products.name as product_name, products.sku as product_sku, products.image as product_image, categories.name as category_name, products.buffer_qty, products.carryover_enabled, stock_movements.grade, '.
                'SUM(CASE '.
                'WHEN stock_movements.type IN (?, ?) THEN stock_movements.quantity '.
                'WHEN stock_movements.type IN (?, ?, ?) THEN -stock_movements.quantity '.
                'ELSE 0 END) as current_stock',
                [
                    $positiveMovementTypes[0],
                    $positiveMovementTypes[1],
                    $negativeMovementTypes[0],
                    $negativeMovementTypes[1],
                    $negativeMovementTypes[2],
                ]
            )
            ->when($date, fn ($q) => $q->whereDate('stock_movements.created_at', '<=', $date))
            ->when($warehouseId, fn ($q) => $q->where('stock_movements.warehouse_id', $warehouseId))
            ->when($categoryIds !== null, fn ($q) => $q->whereIn('products.category_id', $categoryIds))
            ->groupBy('stock_movements.product_id', 'products.public_uuid', 'products.name', 'products.sku', 'products.image', 'categories.name', 'products.buffer_qty', 'products.carryover_enabled', 'stock_movements.grade')
            ->havingRaw('current_stock > 0.0001')
            ->orderByRaw(Product::numericSkuPriorityExpression('products.sku', $driver))
            ->orderByRaw(Product::numericSkuValueExpression('products.sku', $driver))
            ->orderBy('products.sku')
            ->orderBy('stock_movements.grade')
            ->toBase()
            ->get();

        // 2. Fetch pending batches (unsorted stock)
        $pendingBatches = StockBatch::where('status', BatchStatus::Pending)
            ->when($date, fn ($q) => $q->whereDate('received_at', '<=', $date))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($categoryIds !== null, fn ($q) => $q->whereHas('product', fn ($productQuery) => $productQuery->whereIn('category_id', $categoryIds)))
            ->with(['product.category', 'wastageEntries'])
            ->get();

        $unsortedStock = [];
        foreach ($pendingBatches as $batch) {
            if (! $batch->product) {
                continue;
            }

            // Unsorted quantity is the remaining quantity of the batch
            $qty = (float) $batch->remaining_qty;
            if ($qty <= 0) {
                continue;
            }

            $productId = $batch->product_id;

            if (! isset($unsortedStock[$productId])) {
                $unsortedStock[$productId] = [
                    'product_id' => $productId,
                    'product_route_key' => $batch->product->getRouteKey(),
                    'product_name' => $batch->product->name,
                    'product_sku' => $batch->product->sku,
                    'product_image' => $batch->product->image,
                    'category_name' => $batch->product->category->name ?? 'Other',
                    'grade' => 'Unsorted',
                    'current_stock' => 0.0,
                ];
            }

            $unsortedStock[$productId]['current_stock'] += $qty;
        }

        // Convert the unsorted stock map to stdClass objects to match the database query format
        $unsortedCollection = collect(array_values($unsortedStock))->map(function ($item) {
            return (object) $item;
        });

        // 3. Merge the two collections and sort by product_name
        return $movementsStock->concat($unsortedCollection)->sortBy(
            fn ($item): string => Product::sortableSku((string) ($item->product_sku ?? ''))
        );
    }

    /**
     * Get stock for a specific product and grade.
     */
    public function stockForProductAndGrade(int $productId, ProductGrade $grade): float
    {
        $inQty = $this->query()
            ->where('product_id', $productId)
            ->where('grade', $grade->value)
            ->whereIn('type', [StockMovementType::In->value, StockMovementType::SaleReversal->value])
            ->sum('quantity');

        $outQty = $this->query()
            ->where('product_id', $productId)
            ->where('grade', $grade->value)
            ->whereIn('type', [StockMovementType::Out->value, StockMovementType::Wastage->value, StockMovementType::Sale->value])
            ->sum('quantity');

        return max(0, (float) $inQty - (float) $outQty);
    }
}
