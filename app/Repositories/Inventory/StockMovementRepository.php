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
use Illuminate\Support\Carbon;
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
    public function currentStockByProductAndGrade(?string $date = null, ?int $warehouseId = null, ?array $categoryIds = null, string $search = ''): Collection
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
            StockMovementType::Adjustment->value,
        ];

        $movementsStock = $this->query()
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw(
                'stock_movements.product_id, products.public_uuid as product_route_key, products.name as product_name, products.sku as product_sku, products.unit as product_unit, products.image as product_image, categories.name as category_name, products.buffer_qty, products.carryover_enabled, stock_movements.grade, '.
                'SUM(CASE '.
                'WHEN stock_movements.type IN (?, ?) THEN stock_movements.quantity '.
                'WHEN stock_movements.type IN (?, ?, ?, ?) THEN -stock_movements.quantity '.
                'ELSE 0 END) as current_stock',
                [
                    $positiveMovementTypes[0],
                    $positiveMovementTypes[1],
                    $negativeMovementTypes[0],
                    $negativeMovementTypes[1],
                    $negativeMovementTypes[2],
                    $negativeMovementTypes[3],
                ]
            )
            ->when($date, fn ($q) => $q->where('stock_movements.created_at', '<=', Carbon::parse($date)->endOfDay()))
            ->when($warehouseId, fn ($q) => $q->where('stock_movements.warehouse_id', $warehouseId))
            ->when($categoryIds !== null, fn ($q) => $q->whereIn('products.category_id', $categoryIds))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function ($productQuery) use ($like): void {
                    $productQuery->where('products.name', 'like', $like)
                        ->orWhere('products.sku', 'like', $like)
                        ->orWhere('categories.name', 'like', $like);
                });
            })
            ->groupBy('stock_movements.product_id', 'products.public_uuid', 'products.name', 'products.sku', 'products.unit', 'products.image', 'categories.name', 'products.buffer_qty', 'products.carryover_enabled', 'stock_movements.grade')
            ->havingRaw('current_stock > 0.0001')
            ->orderByRaw(Product::numericSkuPriorityExpression('products.sku', $driver))
            ->orderByRaw(Product::numericSkuValueExpression('products.sku', $driver))
            ->orderBy('products.sku')
            ->orderBy('stock_movements.grade')
            ->toBase()
            ->get();

        // 2. Fetch pending batches (unsorted stock)
        $pendingBatches = StockBatch::where('status', BatchStatus::Pending)
            ->when($date, fn ($q) => $q->where('received_at', '<=', $date))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($categoryIds !== null, fn ($q) => $q->whereHas('product', fn ($productQuery) => $productQuery->whereIn('category_id', $categoryIds)))
            ->when($search !== '', fn ($q) => $q->whereHas('product', function ($productQuery) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $productQuery->where('name', 'like', $like)->orWhere('sku', 'like', $like);
            }))
            ->with(['product:id,category_id,public_uuid,name,sku,unit,image,buffer_qty,carryover_enabled', 'product.category:id,name'])
            ->get();

        $writeOffByBatch = StockMovement::query()
            ->whereIn('batch_id', $pendingBatches->pluck('id'))
            ->where('grade', ProductGrade::Unsorted->value)
            ->whereIn('type', [StockMovementType::Wastage->value, StockMovementType::Adjustment->value])
            ->selectRaw('batch_id, SUM(quantity) as quantity')
            ->groupBy('batch_id')
            ->pluck('quantity', 'batch_id');

        $unsortedStock = [];
        foreach ($pendingBatches as $batch) {
            if (! $batch->product) {
                continue;
            }

            // Pending batches do not have a sorted stock ledger yet. Their remaining
            // quantity is reduced by allocations and explicit physical write-offs.
            $writeOffQty = (float) ($writeOffByBatch[$batch->id] ?? 0);
            $qty = max(0.0, (float) $batch->remaining_qty - $writeOffQty);
            if ($qty <= 0.0001) {
                continue;
            }

            $productId = $batch->product_id;

            if (! isset($unsortedStock[$productId])) {
                $unsortedStock[$productId] = [
                    'product_id' => $productId,
                    'product_route_key' => $batch->product->getRouteKey(),
                    'product_name' => $batch->product->name,
                    'product_sku' => $batch->product->sku,
                    'product_unit' => $batch->product->unit,
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

    public function currentStockForProduct(int $productId, ?int $warehouseId = null, ?string $date = null): float
    {
        return round((float) $this->currentStockByProductAndGrade($date, $warehouseId)
            ->where('product_id', $productId)
            ->sum('current_stock'), 3);
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
            ->whereIn('type', [StockMovementType::Out->value, StockMovementType::Wastage->value, StockMovementType::Sale->value, StockMovementType::Adjustment->value])
            ->sum('quantity');

        return max(0, (float) $inQty - (float) $outQty);
    }
}
