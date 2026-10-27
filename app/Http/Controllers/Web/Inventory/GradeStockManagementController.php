<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Actions\Inventory\TransferGradeStockAction;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\GradeStockTransferRequest;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\ProductWarehouseResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class GradeStockManagementController extends Controller
{
    public function __construct(
        private readonly TransferGradeStockAction $transferAction,
        private readonly StockMovementRepository $stockMovementRepository,
        private readonly ProductWarehouseResolver $productWarehouseResolver,
    ) {}

    /**
     * Display the Grade Stock Management page with two tabs:
     * Tab 1: Current Inventory
     * Tab 2: Grade Transfer
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if ($user) {
            abort_unless(
                $user->hasRole(['admin', 'warehouse_receiver', 'warehouse', 'purchaser']) ||
                $user->can('inventory.stock.view') ||
                $user->can('inventory.sorting.process') ||
                $user->can('warehouse.receive.sort') ||
                $user->can('inventory.stock.adjust'),
                403,
                'Unauthorized to access grade stock management.'
            );
        }

        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        $selectedWarehouseId = $request->integer('warehouse_id') ?: null;
        if ($user && ! $user->hasRole('admin') && ! $user->hasAllWarehouseAccess()) {
            $assignedWarehouseIds = $user->warehouses()->pluck('warehouses.id')->all();
            if (! empty($assignedWarehouseIds)) {
                if ($selectedWarehouseId !== null) {
                    abort_unless(in_array($selectedWarehouseId, $assignedWarehouseIds, true), 403, 'Unauthorized warehouse access.');
                } else {
                    $selectedWarehouseId = (int) $assignedWarehouseIds[0];
                }
            }
        }

        if (! $selectedWarehouseId) {
            $selectedWarehouseId = $warehouses->first()?->id ?? 1;
        }

        $search = trim((string) $request->input('search', ''));
        $tab = $request->input('tab', 'inventory');
        if (! in_array($tab, ['inventory', 'transfer'], true)) {
            $tab = 'inventory';
        }

        $selectedProductId = $request->integer('product_id') ?: null;

        // Query products paginated (20 per page)
        $productsQuery = Product::query()
            ->with(['category:id,name'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $products = $productsQuery->paginate(20)->withQueryString();
        $pageProductIds = $products->pluck('id')->all();

        // Calculate canonical grade-wise stock for the products on the current page
        $stockMap = [];
        if (! empty($pageProductIds)) {
            $rawStocks = $this->stockMovementRepository->currentStockByProductAndGrade(
                warehouseId: $selectedWarehouseId,
                productIds: $pageProductIds
            );

            foreach ($rawStocks as $row) {
                $pId = (int) $row->product_id;
                $g = ProductGrade::tryResolve($row->grade)?->value ?? $row->grade;
                $stockMap[$pId][$g] = round((float) $row->current_stock, 3);
            }
        }

        // Global warehouse summary totals (computed across all products in warehouse, not just current page)
        $summaryTotals = $this->calculateWarehouseTotals($selectedWarehouseId);

        // Pre-load product details if on transfer tab or a product is preselected
        $activeProduct = null;
        $activeProductDetails = null;
        if ($selectedProductId) {
            $activeProduct = Product::with('category')->find($selectedProductId);
            if ($activeProduct) {
                $activeProductDetails = $this->buildProductTransferDetails($activeProduct, $selectedWarehouseId);
            }
        }

        return response()->view('inventory.grade-stock.index', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'search' => $search,
            'tab' => $tab,
            'products' => $products,
            'stockMap' => $stockMap,
            'summaryTotals' => $summaryTotals,
            'selectedProductId' => $selectedProductId,
            'activeProduct' => $activeProduct,
            'activeProductDetails' => $activeProductDetails,
        ]);
    }

    /**
     * API: Paginated grade-wise inventory summary.
     */
    public function inventory(Request $request): JsonResponse
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $search = trim((string) $request->input('search', ''));
        $perPage = max(1, min(100, $request->integer('per_page', 20)));
        $hasStockOnly = filter_var($request->input('has_stock_only', false), FILTER_VALIDATE_BOOLEAN);

        $productsQuery = Product::query()
            ->with(['category:id,name'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $products = $productsQuery->paginate($perPage);
        $pageProductIds = $products->pluck('id')->all();

        $stockMap = [];
        if (! empty($pageProductIds)) {
            $rawStocks = $this->stockMovementRepository->currentStockByProductAndGrade(
                warehouseId: $warehouseId,
                productIds: $pageProductIds
            );

            foreach ($rawStocks as $row) {
                $pId = (int) $row->product_id;
                $g = ProductGrade::tryResolve($row->grade)?->value ?? $row->grade;
                $stockMap[$pId][$g] = round((float) $row->current_stock, 3);
            }
        }

        $items = $products->getCollection()->map(function (Product $product) use ($stockMap): array {
            $pId = (int) $product->id;
            $mixed = $stockMap[$pId]['U'] ?? 0.0;
            $gradeA = $stockMap[$pId]['A'] ?? 0.0;
            $gradeB = $stockMap[$pId]['B'] ?? 0.0;
            $gradeC = $stockMap[$pId]['C'] ?? 0.0;
            $total = round($mixed + $gradeA + $gradeB + $gradeC, 3);

            return [
                'product_id' => $pId,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit ?? 'kg',
                'category_name' => $product->category?->name ?? 'Produce',
                'mixed_qty' => $mixed,
                'grade_a_qty' => $gradeA,
                'grade_b_qty' => $gradeB,
                'grade_c_qty' => $gradeC,
                'total_stock' => $total,
            ];
        });

        if ($hasStockOnly) {
            $items = $items->filter(fn (array $item): bool => $item['total_stock'] > 0.001)->values();
        }

        $summaryTotals = $warehouseId ? $this->calculateWarehouseTotals($warehouseId) : [
            'total_mixed' => 0.0,
            'total_grade_a' => 0.0,
            'total_grade_b' => 0.0,
            'total_grade_c' => 0.0,
            'total_all' => 0.0,
        ];

        return ApiResponse::success([
            'data' => $items,
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'summary_totals' => $summaryTotals,
        ]);
    }

    /**
     * Return JSON transfer details for a product (current stock balances & eligible source batches per grade).
     */
    public function productDetails(Request $request, ?Product $product = null): JsonResponse
    {
        if (! $product || ! $product->exists) {
            $productId = $request->integer('product_id');
            $product = Product::with('category')->findOrFail($productId);
        } else {
            $product->loadMissing('category');
        }

        $warehouseId = $request->integer('warehouse_id') ?: null;
        if (! $warehouseId) {
            $warehouseId = $this->productWarehouseResolver->resolve(
                explicitWarehouse: null,
                category: $product->category_id,
                produceType: $product->category?->name
            );
        }

        $details = $this->buildProductTransferDetails($product, (int) $warehouseId);

        return ApiResponse::success($details);
    }

    /**
     * Execute grade stock transfer.
     */
    public function transfer(GradeStockTransferRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $product = Product::findOrFail((int) $validated['product_id']);
        $warehouse = Warehouse::findOrFail((int) $validated['warehouse_id']);
        $sourceGrade = ProductGrade::tryResolve($validated['source_grade']);
        $targetGrade = ProductGrade::tryResolve($validated['target_grade']);
        $quantity = (float) $validated['quantity'];
        $notes = $validated['notes'] ?? null;
        $clientSubmissionId = $validated['client_submission_id'] ?? null;

        $sourceBatch = null;
        if (! empty($validated['source_batch_id'])) {
            $sourceBatch = StockBatch::findOrFail((int) $validated['source_batch_id']);
        }

        $result = $this->transferAction->execute(
            product: $product,
            warehouse: $warehouse,
            sourceGrade: $sourceGrade,
            targetGrade: $targetGrade,
            quantity: $quantity,
            userId: (int) $request->user()->id,
            sourceBatch: $sourceBatch,
            notes: $notes,
            clientSubmissionId: $clientSubmissionId
        );

        $msg = "Successfully transferred {$quantity} kg from Grade {$sourceGrade->value} to Grade {$targetGrade->value}.";

        if ($request->wantsJson()) {
            return ApiResponse::success([
                'result' => $result,
                'updated_stock' => $this->buildProductStockBreakdown($product->id, $warehouse->id),
            ], $msg);
        }

        return redirect()->route('inventory.grade-stock.index', [
            'warehouse_id' => $warehouse->id,
            'tab' => 'transfer',
            'product_id' => $product->id,
        ])->with('success', $msg);
    }

    /**
     * Reverse a prior grade transfer.
     */
    public function reverse(Request $request, StockBatch $childBatch): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
            'client_submission_id' => ['nullable', 'string', 'max:64'],
        ]);

        $quantity = (float) $validated['quantity'];
        $notes = $validated['notes'] ?? null;
        $clientSubmissionId = $validated['client_submission_id'] ?? null;

        $result = $this->transferAction->reverse(
            destinationBatch: $childBatch,
            quantityToReverse: $quantity,
            userId: (int) $request->user()->id,
            notes: $notes,
            clientSubmissionId: $clientSubmissionId
        );

        $msg = "Successfully reversed {$quantity} kg back to source grade.";

        if ($request->wantsJson()) {
            return ApiResponse::success($result, $msg);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Calculate global warehouse summary totals across all produce.
     *
     * @return array{total_mixed: float, total_grade_a: float, total_grade_b: float, total_grade_c: float, total_all: float}
     */
    private function calculateWarehouseTotals(int $warehouseId): array
    {
        // 1. Mixed total: from confirmed pending batches with positive available mixed qty
        $pendingBatches = StockBatch::query()
            ->where('warehouse_id', $warehouseId)
            ->where('warehouse_receive_pending', false)
            ->where('status', BatchStatus::Pending)
            ->where(function ($q): void {
                $q->whereIn('purchase_grade', ['U', 'MIXED', 'Unsorted'])
                    ->orWhereNull('purchase_grade');
            })
            ->get();

        $totalMixed = round((float) $pendingBatches->sum(fn (StockBatch $b) => $b->available_mixed_qty), 3);

        // 2. Graded totals from StockMovement in warehouse
        $movementSums = StockMovement::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('grade', ['A', 'B', 'C'])
            ->selectRaw("grade, SUM(CASE WHEN type IN ('in', 'sale_reversal') THEN quantity WHEN type IN ('out', 'wastage', 'sale', 'adjustment') THEN -quantity ELSE 0 END) as net_stock")
            ->groupBy('grade')
            ->get();

        $gradeA = round(max(0.0, (float) ($movementSums->firstWhere('grade', 'A')?->net_stock ?? 0.0)), 3);
        $gradeB = round(max(0.0, (float) ($movementSums->firstWhere('grade', 'B')?->net_stock ?? 0.0)), 3);
        $gradeC = round(max(0.0, (float) ($movementSums->firstWhere('grade', 'C')?->net_stock ?? 0.0)), 3);

        return [
            'total_mixed' => $totalMixed,
            'total_grade_a' => $gradeA,
            'total_grade_b' => $gradeB,
            'total_grade_c' => $gradeC,
            'total_all' => round($totalMixed + $gradeA + $gradeB + $gradeC, 3),
        ];
    }

    /**
     * Build product transfer details with grade balances and eligible batches per grade.
     *
     * @return array<string, mixed>
     */
    private function buildProductTransferDetails(Product $product, int $warehouseId): array
    {
        $stock = $this->buildProductStockBreakdown($product->id, $warehouseId);

        // Eligible batches per grade
        $batchesByGrade = [
            'U' => $this->mapBatchList($this->transferAction->findEligibleBatches($product->id, $warehouseId, ProductGrade::Unsorted), ProductGrade::Unsorted),
            'A' => $this->mapBatchList($this->transferAction->findEligibleBatches($product->id, $warehouseId, ProductGrade::GradeA), ProductGrade::GradeA),
            'B' => $this->mapBatchList($this->transferAction->findEligibleBatches($product->id, $warehouseId, ProductGrade::GradeB), ProductGrade::GradeB),
            'C' => $this->mapBatchList($this->transferAction->findEligibleBatches($product->id, $warehouseId, ProductGrade::GradeC), ProductGrade::GradeC),
        ];

        // Reversible transferred child batches
        $reversibleBatches = StockBatch::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->whereNotNull('parent_batch_id')
            ->where('status', BatchStatus::Sorted)
            ->orderByDesc('id')
            ->get()
            ->filter(fn (StockBatch $b): bool => $this->transferAction->availableQuantityOnBatch($b, ProductGrade::tryResolve($b->purchase_grade) ?? ProductGrade::GradeA) > 0.001)
            ->map(fn (StockBatch $b): array => [
                'id' => (int) $b->id,
                'reference' => $b->reference,
                'parent_batch_id' => (int) $b->parent_batch_id,
                'grade' => $b->purchase_grade,
                'available_qty' => $this->transferAction->availableQuantityOnBatch($b, ProductGrade::tryResolve($b->purchase_grade) ?? ProductGrade::GradeA),
                'created_at' => $b->created_at?->format('d M Y H:i'),
            ])
            ->values()
            ->all();

        return [
            'product' => [
                'id' => (int) $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit ?? 'kg',
                'category_name' => $product->category?->name ?? 'Produce',
            ],
            'warehouse_id' => $warehouseId,
            'stock' => $stock,
            'batches_by_grade' => $batchesByGrade,
            'batches' => $batchesByGrade['U'], // Backward compatibility
            'reversible_batches' => $reversibleBatches,
        ];
    }

    /**
     * Build canonical stock breakdown for a product and warehouse.
     *
     * @return array{mixed: float, grade_a: float, grade_b: float, grade_c: float, total: float}
     */
    private function buildProductStockBreakdown(int $productId, int $warehouseId): array
    {
        $rawStocks = $this->stockMovementRepository->currentStockByProductAndGrade(
            warehouseId: $warehouseId,
            productId: $productId
        );

        $mixed = round(max(0.0, (float) $rawStocks->whereIn('grade', ['U', 'MIXED', 'Unsorted'])->sum('current_stock')), 3);
        $gradeA = round(max(0.0, (float) $rawStocks->where('grade', 'A')->sum('current_stock')), 3);
        $gradeB = round(max(0.0, (float) $rawStocks->where('grade', 'B')->sum('current_stock')), 3);
        $gradeC = round(max(0.0, (float) $rawStocks->where('grade', 'C')->sum('current_stock')), 3);

        return [
            'mixed' => $mixed,
            'grade_a' => $gradeA,
            'grade_b' => $gradeB,
            'grade_c' => $gradeC,
            'total' => round($mixed + $gradeA + $gradeB + $gradeC, 3),
        ];
    }

    /**
     * Map batch models to lightweight array format with available quantity.
     *
     * @param  Collection<int, StockBatch>  $batches
     * @return array<int, array<string, mixed>>
     */
    private function mapBatchList(Collection $batches, ProductGrade $grade): array
    {
        return $batches->map(fn (StockBatch $b): array => [
            'id' => (int) $b->id,
            'reference' => $b->reference,
            'product_id' => (int) $b->product_id,
            'warehouse_id' => (int) $b->warehouse_id,
            'warehouse_name' => $b->warehouse?->name ?? 'Warehouse',
            'received_at' => $b->received_at?->format('d M Y') ?? '',
            'original_total_kg' => (float) $b->total_kg,
            'available_qty' => $this->transferAction->availableQuantityOnBatch($b, $grade),
            'cost_per_kg' => (float) $b->cost_per_kg,
            'purchase_grade' => $b->purchase_grade ?? $grade->value,
            'status' => $b->status->value,
            'is_eligible' => true,
        ])->all();
    }
}
