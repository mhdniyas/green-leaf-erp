<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Warehouse\SaveWarehouseLoadoutItemsRequest;
use App\Http\Requests\Api\Warehouse\WarehouseLoadoutIndexRequest;
use App\Models\ShopOrder;
use App\Models\Warehouse;
use App\Services\Warehouse\WarehouseLoadoutCompletionService;
use App\Services\Warehouse\WarehouseScopedLoadoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseScopedLoadoutController extends Controller
{
    public function __construct(
        private readonly WarehouseScopedLoadoutService $service,
        private readonly WarehouseLoadoutCompletionService $completionService,
    ) {}

    public function index(WarehouseLoadoutIndexRequest $request, Warehouse $warehouse): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$this->service->orders($request->user(), $warehouse, $request->validated()),
        ]);
    }

    public function show(Request $request, Warehouse $warehouse, ShopOrder $shopOrder): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$this->service->detail($request->user(), $warehouse, $shopOrder),
        ]);
    }

    public function updateItems(
        SaveWarehouseLoadoutItemsRequest $request,
        Warehouse $warehouse,
        ShopOrder $shopOrder,
    ): JsonResponse {
        if ($shopOrder->hasMultiGradeItems() && ! $this->isCompatibleMultiGradeClient($request)) {
            return response()->json([
                'success' => false,
                'code' => 'MULTI_GRADE_MOBILE_RESTRICTED',
                'message' => 'This order contains multi-grade items (Grade B/C). Mobile warehouse loadout is restricted for this order. Please complete loadout using the Web Warehouse portal.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Warehouse loadout items saved successfully.',
            ...$this->service->save(
                $request->user(),
                $warehouse,
                $shopOrder,
                $request->validated('items'),
            ),
        ]);
    }

    public function complete(Request $request, Warehouse $warehouse, ShopOrder $shopOrder): JsonResponse
    {
        if ($shopOrder->hasMultiGradeItems() && ! $this->isCompatibleMultiGradeClient($request)) {
            return response()->json([
                'success' => false,
                'code' => 'MULTI_GRADE_MOBILE_RESTRICTED',
                'message' => 'Completing multi-grade orders is restricted on mobile. Please complete loadout using the Web Warehouse portal.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Warehouse loadout completed successfully.',
            ...$this->completionService->complete($request->user(), $warehouse, $shopOrder),
        ]);
    }

    private function isCompatibleMultiGradeClient(Request $request): bool
    {
        return $request->header('X-Client-Supports-Multi-Grade') === 'true'
            || $request->header('X-Client-Supports-Multi-Grade') === '1'
            || $request->header('X-Warehouse-Client-Supports-Multi-Grade') === 'true'
            || $request->header('X-Warehouse-Client-Supports-Multi-Grade') === '1';
    }
}
