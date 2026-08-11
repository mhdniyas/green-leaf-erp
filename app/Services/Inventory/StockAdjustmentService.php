<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
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

    public function reconcile(Product $product, float $submittedSystemQty, float $countedQty, string $businessDate, string $notes, int $userId, ?int $warehouseId = null): ?StockAdjustment
    {
        return DB::transaction(function () use ($product, $submittedSystemQty, $countedQty, $businessDate, $notes, $userId, $warehouseId): ?StockAdjustment {
            StockBatch::query()->where('product_id', $product->id)->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))->lockForUpdate()->get(['id']);

            $systemQty = $this->stockMovements->currentStockForProduct($product->id, $warehouseId, $businessDate);
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
                'created_by' => $userId,
                'business_date' => Carbon::parse($businessDate)->toDateString(),
                'system_qty' => $systemQty,
                'counted_qty' => $countedQty,
                'variance_qty' => $variance,
                'category' => $category,
                'notes' => trim($notes),
            ]);

            if ($variance > 0) {
                $latestBatch = StockBatch::query()->where('product_id', $product->id)->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))->latest('received_at')->latest('id')->first();
                $batch = StockBatch::query()->create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId ?? $latestBatch?->warehouse_id,
                    'created_by' => $userId,
                    'reference' => 'ADJ-'.now()->format('YmdHis').'-'.$product->id.'-'.$adjustment->id,
                    'received_at' => $businessDate,
                    'total_kg' => $variance,
                    'cost_per_kg' => (float) ($latestBatch?->cost_per_kg ?? 0),
                    'status' => BatchStatus::Sorted->value,
                    'warehouse_receive_pending' => false,
                    'sorted_at' => now(),
                    'notes' => 'Old stock reconciliation #'.$adjustment->id,
                ]);

                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'warehouse_id' => $batch->warehouse_id,
                    'created_by' => $userId,
                    'grade' => ProductGrade::GradeA->value,
                    'type' => StockMovementType::In->value,
                    'quantity' => $variance,
                    'cost_per_unit' => (float) $batch->cost_per_kg,
                    'notes' => 'Old stock adjustment #'.$adjustment->id.': '.trim($notes),
                ]);
            } else {
                $consumed = $this->ledger->consumeSortedStockForProduct(
                    $product->id,
                    abs($variance),
                    $userId,
                    StockMovementType::Wastage,
                    'Physical count wastage adjustment #'.$adjustment->id.': '.trim($notes),
                    warehouseId: $warehouseId,
                );

                if (abs($consumed - abs($variance)) > 0.001) {
                    throw ValidationException::withMessages(['counted_qty' => 'The physical count is lower than the stock currently available. Refresh and retry.']);
                }
            }

            return $adjustment;
        });
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
            }

            $adjustmentCount = 0;
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

                $adjustmentCount++;
            }

            return $adjustmentCount;
        });
    }

    public function emptyProductInventory(int $productId, int $userId): bool
    {
        return DB::transaction(function () use ($productId, $userId): bool {
            $positiveTypes = [StockMovementType::In->value, StockMovementType::SaleReversal->value];
            $negativeTypes = [StockMovementType::Out->value, StockMovementType::Wastage->value, StockMovementType::Sale->value, StockMovementType::Adjustment->value];
            $lots = StockMovement::query()->where('product_id', $productId)
                ->selectRaw('batch_id, warehouse_id, grade, MAX(cost_per_unit) as cost_per_unit, SUM(CASE WHEN type IN (?, ?) THEN quantity WHEN type IN (?, ?, ?, ?) THEN -quantity ELSE 0 END) as available_quantity', [...$positiveTypes, ...$negativeTypes])
                ->groupBy('batch_id', 'warehouse_id', 'grade')->havingRaw('ABS(available_quantity) > 0.0001')->lockForUpdate()->get();
            $pending = StockBatch::query()->where('product_id', $productId)->where('status', BatchStatus::Pending->value)->where('warehouse_receive_pending', false)->lockForUpdate()->get();
            foreach ($pending as $batch) {
                $writtenOff = (float) StockMovement::query()->where('batch_id', $batch->id)->where('grade', ProductGrade::Unsorted->value)->whereIn('type', [StockMovementType::Wastage->value, StockMovementType::Adjustment->value])->sum('quantity');
                $available = max(0.0, (float) $batch->remaining_qty - $writtenOff);
                if ($available > 0.0001) $lots->push((object) ['batch_id'=>$batch->id,'warehouse_id'=>$batch->warehouse_id,'grade'=>ProductGrade::Unsorted->value,'cost_per_unit'=>$batch->cost_per_kg,'available_quantity'=>$available]);
            }
            if ($lots->isEmpty()) return false;
            foreach ($lots as $lot) {
                $availableQuantity = (float) $lot->available_quantity;
                StockMovement::create([
                    'product_id'=>$productId,
                    'batch_id'=>$lot->batch_id,
                    'warehouse_id'=>$lot->warehouse_id,
                    'created_by'=>$userId,
                    'grade'=>$lot->grade,
                    'type'=>$availableQuantity > 0 ? StockMovementType::Wastage->value : StockMovementType::In->value,
                    'quantity'=>abs($availableQuantity),
                    'cost_per_unit'=>(float)$lot->cost_per_unit,
                    'notes'=>'Global inventory empty process: balance reset to zero',
                ]);
            }
            return true;
        });
    }
}
