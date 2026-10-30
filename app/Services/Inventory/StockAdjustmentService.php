<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Repositories\Inventory\StockMovementRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly StockMovementRepository $stockMovements,
    ) {}

    public function reconcile(Product $product, float $submittedSystemQty, float $countedQty, string $businessDate, string $notes, int $userId, ?int $warehouseId = null, ?string $grade = null): ?StockAdjustment
    {
        return DB::transaction(function () use ($product, $submittedSystemQty, $countedQty, $businessDate, $notes, $userId, $warehouseId, $grade): ?StockAdjustment {
            StockBatch::query()->where('product_id', $product->id)->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))->lockForUpdate()->get(['id']);

            $gradeEnum = ProductGrade::tryResolve($grade);
            $normalizedGrade = $gradeEnum?->value;

            $systemQty = $gradeEnum !== null
                ? $this->stockMovements->stockForProductAndGrade($product->id, $gradeEnum, $warehouseId, $businessDate)
                : $this->stockMovements->currentStockForProduct($product->id, $warehouseId, $businessDate);

            if (abs($systemQty - $submittedSystemQty) > 0.01) {
                throw ValidationException::withMessages(['counted_qty' => 'Stock changed while you were counting. Refresh the page and try again.']);
            }

            $variance = round($countedQty - $systemQty, 3);
            if (abs($variance) < 0.001) {
                return null;
            }

            $category = $variance > 0 ? 'old_stock' : 'wastage';
            $adjustment = StockAdjustment::query()->create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'grade' => $normalizedGrade,
                'created_by' => $userId,
                'business_date' => Carbon::parse($businessDate)->toDateString(),
                'system_qty' => $systemQty,
                'counted_qty' => $countedQty,
                'variance_qty' => $variance,
                'category' => $category,
                'notes' => trim($notes),
            ]);

            $latestBatch = StockBatch::query()->where('product_id', $product->id)
                ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
                ->latest('received_at')
                ->latest('id')
                ->first();

            if (! $latestBatch) {
                $latestBatch = StockBatch::query()->create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId ?? $product->default_warehouse_id,
                    'created_by' => $userId,
                    'reference' => 'ADJ-'.now()->format('YmdHis').'-'.$product->id.'-'.$adjustment->id,
                    'received_at' => $businessDate,
                    'total_kg' => abs($variance),
                    'cost_per_kg' => 0.0,
                    'status' => BatchStatus::Sorted->value,
                    'warehouse_receive_pending' => false,
                    'sorted_at' => now(),
                    'notes' => 'Stock reconciliation baseline #'.$adjustment->id,
                ]);
            }

            $movementGrade = $normalizedGrade ?? ProductGrade::GradeA->value;
            StockMovement::query()->create([
                'product_id' => $product->id,
                'batch_id' => $latestBatch->id,
                'warehouse_id' => $warehouseId ?? $latestBatch->warehouse_id,
                'created_by' => $userId,
                'grade' => $movementGrade,
                'type' => $variance > 0 ? StockMovementType::In->value : StockMovementType::Adjustment->value,
                'quantity' => abs($variance),
                'cost_per_unit' => (float) $latestBatch->cost_per_kg,
                'notes' => ($variance > 0 ? 'Old stock adjustment' : 'Physical count adjustment').' #'.$adjustment->id.': '.trim($notes),
            ]);

            return $adjustment;
        });
    }

    /**
     * Preview the effects of emptying a warehouse without making any database changes.
     *
     * @return array{
     *     warehouse_id: int,
     *     warehouse_name: string,
     *     business_date: string,
     *     positive_lots_count: int,
     *     positive_stock_kg: float,
     *     pending_batches_count: int,
     *     pending_batches_kg: float,
     *     unconfirmed_receive_pending_batches_count: int,
     *     unconfirmed_receive_pending_batches_kg: float,
     *     negative_balance_lines_count: int,
     *     negative_balance_kg: float,
     *     estimated_adjustments_count: int,
     *     affected_products_count: int
     * }
     */
    public function previewEmptyWarehouse(Warehouse $warehouse, string $businessDate): array
    {
        $positiveTypes = [StockMovementType::In->value, StockMovementType::SaleReversal->value];
        $negativeTypes = [StockMovementType::Out->value, StockMovementType::Wastage->value, StockMovementType::Sale->value, StockMovementType::Adjustment->value];

        $lots = StockMovement::query()
            ->where('warehouse_id', $warehouse->id)
            ->selectRaw(
                'product_id, batch_id, grade, MAX(cost_per_unit) as cost_per_unit, '.
                'SUM(CASE WHEN type IN (?, ?) THEN quantity WHEN type IN (?, ?, ?, ?) THEN -quantity ELSE 0 END) as available_quantity',
                [...$positiveTypes, ...$negativeTypes],
            )
            ->groupBy('product_id', 'batch_id', 'grade')
            ->havingRaw('available_quantity > 0.0001')
            ->get();

        $pendingBatches = StockBatch::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('status', BatchStatus::Pending->value)
            ->where('warehouse_receive_pending', false)
            ->get();

        $pendingBatchesKg = 0.0;
        foreach ($pendingBatches as $batch) {
            $writtenOff = (float) StockMovement::query()
                ->where('batch_id', $batch->id)
                ->where('grade', ProductGrade::Unsorted->value)
                ->whereIn('type', [StockMovementType::Wastage->value, StockMovementType::Adjustment->value])
                ->sum('quantity');
            $availableQuantity = max(0.0, (float) $batch->remaining_qty - $writtenOff);

            if ($availableQuantity > 0.0001) {
                $pendingBatchesKg += $availableQuantity;
                $lots->push((object) [
                    'product_id' => $batch->product_id,
                    'batch_id' => $batch->id,
                    'grade' => ProductGrade::Unsorted->value,
                    'cost_per_unit' => $batch->cost_per_kg,
                    'available_quantity' => $availableQuantity,
                ]);
            }
        }

        $unconfirmedBatches = StockBatch::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('status', BatchStatus::Pending->value)
            ->where('warehouse_receive_pending', true)
            ->get();

        $currentStocks = $this->stockMovements->currentStockByProductAndGrade(
            date: $businessDate,
            warehouseId: $warehouse->id
        );

        $handledProductIds = $lots->pluck('product_id')->unique()->all();
        $negativeLines = [];
        $negativeTotalKg = 0.0;

        foreach ($currentStocks as $row) {
            $productId = (int) $row->product_id;
            if (in_array($productId, $handledProductIds, true)) {
                continue;
            }

            $currentQty = round((float) $row->current_stock, 3);
            if (abs($currentQty) > 0.0001) {
                $handledProductIds[] = $productId;
                if ($currentQty < 0) {
                    $negativeTotalKg += abs($currentQty);
                    $negativeLines[] = [
                        'product_id' => $productId,
                        'grade' => $row->grade,
                        'current_stock' => $currentQty,
                    ];
                }
            }
        }

        $affectedProductIds = array_unique(array_merge(
            $lots->pluck('product_id')->all(),
            $unconfirmedBatches->pluck('product_id')->all(),
            $handledProductIds
        ));

        return [
            'warehouse_id' => (int) $warehouse->id,
            'warehouse_name' => (string) $warehouse->name,
            'business_date' => $businessDate,
            'positive_lots_count' => $lots->count(),
            'positive_stock_kg' => round((float) $lots->sum('available_quantity'), 3),
            'pending_batches_count' => $pendingBatches->count(),
            'pending_batches_kg' => round($pendingBatchesKg, 3),
            'unconfirmed_receive_pending_batches_count' => $unconfirmedBatches->count(),
            'unconfirmed_receive_pending_batches_kg' => round((float) $unconfirmedBatches->sum('total_kg'), 3),
            'negative_balance_lines_count' => count($negativeLines),
            'negative_balance_kg' => round($negativeTotalKg, 3),
            'estimated_adjustments_count' => count($handledProductIds),
            'affected_products_count' => count($affectedProductIds),
        ];
    }

    public function emptyWarehouse(Warehouse $warehouse, string $businessDate, int $userId): int
    {
        return DB::transaction(function () use ($warehouse, $businessDate, $userId): int {
            $positiveTypes = [StockMovementType::In->value, StockMovementType::SaleReversal->value];
            $negativeTypes = [StockMovementType::Out->value, StockMovementType::Wastage->value, StockMovementType::Sale->value, StockMovementType::Adjustment->value];
            $lots = StockMovement::query()
                ->where('warehouse_id', $warehouse->id)
                ->selectRaw(
                    'product_id, batch_id, grade, MAX(cost_per_unit) as cost_per_unit, '.
                    'SUM(CASE WHEN type IN (?, ?) THEN quantity WHEN type IN (?, ?, ?, ?) THEN -quantity ELSE 0 END) as available_quantity',
                    [...$positiveTypes, ...$negativeTypes],
                )
                ->groupBy('product_id', 'batch_id', 'grade')
                ->havingRaw('available_quantity > 0.0001')
                ->lockForUpdate()
                ->get();

            $pendingBatches = StockBatch::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('status', BatchStatus::Pending->value)
                ->where('warehouse_receive_pending', false)
                ->lockForUpdate()
                ->get();

            foreach ($pendingBatches as $batch) {
                $writtenOff = (float) StockMovement::query()
                    ->where('batch_id', $batch->id)
                    ->where('grade', ProductGrade::Unsorted->value)
                    ->whereIn('type', [StockMovementType::Wastage->value, StockMovementType::Adjustment->value])
                    ->sum('quantity');
                $availableQuantity = max(0.0, (float) $batch->remaining_qty - $writtenOff);

                if ($availableQuantity > 0.0001) {
                    $lots->push((object) [
                        'product_id' => $batch->product_id,
                        'batch_id' => $batch->id,
                        'grade' => ProductGrade::Unsorted->value,
                        'cost_per_unit' => $batch->cost_per_kg,
                        'available_quantity' => $availableQuantity,
                    ]);
                }

                // Close pre-reset pending batch so it cannot be sorted post-reset
                $batch->update([
                    'status' => BatchStatus::Closed,
                    'notes' => trim(($batch->notes ?? '').' [Closed during warehouse reset baseline isolation]'),
                ]);
            }

            // Close pre-reset unconfirmed receive-pending batches so they cannot inject stock post-reset
            StockBatch::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('status', BatchStatus::Pending->value)
                ->where('warehouse_receive_pending', true)
                ->lockForUpdate()
                ->get()
                ->each(function (StockBatch $batch): void {
                    $batch->update([
                        'status' => BatchStatus::Closed,
                        'notes' => trim(($batch->notes ?? '').' [Closed unconfirmed batch during warehouse reset baseline isolation]'),
                    ]);
                });

            $adjustmentCount = 0;
            $handledProductIds = [];

            foreach ($lots->groupBy('product_id') as $productId => $productLots) {
                $systemQty = round((float) $productLots->sum('available_quantity'), 3);
                $adjustment = StockAdjustment::query()->create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouse->id,
                    'created_by' => $userId,
                    'business_date' => Carbon::parse($businessDate)->toDateString(),
                    'system_qty' => $systemQty,
                    'counted_qty' => 0,
                    'variance_qty' => -$systemQty,
                    'category' => 'wastage',
                    'notes' => 'Warehouse emptied before physical old-stock count.',
                ]);

                foreach ($productLots as $lot) {
                    StockMovement::query()->create([
                        'product_id' => $productId,
                        'batch_id' => $lot->batch_id,
                        'warehouse_id' => $warehouse->id,
                        'created_by' => $userId,
                        'grade' => $lot->grade,
                        'type' => StockMovementType::Wastage->value,
                        'quantity' => (float) $lot->available_quantity,
                        'cost_per_unit' => (float) $lot->cost_per_unit,
                        'notes' => 'Warehouse reset adjustment #'.$adjustment->id,
                    ]);
                }

                $handledProductIds[] = $productId;
                $adjustmentCount++;
            }

            // Also isolate any remaining products with negative or pending stock to zero baseline
            $currentStocks = $this->stockMovements->currentStockByProductAndGrade(
                date: $businessDate,
                warehouseId: $warehouse->id
            );

            foreach ($currentStocks as $row) {
                $productId = (int) $row->product_id;
                if (in_array($productId, $handledProductIds, true)) {
                    continue;
                }

                $currentQty = round((float) $row->current_stock, 3);
                if (abs($currentQty) > 0.0001) {
                    StockAdjustment::query()->create([
                        'product_id' => $productId,
                        'warehouse_id' => $warehouse->id,
                        'created_by' => $userId,
                        'business_date' => Carbon::parse($businessDate)->toDateString(),
                        'system_qty' => $currentQty,
                        'counted_qty' => 0,
                        'variance_qty' => -$currentQty,
                        'category' => $currentQty > 0 ? 'wastage' : 'negative_reconciliation',
                        'notes' => 'Warehouse reset baseline isolation to zero.',
                    ]);
                    $handledProductIds[] = $productId;
                    $adjustmentCount++;
                }
            }

            return $adjustmentCount;
        });
    }

    public function emptyProductInventory(int $productId, int $userId): bool
    {
        return DB::transaction(function () use ($productId, $userId): bool {
            $stockRows = $this->stockMovements->currentStockByProductAndGrade(null, null, null, '', $productId);
            if ($stockRows->isEmpty()) {
                return true;
            }

            $positiveTypes = [StockMovementType::In->value, StockMovementType::SaleReversal->value];
            $negativeTypes = [StockMovementType::Out->value, StockMovementType::Wastage->value, StockMovementType::Sale->value, StockMovementType::Adjustment->value];
            $lots = StockMovement::query()->where('product_id', $productId)
                ->where(function ($query): void {
                    $query->where('grade', '!=', ProductGrade::Unsorted->value)
                        ->orWhereDoesntHave('batch', fn ($batchQuery) => $batchQuery->where('status', BatchStatus::Pending->value));
                })
                ->whereDoesntHave('batch', fn ($batchQuery) => $batchQuery->where('warehouse_receive_pending', true))
                ->selectRaw('batch_id, warehouse_id, grade, MAX(cost_per_unit) as cost_per_unit, SUM(CASE WHEN type IN (?, ?) THEN quantity WHEN type IN (?, ?, ?, ?) THEN -quantity ELSE 0 END) as available_quantity', [...$positiveTypes, ...$negativeTypes])
                ->groupBy('batch_id', 'warehouse_id', 'grade')->havingRaw('available_quantity > 0.0001')->lockForUpdate()->get();
            $pending = StockBatch::query()->where('product_id', $productId)->where('status', BatchStatus::Pending->value)->where('warehouse_receive_pending', false)->lockForUpdate()->get();
            $batchIds = $pending->pluck('id');
            $wastageByBatch = WastageEntry::query()->whereIn('batch_id', $batchIds)->selectRaw('batch_id, SUM(quantity) as quantity')->groupBy('batch_id')->pluck('quantity', 'batch_id');
            $writeOffByBatch = StockMovement::query()->whereIn('batch_id', $batchIds)->whereIn('type', [
                StockMovementType::Out->value,
                StockMovementType::Sale->value,
                StockMovementType::Wastage->value,
                StockMovementType::Adjustment->value,
            ])->selectRaw('batch_id, SUM(quantity) as quantity')->groupBy('batch_id')->pluck('quantity', 'batch_id');

            foreach ($pending as $batch) {
                $wasted = (float) ($wastageByBatch[$batch->id] ?? 0.0);
                $writtenOff = (float) ($writeOffByBatch[$batch->id] ?? 0.0);
                $available = max(0.0, (float) $batch->total_kg - $wasted - $writtenOff);
                if ($available > 0.0001) {
                    $lots->push((object) [
                        'batch_id' => $batch->id,
                        'warehouse_id' => $batch->warehouse_id,
                        'grade' => ProductGrade::Unsorted->value,
                        'cost_per_unit' => $batch->cost_per_kg,
                        'available_quantity' => $available,
                    ]);

                    WastageEntry::create([
                        'product_id' => $productId,
                        'batch_id' => $batch->id,
                        'recorded_by' => $userId,
                        'grade' => ProductGrade::Unsorted->value,
                        'quantity' => $available,
                        'cost_per_kg' => (float) $batch->cost_per_kg,
                        'reason' => WastageReason::Unsold->value,
                        'wastage_date' => now()->toDateString(),
                        'notes' => 'Global inventory empty process: balance reset to zero',
                    ]);
                }

                // Close pending batch so it is not counted in unsorted stock
                $batch->update([
                    'status' => BatchStatus::Closed,
                    'notes' => trim(($batch->notes ?? '').' [Closed during inventory empty process]'),
                ]);
            }

            // Close unconfirmed receive-pending batches for this product so they do not inject stock post-reset
            StockBatch::query()
                ->where('product_id', $productId)
                ->where('status', BatchStatus::Pending->value)
                ->where('warehouse_receive_pending', true)
                ->lockForUpdate()
                ->get()
                ->each(function (StockBatch $batch): void {
                    $batch->update([
                        'status' => BatchStatus::Closed,
                        'notes' => trim(($batch->notes ?? '').' [Closed unconfirmed batch during inventory empty process]'),
                    ]);
                });

            foreach ($lots as $lot) {
                $availableQuantity = (float) $lot->available_quantity;
                if ($availableQuantity <= 0.0001) {
                    continue;
                }
                StockMovement::create([
                    'product_id' => $productId,
                    'batch_id' => $lot->batch_id,
                    'warehouse_id' => $lot->warehouse_id,
                    'created_by' => $userId,
                    'grade' => $lot->grade,
                    'type' => StockMovementType::Wastage->value,
                    'quantity' => $availableQuantity,
                    'cost_per_unit' => (float) $lot->cost_per_unit,
                    'notes' => 'Global inventory empty process: balance reset to zero',
                ]);
            }

            // Create baseline StockAdjustment records for all warehouses and grades where the product had stock
            foreach ($stockRows as $row) {
                $systemQty = round((float) $row->current_stock, 3);
                $whId = $row->warehouse_id ?? null;
                $rowGrade = $row->grade ?? null;

                StockAdjustment::query()->create([
                    'product_id' => $productId,
                    'warehouse_id' => $whId,
                    'grade' => $rowGrade,
                    'created_by' => $userId,
                    'business_date' => now()->toDateString(),
                    'system_qty' => $systemQty,
                    'counted_qty' => 0,
                    'variance_qty' => -$systemQty,
                    'category' => $systemQty >= 0 ? 'wastage' : 'negative_reconciliation',
                    'notes' => 'Global inventory empty process: baseline reset to zero',
                ]);
            }

            return true;
        });
    }
}
