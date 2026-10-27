<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inventory;

use App\Actions\Inventory\ProcessPartialSegregationAction;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventory\ReverseSegregationRequest;
use App\Http\Requests\Api\Inventory\SegregateBatchRequest;
use App\Http\Resources\Inventory\StockBatchResource;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\ProductWarehouseResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class SegregateBatchController extends Controller
{
    public function __construct(
        private readonly ProcessPartialSegregationAction $action,
        private readonly StockMovementRepository $stockMovementRepository,
        private readonly ProductWarehouseResolver $productWarehouseResolver,
    ) {}

    /**
     * Product Segregation Summary for warehouse.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            abort_unless(
                $user->hasRole(['admin', 'warehouse_receiver', 'warehouse', 'purchaser']) ||
                $user->can('inventory.stock.view') ||
                $user->can('inventory.sorting.process') ||
                $user->can('warehouse.receive.sort'),
                403,
                'Unauthorized to view warehouse segregation.'
            );
        }

        $warehouseId = $request->integer('warehouse_id') ?: null;

        // Warehouse isolation scoping
        if ($user && ! $user->hasRole('admin') && ! $user->hasAllWarehouseAccess()) {
            $assignedWarehouseIds = $user->warehouses()->pluck('warehouses.id')->all();
            if (! empty($assignedWarehouseIds)) {
                if ($warehouseId !== null) {
                    abort_unless(in_array($warehouseId, $assignedWarehouseIds, true), 403, 'Unauthorized warehouse access.');
                } else {
                    $warehouseId = count($assignedWarehouseIds) === 1 ? (int) $assignedWarehouseIds[0] : null;
                }
            }
        }

        $search = trim((string) $request->input('search', ''));
        $onlyMixed = filter_var($request->input('only_mixed', true), FILTER_VALIDATE_BOOLEAN);

        $batchesQuery = StockBatch::query()
            ->where('warehouse_receive_pending', false)
            ->where('status', BatchStatus::Pending)
            ->where(function ($q): void {
                $q->whereIn('purchase_grade', ['U', 'MIXED', 'Unsorted'])
                    ->orWhereNull('purchase_grade');
            })
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($search !== '', fn ($q) => $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->with(['product.category', 'warehouse']);

        $eligibleBatches = $batchesQuery->get();
        $batchesWithMixed = $eligibleBatches->filter(fn (StockBatch $b): bool => $b->available_mixed_qty > 0.001);
        $productBatches = $batchesWithMixed->groupBy('product_id');

        $gradeStocks = collect();
        if ($productBatches->isNotEmpty()) {
            $gradeStocks = StockMovement::query()
                ->whereIn('product_id', $productBatches->keys())
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->whereIn('grade', ['A', 'B', 'C'])
                ->selectRaw("product_id, grade, SUM(CASE WHEN type IN ('in', 'sale_reversal') THEN quantity WHEN type IN ('out', 'wastage', 'sale', 'adjustment') THEN -quantity ELSE 0 END) as net_stock")
                ->groupBy('product_id', 'grade')
                ->get();
        }

        $warehouses = Warehouse::query()->where('is_active', true)->get(['id', 'name', 'code']);

        $items = $productBatches->map(function ($batches, $productId) use ($gradeStocks, $warehouseId, $warehouses): array {
            $firstBatch = $batches->first();
            $product = $firstBatch->product;

            $resolvedWarehouseId = $warehouseId
                ?: ($firstBatch->warehouse_id
                    ?: $this->productWarehouseResolver->resolve(
                        explicitWarehouse: null,
                        category: $product->category_id,
                        produceType: $product->category?->name
                    ));

            $warehouse = $firstBatch->warehouse
                ?: ($resolvedWarehouseId ? $warehouses->firstWhere('id', $resolvedWarehouseId) : null);

            $availableMixed = round((float) $batches->sum(fn (StockBatch $b): float => $b->available_mixed_qty), 3);
            $gradeA = round(max(0.0, (float) $gradeStocks->where('product_id', $productId)->where('grade', 'A')->sum('net_stock')), 3);
            $gradeB = round(max(0.0, (float) $gradeStocks->where('product_id', $productId)->where('grade', 'B')->sum('net_stock')), 3);
            $gradeC = round(max(0.0, (float) $gradeStocks->where('product_id', $productId)->where('grade', 'C')->sum('net_stock')), 3);
            $totalStock = round($availableMixed + $gradeA + $gradeB + $gradeC, 3);

            return [
                'product_id' => (int) $productId,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'product_unit' => $product->unit ?? 'kg',
                'category_name' => $product->category?->name ?? 'Produce',
                'warehouse_id' => (int) ($warehouse->id ?? $resolvedWarehouseId ?? 0),
                'warehouse_name' => $warehouse->name ?? 'Warehouse',
                'warehouse_code' => $warehouse->code ?? 'WH',
                'available_mixed' => $availableMixed,
                'grade_a' => $gradeA,
                'grade_b' => $gradeB,
                'grade_c' => $gradeC,
                'total_stock' => $totalStock,
                'batches_count' => $batches->count(),
            ];
        })->values();

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $currentPage = max(1, (int) $request->input('page', 1));

        $paginated = new LengthAwarePaginator(
            $items->forPage($currentPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return ApiResponse::paginated($paginated);
    }

    /**
     * Eligible Source Batches for a specific product and warehouse.
     */
    public function batches(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            abort_unless(
                $user->hasRole(['admin', 'warehouse_receiver', 'warehouse', 'purchaser']) ||
                $user->can('inventory.stock.view') ||
                $user->can('inventory.sorting.process') ||
                $user->can('warehouse.receive.sort'),
                403,
                'Unauthorized to view eligible batches.'
            );
        }

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $productId = (int) $validated['product_id'];
        $warehouseId = isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;

        if ($user && ! $user->hasRole('admin') && ! $user->hasAllWarehouseAccess()) {
            $assignedWarehouseIds = $user->warehouses()->pluck('warehouses.id')->all();
            if (! empty($assignedWarehouseIds)) {
                if ($warehouseId !== null) {
                    abort_unless(in_array($warehouseId, $assignedWarehouseIds, true), 403, 'Unauthorized warehouse access.');
                } else {
                    $warehouseId = count($assignedWarehouseIds) === 1 ? (int) $assignedWarehouseIds[0] : null;
                }
            }
        }

        $product = Product::with('category')->findOrFail($productId);

        $batches = StockBatch::query()
            ->where('product_id', $product->id)
            ->where('warehouse_receive_pending', false)
            ->where('status', BatchStatus::Pending)
            ->where(function ($q): void {
                $q->whereIn('purchase_grade', ['U', 'MIXED', 'Unsorted'])
                    ->orWhereNull('purchase_grade');
            })
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderBy('received_at', 'asc')
            ->orderBy('id', 'asc')
            ->with('warehouse')
            ->get()
            ->filter(fn (StockBatch $b): bool => $b->available_mixed_qty > 0.001)
            ->values();

        $batchesData = $batches->map(fn (StockBatch $b): array => [
            'id' => (int) $b->id,
            'reference' => $b->reference,
            'product_id' => (int) $b->product_id,
            'warehouse_id' => (int) $b->warehouse_id,
            'warehouse_name' => $b->warehouse?->name ?? 'Warehouse',
            'received_at' => $b->received_at ? $b->received_at->format('d M Y') : 'N/A',
            'original_total_kg' => round((float) ($b->original_total_kg ?? $b->total_kg), 3),
            'available_mixed_qty' => round((float) $b->available_mixed_qty, 3),
            'cost_per_kg' => (float) $b->cost_per_kg,
            'purchase_grade' => $b->purchase_grade ?? 'U',
            'status' => $b->status->value,
            'is_eligible' => true,
        ]);

        $availableMixed = round((float) $batches->sum(fn (StockBatch $b): float => $b->available_mixed_qty), 3);
        $gradeA = $this->stockMovementRepository->stockForProductAndGrade($product->id, ProductGrade::GradeA, $warehouseId);
        $gradeB = $this->stockMovementRepository->stockForProductAndGrade($product->id, ProductGrade::GradeB, $warehouseId);
        $gradeC = $this->stockMovementRepository->stockForProductAndGrade($product->id, ProductGrade::GradeC, $warehouseId);

        // Reversible segregated child batches
        $reversibleBatches = StockBatch::query()
            ->where('product_id', $product->id)
            ->whereNotNull('parent_batch_id')
            ->where('status', BatchStatus::Sorted)
            ->where('warehouse_receive_pending', false)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByDesc('id')
            ->get()
            ->filter(fn (StockBatch $b): bool => (float) $b->remaining_qty > 0.001)
            ->map(fn (StockBatch $b): array => [
                'id' => (int) $b->id,
                'reference' => $b->reference,
                'parent_batch_id' => (int) $b->parent_batch_id,
                'grade' => $b->purchase_grade,
                'available_qty' => round((float) $b->remaining_qty, 3),
                'created_at' => $b->created_at?->format('d M Y H:i'),
            ])
            ->values();

        return ApiResponse::success([
            'product' => [
                'id' => (int) $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit ?? 'kg',
            ],
            'warehouse_id' => $warehouseId,
            'stock' => [
                'mixed' => $availableMixed,
                'grade_a' => $gradeA,
                'grade_b' => $gradeB,
                'grade_c' => $gradeC,
                'total' => round($availableMixed + $gradeA + $gradeB + $gradeC, 3),
            ],
            'batches' => $batchesData,
            'reversible_batches' => $reversibleBatches,
        ]);
    }

    public function segregate(SegregateBatchRequest $request, StockBatch $batch): JsonResponse
    {
        try {
            $validated = $request->validated();
            $targetGrade = ProductGrade::tryResolve($validated['target_grade']) ?? ProductGrade::from($validated['target_grade']);
            $quantity = (float) $validated['quantity'];
            $notes = $validated['notes'] ?? null;
            $clientSubmissionId = $validated['client_submission_id'] ?? null;
            $reason = isset($validated['reason']) ? WastageReason::tryFrom($validated['reason']) : null;

            $result = $this->action->execute(
                $batch,
                $targetGrade,
                $quantity,
                (int) $request->user()->id,
                $notes,
                $clientSubmissionId,
                $reason
            );

            return ApiResponse::success(
                [
                    'parent' => new StockBatchResource($result['parent']),
                    'child' => $result['child'] ? new StockBatchResource($result['child']) : null,
                    'wastage' => $result['wastage'] ? $result['wastage'] : null,
                ],
                $result['child']
                    ? "Batch {$batch->reference} partially segregated into Grade {$targetGrade->value}"
                    : "Recorded {$quantity} kg unsellable damage from batch {$batch->reference}"
            );
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        }
    }

    public function reverse(ReverseSegregationRequest $request, StockBatch $batch): JsonResponse
    {
        try {
            $validated = $request->validated();
            $quantity = (float) $validated['quantity'];
            $notes = $validated['notes'] ?? null;
            $clientSubmissionId = $validated['client_submission_id'] ?? null;

            $result = $this->action->reverse(
                $batch,
                $quantity,
                (int) $request->user()->id,
                $notes,
                $clientSubmissionId
            );

            return ApiResponse::success(
                [
                    'parent' => new StockBatchResource($result['parent']),
                    'child' => new StockBatchResource($result['child']),
                ],
                "Reversed {$quantity} kg from batch {$batch->reference}"
            );
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        }
    }
}
