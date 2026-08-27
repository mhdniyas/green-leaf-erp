<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchasing;

use App\DTOs\Purchasing\GoodsReceivedData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchasing\StoreGoodsReceivedRequest;
use App\Http\Resources\Purchasing\GoodsReceivedResource;
use App\Http\Resources\Purchasing\GoodsReceivedSummaryResource;
use App\Models\GoodsReceived;
use App\Models\Warehouse;
use App\Services\Purchasing\GoodsReceivedService;
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

        $filters = $request->only(['bill_status', 'date', 'search', 'warehouse_id']);
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

    public function show(GoodsReceived $grn): JsonResponse
    {
        Gate::authorize('view', $grn);

        return ApiResponse::success(
            new GoodsReceivedResource($grn->load(['purchaseOrder.supplier', 'items.product', 'receivedBy', 'updatedBy', 'purchaseInvoices']))
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
