<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
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
    public function currentStockByProductAndGrade(?string $date = null): Collection
    {
        // 1. Fetch sorted stock levels from StockMovement
        $movementsStock = $this->query()
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->selectRaw('stock_movements.product_id, products.name as product_name, products.image as product_image, stock_movements.grade, SUM(CASE WHEN stock_movements.type = ? THEN stock_movements.quantity ELSE -stock_movements.quantity END) as current_stock', [StockMovementType::In->value])
            ->when($date, fn ($q) => $q->whereDate('stock_movements.created_at', '<=', $date))
            ->groupBy('stock_movements.product_id', 'products.name', 'products.image', 'stock_movements.grade')
            ->having('current_stock', '>', 0)
            ->orderBy('products.name')
            ->orderBy('stock_movements.grade')
            ->toBase()
            ->get();

        // 2. Fetch pending batches (unsorted stock)
        $pendingBatches = StockBatch::where('status', BatchStatus::Pending)
            ->when($date, fn ($q) => $q->whereDate('received_at', '<=', $date))
            ->with(['product', 'wastageEntries'])
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
                    'product_name' => $batch->product->name,
                    'product_image' => $batch->product->image,
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
        return $movementsStock->concat($unsortedCollection)->sortBy('product_name');
    }

    /**
     * Get stock for a specific product and grade.
     */
    public function stockForProductAndGrade(int $productId, ProductGrade $grade): float
    {
        $inQty = $this->query()
            ->where('product_id', $productId)
            ->where('grade', $grade->value)
            ->where('type', StockMovementType::In->value)
            ->sum('quantity');

        $outQty = $this->query()
            ->where('product_id', $productId)
            ->where('grade', $grade->value)
            ->whereIn('type', [StockMovementType::Out->value, StockMovementType::Wastage->value])
            ->sum('quantity');

        return max(0, (float) $inQty - (float) $outQty);
    }
}
