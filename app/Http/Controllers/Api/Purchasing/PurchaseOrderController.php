<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchasing;

use App\DTOs\Purchasing\PurchaseOrderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchasing\StorePurchaseOrderRequest;
use App\Http\Resources\Purchasing\PurchaseOrderResource;
use App\Http\Resources\Purchasing\PurchaseOrderSummaryResource;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\WarehouseReceiptReadScope;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (isset($validated['warehouse_id'])) {
            $warehouse = Warehouse::findOrFail((int) $validated['warehouse_id']);
            abort_unless(
                $request->user()?->hasRole('admin') || $request->user()?->canAccessWarehouse($warehouse),
                403,
                'Unauthorized warehouse access.'
            );
        }

        $validated['authorized_warehouse_ids'] = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null);

        $perPage = (int) ($validated['per_page'] ?? (($validated['status'] ?? null) === 'received' ? 20 : 25));
        $orders = $this->service->paginateFiltered($validated, $perPage);

        return ApiResponse::paginated(PurchaseOrderSummaryResource::collection($orders));
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        // Already authorized at FormRequest level, but we check service create
        $order = $this->service->create(
            PurchaseOrderData::fromRequest($request),
            (int) $request->user()->id
        );

        return ApiResponse::success(
            new PurchaseOrderResource($order->load(['supplier', 'items.product'])),
            'Purchase order created successfully',
            201
        );
    }

    public function show(Request $request, PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('view', $order);
        $scope = app(WarehouseReceiptReadScope::class);
        abort_unless($scope->orders(PurchaseOrder::query()->whereKey($order->id), $scope->warehouseIds($request->user()))->exists(), 403);

        return ApiResponse::success(
            new PurchaseOrderResource($order->load(['supplier', 'items.product', 'createdBy']))
        );
    }

    public function approve(PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('approve', $order);

        $approved = $this->service->approve($order);

        return ApiResponse::success(
            new PurchaseOrderResource($approved->load(['supplier', 'items.product'])),
            'Purchase order approved successfully'
        );
    }

    public function destroy(PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('delete', $order);

        $this->service->delete($order);

        return ApiResponse::success(null, 'Purchase order deleted successfully');
    }
}
