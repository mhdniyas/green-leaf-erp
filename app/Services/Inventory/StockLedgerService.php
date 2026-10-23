<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\WastageEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockLedgerService
{
    public function availableSortedStockForProduct(int $productId, ?int $warehouseId = null, ?ProductGrade $grade = null): float
    {
        $lots = $this->sortedLotsForProduct($productId, $warehouseId, $grade);

        return (float) $lots->sum('available_quantity');
    }

    /**
     * Bulk version of availableSortedStockForProduct.
     * Returns a map of [product_id => available_quantity] for all given product IDs.
     * Uses a single aggregated query instead of one per product.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, float> Keys are product IDs, values are total available sorted stock.
     */
    public function availableSortedStockForProducts(array $productIds, ?int $warehouseId = null): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = StockMovement::query()
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_movements.batch_id')
            ->whereIn('stock_movements.product_id', $productIds)
            ->whereNull('stock_batches.deleted_at')
            ->where('stock_batches.status', BatchStatus::Sorted->value)
            ->where('stock_batches.warehouse_receive_pending', false)
            ->when($warehouseId !== null, fn ($query) => $query->where('stock_batches.warehouse_id', $warehouseId))
            ->selectRaw(
                'stock_movements.product_id, '.
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
            ->groupBy('stock_movements.product_id')
            ->having('available_quantity', '>', 0)
            ->toBase()
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->product_id] = (float) $row->available_quantity;
        }

        return $result;
    }

    public function availableStockForProduct(int $productId, ?int $warehouseId = null, ?ProductGrade $grade = null): float
    {
        $sortedAvailable = $this->availableSortedStockForProduct($productId, $warehouseId, $grade);

        $unsortedAvailable = $this->pendingBatchesForProduct($productId, $warehouseId, $grade)
            ->sum(fn (StockBatch $batch): float => $this->pendingBatchAvailableQuantity($batch));

        return round($sortedAvailable + $unsortedAvailable, 3);
    }

    /**
     * Calculate available stock for a loadout line with Grade-first / Mixed-second fallback.
     * Returns an array with breakdown: grade_stock, mixed_stock, and total available.
     *
     * @return array{grade_stock: float, mixed_stock: float, available_stock: float}
     */
    public function availableLoadoutStockBreakdown(int $productId, ?int $warehouseId = null, ?ProductGrade $grade = null): array
    {
        $resolvedGrade = $grade ?? ProductGrade::GradeA;
        $gradeStock = $this->availableStockForProduct($productId, $warehouseId, $resolvedGrade);

        $mixedStock = 0.0;
        if ($resolvedGrade !== ProductGrade::Unsorted) {
            $mixedStock = $this->availableStockForProduct($productId, $warehouseId, ProductGrade::Unsorted);
        }

        return [
            'grade_stock' => round($gradeStock, 3),
            'mixed_stock' => round($mixedStock, 3),
            'available_stock' => round($gradeStock + $mixedStock, 3),
        ];
    }

    public function consumeSortedStockForProduct(
        int $productId,
        float $quantity,
        int $userId,
        StockMovementType $movementType,
        string $notes,
        ?int $shopOrderItemId = null,
        ?int $warehouseId = null,
        ?ProductGrade $grade = null,
    ): float {
        if ($quantity <= 0.0) {
            return 0.0;
        }

        $targetGrade = $grade ?? ProductGrade::GradeA;

        return DB::transaction(function () use ($productId, $quantity, $userId, $movementType, $notes, $shopOrderItemId, $warehouseId, $targetGrade): float {
            $this->lockStockBatchesForProduct($productId, $warehouseId, $targetGrade);
            if ($targetGrade !== ProductGrade::Unsorted) {
                $this->lockStockBatchesForProduct($productId, $warehouseId, ProductGrade::Unsorted);
            }

            $remainingQuantity = $quantity;
            $consumedQuantity = 0.0;

            // 1. Consume available stock of approved grade first
            [$remainingQuantity, $consumedQuantity] = $this->consumeSortedLots(
                $productId,
                $remainingQuantity,
                $consumedQuantity,
                $userId,
                $movementType,
                $notes,
                $shopOrderItemId,
                $warehouseId,
                $targetGrade,
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
                    $targetGrade,
                );
            }

            // 2. Consume remaining from Mixed / Unsorted ('U')
            if ($remainingQuantity > 0.0 && $targetGrade !== ProductGrade::Unsorted) {
                [$remainingQuantity, $consumedQuantity] = $this->consumeSortedLots(
                    $productId,
                    $remainingQuantity,
                    $consumedQuantity,
                    $userId,
                    $movementType,
                    $notes,
                    $shopOrderItemId,
                    $warehouseId,
                    ProductGrade::Unsorted,
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
                        ProductGrade::Unsorted,
                    );
                }
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
        ?int $warehouseId = null,
        ?ProductGrade $grade = null,
    ): float {
        if ($quantity <= 0.0) {
            return 0.0;
        }

        $targetGrade = $grade ?? ProductGrade::GradeA;

        return DB::transaction(function () use ($productId, $quantity, $userId, $movementType, $notes, $shopOrderItemId, $warehouseId, $targetGrade): float {
            $this->lockStockBatchesForProduct($productId, $warehouseId, $targetGrade);
            if ($targetGrade !== ProductGrade::Unsorted) {
                $this->lockStockBatchesForProduct($productId, $warehouseId, ProductGrade::Unsorted);
            }

            $remainingQuantity = $quantity;
            $consumedQuantity = 0.0;

            // 1. Consume available stock of approved grade first (sorted lots, then pending batches)
            [$remainingQuantity, $consumedQuantity] = $this->consumeSortedLots(
                $productId,
                $remainingQuantity,
                $consumedQuantity,
                $userId,
                $movementType,
                $notes,
                $shopOrderItemId,
                $warehouseId,
                $targetGrade,
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
                    $targetGrade,
                );
            }

            // 2. Consume remaining required quantity from Mixed / Unsorted ('U')
            if ($remainingQuantity > 0.0 && $targetGrade !== ProductGrade::Unsorted) {
                [$remainingQuantity, $consumedQuantity] = $this->consumeSortedLots(
                    $productId,
                    $remainingQuantity,
                    $consumedQuantity,
                    $userId,
                    $movementType,
                    $notes,
                    $shopOrderItemId,
                    $warehouseId,
                    ProductGrade::Unsorted,
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
                        ProductGrade::Unsorted,
                    );
                }
            }

            if ($remainingQuantity <= 0.0) {
                return round($consumedQuantity, 3);
            }

            // 3. Document remaining shortfall negative quantity under original approved grade
            $batch = $this->negativeStockBatchForProduct($productId, $userId, $warehouseId, $targetGrade);

            StockMovement::create([
                'product_id' => $productId,
                'batch_id' => $batch->id,
                'created_by' => $userId,
                'grade' => $targetGrade->value,
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
     * Reverse previously deducted stock for a shop order item back to its original physical sources.
     * Conserves stock by returning quantity to the exact physical pools/batches and grades deducted,
     * in LIFO order (latest deduction reversed first).
     */
    public function reverseStockForOrderItem(
        int $shopOrderItemId,
        float $quantityToReverse,
        int $userId,
        string $notes,
    ): float {
        if ($quantityToReverse <= 0.0) {
            return 0.0;
        }

        return DB::transaction(function () use ($shopOrderItemId, $quantityToReverse, $userId, $notes): float {
            $item = ShopOrderItem::find($shopOrderItemId);

            // Find Out movements associated with this shop_order_item_id
            $outMovements = StockMovement::query()
                ->where('shop_order_item_id', $shopOrderItemId)
                ->where('type', StockMovementType::Out->value)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            // Fallback for legacy movements without shop_order_item_id
            if ($outMovements->isEmpty() && $item && $item->order) {
                $orderNumber = $item->order->order_number;
                $outMovements = StockMovement::query()
                    ->where('product_id', $item->product_id)
                    ->where('type', StockMovementType::Out->value)
                    ->where('notes', 'like', "%Order: {$orderNumber}%")
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->get();
            }

            // Calculate already reversed quantities per batch_id and grade
            $existingReversals = StockMovement::query()
                ->where('shop_order_item_id', $shopOrderItemId)
                ->where('type', StockMovementType::SaleReversal->value)
                ->get();

            $reversedByBatchAndGrade = [];
            foreach ($existingReversals as $rev) {
                $revGrade = ProductGrade::tryResolve($rev->grade)?->value ?? (string) ($rev->grade ?? 'A');
                $key = ((int) $rev->batch_id).'|'.$revGrade;
                $reversedByBatchAndGrade[$key] = ($reversedByBatchAndGrade[$key] ?? 0.0) + (float) $rev->quantity;
            }

            $remainingToReverse = $quantityToReverse;
            $totalReversed = 0.0;

            foreach ($outMovements as $source) {
                if ($remainingToReverse <= 0.0001) {
                    break;
                }

                $sourceGrade = ProductGrade::tryResolve($source->grade)?->value ?? (string) ($source->grade ?? 'A');
                $key = ((int) $source->batch_id).'|'.$sourceGrade;
                $alreadyReversedForSource = (float) ($reversedByBatchAndGrade[$key] ?? 0.0);
                $netAvailableToReverse = max(0.0, (float) $source->quantity - $alreadyReversedForSource);

                if ($netAvailableToReverse <= 0.0001) {
                    continue;
                }

                $reversalAmount = min($remainingToReverse, $netAvailableToReverse);

                StockMovement::create([
                    'batch_id' => $source->batch_id,
                    'product_id' => $source->product_id,
                    'created_by' => $userId,
                    'grade' => $sourceGrade,
                    'type' => StockMovementType::SaleReversal->value,
                    'quantity' => $reversalAmount,
                    'cost_per_unit' => (float) $source->cost_per_unit,
                    'warehouse_id' => $source->warehouse_id,
                    'shop_order_item_id' => $shopOrderItemId,
                    'notes' => $notes,
                ]);

                $reversedByBatchAndGrade[$key] = $alreadyReversedForSource + $reversalAmount;
                $remainingToReverse = round($remainingToReverse - $reversalAmount, 3);
                $totalReversed = round($totalReversed + $reversalAmount, 3);
            }

            // Fallback for any unaccounted leftover reversal
            if ($remainingToReverse > 0.0001 && $item) {
                $itemGrade = ProductGrade::tryResolve($item->product_grade) ?? ProductGrade::GradeA;
                $fallbackBatch = StockBatch::query()
                    ->where('product_id', $item->product_id)
                    ->when($item->product?->default_warehouse_id, fn ($q, $wh) => $q->where('warehouse_id', $wh))
                    ->latest('id')
                    ->first();

                if ($fallbackBatch) {
                    StockMovement::create([
                        'batch_id' => $fallbackBatch->id,
                        'product_id' => $item->product_id,
                        'created_by' => $userId,
                        'grade' => $itemGrade->value,
                        'type' => StockMovementType::SaleReversal->value,
                        'quantity' => $remainingToReverse,
                        'cost_per_unit' => (float) $fallbackBatch->cost_per_kg,
                        'warehouse_id' => $fallbackBatch->warehouse_id,
                        'shop_order_item_id' => $shopOrderItemId,
                        'notes' => $notes,
                    ]);
                    $totalReversed = round($totalReversed + $remainingToReverse, 3);
                }
            }

            return $totalReversed;
        });
    }

    /**
     * @return Collection<int, object>
     */
    private function sortedLotsForProduct(int $productId, ?int $warehouseId = null, ?ProductGrade $grade = null): Collection
    {
        return StockMovement::query()
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_movements.batch_id')
            ->where('stock_movements.product_id', $productId)
            ->whereNull('stock_batches.deleted_at')
            ->where('stock_batches.status', BatchStatus::Sorted->value)
            ->where('stock_batches.warehouse_receive_pending', false)
            ->when($warehouseId !== null, fn ($query) => $query->where('stock_batches.warehouse_id', $warehouseId))
            ->when($grade !== null, function ($query) use ($grade) {
                if ($grade === ProductGrade::Unsorted) {
                    $query->whereIn('stock_movements.grade', ['U', 'MIXED', 'UNSORTED']);
                } else {
                    $query->where('stock_movements.grade', $grade->value);
                }
            })
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
        ?ProductGrade $grade = null,
    ): array {
        foreach ($this->sortedLotsForProduct($productId, $warehouseId, $grade) as $lot) {
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
        ?ProductGrade $grade = null,
    ): array {
        foreach ($this->pendingBatchesForProduct($productId, $warehouseId, $grade) as $batch) {
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
                'grade' => $grade?->value ?? ProductGrade::Unsorted->value,
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
    private function pendingBatchesForProduct(int $productId, ?int $warehouseId = null, ?ProductGrade $grade = null): Collection
    {
        return StockBatch::query()
            ->where('product_id', $productId)
            ->where('status', BatchStatus::Pending)
            ->where('warehouse_receive_pending', false)
            ->when($grade !== null, function ($query) use ($grade) {
                if ($grade === ProductGrade::Unsorted) {
                    $query->where(function ($q) {
                        $q->whereIn('purchase_grade', ['U', 'MIXED', 'UNSORTED'])
                            ->orWhereNull('purchase_grade');
                    });
                } else {
                    $query->where('purchase_grade', $grade->value);
                }
            })
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }

    private function pendingBatchAvailableQuantity(StockBatch $batch): float
    {
        // Pending-batch remaining_qty is the established source for dispatches.
        // Subtract net write-offs (Out, Sale, Wastage, Adjustment minus SaleReversal).
        $writeOffQuantity = (float) StockMovement::query()
            ->where('batch_id', $batch->id)
            ->whereIn('type', [
                StockMovementType::Out->value,
                StockMovementType::Sale->value,
                StockMovementType::Wastage->value,
                StockMovementType::Adjustment->value,
            ])
            ->sum('quantity');

        $reversalQuantity = (float) StockMovement::query()
            ->where('batch_id', $batch->id)
            ->where('type', StockMovementType::SaleReversal->value)
            ->sum('quantity');

        $netWriteOff = max(0.0, $writeOffQuantity - $reversalQuantity);

        return round(max(0.0, (float) $batch->remaining_qty - $netWriteOff), 3);
    }

    private function negativeStockBatchForProduct(int $productId, int $userId, ?int $warehouseId = null, ?ProductGrade $grade = null): StockBatch
    {
        $latestBatch = StockBatch::query()
            ->where('product_id', $productId)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($grade !== null, function ($query) use ($grade) {
                if ($grade === ProductGrade::Unsorted) {
                    $query->where(function ($q) {
                        $q->whereIn('purchase_grade', ['U', 'MIXED', 'UNSORTED'])
                            ->orWhereNull('purchase_grade');
                    });
                } else {
                    $query->where('purchase_grade', $grade->value);
                }
            })
            ->lockForUpdate()
            ->latest('received_at')
            ->latest('id')
            ->first();

        if ($latestBatch) {
            return $latestBatch;
        }

        $reference = 'NEG-'.Carbon::today()->format('Ymd').'-'.$productId.'-'.($grade?->value ?? 'A').($warehouseId !== null ? '-W'.$warehouseId : '');

        return StockBatch::query()->firstOrCreate(
            ['reference' => $reference],
            [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
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
                'purchase_grade' => $grade?->value ?? 'A',
                'grading_mode' => 'fixed_purchase_grade',
                'sorted_at' => now(),
            ],
        );
    }

    private function lockStockBatchesForProduct(int $productId, ?int $warehouseId = null, ?ProductGrade $grade = null): void
    {
        StockBatch::query()
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($grade !== null, function ($query) use ($grade) {
                if ($grade === ProductGrade::Unsorted) {
                    $query->where(function ($q) {
                        $q->whereIn('purchase_grade', ['U', 'MIXED', 'UNSORTED'])
                            ->orWhereNull('purchase_grade');
                    });
                } else {
                    $query->where('purchase_grade', $grade->value);
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');
    }

    /**
     * Reconcile inventory per product, grade, and warehouse according to the canonical formula:
     * Opening + Receipts + Transfers In - Transfers Out - Physical Consumption - Damage +- Adjustments = Closing
     *
     * Accounts separately for automatic grade classification during loadout so internal grade
     * transfers are not counted as additional physical receipts or physical consumption.
     *
     * @return array{
     *     product_id: int,
     *     warehouse_id: int,
     *     by_grade: array<string, array{
     *         grade: string,
     *         opening: float,
     *         receipts: float,
     *         transfers_in: float,
     *         transfers_out: float,
     *         physical_consumption: float,
     *         damage: float,
     *         adjustments: float,
     *         formula_closing: float,
     *         available_stock: float,
     *         reconciled: bool
     *     }>,
     *     total: array{
     *         opening: float,
     *         receipts: float,
     *         transfers_in: float,
     *         transfers_out: float,
     *         physical_consumption: float,
     *         damage: float,
     *         adjustments: float,
     *         formula_closing: float,
     *         physical_available: float,
     *         reconciled: bool
     *     },
     *     is_fully_reconciled: bool
     * }
     */
    public function reconcileInventory(int $productId, int $warehouseId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $grades = ['A', 'B', 'C', 'D', 'U'];
        $byGrade = [];

        $totalOpening = 0.0;
        $totalReceipts = 0.0;
        $totalTransfersIn = 0.0;
        $totalTransfersOut = 0.0;
        $totalConsumption = 0.0;
        $totalDamage = 0.0;
        $totalAdjustments = 0.0;

        foreach ($grades as $gradeStr) {
            $gradeEnum = ProductGrade::tryResolve($gradeStr);

            // 1. Opening
            $opening = 0.0; // isolated evaluation or single-period

            // 2. Receipts (initial confirmed batches from vendor/direct)
            $receiptsQuery = StockBatch::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('warehouse_receive_pending', false)
                ->whereNull('parent_batch_id')
                ->when($fromDate !== null, fn ($q) => $q->whereDate('received_at', '>=', $fromDate))
                ->when($toDate !== null, fn ($q) => $q->whereDate('received_at', '<=', $toDate));

            if ($gradeStr === 'U') {
                $receiptsQuery->where(function ($q) {
                    $q->whereIn('purchase_grade', ['U', 'MIXED', 'UNSORTED'])
                        ->orWhereNull('purchase_grade');
                });
            } else {
                $receiptsQuery->where('purchase_grade', $gradeStr);
            }

            // Sum original initial total kg received
            $receipts = (float) $receiptsQuery->get()->sum(fn (StockBatch $b): float => $b->original_total_kg);

            // 3. Transfers In & Out
            $transfersIn = 0.0;
            $transfersOut = 0.0;

            if ($gradeStr !== 'U') {
                // A) Manual segregation into this grade (child batches created with parent)
                $childBatchIds = StockBatch::query()
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->whereNotNull('parent_batch_id')
                    ->where('purchase_grade', $gradeStr)
                    ->pluck('id');

                $transfersIn += (float) StockMovement::query()
                    ->whereIn('batch_id', $childBatchIds)
                    ->where('type', StockMovementType::In->value)
                    ->when($fromDate !== null, fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
                    ->when($toDate !== null, fn ($q) => $q->whereDate('created_at', '<=', $toDate))
                    ->sum('quantity');

                // B) Loadout automatic classification: Mixed stock consumed for an order item of this grade (net of reversals)
                $autoClassifiedForGrade = (float) StockMovement::query()
                    ->join('shop_order_items', 'shop_order_items.id', '=', 'stock_movements.shop_order_item_id')
                    ->where('stock_movements.product_id', $productId)
                    ->where('stock_movements.warehouse_id', $warehouseId)
                    ->where('stock_movements.type', StockMovementType::Out->value)
                    ->where('stock_movements.grade', ProductGrade::Unsorted->value)
                    ->where('shop_order_items.product_grade', $gradeStr)
                    ->when($fromDate !== null, fn ($q) => $q->whereDate('stock_movements.created_at', '>=', $fromDate))
                    ->when($toDate !== null, fn ($q) => $q->whereDate('stock_movements.created_at', '<=', $toDate))
                    ->sum('stock_movements.quantity');

                $autoReversalsForGrade = (float) StockMovement::query()
                    ->join('shop_order_items', 'shop_order_items.id', '=', 'stock_movements.shop_order_item_id')
                    ->where('stock_movements.product_id', $productId)
                    ->where('stock_movements.warehouse_id', $warehouseId)
                    ->where('stock_movements.type', StockMovementType::SaleReversal->value)
                    ->where('stock_movements.grade', ProductGrade::Unsorted->value)
                    ->where('shop_order_items.product_grade', $gradeStr)
                    ->when($fromDate !== null, fn ($q) => $q->whereDate('stock_movements.created_at', '>=', $fromDate))
                    ->when($toDate !== null, fn ($q) => $q->whereDate('stock_movements.created_at', '<=', $toDate))
                    ->sum('stock_movements.quantity');

                $netAutoForGrade = max(0.0, $autoClassifiedForGrade - $autoReversalsForGrade);
                $transfersIn += $netAutoForGrade;

                // Transfers OUT: if this sorted grade batch was regraded into another grade
                $transfersOut += (float) StockMovement::query()
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('type', StockMovementType::Out->value)
                    ->where('grade', $gradeStr)
                    ->where('notes', 'like', 'Regraded %')
                    ->when($fromDate !== null, fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
                    ->when($toDate !== null, fn ($q) => $q->whereDate('created_at', '<=', $toDate))
                    ->sum('quantity');
            } else {
                // For Mixed 'U':
                // Transfers OUT = manual segregation into child batches + loadout automatic classification (net of reversals)
                $childBatchIdsFromMixed = StockBatch::query()
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->whereNotNull('parent_batch_id')
                    ->whereHas('parentBatch', function ($pq) {
                        $pq->whereIn('purchase_grade', ['U', 'MIXED', 'UNSORTED'])
                            ->orWhereNull('purchase_grade');
                    })
                    ->pluck('id');

                $manualSegregatedFromMixed = (float) StockMovement::query()
                    ->whereIn('batch_id', $childBatchIdsFromMixed)
                    ->where('type', StockMovementType::In->value)
                    ->when($fromDate !== null, fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
                    ->when($toDate !== null, fn ($q) => $q->whereDate('created_at', '<=', $toDate))
                    ->sum('quantity');

                $autoClassifiedFromMixed = (float) StockMovement::query()
                    ->join('shop_order_items', 'shop_order_items.id', '=', 'stock_movements.shop_order_item_id')
                    ->where('stock_movements.product_id', $productId)
                    ->where('stock_movements.warehouse_id', $warehouseId)
                    ->where('stock_movements.type', StockMovementType::Out->value)
                    ->where('stock_movements.grade', ProductGrade::Unsorted->value)
                    ->where('shop_order_items.product_grade', '!=', 'U')
                    ->when($fromDate !== null, fn ($q) => $q->whereDate('stock_movements.created_at', '>=', $fromDate))
                    ->when($toDate !== null, fn ($q) => $q->whereDate('stock_movements.created_at', '<=', $toDate))
                    ->sum('stock_movements.quantity');

                $autoReversalsToMixed = (float) StockMovement::query()
                    ->join('shop_order_items', 'shop_order_items.id', '=', 'stock_movements.shop_order_item_id')
                    ->where('stock_movements.product_id', $productId)
                    ->where('stock_movements.warehouse_id', $warehouseId)
                    ->where('stock_movements.type', StockMovementType::SaleReversal->value)
                    ->where('stock_movements.grade', ProductGrade::Unsorted->value)
                    ->where('shop_order_items.product_grade', '!=', 'U')
                    ->when($fromDate !== null, fn ($q) => $q->whereDate('stock_movements.created_at', '>=', $fromDate))
                    ->when($toDate !== null, fn ($q) => $q->whereDate('stock_movements.created_at', '<=', $toDate))
                    ->sum('stock_movements.quantity');

                $transfersOut = $manualSegregatedFromMixed + max(0.0, $autoClassifiedFromMixed - $autoReversalsToMixed);
            }

            // 4. Physical Consumption (Order dispatches & sales)
            if ($gradeStr !== 'U') {
                // Out movements from batches of this grade for order items/sales
                $directConsumption = (float) StockMovement::query()
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('grade', $gradeStr)
                    ->whereIn('type', [StockMovementType::Out->value, StockMovementType::Sale->value])
                    ->where('notes', 'not like', 'Regraded %')
                    ->when($fromDate !== null, fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
                    ->when($toDate !== null, fn ($q) => $q->whereDate('created_at', '<=', $toDate))
                    ->sum('quantity');

                $directReversals = (float) StockMovement::query()
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('grade', $gradeStr)
                    ->where('type', StockMovementType::SaleReversal->value)
                    ->when($fromDate !== null, fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
                    ->when($toDate !== null, fn ($q) => $q->whereDate('created_at', '<=', $toDate))
                    ->sum('quantity');

                $physicalConsumption = max(0.0, $directConsumption - $directReversals) + $netAutoForGrade;
            } else {
                // For U: only if an order explicitly consumed Mixed directly (without a specific product_grade)
                $physicalConsumption = (float) StockMovement::query()
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('grade', ProductGrade::Unsorted->value)
                    ->whereIn('type', [StockMovementType::Out->value, StockMovementType::Sale->value])
                    ->whereNull('shop_order_item_id')
                    ->when($fromDate !== null, fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
                    ->when($toDate !== null, fn ($q) => $q->whereDate('created_at', '<=', $toDate))
                    ->sum('quantity');
            }

            // 5. Damage (Wastage)
            $damage = (float) WastageEntry::query()
                ->where('product_id', $productId)
                ->whereHas('batch', fn ($bq) => $bq->where('warehouse_id', $warehouseId))
                ->where(function ($q) use ($gradeStr) {
                    if ($gradeStr === 'U') {
                        $q->whereIn('grade', ['U', 'MIXED', 'UNSORTED', ProductGrade::Unsorted->value]);
                    } else {
                        $q->where('grade', $gradeStr);
                    }
                })
                ->when($fromDate !== null, fn ($q) => $q->whereDate('wastage_date', '>=', $fromDate))
                ->when($toDate !== null, fn ($q) => $q->whereDate('wastage_date', '<=', $toDate))
                ->sum('quantity');

            // 6. Adjustments
            $adjustments = (float) StockMovement::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('grade', $gradeStr)
                ->where('type', StockMovementType::Adjustment->value)
                ->when($fromDate !== null, fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
                ->when($toDate !== null, fn ($q) => $q->whereDate('created_at', '<=', $toDate))
                ->sum('quantity');

            // 7. Formula Closing:
            // Opening + Receipts + Transfers In - Transfers Out - Physical Consumption - Damage +- Adjustments
            $formulaClosing = round($opening + $receipts + $transfersIn - $transfersOut - $physicalConsumption - $damage + $adjustments, 3);

            // 8. Physical Available Stock currently in ledger
            $availableStock = $this->availableStockForProduct($productId, $warehouseId, $gradeEnum);

            $reconciled = abs($formulaClosing - $availableStock) < 0.001;

            $byGrade[$gradeStr] = [
                'grade' => $gradeStr,
                'opening' => round($opening, 3),
                'receipts' => round($receipts, 3),
                'transfers_in' => round($transfersIn, 3),
                'transfers_out' => round($transfersOut, 3),
                'physical_consumption' => round($physicalConsumption, 3),
                'damage' => round($damage, 3),
                'adjustments' => round($adjustments, 3),
                'formula_closing' => $formulaClosing,
                'available_stock' => round($availableStock, 3),
                'reconciled' => $reconciled,
            ];

            $totalOpening += $opening;
            $totalReceipts += $receipts;
            $totalTransfersIn += $transfersIn;
            $totalTransfersOut += $transfersOut;
            $totalConsumption += $physicalConsumption;
            $totalDamage += $damage;
            $totalAdjustments += $adjustments;
        }

        $totalFormulaClosing = round($totalOpening + $totalReceipts + $totalTransfersIn - $totalTransfersOut - $totalConsumption - $totalDamage + $totalAdjustments, 3);
        $totalPhysicalAvailable = $this->availableStockForProduct($productId, $warehouseId);
        $totalReconciled = abs($totalFormulaClosing - $totalPhysicalAvailable) < 0.001;

        $allGradesReconciled = collect($byGrade)->every(fn ($g): bool => $g['reconciled']);

        return [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'by_grade' => $byGrade,
            'total' => [
                'opening' => round($totalOpening, 3),
                'receipts' => round($totalReceipts, 3),
                'transfers_in' => round($totalTransfersIn, 3),
                'transfers_out' => round($totalTransfersOut, 3),
                'physical_consumption' => round($totalConsumption, 3),
                'damage' => round($totalDamage, 3),
                'adjustments' => round($totalAdjustments, 3),
                'formula_closing' => $totalFormulaClosing,
                'physical_available' => round($totalPhysicalAvailable, 3),
                'reconciled' => $totalReconciled,
            ],
            'is_fully_reconciled' => $allGradesReconciled && $totalReconciled,
        ];
    }
}
