<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\StockMovementType;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class StockLedgerService
{
    public function availableSortedStockForProduct(int $productId): float
    {
        $lots = $this->sortedLotsForProduct($productId);

        return (float) $lots->sum('available_quantity');
    }

    public function consumeSortedStockForProduct(
        int $productId,
        float $quantity,
        int $userId,
        StockMovementType $movementType,
        string $notes
    ): float {
        if ($quantity <= 0.0) {
            return 0.0;
        }

        $remainingQuantity = $quantity;
        $consumedQuantity = 0.0;

        foreach ($this->sortedLotsForProduct($productId) as $lot) {
            if ($remainingQuantity <= 0.0) {
                break;
            }

            $availableQuantity = (float) $lot->available_quantity;

            if ($availableQuantity <= 0.0) {
                continue;
            }

            $deductionQuantity = min($remainingQuantity, $availableQuantity);

            StockMovement::create([
                'product_id' => $productId,
                'batch_id' => (int) $lot->batch_id,
                'created_by' => $userId,
                'grade' => (string) $lot->grade,
                'type' => $movementType->value,
                'quantity' => $deductionQuantity,
                'cost_per_unit' => (float) $lot->cost_per_unit,
                'notes' => $notes,
            ]);

            $remainingQuantity -= $deductionQuantity;
            $consumedQuantity += $deductionQuantity;
        }

        return round($consumedQuantity, 3);
    }

    /**
     * @return Collection<int, object>
     */
    private function sortedLotsForProduct(int $productId): Collection
    {
        return StockMovement::query()
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_movements.batch_id')
            ->where('stock_movements.product_id', $productId)
            ->whereNull('stock_batches.deleted_at')
            ->selectRaw(
                'stock_movements.batch_id, stock_movements.grade, '.
                'MAX(stock_movements.cost_per_unit) as cost_per_unit, '.
                'SUM(CASE '.
                'WHEN stock_movements.type IN (?, ?) THEN stock_movements.quantity '.
                'WHEN stock_movements.type IN (?, ?, ?) THEN -stock_movements.quantity '.
                'ELSE 0 END) as available_quantity',
                [
                    StockMovementType::In->value,
                    StockMovementType::SaleReversal->value,
                    StockMovementType::Out->value,
                    StockMovementType::Wastage->value,
                    StockMovementType::Sale->value,
                ]
            )
            ->groupBy('stock_movements.batch_id', 'stock_movements.grade', 'stock_batches.received_at', 'stock_batches.id')
            ->having('available_quantity', '>', 0)
            ->orderBy('stock_batches.received_at')
            ->orderBy('stock_batches.id')
            ->orderBy('stock_movements.grade')
            ->toBase()
            ->get();
    }
}
