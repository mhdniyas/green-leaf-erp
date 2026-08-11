<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockLedgerService
{
    public function availableSortedStockForProduct(int $productId, ?int $warehouseId = null): float
    {
        $lots = $this->sortedLotsForProduct($productId, $warehouseId);

        return (float) $lots->sum('available_quantity');
    }

    public function availableStockForProduct(int $productId, ?int $warehouseId = null): float
    {
        $sortedAvailable = $this->availableSortedStockForProduct($productId, $warehouseId);

        $unsortedAvailable = $this->pendingBatchesForProduct($productId, $warehouseId)
            ->sum(fn (StockBatch $batch): float => $this->pendingBatchAvailableQuantity($batch));

        return round($sortedAvailable + $unsortedAvailable, 3);
    }

    public function consumeSortedStockForProduct(
        int $productId,
        float $quantity,
        int $userId,
        StockMovementType $movementType,
        string $notes,
        ?int $shopOrderItemId = null,
        ?int $warehouseId = null,
    ): float {
        if ($quantity <= 0.0) {
            return 0.0;
        }

        return DB::transaction(function () use ($productId, $quantity, $userId, $movementType, $notes, $shopOrderItemId, $warehouseId): float {
            $this->lockStockBatchesForProduct($productId, $warehouseId);

            $remainingQuantity = $quantity;
            $consumedQuantity = 0.0;

            [$remainingQuantity, $consumedQuantity] = $this->consumeSortedLots(
                $productId,
                $remainingQuantity,
                $consumedQuantity,
                $userId,
                $movementType,
                $notes,
                $shopOrderItemId,
                $warehouseId,
            );

            if ($remainingQuantity > 0.0) {
                [$remainingQuantity, $consumedQuantity] = $this->consumePendingBatches(
                    $productId,
                    $remainingQuantity,
                    $consumedQuantity,
                    $userId,
                    $movementType,
                    $notes,
                    $shopOrderItemId,
                    $warehouseId,
                );
            }

            return round($consumedQuantity, 3);
        });
    }

    public function consumeStockForProductAllowingNegative(
        int $productId,
        float $quantity,
        int $userId,
        StockMovementType $movementType,
        string $notes,
        ?int $shopOrderItemId = null,
    ): float {
        if ($quantity <= 0.0) {
            return 0.0;
        }

        return DB::transaction(function () use ($productId, $quantity, $userId, $movementType, $notes, $shopOrderItemId): float {
            $this->lockStockBatchesForProduct($productId);

            $remainingQuantity = $quantity;
            $consumedQuantity = 0.0;

            [$remainingQuantity, $consumedQuantity] = $this->consumeSortedLots(
                $productId,
                $remainingQuantity,
                $consumedQuantity,
                $userId,
                $movementType,
                $notes,
                $shopOrderItemId,
            );

            if ($remainingQuantity > 0.0) {
                [$remainingQuantity, $consumedQuantity] = $this->consumePendingBatches(
                    $productId,
                    $remainingQuantity,
                    $consumedQuantity,
                    $userId,
                    $movementType,
                    $notes,
                    $shopOrderItemId,
                );
            }

            if ($remainingQuantity <= 0.0) {
                return round($consumedQuantity, 3);
            }

            $batch = $this->negativeStockBatchForProduct($productId, $userId);

            StockMovement::create([
                'product_id' => $productId,
                'batch_id' => $batch->id,
                'created_by' => $userId,
                'grade' => ProductGrade::GradeA->value,
                'type' => $movementType->value,
                'quantity' => $remainingQuantity,
                'cost_per_unit' => (float) $batch->cost_per_kg,
                'warehouse_id' => $batch->warehouse_id,
                'shop_order_item_id' => $shopOrderItemId,
                'notes' => $notes.'; negative stock allowed',
            ]);

            return round($quantity, 3);
        });
    }

    /**
     * @return Collection<int, object>
     */
    private function sortedLotsForProduct(int $productId, ?int $warehouseId = null): Collection
    {
        return StockMovement::query()
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_movements.batch_id')
            ->where('stock_movements.product_id', $productId)
            ->whereNull('stock_batches.deleted_at')
            ->where('stock_batches.status', BatchStatus::Sorted->value)
            ->when($warehouseId !== null, fn ($query) => $query->where('stock_batches.warehouse_id', $warehouseId))
            ->selectRaw(
                'stock_movements.batch_id, stock_movements.grade, '.
                'MAX(stock_movements.cost_per_unit) as cost_per_unit, '.
                'stock_batches.warehouse_id, '.
                'SUM(CASE '.
                'WHEN stock_movements.type IN (?, ?) THEN stock_movements.quantity '.
                'WHEN stock_movements.type IN (?, ?, ?, ?) THEN -stock_movements.quantity '.
                'ELSE 0 END) as available_quantity',
                [
                    StockMovementType::In->value,
                    StockMovementType::SaleReversal->value,
                    StockMovementType::Out->value,
                    StockMovementType::Wastage->value,
                    StockMovementType::Sale->value,
                    StockMovementType::Adjustment->value,
                ]
            )
            ->groupBy('stock_movements.batch_id', 'stock_movements.grade', 'stock_batches.received_at', 'stock_batches.id', 'stock_batches.warehouse_id')
            ->having('available_quantity', '>', 0)
            ->orderBy('stock_batches.received_at')
            ->orderBy('stock_batches.id')
            ->orderBy('stock_movements.grade')
            ->toBase()
            ->get();
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function consumeSortedLots(
        int $productId,
        float $remainingQuantity,
        float $consumedQuantity,
        int $userId,
        StockMovementType $movementType,
        string $notes,
        ?int $shopOrderItemId = null,
        ?int $warehouseId = null,
    ): array {
        foreach ($this->sortedLotsForProduct($productId, $warehouseId) as $lot) {
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
                'warehouse_id' => $lot->warehouse_id ? (int) $lot->warehouse_id : null,
                'shop_order_item_id' => $shopOrderItemId,
                'notes' => $notes,
            ]);

            $remainingQuantity -= $deductionQuantity;
            $consumedQuantity += $deductionQuantity;
        }

        return [$remainingQuantity, $consumedQuantity];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function consumePendingBatches(
        int $productId,
        float $remainingQuantity,
        float $consumedQuantity,
        int $userId,
        StockMovementType $movementType,
        string $notes,
        ?int $shopOrderItemId = null,
        ?int $warehouseId = null,
    ): array {
        foreach ($this->pendingBatchesForProduct($productId, $warehouseId) as $batch) {
            if ($remainingQuantity <= 0.0) {
                break;
            }

            $availableQuantity = $this->pendingBatchAvailableQuantity($batch);

            if ($availableQuantity <= 0.0) {
                continue;
            }

            $deductionQuantity = min($remainingQuantity, $availableQuantity);

            StockMovement::create([
                'product_id' => $productId,
                'batch_id' => $batch->id,
                'created_by' => $userId,
                'grade' => ProductGrade::Unsorted->value,
                'type' => $movementType->value,
                'quantity' => $deductionQuantity,
                'cost_per_unit' => (float) $batch->cost_per_kg,
                'warehouse_id' => $batch->warehouse_id,
                'shop_order_item_id' => $shopOrderItemId,
                'notes' => $notes,
            ]);

            $remainingQuantity -= $deductionQuantity;
            $consumedQuantity += $deductionQuantity;
        }

        return [$remainingQuantity, $consumedQuantity];
    }

    /**
     * @return Collection<int, StockBatch>
     */
    private function pendingBatchesForProduct(int $productId, ?int $warehouseId = null): Collection
    {
        return StockBatch::query()
            ->where('product_id', $productId)
            ->where('status', BatchStatus::Pending)
            ->where('warehouse_receive_pending', false)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }

    private function pendingBatchAvailableQuantity(StockBatch $batch): float
    {
        // Pending-batch remaining_qty is the established source for dispatches.
        // Only physical write-offs are absent from that accessor, so subtract
        // those here. This must match the quantity shown on the stock card.
        $writeOffQuantity = (float) StockMovement::query()
            ->where('batch_id', $batch->id)
            ->where('grade', ProductGrade::Unsorted->value)
            ->whereIn('type', [
                StockMovementType::Wastage->value,
                StockMovementType::Adjustment->value,
            ])
            ->sum('quantity');

        return round(max(0.0, (float) $batch->remaining_qty - $writeOffQuantity), 3);
    }

    private function negativeStockBatchForProduct(int $productId, int $userId): StockBatch
    {
        $latestBatch = StockBatch::query()
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->latest('received_at')
            ->latest('id')
            ->first();

        if ($latestBatch) {
            return $latestBatch;
        }

        $reference = 'NEG-'.Carbon::today()->format('Ymd').'-'.$productId;

        return StockBatch::query()->firstOrCreate(
            ['reference' => $reference],
            [
                'product_id' => $productId,
                'created_by' => $userId,
                'received_at' => Carbon::today()->toDateString(),
                'total_kg' => 0,
                'cost_per_kg' => 0,
                'transport_cost' => 0,
                'labour_cost' => 0,
                'status' => BatchStatus::Sorted->value,
                'warehouse_receive_pending' => false,
                'warehouse_confirmed_at' => now(),
                'warehouse_confirmed_by' => $userId,
                'notes' => 'System batch for negative stock adjustment.',
                'sorted_at' => now(),
            ],
        );
    }

    private function lockStockBatchesForProduct(int $productId, ?int $warehouseId = null): void
    {
        StockBatch::query()
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');
    }
}
