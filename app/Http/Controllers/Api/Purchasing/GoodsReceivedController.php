<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchasing;

use App\DTOs\Purchasing\GoodsReceivedData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchasing\StoreGoodsReceivedRequest;
use App\Http\Resources\Purchasing\GoodsReceivedResource;
use App\Models\GoodsReceived;
use App\Services\Purchasing\GoodsReceivedService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GoodsReceivedController extends Controller
{
    public function __construct(
        private readonly GoodsReceivedService $service,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', GoodsReceived::class);

        $grns = $this->service->paginate();

        return ApiResponse::paginated(GoodsReceivedResource::collection($grns));
    }

    public function store(StoreGoodsReceivedRequest $request): JsonResponse
    {
        $grn = $this->service->create(
            GoodsReceivedData::fromRequest($request),
            (int) $request->user()->id
        );

        return ApiResponse::success(
            new GoodsReceivedResource($grn->load(['purchaseOrder.supplier', 'items.product'])),
            'Goods received note recorded successfully',
            201
        );
    }

    public function show(GoodsReceived $goodsReceived): JsonResponse
    {
        Gate::authorize('view', $goodsReceived);

        return ApiResponse::success(
            new GoodsReceivedResource($goodsReceived->load(['purchaseOrder.supplier', 'items.product', 'receivedBy']))
        );
    }
}
