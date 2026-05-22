<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
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
     *
     * @return Collection<int, object{product_id: int, product_name: string, grade: string, current_stock: float}>
     */
    public function currentStockByProductAndGrade(): Collection
    {
        return $this->query()
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->selectRaw('stock_movements.product_id, products.name as product_name, products.image as product_image, stock_movements.grade, SUM(CASE WHEN stock_movements.type = ? THEN stock_movements.quantity ELSE -stock_movements.quantity END) as current_stock', [StockMovementType::In->value])
            ->groupBy('stock_movements.product_id', 'products.name', 'products.image', 'stock_movements.grade')
            ->having('current_stock', '>', 0)
            ->orderBy('products.name')
            ->orderBy('stock_movements.grade')
            ->toBase()
            ->get();
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
