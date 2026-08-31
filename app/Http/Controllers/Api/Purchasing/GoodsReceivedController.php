<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchasing;

use App\DTOs\Purchasing\GoodsReceivedData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchasing\StoreGoodsReceivedRequest;
use App\Http\Resources\Purchasing\GoodsReceivedResource;
use App\Http\Resources\Purchasing\GoodsReceivedSummaryResource;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\AutoAdvanceClearExecutionService;
use App\Services\Purchasing\AutoAdvanceClearPlanningService;
use App\Services\Purchasing\GoodsReceivedService;
use App\Services\Purchasing\WarehouseReceiptReadScope;
use App\Services\Purchasing\WarehouseReceiptStateResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GoodsReceivedController extends Controller
{
    public function __construct(
        private readonly GoodsReceivedService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', GoodsReceived::class);

        $filters = $request->validate([
            'bill_status' => ['nullable', 'in:bill_pending,bill_available'],
            'receipt_type' => ['nullable', 'in:warehouse_advance,normal_purchase'],
            'receipt_status' => ['nullable', 'in:pending,received'],
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);
        $filters['authorized_warehouse_ids'] = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null);
        if ($request->filled('warehouse_id')) {
            $warehouse = Warehouse::findOrFail($request->integer('warehouse_id'));
            abort_unless(
                $request->user()?->hasRole('admin') || $request->user()?->canAccessWarehouse($warehouse),
                403,
                'Unauthorized warehouse access.'
            );
        }
        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $grns = $this->service->paginate($filters, $perPage);

        return ApiResponse::paginated(GoodsReceivedSummaryResource::collection($grns));
    }

    public function store(StoreGoodsReceivedRequest $request): JsonResponse
    {
        $grn = $this->service->create(
            GoodsReceivedData::fromRequest($request),
            (int) $request->user()->id
        );

        $msg = $grn->bill_status === 'bill_pending'
            ? 'Goods received note recorded (BILL PENDING)'
            : 'Goods received note recorded successfully';

        return ApiResponse::success(
            new GoodsReceivedResource($grn->load(['purchaseOrder.supplier', 'items.product', 'receivedBy'])),
            $msg,
            201
        );
    }

    public function show(Request $request, GoodsReceived $grn): JsonResponse
    {
        Gate::authorize('view', $grn);
        $scope = app(WarehouseReceiptReadScope::class);
        abort_unless($scope->receipts(GoodsReceived::query()->whereKey($grn->id), $scope->warehouseIds($request->user()))->exists(), 403);

        return ApiResponse::success(
            new GoodsReceivedResource($grn->load([
                'purchaseOrder.supplier',
                'items.product',
                'receivedBy',
                'updatedBy',
                'purchaseInvoices',
                'billReconciliation.lines.product',
                'billReconciliation.confirmedByUser',
                'advanceMatchesAsBill.advanceGoodsReceived',
                'advanceMatchesAsBill.advanceStockBatch',
            ]))
        );
    }

    public function linkBill(Request $request, GoodsReceived $grn): JsonResponse
    {
        return $this->matchBill($request, $grn);
    }

    public function matchBill(Request $request, GoodsReceived $grn): JsonResponse
    {
        $this->authorizeAdminOrPurchaser($request);

        $validated = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'bill_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->service->matchBill($grn, $validated, (int) $request->user()->id);

        return ApiResponse::success(
            new GoodsReceivedResource($updated),
            'Vendor bill matched and receipt cleared successfully'
        );
    }

    public function advanceMatchSuggestions(Request $request): JsonResponse
    {
        $this->authorizeAdminOrPurchaser($request);

        $poId = $request->integer('purchase_order_id');
        $po = PurchaseOrder::findOrFail($poId);

        $warehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;
        $suggestions = app(AdvanceReceiveReconciliationService::class)->getSuggestionsForOrder($po, $warehouseId);

        return ApiResponse::success(
            $suggestions,
            'Advance match suggestions retrieved successfully'
        );
    }

    public function advanceMatchCandidates(Request $request): JsonResponse
    {
        $this->authorizeAdminOrPurchaser($request);

        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'date' => ['nullable', 'date'],
            'date_before' => ['nullable', 'date'],
            'period' => ['nullable', 'in:today,older,all'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $validated['authorized_warehouse_ids'] = app(WarehouseReceiptReadScope::class)->warehouseIds(
            $request->user(),
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null
        );

        $perPage = (int) ($validated['per_page'] ?? 25);
        $candidates = app(AdvanceReceiveReconciliationService::class)->paginateMatchCandidates($validated, $perPage);

        return ApiResponse::paginated($candidates);
    }

    public function autoClearPreview(Request $request): JsonResponse
    {
        $this->authorizeAdminOrPurchaser($request);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $warehouseId);

        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan(
            $warehouseId,
            (int) $request->user()->id
        );

        return ApiResponse::success($plan, 'Auto-match clear preview generated successfully');
    }

    public function autoClearExecute(Request $request): JsonResponse
    {
        $this->authorizeAdminOrPurchaser($request);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'plan_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'client_submission_id' => ['required', 'string', 'uuid'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $warehouseId);

        $result = app(AutoAdvanceClearExecutionService::class)->execute(
            $warehouseId,
            (string) $validated['plan_hash'],
            (string) $validated['client_submission_id'],
            (int) $request->user()->id
        );

        if (isset($result['status_code']) && $result['status_code'] === 409) {
            return response()->json($result['error'], 409);
        }

        return ApiResponse::success($result, 'Automatic advance reconciliation completed');
    }

    public function pendingSuggestions(Request $request): JsonResponse
    {
        $this->authorizeAdminOrPurchaser($request);

        $params = $request->only(['destination_shop_id', 'warehouse_id', 'date', 'product_ids']);
        $suggestions = $this->service->suggestPendingReceipts($params);

        return ApiResponse::success(
            GoodsReceivedResource::collection($suggestions),
            'Pending receipts fetched successfully'
        );
    }

    public function updateItems(Request $request, GoodsReceived $grn): JsonResponse
    {
        $this->authorizeAdminOrPurchaser($request);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
        ]);

        $updated = $this->service->updateItems($grn, $validated['items'], (int) $request->user()->id);

        return ApiResponse::success(
            new GoodsReceivedResource($updated),
            'Received quantities updated and inventory adjusted'
        );
    }

    public function receiveCounts(Request $request): JsonResponse
    {
        $this->authorizeAdminOrPurchaser($request);

        $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'date' => ['nullable', 'date'],
        ]);

        $today = $request->input('date', now()->toDateString());
        $warehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;
        $authWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $warehouseId);

        // Pending POs
        $pendingPoQuery = PurchaseOrder::query()
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->where(function ($pending): void {
                $pending->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'))
                    ->orWhere(function ($withoutReceipt): void {
                        $withoutReceipt->whereDoesntHave('goodsReceiveds')->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received']);
                    });
            });
        app(WarehouseReceiptReadScope::class)->orders($pendingPoQuery, $authWarehouseIds);

        $pendingToday = (clone $pendingPoQuery)->whereDate('order_date', $today)->count();
        $pendingOlder = (clone $pendingPoQuery)->whereDate('order_date', '<', $today)->count();
        $pendingTotal = (clone $pendingPoQuery)->count();

        // Active Match Candidates
        $matchCandidateQuery = PurchaseOrder::query()
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->where(function ($pending): void {
                $pending->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'))
                    ->orWhere(function ($withoutReceipt): void {
                        $withoutReceipt->whereDoesntHave('goodsReceiveds')->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received']);
                    });
            });
        app(WarehouseReceiptReadScope::class)->orders($matchCandidateQuery, $authWarehouseIds);

        $matchToday = (clone $matchCandidateQuery)->whereDate('order_date', $today)->count();
        $matchOlder = (clone $matchCandidateQuery)->whereDate('order_date', '<', $today)->count();
        $matchTotal = (clone $matchCandidateQuery)->count();

        // Received Today
        $receivedQuery = GoodsReceived::query()->whereDate('received_at', $today);
        app(WarehouseReceiptStateResolver::class)->filter($receivedQuery, 'received');
        app(WarehouseReceiptReadScope::class)->receipts($receivedQuery, $authWarehouseIds);
        $receivedToday = $receivedQuery->count();

        // Open Advance
        $advanceQuery = GoodsReceived::query()->openWarehouseAdvance();
        app(WarehouseReceiptReadScope::class)->receipts($advanceQuery, $authWarehouseIds);
        $openAdvance = $advanceQuery->count();

        return ApiResponse::success([
            'pending_today' => $pendingToday,
            'pending_older' => $pendingOlder,
            'pending_total' => $pendingTotal,
            'match_today' => $matchToday,
            'match_older' => $matchOlder,
            'match_total' => $matchTotal,
            'received_today' => $receivedToday,
            'open_advance' => $openAdvance,
        ], 'Receive counts fetched successfully');
    }

    private function authorizeAdminOrPurchaser(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->hasRole(['admin', 'purchase', 'purchaser', 'warehouse_receiver'])
            || $user->canAny(['purchasing.grn.create', 'purchasing.grn.approve', 'warehouse.receive.view'])) {
            return;
        }

        abort(403, 'Unauthorized.');
    }
}
