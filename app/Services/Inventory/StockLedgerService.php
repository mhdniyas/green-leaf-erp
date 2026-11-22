<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Actions\Inventory\ProcessPartialSegregationAction;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Models\Product;
use App\Models\PurchaseCreditNote;
use App\Models\PurchaseInvoice;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\WastageEntry;
use App\Services\Purchasing\PurchaserReadCacheService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLedgerService
{
    public function __construct(
        private readonly ProcessPartialSegregationAction $segregationAction,
    ) {}

    public function netDeductedQuantityForOrderItem(int $shopOrderItemId): float
    {
        return $this->netDeductedQuantityForOrderItems([$shopOrderItemId]);
    }

    /**
     * @param  array<int, int>  $shopOrderItemIds
     */
    public function netDeductedQuantityForOrderItems(array $shopOrderItemIds): float
    {
        if (empty($shopOrderItemIds)) {
            return 0.0;
        }

        $out = (float) StockMovement::query()
            ->whereIn('shop_order_item_id', $shopOrderItemIds)
            ->where('type', StockMovementType::Out->value)
            ->sum('quantity');

        $reversals = (float) StockMovement::query()
            ->whereIn('shop_order_item_id', $shopOrderItemIds)
            ->where('type', StockMovementType::SaleReversal->value)
            ->sum('quantity');

        return round(max(0.0, $out - $reversals), 3);
    }

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
        $breakdowns = $this->availableLoadoutStockBreakdownsForProducts([$productId], $warehouseId);
        $gradeKey = $resolvedGrade->value;

        return $breakdowns[$productId][$gradeKey] ?? [
            'grade_stock' => 0.0,
            'mixed_stock' => 0.0,
            'available_stock' => 0.0,
        ];
    }

    /**
     * Bulk version of availableLoadoutStockBreakdown for an array of products.
     * Avoids N+1 queries during loadout initialization.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, array<string, array{grade_stock: float, mixed_stock: float, available_stock: float}>>
     */
    public function availableLoadoutStockBreakdownsForProducts(array $productIds, ?int $warehouseId = null): array
    {
        $cleanIds = array_values(array_filter(array_unique($productIds)));
        if (empty($cleanIds)) {
            return [];
        }

        // 1. Bulk query sorted lots stock by product and grade
        $sortedRows = DB::table('stock_movements')
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_movements.batch_id')
            ->whereIn('stock_movements.product_id', $cleanIds)
            ->whereNull('stock_batches.deleted_at')
            ->where('stock_batches.status', BatchStatus::Sorted->value)
            ->where('stock_batches.warehouse_receive_pending', false)
            ->when($warehouseId !== null, fn ($query) => $query->where('stock_batches.warehouse_id', $warehouseId))
            ->selectRaw(
                'stock_movements.product_id, stock_movements.grade, '.
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
            ->groupBy('stock_movements.product_id', 'stock_movements.grade')
            ->having('available_quantity', '>', 0)
            ->get();

        $sortedStock = [];
        foreach ($sortedRows as $row) {
            $pId = (int) $row->product_id;
            $g = (string) ($row->grade ?? '');
            $canonicalGrade = match (strtoupper($g)) {
                'U', 'MIXED', 'UNSORTED' => 'U',
                'A' => 'A',
                'B' => 'B',
                'C' => 'C',
                default => $g,
            };
            $sortedStock[$pId][$canonicalGrade] = ($sortedStock[$pId][$canonicalGrade] ?? 0.0) + (float) $row->available_quantity;
        }

        // 2. Bulk query pending batches stock by product and grade using set-based SQL
        $pendingBatches = DB::table('stock_batches')
            ->whereIn('product_id', $cleanIds)
            ->where('status', BatchStatus::Pending->value)
            ->where('warehouse_receive_pending', false)
            ->whereNull('deleted_at')
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('id', 'asc')
            ->select(['id', 'product_id', 'purchase_grade', 'total_kg', 'received_at'])
            ->get();

        $pendingStock = [];
        if ($pendingBatches->isNotEmpty()) {
            $batchIds = $pendingBatches->pluck('id')->all();

            $wastageMap = DB::table('wastage_entries')
                ->whereIn('batch_id', $batchIds)
                ->whereNull('deleted_at')
                ->selectRaw('batch_id, SUM(quantity) as wasted')
                ->groupBy('batch_id')
                ->pluck('wasted', 'batch_id');

            $writeOffMap = DB::table('stock_movements')
                ->whereIn('batch_id', $batchIds)
                ->whereIn('type', [
                    StockMovementType::Out->value,
                    StockMovementType::Sale->value,
                    StockMovementType::Wastage->value,
                    StockMovementType::Adjustment->value,
                ])
                ->selectRaw('batch_id, SUM(quantity) as write_offs')
                ->groupBy('batch_id')
                ->pluck('write_offs', 'batch_id');

            $reversalMap = DB::table('stock_movements')
                ->whereIn('batch_id', $batchIds)
                ->where('type', StockMovementType::SaleReversal->value)
                ->selectRaw('batch_id, SUM(quantity) as reversals')
                ->groupBy('batch_id')
                ->pluck('reversals', 'batch_id');

            $allocations = DB::table('shop_order_items')
                ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.shop_order_id')
                ->whereIn('shop_order_items.product_id', $cleanIds)
                ->where('shop_order_items.sorting_status', 'allocated')
                ->where('shop_orders.state', 'approved')
                ->whereNull('shop_order_items.deleted_at')
                ->selectRaw('shop_order_items.product_id, DATE(shop_orders.business_date) as b_date, SUM(shop_order_items.approved_qty) as total_allocated')
                ->groupBy('shop_order_items.product_id', DB::raw('DATE(shop_orders.business_date)'))
                ->get();

            $allocationMap = [];
            foreach ($allocations as $a) {
                $allocationMap[(int) $a->product_id][(string) $a->b_date] = (float) $a->total_allocated;
            }

            $batchesByProductAndDate = $pendingBatches->groupBy(function ($b): string {
                $date = $b->received_at ? substr((string) $b->received_at, 0, 10) : '';

                return $b->product_id.'|'.$date;
            });

            foreach ($batchesByProductAndDate as $key => $batchesGroup) {
                [$pIdStr, $bDate] = explode('|', (string) $key);
                $pId = (int) $pIdStr;
                $remainingAllocated = (float) ($allocationMap[$pId][$bDate] ?? 0.0);

                foreach ($batchesGroup as $batch) {
                    $bId = (int) $batch->id;
                    $wasted = (float) ($wastageMap[$bId] ?? 0.0);
                    $totalKg = (float) $batch->total_kg;

                    $maxAllocatable = max(0.0, $totalKg - $wasted);
                    $allocatedForThisBatch = 0.0;
                    if ($remainingAllocated > 0.0) {
                        $allocatedForThisBatch = min($maxAllocatable, $remainingAllocated);
                        $remainingAllocated -= $allocatedForThisBatch;
                    }

                    $remainingQty = max(0.0, $totalKg - $wasted - $allocatedForThisBatch);

                    $writeOffs = (float) ($writeOffMap[$bId] ?? 0.0);
                    $reversals = (float) ($reversalMap[$bId] ?? 0.0);
                    $netWriteOff = max(0.0, $writeOffs - $reversals);

                    $availableOnBatch = round(max(0.0, $remainingQty - $netWriteOff), 3);

                    if ($availableOnBatch > 0.0001) {
                        $pGrade = (string) ($batch->purchase_grade ?? 'U');
                        $canonicalGrade = match (strtoupper($pGrade)) {
                            'U', 'MIXED', 'UNSORTED', '' => 'U',
                            'A' => 'A',
                            'B' => 'B',
                            'C' => 'C',
                            default => $pGrade,
                        };
                        $pendingStock[$pId][$canonicalGrade] = ($pendingStock[$pId][$canonicalGrade] ?? 0.0) + $availableOnBatch;
                    }
                }
            }
        }

        // 3. Assemble breakdowns for requested products across all standard grades
        $grades = ['A', 'B', 'C', 'U'];
        $results = [];

        foreach ($cleanIds as $pId) {
            $mixedStock = round(
                ($sortedStock[$pId]['U'] ?? 0.0) + ($pendingStock[$pId]['U'] ?? 0.0),
                3
            );

            foreach ($grades as $g) {
                $gradeStock = round(
                    ($sortedStock[$pId][$g] ?? 0.0) + ($pendingStock[$pId][$g] ?? 0.0),
                    3
                );

                $totalAvailable = $g === 'U'
                    ? $gradeStock
                    : round($gradeStock + $mixedStock, 3);

                $results[$pId][$g] = [
                    'grade_stock' => $gradeStock,
                    'mixed_stock' => $g === 'U' ? 0.0 : $mixedStock,
                    'available_stock' => $totalAvailable,
                ];
            }
        }

        return $results;
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
        bool $fallbackToUnsorted = true,
        ?string $wastageDate = null,
        ?WastageReason $wastageReason = null,
    ): float {
        if ($quantity <= 0.0) {
            return 0.0;
        }

        $targetGrade = $grade ?? ProductGrade::GradeA;

        return DB::transaction(function () use (
            $productId,
            $quantity,
            $userId,
            $movementType,
            $notes,
            $shopOrderItemId,
            $warehouseId,
            $targetGrade,
            $fallbackToUnsorted,
            $wastageDate,
            $wastageReason
        ): float {
            $this->lockStockBatchesForProduct($productId, $warehouseId, $targetGrade);
            if ($fallbackToUnsorted && $targetGrade !== ProductGrade::Unsorted) {
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
                $wastageDate,
                $wastageReason,
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
                    $wastageDate,
                    $wastageReason,
                );
            }

            // 2. Consume remaining from Mixed / Unsorted ('U') only if fallback enabled
            if ($fallbackToUnsorted && $remainingQuantity > 0.0 && $targetGrade !== ProductGrade::Unsorted) {
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
                    $wastageDate,
                    $wastageReason,
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
                        $wastageDate,
                        $wastageReason,
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

            // 2. Fix 1: If remaining shortfall exists and requested grade is not Unsorted, auto-segregate from physically available Mixed/U stock into requested grade
            if ($remainingQuantity > 0.0001 && $targetGrade !== ProductGrade::Unsorted) {
                // First check pending batches of Unsorted/Mixed
                foreach ($this->pendingBatchesForProduct($productId, $warehouseId, ProductGrade::Unsorted) as $pendingBatch) {
                    if ($remainingQuantity <= 0.0001) {
                        break;
                    }

                    $availableOnBatch = $this->pendingBatchAvailableQuantity($pendingBatch);
                    if ($availableOnBatch <= 0.0001) {
                        continue;
                    }

                    $segregateQty = min($remainingQuantity, $availableOnBatch);
                    $segResult = $this->segregationAction->execute(
                        $pendingBatch,
                        $targetGrade,
                        $segregateQty,
                        $userId,
                        "Auto-segregated {$segregateQty} kg to {$targetGrade->value} for order item #{$shopOrderItemId}"
                    );

                    $childBatch = $segResult['child'];
                    if ($childBatch) {
                        StockMovement::create([
                            'product_id' => $productId,
                            'batch_id' => $childBatch->id,
                            'created_by' => $userId,
                            'grade' => $targetGrade->value,
                            'type' => $movementType->value,
                            'quantity' => $segregateQty,
                            'cost_per_unit' => (float) $childBatch->cost_per_kg,
                            'warehouse_id' => $childBatch->warehouse_id,
                            'shop_order_item_id' => $shopOrderItemId,
                            'notes' => $notes,
                        ]);

                        $remainingQuantity = round($remainingQuantity - $segregateQty, 3);
                        $consumedQuantity = round($consumedQuantity + $segregateQty, 3);
                    }
                }

                // Next check sorted lots of Unsorted/Mixed if shortfall still remains
                if ($remainingQuantity > 0.0001) {
                    foreach ($this->sortedLotsForProduct($productId, $warehouseId, ProductGrade::Unsorted) as $sortedLot) {
                        if ($remainingQuantity <= 0.0001) {
                            break;
                        }

                        $availableOnLot = (float) $sortedLot->available_quantity;
                        if ($availableOnLot <= 0.0001) {
                            continue;
                        }

                        $sortedBatch = StockBatch::find($sortedLot->batch_id);
                        if (! $sortedBatch) {
                            continue;
                        }

                        $segregateQty = min($remainingQuantity, $availableOnLot);
                        $segResult = $this->segregationAction->execute(
                            $sortedBatch,
                            $targetGrade,
                            $segregateQty,
                            $userId,
                            "Auto-segregated {$segregateQty} kg to {$targetGrade->value} for order item #{$shopOrderItemId}"
                        );

                        $childBatch = $segResult['child'];
                        if ($childBatch) {
                            StockMovement::create([
                                'product_id' => $productId,
                                'batch_id' => $childBatch->id,
                                'created_by' => $userId,
                                'grade' => $targetGrade->value,
                                'type' => $movementType->value,
                                'quantity' => $segregateQty,
                                'cost_per_unit' => (float) $childBatch->cost_per_kg,
                                'warehouse_id' => $childBatch->warehouse_id,
                                'shop_order_item_id' => $shopOrderItemId,
                                'notes' => $notes,
                            ]);

                            $remainingQuantity = round($remainingQuantity - $segregateQty, 3);
                            $consumedQuantity = round($consumedQuantity + $segregateQty, 3);
                        }
                    }
                }
            }

            // 3. If targetGrade was Unsorted, consume from Unsorted directly
            if ($remainingQuantity > 0.0001 && $targetGrade === ProductGrade::Unsorted) {
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

                if ($remainingQuantity > 0.0001) {
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

            if ($remainingQuantity > 0.0001) {
                $batch = $this->negativeStockBatchForProduct($productId, $userId, $warehouseId, $targetGrade);

                StockMovement::create([
                    'product_id' => $productId,
                    'batch_id' => $batch->id,
                    'created_by' => $userId,
                    'grade' => $targetGrade->value,
                    'type' => $movementType->value,
                    'quantity' => $remainingQuantity,
                    'cost_per_unit' => (float) ($batch->cost_per_kg ?? 0),
                    'warehouse_id' => $batch->warehouse_id ?? $warehouseId,
                    'shop_order_item_id' => $shopOrderItemId,
                    'notes' => $notes.'; negative stock allowed',
                ]);

                $consumedQuantity = round($consumedQuantity + $remainingQuantity, 3);
            }

            return round($consumedQuantity, 3);
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
     * Restore physically returned stock for a credit note item into warehouse inventory.
     * Restores stock to the exact physical pools/batches and grades deducted during loadout.
     *
     * @throws ValidationException
     */
    public function restoreStockForCreditNoteItem(
        ShopCreditNoteItem $creditItem,
        ShopCreditNote $creditNote,
        int $userId,
    ): float {
        $quantityToReturn = round((float) $creditItem->adjusted_quantity, 3);
        if ($quantityToReturn <= 0.0) {
            return 0.0;
        }

        $invoiceItem = $creditItem->invoiceItem;
        if (! $invoiceItem instanceof ShopInvoiceItem) {
            throw ValidationException::withMessages([
                'items' => "Invoice item for credit note item #{$creditItem->id} not found.",
            ]);
        }

        // Check idempotency: has stock already been restored for this specific credit note item?
        $noteMarker = "Credit Note Return — SCN: {$creditNote->credit_note_number}; NoteItem: {$creditItem->id};";
        $existingReturn = StockMovement::query()
            ->where('type', StockMovementType::SaleReversal->value)
            ->where('notes', 'like', "{$noteMarker}%")
            ->sum('quantity');

        if ((float) $existingReturn >= ($quantityToReturn - 0.001)) {
            // Already restored for this exact credit note item
            return (float) $existingReturn;
        }

        $orderItem = $invoiceItem->orderItem;
        if (! $orderItem && $creditNote->invoice && $creditNote->invoice->order) {
            $order = $creditNote->invoice->order;
            $orderItem = $order->items()
                ->where('product_id', $invoiceItem->product_id)
                ->where('product_grade', $invoiceItem->product_grade)
                ->first();
        }

        if (! $orderItem) {
            throw ValidationException::withMessages([
                'inventory_action' => "Cannot verify physical receipt for product '{$creditItem->product_name}': Original warehouse loadout record not found.",
            ]);
        }

        // Lock existing movements for this shop order item to guarantee concurrency safety
        $outMovements = StockMovement::query()
            ->where('shop_order_item_id', $orderItem->id)
            ->where('type', StockMovementType::Out->value)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        if ($outMovements->isEmpty()) {
            throw ValidationException::withMessages([
                'inventory_action' => "Cannot restore stock for product '{$creditItem->product_name}': No loadout stock deduction was recorded for this order item.",
            ]);
        }

        $totalDeducted = round((float) $outMovements->sum('quantity'), 3);

        // Calculate all previously reversed quantities across this order item (including delivery shortages and prior credit notes)
        $previousReversals = StockMovement::query()
            ->where('shop_order_item_id', $orderItem->id)
            ->where('type', StockMovementType::SaleReversal->value)
            ->lockForUpdate()
            ->get();

        $totalAlreadyReversed = round((float) $previousReversals->sum('quantity'), 3);
        $maxRestorable = round(max(0.0, $totalDeducted - $totalAlreadyReversed), 3);

        if ($quantityToReturn > ($maxRestorable + 0.001)) {
            throw ValidationException::withMessages([
                'adjusted_quantity' => "Return quantity ({$quantityToReturn} kg) exceeds remaining physically delivered quantity ({$maxRestorable} kg). Deducted on loadout: {$totalDeducted} kg, already reversed/short: {$totalAlreadyReversed} kg.",
            ]);
        }

        // Map already reversed quantities by batch_id and grade
        $reversedByBatchAndGrade = [];
        foreach ($previousReversals as $rev) {
            $revGrade = ProductGrade::tryResolve($rev->grade)?->value ?? (string) ($rev->grade ?? 'A');
            $key = ((int) $rev->batch_id).'|'.$revGrade;
            $reversedByBatchAndGrade[$key] = ($reversedByBatchAndGrade[$key] ?? 0.0) + (float) $rev->quantity;
        }

        $remainingToRestore = $quantityToReturn;
        $totalRestored = 0.0;

        foreach ($outMovements as $source) {
            if ($remainingToRestore <= 0.0001) {
                break;
            }

            $sourceGrade = ProductGrade::tryResolve($source->grade)?->value ?? (string) ($source->grade ?? 'A');
            $key = ((int) $source->batch_id).'|'.$sourceGrade;
            $alreadyReversedForSource = (float) ($reversedByBatchAndGrade[$key] ?? 0.0);
            $netAvailableOnSource = max(0.0, (float) $source->quantity - $alreadyReversedForSource);

            if ($netAvailableOnSource <= 0.0001) {
                continue;
            }

            $restoredAmount = min($remainingToRestore, $netAvailableOnSource);

            StockMovement::create([
                'batch_id' => $source->batch_id,
                'product_id' => $source->product_id,
                'warehouse_id' => $source->warehouse_id,
                'shop_order_item_id' => $orderItem->id,
                'created_by' => $userId,
                'grade' => $sourceGrade,
                'type' => StockMovementType::SaleReversal,
                'quantity' => $restoredAmount,
                'cost_per_unit' => (float) $source->cost_per_unit,
                'notes' => "Credit Note Return — SCN: {$creditNote->credit_note_number}; NoteItem: {$creditItem->id}; InvoiceItem: {$invoiceItem->id}",
            ]);

            $reversedByBatchAndGrade[$key] = $alreadyReversedForSource + $restoredAmount;
            $remainingToRestore = round($remainingToRestore - $restoredAmount, 3);
            $totalRestored = round($totalRestored + $restoredAmount, 3);
        }

        if ($remainingToRestore > 0.001) {
            throw ValidationException::withMessages([
                'inventory_action' => "Could not match full return quantity ({$quantityToReturn} kg) to original physical loadout batches. Unrestored: {$remainingToRestore} kg.",
            ]);
        }

        return $totalRestored;
    }

    /**
     * Record physical damage/wastage write-off for a credit note item.
     * Attributes physical damage to the original loadout batch without creating
     * a duplicate stock movement deduction (since stock was already deducted on loadout).
     *
     * @throws ValidationException
     */
    public function recordCreditNoteDamageItem(
        ShopCreditNoteItem $creditItem,
        ShopCreditNote $creditNote,
        int $userId,
        ?WastageReason $wastageReason = null,
    ): float {
        $quantityToDamage = round((float) $creditItem->adjusted_quantity, 3);
        if ($quantityToDamage <= 0.0) {
            return 0.0;
        }

        // Check idempotency: has damage already been recorded for this specific credit note item?
        $noteMarker = "Credit Note Damage — SCN: {$creditNote->credit_note_number}; NoteItem: {$creditItem->id};";
        $existingWastage = WastageEntry::query()
            ->where('notes', 'like', "{$noteMarker}%")
            ->sum('quantity');

        if ((float) $existingWastage >= ($quantityToDamage - 0.001)) {
            return (float) $existingWastage;
        }

        $invoiceItem = $creditItem->invoiceItem;
        if (! $invoiceItem instanceof ShopInvoiceItem) {
            $resolvedReason = $wastageReason ?? $this->resolveWastageReasonForCreditItem($creditItem, $creditNote);
            $grade = ProductGrade::tryResolve($creditItem->product_grade)?->value ?? (string) ($creditItem->product_grade ?? 'A');
            $costPerKg = (float) ($creditItem->credit_rate ?? $creditItem->adjusted_unit_price ?? 0.0);
            $shop = $creditNote->shop;
            $invoice = $creditItem->sourceInvoice;
            $invNotes = $invoice ? " Invoice: {$invoice->invoice_number};" : '';
            $notes = "{$noteMarker}{$invNotes} Shop: {$shop?->name}; Reason: {$resolvedReason->label()}; Location: shop; ".($creditItem->notes ?? '');

            $wastage = WastageEntry::create([
                'product_id' => $creditItem->product_id,
                'batch_id' => null,
                'recorded_by' => $userId,
                'grade' => $grade,
                'quantity' => $quantityToDamage,
                'cost_per_kg' => $costPerKg,
                'reason' => $resolvedReason,
                'wastage_date' => $creditNote->credit_note_date?->toDateString() ?? today()->toDateString(),
                'notes' => trim($notes),
            ]);

            $creditItem->forceFill(['wastage_entry_id' => $wastage->id])->save();

            return $quantityToDamage;
        }

        $orderItem = $invoiceItem->orderItem;
        $invoice = $creditItem->sourceInvoice ?? $invoiceItem->invoice ?? $creditNote->invoice;

        if (! $orderItem && $invoice && $invoice->order) {
            $order = $invoice->order;
            $orderItem = $order->items()
                ->where('product_id', $invoiceItem->product_id)
                ->where('product_grade', $invoiceItem->product_grade)
                ->first();
        }

        if (! $orderItem) {
            throw ValidationException::withMessages([
                'inventory_action' => "Cannot verify physical loadout for product '{$creditItem->product_name}': Original warehouse loadout record not found.",
            ]);
        }

        // Lock existing Out movements for this shop order item to guarantee concurrency safety
        $outMovements = StockMovement::query()
            ->where('shop_order_item_id', $orderItem->id)
            ->where('type', StockMovementType::Out->value)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        $resolvedReason = $wastageReason ?? $this->resolveWastageReasonForCreditItem($creditItem, $creditNote);
        $order = $orderItem->order;
        $invoice = $creditItem->sourceInvoice ?? $invoiceItem?->invoice ?? $creditNote->invoice;
        $shop = $creditNote->shop ?? $invoice?->shop;

        if ($outMovements->isEmpty()) {
            $costPerKg = (float) ($orderItem->unit_cost ?? $invoiceItem?->unit_price ?? 0.0);
            $grade = ProductGrade::tryResolve($invoiceItem?->product_grade)?->value ?? (string) ($invoiceItem?->product_grade ?? 'A');
            $notes = "{$noteMarker} Invoice: {$invoice?->invoice_number}; Order: {$order?->order_number}; Shop: {$shop?->name}; Reason: {$resolvedReason->label()}; Location: shop; ".($creditItem->notes ?? '');

            $wastage = WastageEntry::create([
                'product_id' => $creditItem->product_id,
                'batch_id' => null,
                'recorded_by' => $userId,
                'grade' => $grade,
                'quantity' => $quantityToDamage,
                'cost_per_kg' => $costPerKg,
                'reason' => $resolvedReason,
                'wastage_date' => $creditNote->credit_note_date?->toDateString() ?? today()->toDateString(),
                'notes' => trim($notes),
            ]);

            $creditItem->forceFill(['wastage_entry_id' => $wastage->id])->save();

            return $quantityToDamage;
        }

        $totalDeducted = round((float) $outMovements->sum('quantity'), 3);

        // Calculate all previously reversed quantities across this order item
        $previousReversals = StockMovement::query()
            ->where('shop_order_item_id', $orderItem->id)
            ->where('type', StockMovementType::SaleReversal->value)
            ->lockForUpdate()
            ->sum('quantity');

        // Calculate previously recorded wastage entries for this order item across credit notes
        $orderNumber = $orderItem->order?->order_number ?? '';
        $previousDamages = WastageEntry::query()
            ->where('product_id', $creditItem->product_id)
            ->where('notes', 'like', "%Order: {$orderNumber}%")
            ->where('notes', 'not like', "{$noteMarker}%")
            ->sum('quantity');

        $maxDamagable = round(max(0.0, $totalDeducted - (float) $previousReversals - (float) $previousDamages), 3);

        if ($quantityToDamage > ($maxDamagable + 0.001)) {
            throw ValidationException::withMessages([
                'adjusted_quantity' => "Damage quantity ({$quantityToDamage} kg) exceeds remaining delivered quantity ({$maxDamagable} kg). Deducted on loadout: {$totalDeducted} kg, already reversed/damaged: ".round((float) $previousReversals + (float) $previousDamages, 3).' kg.',
            ]);
        }

        $resolvedReason = $wastageReason ?? $this->resolveWastageReasonForCreditItem($creditItem, $creditNote);

        $order = $orderItem->order;
        $invoice = $creditItem->sourceInvoice ?? $invoiceItem?->invoice ?? $creditNote->invoice;
        $shop = $creditNote->shop ?? $invoice?->shop;

        $remainingToDamage = $quantityToDamage;
        $totalDamaged = 0.0;

        foreach ($outMovements as $source) {
            if ($remainingToDamage <= 0.0001) {
                break;
            }

            $sourceGrade = ProductGrade::tryResolve($source->grade)?->value ?? (string) ($source->grade ?? 'A');
            $damageAmount = min($remainingToDamage, (float) $source->quantity);

            $wastage = WastageEntry::create([
                'product_id' => $creditItem->product_id,
                'batch_id' => $source->batch_id,
                'recorded_by' => $userId,
                'grade' => $sourceGrade,
                'quantity' => $damageAmount,
                'cost_per_kg' => (float) $source->cost_per_unit,
                'reason' => $resolvedReason,
                'wastage_date' => $creditNote->credit_note_date?->toDateString() ?? today()->toDateString(),
                'notes' => "{$noteMarker} Invoice: {$invoice?->invoice_number}; Order: {$order?->order_number}; Shop: {$shop?->name}; Reason: {$resolvedReason->label()}; Location: shop; ".($creditItem->notes ?? ''),
            ]);

            $creditItem->forceFill(['wastage_entry_id' => $wastage->id])->save();

            $remainingToDamage = round($remainingToDamage - $damageAmount, 3);
            $totalDamaged = round($totalDamaged + $damageAmount, 3);
        }

        if (class_exists(PurchaserReadCacheService::class)) {
            app(PurchaserReadCacheService::class)->invalidate(['orders', 'products']);
        }

        return $totalDamaged;
    }

    /**
     * Resolve the appropriate WastageReason for a damaged credit note item.
     */
    private function resolveWastageReasonForCreditItem(
        ShopCreditNoteItem $creditItem,
        ShopCreditNote $creditNote
    ): WastageReason {
        if (! empty($creditItem->notes)) {
            if (preg_match('/(?:damage_reason|Damage Reason):\s*([a-z_]+)/i', $creditItem->notes, $matches)) {
                $candidate = WastageReason::tryFrom(strtolower(trim($matches[1])));
                if ($candidate) {
                    return $candidate;
                }
            }
        }

        $itemReason = $creditItem->reason_type instanceof ShopCreditNoteReason
            ? $creditItem->reason_type
            : ShopCreditNoteReason::tryFrom((string) $creditItem->reason_type);

        $reasonToMatch = $itemReason ?? $creditNote->reason_type;

        return match ($reasonToMatch) {
            ShopCreditNoteReason::DamageWastage => WastageReason::TransitDamage,
            ShopCreditNoteReason::Shortage => WastageReason::Shrinkage,
            default => WastageReason::TransitDamage,
        };
    }

    /**
     * Deduct warehouse stock for a purchase credit note (returning goods to vendor).
     * Prefers batches from the linked PurchaseInvoice's GoodsReceived record, then FIFO.
     *
     * @return Collection<int, StockMovement>
     *
     * @throws ValidationException
     */
    public function recordPurchaseCreditOut(
        int $productId,
        int $warehouseId,
        string|ProductGrade $grade,
        float $quantity,
        int $userId,
        string $notes,
        ?int $purchaseInvoiceId = null,
        bool $allowNegativeStock = false,
        ?float $fallbackUnitCost = null,
    ): Collection {
        $quantityToDeduct = round($quantity, 3);
        if ($quantityToDeduct <= 0.0) {
            return collect();
        }

        $targetGrade = $grade instanceof ProductGrade ? $grade : (ProductGrade::tryResolve($grade) ?? ProductGrade::GradeA);

        return DB::transaction(function () use (
            $productId,
            $warehouseId,
            $targetGrade,
            $quantityToDeduct,
            $userId,
            $notes,
            $purchaseInvoiceId,
            $allowNegativeStock,
            $fallbackUnitCost,
        ): Collection {
            $this->lockStockBatchesForProduct($productId, $warehouseId, $targetGrade);

            // 1. Identify preferred batch IDs from linked invoice (if any)
            $preferredBatchIds = [];
            if ($purchaseInvoiceId) {
                $invoice = PurchaseInvoice::find($purchaseInvoiceId);
                if ($invoice && $invoice->goods_received_id) {
                    $preferredBatchIds = StockBatch::query()
                        ->where('goods_received_id', $invoice->goods_received_id)
                        ->where('warehouse_id', $warehouseId)
                        ->where('product_id', $productId)
                        ->pluck('id')
                        ->all();
                }
            }

            // 2. Fetch available sorted lots for this product and grade
            $allLots = $this->sortedLotsForProduct($productId, $warehouseId, $targetGrade);

            // Sort so preferred batches come first, keeping FIFO within each group
            $sortedLots = $allLots->sortBy(function ($lot) use ($preferredBatchIds) {
                return in_array((int) $lot->batch_id, $preferredBatchIds, true) ? 0 : 1;
            })->values();

            // 3. Fetch available pending batches as fallback
            $allPending = $this->pendingBatchesForProduct($productId, $warehouseId, $targetGrade);
            $sortedPending = $allPending->sortBy(function ($batch) use ($preferredBatchIds) {
                return in_array((int) $batch->id, $preferredBatchIds, true) ? 0 : 1;
            })->values();

            // Check total available across sorted lots and pending batches
            $totalAvailable = round(
                (float) $sortedLots->sum('available_quantity') +
                (float) $sortedPending->sum(fn ($b) => $this->pendingBatchAvailableQuantity($b)),
                3
            );

            if (! $allowNegativeStock && $quantityToDeduct > ($totalAvailable + 0.001)) {
                $productName = Product::find($productId)?->name ?? "Product #{$productId}";
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock in warehouse for {$productName} (Grade {$targetGrade->value}). Requested return: {$quantityToDeduct} kg, available: {$totalAvailable} kg.",
                ]);
            }

            $remaining = $quantityToDeduct;
            $movements = collect();

            // Deduct from sorted lots
            foreach ($sortedLots as $lot) {
                if ($remaining <= 0.0001) {
                    break;
                }

                $available = (float) $lot->available_quantity;
                if ($available <= 0.0001) {
                    continue;
                }

                $deduct = min($remaining, $available);
                $movement = StockMovement::create([
                    'product_id' => $productId,
                    'batch_id' => (int) $lot->batch_id,
                    'warehouse_id' => $warehouseId,
                    'grade' => (string) $lot->grade,
                    'type' => StockMovementType::Out->value,
                    'quantity' => $deduct,
                    'cost_per_unit' => (float) $lot->cost_per_unit,
                    'created_by' => $userId,
                    'notes' => $notes,
                ]);

                $movements->push($movement);
                $remaining = round($remaining - $deduct, 3);
            }

            // Deduct from pending batches if still needed
            if ($remaining > 0.0001) {
                foreach ($sortedPending as $batch) {
                    if ($remaining <= 0.0001) {
                        break;
                    }

                    $available = $this->pendingBatchAvailableQuantity($batch);
                    if ($available <= 0.0001) {
                        continue;
                    }

                    $deduct = min($remaining, $available);
                    $movement = StockMovement::create([
                        'product_id' => $productId,
                        'batch_id' => (int) $batch->id,
                        'warehouse_id' => $warehouseId,
                        'grade' => $targetGrade->value,
                        'type' => StockMovementType::Out->value,
                        'quantity' => $deduct,
                        'cost_per_unit' => (float) $batch->cost_per_kg,
                        'created_by' => $userId,
                        'notes' => $notes,
                    ]);

                    $movements->push($movement);
                    $remaining = round($remaining - $deduct, 3);
                }
            }

            // Deduct remainder from synthetic NEG- batch if negative stock is permitted
            if ($allowNegativeStock && $remaining > 0.0001) {
                $batch = $this->negativeStockBatchForProduct($productId, $userId, $warehouseId, $targetGrade);
                $costPerUnit = $fallbackUnitCost !== null && $fallbackUnitCost > 0
                    ? $fallbackUnitCost
                    : (float) ($batch->cost_per_kg ?? 0);

                $movement = StockMovement::create([
                    'product_id' => $productId,
                    'batch_id' => $batch->id,
                    'warehouse_id' => $batch->warehouse_id ?? $warehouseId,
                    'grade' => $targetGrade->value,
                    'type' => StockMovementType::Out->value,
                    'quantity' => $remaining,
                    'cost_per_unit' => $costPerUnit,
                    'created_by' => $userId,
                    'notes' => $notes.'; negative stock allowed',
                ]);

                $movements->push($movement);
                $remaining = 0.0;
            }

            return $movements;
        });
    }

    /**
     * Add stock into warehouse for a purchase credit note.
     *
     * @throws ValidationException
     */
    public function recordPurchaseCreditIn(
        int $productId,
        int $warehouseId,
        string|ProductGrade $grade,
        float $quantity,
        float $unitPrice,
        int $userId,
        string $notes,
        ?int $batchId = null,
    ): StockMovement {
        $quantityToAdd = round($quantity, 3);
        if ($quantityToAdd <= 0.0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }

        $targetGrade = $grade instanceof ProductGrade ? $grade : (ProductGrade::tryResolve($grade) ?? ProductGrade::GradeA);
        $costPerKg = round($unitPrice, 4);

        return DB::transaction(function () use (
            $productId,
            $warehouseId,
            $targetGrade,
            $quantityToAdd,
            $costPerKg,
            $userId,
            $notes,
            $batchId,
        ): StockMovement {
            if ($batchId) {
                $batch = StockBatch::find($batchId);
            } else {
                $batch = StockBatch::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'purchase_grade' => $targetGrade->value,
                    'grading_mode' => 'fixed_purchase_grade',
                    'status' => BatchStatus::Sorted,
                    'warehouse_receive_pending' => false,
                    'warehouse_confirmed_at' => now(),
                    'warehouse_confirmed_by' => $userId,
                    'sorted_at' => now(),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'received_at' => now()->toDateString(),
                    'total_kg' => $quantityToAdd,
                    'cost_per_kg' => $costPerKg,
                    'reference' => 'PCN-IN',
                    'notes' => $notes,
                ]);
            }

            return StockMovement::create([
                'product_id' => $productId,
                'batch_id' => $batch->id,
                'warehouse_id' => $warehouseId,
                'grade' => $targetGrade->value,
                'type' => StockMovementType::In->value,
                'quantity' => $quantityToAdd,
                'cost_per_unit' => $costPerKg,
                'created_by' => $userId,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Record damage / wastage for a purchase credit note.
     * If $deductStock is true, also deducts stock from warehouse via StockMovementType::Wastage.
     *
     * @return array{wastage: WastageEntry, movements: Collection<int, StockMovement>}
     *
     * @throws ValidationException
     */
    public function recordPurchaseCreditDamage(
        int $productId,
        ?int $warehouseId,
        string|ProductGrade $grade,
        float $quantity,
        float $costPerKg,
        int $userId,
        WastageReason $reason,
        string $notes,
        bool $deductStock = true,
        ?string $wastageDate = null,
        ?int $purchaseInvoiceId = null,
        bool $allowNegativeStock = false,
    ): array {
        $quantityToDamage = round($quantity, 3);
        if ($quantityToDamage <= 0.0) {
            throw ValidationException::withMessages(['quantity' => 'Wastage quantity must be greater than zero.']);
        }

        $targetGrade = $grade instanceof ProductGrade ? $grade : (ProductGrade::tryResolve($grade) ?? ProductGrade::GradeA);
        $cost = round($costPerKg, 4);

        return DB::transaction(function () use (
            $productId,
            $warehouseId,
            $targetGrade,
            $quantityToDamage,
            $cost,
            $userId,
            $reason,
            $notes,
            $deductStock,
            $wastageDate,
            $purchaseInvoiceId,
            $allowNegativeStock,
        ): array {
            $movements = collect();
            $primaryBatchId = null;

            if ($deductStock && $warehouseId !== null) {
                $this->lockStockBatchesForProduct($productId, $warehouseId, $targetGrade);

                $preferredBatchIds = [];
                if ($purchaseInvoiceId) {
                    $invoice = PurchaseInvoice::find($purchaseInvoiceId);
                    if ($invoice && $invoice->goods_received_id) {
                        $preferredBatchIds = StockBatch::query()
                            ->where('goods_received_id', $invoice->goods_received_id)
                            ->where('warehouse_id', $warehouseId)
                            ->where('product_id', $productId)
                            ->pluck('id')
                            ->all();
                    }
                }

                $allLots = $this->sortedLotsForProduct($productId, $warehouseId, $targetGrade);
                $sortedLots = $allLots->sortBy(function ($lot) use ($preferredBatchIds) {
                    return in_array((int) $lot->batch_id, $preferredBatchIds, true) ? 0 : 1;
                })->values();

                $allPending = $this->pendingBatchesForProduct($productId, $warehouseId, $targetGrade);
                $sortedPending = $allPending->sortBy(function ($batch) use ($preferredBatchIds) {
                    return in_array((int) $batch->id, $preferredBatchIds, true) ? 0 : 1;
                })->values();

                $totalAvailable = round(
                    (float) $sortedLots->sum('available_quantity') +
                    (float) $sortedPending->sum(fn ($b) => $this->pendingBatchAvailableQuantity($b)),
                    3
                );

                if (! $allowNegativeStock && $quantityToDamage > ($totalAvailable + 0.001)) {
                    $productName = Product::find($productId)?->name ?? "Product #{$productId}";
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock in warehouse for {$productName} (Grade {$targetGrade->value}) to record damage. Requested: {$quantityToDamage} kg, available: {$totalAvailable} kg.",
                    ]);
                }

                $remaining = $quantityToDamage;

                foreach ($sortedLots as $lot) {
                    if ($remaining <= 0.0001) {
                        break;
                    }

                    $available = (float) $lot->available_quantity;
                    if ($available <= 0.0001) {
                        continue;
                    }

                    $deduct = min($remaining, $available);
                    $movement = StockMovement::create([
                        'product_id' => $productId,
                        'batch_id' => (int) $lot->batch_id,
                        'warehouse_id' => $warehouseId,
                        'grade' => (string) $lot->grade,
                        'type' => StockMovementType::Wastage->value,
                        'quantity' => $deduct,
                        'cost_per_unit' => (float) $lot->cost_per_unit,
                        'created_by' => $userId,
                        'notes' => $notes,
                    ]);

                    $movements->push($movement);
                    if ($primaryBatchId === null) {
                        $primaryBatchId = (int) $lot->batch_id;
                    }
                    $remaining = round($remaining - $deduct, 3);
                }

                if ($remaining > 0.0001) {
                    foreach ($sortedPending as $batch) {
                        if ($remaining <= 0.0001) {
                            break;
                        }

                        $available = $this->pendingBatchAvailableQuantity($batch);
                        if ($available <= 0.0001) {
                            continue;
                        }

                        $deduct = min($remaining, $available);
                        $movement = StockMovement::create([
                            'product_id' => $productId,
                            'batch_id' => (int) $batch->id,
                            'warehouse_id' => $warehouseId,
                            'grade' => $targetGrade->value,
                            'type' => StockMovementType::Wastage->value,
                            'quantity' => $deduct,
                            'cost_per_unit' => (float) $batch->cost_per_kg,
                            'created_by' => $userId,
                            'notes' => $notes,
                        ]);

                        $movements->push($movement);
                        if ($primaryBatchId === null) {
                            $primaryBatchId = (int) $batch->id;
                        }
                        $remaining = round($remaining - $deduct, 3);
                    }
                }

                // Deduct remainder from synthetic NEG- batch if negative stock is permitted
                if ($allowNegativeStock && $remaining > 0.0001) {
                    $batch = $this->negativeStockBatchForProduct($productId, $userId, $warehouseId, $targetGrade);
                    $costPerUnit = $cost > 0 ? $cost : (float) ($batch->cost_per_kg ?? 0);

                    $movement = StockMovement::create([
                        'product_id' => $productId,
                        'batch_id' => $batch->id,
                        'warehouse_id' => $batch->warehouse_id ?? $warehouseId,
                        'grade' => $targetGrade->value,
                        'type' => StockMovementType::Wastage->value,
                        'quantity' => $remaining,
                        'cost_per_unit' => $costPerUnit,
                        'created_by' => $userId,
                        'notes' => $notes.'; negative stock allowed',
                    ]);

                    $movements->push($movement);
                    if ($primaryBatchId === null) {
                        $primaryBatchId = $batch->id;
                    }
                    $remaining = 0.0;
                }
            }

            $wastage = WastageEntry::create([
                'product_id' => $productId,
                'batch_id' => $primaryBatchId,
                'recorded_by' => $userId,
                'grade' => $targetGrade->value,
                'quantity' => $quantityToDamage,
                'cost_per_kg' => $cost,
                'reason' => $reason,
                'wastage_date' => $wastageDate ?? now()->toDateString(),
                'notes' => $notes,
            ]);

            return [
                'wastage' => $wastage,
                'movements' => $movements,
            ];
        });
    }

    /**
     * Reverse all stock movements and soft-delete wastage entries created for a Purchase Credit Note.
     */
    public function reversePurchaseCreditNoteMovements(
        PurchaseCreditNote $creditNote,
        int $userId,
        string $reason = 'Voided Purchase Credit Note',
    ): void {
        DB::transaction(function () use ($creditNote, $userId, $reason): void {
            $marker = "PCN: {$creditNote->credit_note_number};";

            // Find all movements created for this credit note
            $movements = StockMovement::query()
                ->where('notes', 'like', "%{$marker}%")
                ->lockForUpdate()
                ->get();

            foreach ($movements as $movement) {
                // Skip if this movement is already a reversal
                if (str_contains((string) $movement->notes, 'Void Reversal')) {
                    continue;
                }

                $qty = (float) $movement->quantity;
                if ($qty <= 0.0001) {
                    continue;
                }

                $movType = $movement->type instanceof StockMovementType ? $movement->type : StockMovementType::tryFrom((string) $movement->type);
                $gradeVal = $movement->grade instanceof ProductGrade ? $movement->grade->value : (string) ($movement->grade ?? 'A');

                // If original movement was an OUT or WASTAGE deduction, restore stock via StockMovementType::In
                if (in_array($movType, [StockMovementType::Out, StockMovementType::Wastage], true)) {
                    StockMovement::create([
                        'product_id' => $movement->product_id,
                        'batch_id' => $movement->batch_id,
                        'warehouse_id' => $movement->warehouse_id,
                        'grade' => $gradeVal,
                        'type' => StockMovementType::In->value,
                        'quantity' => $qty,
                        'cost_per_unit' => (float) $movement->cost_per_unit,
                        'created_by' => $userId,
                        'notes' => "Void Reversal — Restored stock for PCN: {$creditNote->credit_note_number}; OrigMovement #{$movement->id}; Reason: {$reason}",
                    ]);
                } elseif ($movType === StockMovementType::In) {
                    // Original movement was an IN, reverse it via StockMovementType::Adjustment (deduction)
                    StockMovement::create([
                        'product_id' => $movement->product_id,
                        'batch_id' => $movement->batch_id,
                        'warehouse_id' => $movement->warehouse_id,
                        'grade' => $gradeVal,
                        'type' => StockMovementType::Adjustment->value,
                        'quantity' => $qty,
                        'cost_per_unit' => (float) $movement->cost_per_unit,
                        'created_by' => $userId,
                        'notes' => "Void Reversal — Deducted returned stock for PCN: {$creditNote->credit_note_number}; OrigMovement #{$movement->id}; Reason: {$reason}",
                    ]);
                }
            }

            // Soft-delete linked WastageEntry records
            $linkedWastageIds = $creditNote->items->pluck('wastage_entry_id')->filter()->all();
            $wastageQuery = WastageEntry::query()
                ->where(function ($q) use ($linkedWastageIds, $marker): void {
                    if (! empty($linkedWastageIds)) {
                        $q->whereIn('id', $linkedWastageIds);
                    }
                    $q->orWhere('notes', 'like', "%{$marker}%");
                })
                ->lockForUpdate()
                ->get();

            foreach ($wastageQuery as $wastage) {
                $wastage->notes = trim(($wastage->notes ?? '')." [VOIDED: {$reason}]");
                $wastage->save();
                $wastage->delete();
            }
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
        ?string $wastageDate = null,
        ?WastageReason $wastageReason = null,
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

            if ($movementType === StockMovementType::Wastage) {
                WastageEntry::create([
                    'product_id' => $productId,
                    'batch_id' => (int) $lot->batch_id,
                    'recorded_by' => $userId,
                    'grade' => (string) $lot->grade,
                    'quantity' => $deductionQuantity,
                    'cost_per_kg' => (float) $lot->cost_per_unit,
                    'reason' => $wastageReason ?? WastageReason::Unsold,
                    'wastage_date' => $wastageDate ?? now()->toDateString(),
                    'notes' => $notes,
                ]);
            }

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
        ?string $wastageDate = null,
        ?WastageReason $wastageReason = null,
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

            if ($movementType === StockMovementType::Wastage) {
                WastageEntry::create([
                    'product_id' => $productId,
                    'batch_id' => (int) $batch->id,
                    'recorded_by' => $userId,
                    'grade' => $grade?->value ?? ProductGrade::Unsorted->value,
                    'quantity' => $deductionQuantity,
                    'cost_per_kg' => (float) $batch->cost_per_kg,
                    'reason' => $wastageReason ?? WastageReason::Unsold,
                    'wastage_date' => $wastageDate ?? now()->toDateString(),
                    'notes' => $notes,
                ]);
            }

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
        // Pending-batch remaining_qty is the established source for dispatches (already accounts for WastageEntry).
        // Subtract net write-offs (Out, Sale, Adjustment minus SaleReversal).
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
