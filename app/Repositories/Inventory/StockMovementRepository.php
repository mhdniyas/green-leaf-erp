<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\ShopOrderItem;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\WastageEntry;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    /** @param array<int, int>|null $productIds */
    public function currentStockByProductAndGrade(?string $date = null, ?int $warehouseId = null, ?array $categoryIds = null, string $search = '', ?int $productId = null, ?array $productIds = null): Collection
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

        $targetDate = $date ? Carbon::parse($date)->toDateString() : null;
        $targetEndOfDay = $date ? Carbon::parse($date)->endOfDay() : null;

        // Fetch latest authoritative stock adjustments on or before the queried date
        $adjustments = StockAdjustment::query()
            ->when($productId !== null, fn ($q) => $q->where('product_id', $productId))
            ->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds))
            ->when($warehouseId !== null, fn ($q) => $q->where(fn ($sub) => $sub->where('warehouse_id', $warehouseId)->orWhereNull('warehouse_id')))
            ->when($targetDate, fn ($q) => $q->whereDate('business_date', '<=', $targetDate))
            ->when($targetEndOfDay, fn ($q) => $q->where('created_at', '<=', $targetEndOfDay))
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->with(['product:id,category_id,public_uuid,name,sku,unit,image,buffer_qty,carryover_enabled', 'product.category:id,name'])
            ->get();

        $latestAdjustments = collect();
        foreach ($adjustments->groupBy('product_id') as $pId => $pAdjustments) {
            foreach ($pAdjustments->groupBy(fn ($a) => ProductGrade::tryResolve($a->grade)?->value ?? $a->grade ?? 'all') as $gradeKey => $gradeAdjustments) {
                if ($warehouseId !== null) {
                    $whAdj = $gradeAdjustments->first(fn ($a) => (int) $a->warehouse_id === $warehouseId)
                        ?? $gradeAdjustments->first(fn ($a) => $a->warehouse_id === null);
                    if ($whAdj) {
                        $latestAdjustments->push($whAdj);
                    }
                } else {
                    $hasWarehouseSpecific = $gradeAdjustments->contains(fn ($a) => $a->warehouse_id !== null);
                    if ($hasWarehouseSpecific) {
                        foreach ($gradeAdjustments->whereNotNull('warehouse_id')->groupBy('warehouse_id') as $wAdjustments) {
                            $latestAdjustments->push($wAdjustments->first());
                        }
                    } else {
                        $latestAdjustments->push($gradeAdjustments->first());
                    }
                }
            }
        }

        $movementsQuery = $this->query()
            ->when($productId !== null, fn ($q) => $q->where('stock_movements.product_id', $productId))
            ->when($productIds !== null, fn ($q) => $q->whereIn('stock_movements.product_id', $productIds))
            ->whereDoesntHave('batch', fn ($batchQuery) => $batchQuery->where('warehouse_receive_pending', true))

            ->where(function ($q): void {
                $q->whereIn('stock_movements.type', [
                    StockMovementType::Out->value,
                    StockMovementType::Sale->value,
                    StockMovementType::Wastage->value,
                    StockMovementType::Adjustment->value,
                    StockMovementType::SaleReversal->value,
                ])->orWhere(function ($inQ): void {
                    $inQ->where('stock_movements.type', StockMovementType::In->value)
                        ->whereDoesntHave('batch', fn ($batchQuery) => $batchQuery->where('status', BatchStatus::Pending->value));
                });
            })
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
            });

        if ($latestAdjustments->isNotEmpty()) {
            $movementsQuery->where(function ($query) use ($latestAdjustments): void {
                foreach ($latestAdjustments as $adj) {
                    $adjGrade = ProductGrade::tryResolve($adj->grade)?->value ?? $adj->grade;
                    $query->where(function ($productSub) use ($adj, $adjGrade): void {
                        $productSub->where('stock_movements.product_id', '!=', $adj->product_id)
                            ->when($adj->warehouse_id !== null, fn ($w) => $w->orWhere('stock_movements.warehouse_id', '!=', $adj->warehouse_id))
                            ->when($adjGrade !== null, fn ($g) => $g->orWhere('stock_movements.grade', '!=', $adjGrade))
                            ->orWhere(function ($validMovements) use ($adj, $adjGrade): void {
                                $validMovements->where('stock_movements.product_id', $adj->product_id)
                                    ->when($adj->warehouse_id !== null, fn ($w) => $w->where('stock_movements.warehouse_id', $adj->warehouse_id))
                                    ->when($adjGrade !== null, fn ($g) => $g->where('stock_movements.grade', $adjGrade))
                                    ->where('stock_movements.created_at', '>', $adj->created_at)
                                    ->where('stock_movements.notes', 'not like', '%#'.$adj->id.'%');
                            });
                    });
                }
            });
        }

        $movementsStock = $movementsQuery
            ->groupBy('stock_movements.product_id', 'products.public_uuid', 'products.name', 'products.sku', 'products.unit', 'products.image', 'categories.name', 'products.buffer_qty', 'products.carryover_enabled', 'stock_movements.grade')
            ->havingRaw('ABS(current_stock) > 0.0001')
            ->orderByRaw(Product::numericSkuPriorityExpression('products.sku', $driver))
            ->orderByRaw(Product::numericSkuValueExpression('products.sku', $driver))
            ->orderBy('products.sku')
            ->orderBy('stock_movements.grade')
            ->toBase()
            ->get();

        // 2. Fetch pending batches (unsorted stock) that are warehouse confirmed without full Eloquent hydration
        $pendingBatchesQuery = DB::table('stock_batches')
            ->where('stock_batches.status', BatchStatus::Pending->value)
            ->where('stock_batches.warehouse_receive_pending', false)
            ->whereNull('stock_batches.deleted_at')
            ->when($productId !== null, fn ($q) => $q->where('stock_batches.product_id', $productId))
            ->when($productIds !== null, fn ($q) => $q->whereIn('stock_batches.product_id', $productIds))
            ->when($date, fn ($q) => $q->whereDate('stock_batches.received_at', '<=', $date))
            ->when($warehouseId, fn ($q) => $q->where('stock_batches.warehouse_id', $warehouseId))
            ->when($categoryIds !== null, fn ($q) => $q->whereIn('products.category_id', $categoryIds))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function ($productQuery) use ($like): void {
                    $productQuery->where('products.name', 'like', $like)
                        ->orWhere('products.sku', 'like', $like)
                        ->orWhere('categories.name', 'like', $like);
                });
            })
            ->join('products', 'stock_batches.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select([
                'stock_batches.id',
                'stock_batches.product_id',
                'stock_batches.total_kg',
                'stock_batches.received_at',
                'stock_batches.warehouse_id',
                'stock_batches.purchase_grade',
                'products.public_uuid as product_route_key',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.unit as product_unit',
                'products.image as product_image',
                'categories.name as category_name',
            ]);

        if ($latestAdjustments->isNotEmpty()) {
            $pendingBatchesQuery->where(function ($query) use ($latestAdjustments): void {
                foreach ($latestAdjustments as $adj) {
                    $adjGrade = ProductGrade::tryResolve($adj->grade)?->value ?? $adj->grade;
                    if ($adjGrade !== null && $adjGrade !== ProductGrade::Unsorted->value) {
                        continue;
                    }
                    $query->where(function ($productSub) use ($adj): void {
                        $productSub->where('stock_batches.product_id', '!=', $adj->product_id)
                            ->when($adj->warehouse_id !== null, fn ($w) => $w->orWhere('stock_batches.warehouse_id', '!=', $adj->warehouse_id))
                            ->orWhere(function ($validBatches) use ($adj): void {
                                $validBatches->where('stock_batches.product_id', $adj->product_id)
                                    ->when($adj->warehouse_id !== null, fn ($w) => $w->where('stock_batches.warehouse_id', $adj->warehouse_id))
                                    ->where('stock_batches.created_at', '>', $adj->created_at);
                            });
                    });
                }
            });
        }

        $pendingBatches = $pendingBatchesQuery->get();

        $pendingProductDateKeys = $pendingBatches
            ->map(fn ($batch): string => $batch->product_id.'|'.substr((string) $batch->received_at, 0, 10))
            ->unique()
            ->values();
        $pendingProductIds = $pendingBatches->pluck('product_id')->unique()->values();
        $pendingDates = $pendingBatches->map(fn ($batch): string => substr((string) $batch->received_at, 0, 10))->unique()->values();

        $allocatedByProductDate = collect();
        if ($pendingProductIds->isNotEmpty() && $pendingDates->isNotEmpty()) {
            $allocatedByProductDate = ShopOrderItem::query()
                ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.shop_order_id')
                ->whereIn('shop_order_items.product_id', $pendingProductIds)
                ->whereIn('shop_orders.business_date', $pendingDates)
                ->where('shop_orders.state', 'approved')
                ->where('shop_order_items.sorting_status', 'allocated')
                ->selectRaw('shop_order_items.product_id, DATE(shop_orders.business_date) as business_date, SUM(shop_order_items.approved_qty) as approved_qty')
                ->groupBy('shop_order_items.product_id', DB::raw('DATE(shop_orders.business_date)'))
                ->get()
                ->mapWithKeys(fn ($row): array => [$row->product_id.'|'.$row->business_date => (float) $row->approved_qty]);
        }

        $wastageByBatch = WastageEntry::query()
            ->when($date, fn ($q) => $q->whereDate('wastage_date', '<=', $date))
            ->whereIn('batch_id', function ($query) use ($productId, $productIds, $warehouseId, $date): void {
                $query->select('id')
                    ->from('stock_batches')
                    ->where('status', BatchStatus::Pending->value)
                    ->where('warehouse_receive_pending', false)
                    ->whereNull('deleted_at')
                    ->when($productId !== null, fn ($q) => $q->where('product_id', $productId))
                    ->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds))
                    ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
                    ->when($date, fn ($q) => $q->whereDate('received_at', '<=', $date));
            })
            ->selectRaw('batch_id, SUM(quantity) as quantity')
            ->groupBy('batch_id')
            ->get()
            ->mapWithKeys(fn ($r): array => [(int) $r->batch_id => (float) $r->quantity]);

        $unsortedStock = [];
        $allocatedConsumedByProductDate = $pendingProductDateKeys->mapWithKeys(fn (string $key): array => [$key => 0.0]);
        foreach ($pendingBatches as $batch) {
            $wastedQty = (float) ($wastageByBatch[$batch->id] ?? 0);
            $rDate = substr((string) $batch->received_at, 0, 10);
            $productDateKey = $batch->product_id.'|'.$rDate;
            $remainingAllocated = max(
                0.0,
                (float) ($allocatedByProductDate[$productDateKey] ?? 0.0) - (float) ($allocatedConsumedByProductDate[$productDateKey] ?? 0.0)
            );
            $maxAllocatable = max(0.0, (float) $batch->total_kg - $wastedQty);
            $allocatedQty = min($maxAllocatable, $remainingAllocated);
            $allocatedConsumedByProductDate[$productDateKey] = (float) ($allocatedConsumedByProductDate[$productDateKey] ?? 0.0) + $allocatedQty;
            $qty = max(0.0, (float) $batch->total_kg - $wastedQty - $allocatedQty);
            if ($qty <= 0.0001) {
                continue;
            }

            $productIdKey = (int) $batch->product_id;

            if (! isset($unsortedStock[$productIdKey])) {
                $unsortedStock[$productIdKey] = [
                    'product_id' => $productIdKey,
                    'product_route_key' => $batch->product_route_key,
                    'product_name' => $batch->product_name,
                    'product_sku' => $batch->product_sku,
                    'product_unit' => $batch->product_unit,
                    'product_image' => $batch->product_image,
                    'category_name' => $batch->category_name ?? 'Other',
                    'grade' => ProductGrade::Unsorted->value,
                    'current_stock' => 0.0,
                ];
            }

            $unsortedStock[$productIdKey]['current_stock'] += $qty;
        }

        // Convert the unsorted stock map to stdClass objects to match the database query format
        $unsortedCollection = collect(array_values($unsortedStock))->map(function ($item) {
            return (object) $item;
        });

        // 3. Build baseline rows from authoritative adjustments
        $baselineRows = [];
        foreach ($latestAdjustments as $adj) {
            if (! $adj->product) {
                continue;
            }
            if ($categoryIds !== null && ! in_array($adj->product->category_id, $categoryIds, true)) {
                continue;
            }
            if ($search !== '') {
                $like = mb_strtolower($search);
                $haystack = mb_strtolower($adj->product->name.' '.$adj->product->sku.' '.($adj->product->category?->name ?? ''));
                if (! str_contains($haystack, $like)) {
                    continue;
                }
            }

            $baselineRows[] = (object) [
                'product_id' => $adj->product_id,
                'product_route_key' => $adj->product->getRouteKey(),
                'product_name' => $adj->product->name,
                'product_sku' => $adj->product->sku,
                'product_unit' => $adj->product->unit,
                'product_image' => $adj->product->image,
                'category_name' => $adj->product->category?->name ?? 'Other',
                'buffer_qty' => $adj->product->buffer_qty,
                'carryover_enabled' => $adj->product->carryover_enabled,
                'grade' => ProductGrade::tryResolve($adj->grade)?->value ?? $adj->grade ?? ProductGrade::GradeA->value,
                'current_stock' => (float) $adj->counted_qty,
            ];
        }
        $baselineCollection = collect($baselineRows);

        // 4. Merge all collections and group by product_id and grade
        $merged = $movementsStock->concat($unsortedCollection)->concat($baselineCollection);

        $grouped = $merged->groupBy(fn ($item) => $item->product_id.'|'.(ProductGrade::tryResolve($item->grade)?->value ?? $item->grade))->map(function ($items) {
            $first = clone $items->first();
            $first->grade = ProductGrade::tryResolve($first->grade)?->value ?? $first->grade;
            $first->current_stock = round((float) $items->sum('current_stock'), 3);

            return $first;
        })->filter(fn ($item) => abs((float) $item->current_stock) > 0.0001)->values();

        return $grouped->sortBy(
            fn ($item): string => Product::sortableSku((string) ($item->product_sku ?? ''))
        );
    }

    public function currentStockForProduct(int $productId, ?int $warehouseId = null, ?string $date = null): float
    {
        return round((float) $this->currentStockByProductAndGrade($date, $warehouseId, null, '', $productId)
            ->where('product_id', $productId)
            ->sum('current_stock'), 3);
    }

    /**
     * Get stock for a specific product and grade.
     */
    public function stockForProductAndGrade(int $productId, ProductGrade $grade, ?int $warehouseId = null, ?string $date = null): float
    {
        $grades = $grade === ProductGrade::Unsorted ? ['Unsorted', 'U'] : [$grade->value];

        return round((float) $this->currentStockByProductAndGrade($date, $warehouseId, null, '', $productId)
            ->where('product_id', $productId)
            ->whereIn('grade', $grades)
            ->sum('current_stock'), 3);
    }
}
