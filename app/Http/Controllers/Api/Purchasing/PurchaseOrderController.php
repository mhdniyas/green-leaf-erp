<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchasing;

use App\DTOs\Purchasing\PurchaseOrderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchasing\StorePurchaseOrderRequest;
use App\Http\Resources\Purchasing\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $orders = $this->service->paginate();

        return ApiResponse::paginated(PurchaseOrderResource::collection($orders));
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

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('view', $purchaseOrder);

        return ApiResponse::success(
            new PurchaseOrderResource($purchaseOrder->load(['supplier', 'items.product', 'createdBy']))
        );
    }

    public function approve(PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('approve', $purchaseOrder);

        $approved = $this->service->approve($purchaseOrder);

        return ApiResponse::success(
            new PurchaseOrderResource($approved->load(['supplier', 'items.product'])),
            'Purchase order approved successfully'
        );
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('delete', $purchaseOrder);

        $this->service->delete($purchaseOrder);

        return ApiResponse::success(null, 'Purchase order deleted successfully');
    }
}
