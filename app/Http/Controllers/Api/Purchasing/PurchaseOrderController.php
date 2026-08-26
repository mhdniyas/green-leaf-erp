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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $orders = $this->service->paginateFiltered($validated, $perPage);

        \Log::info('Purchasing orders index requested', [
            'url' => $request->fullUrl(),
            'filters' => $validated,
            'user' => $request->user()?->id,
            'total' => $orders->total(),
            'items' => collect($orders->items())->map(fn ($o) => ['po_number' => $o->po_number, 'status' => $o->status->value ?? $o->status])->toArray(),
        ]);

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

    public function show(PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('view', $order);

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
