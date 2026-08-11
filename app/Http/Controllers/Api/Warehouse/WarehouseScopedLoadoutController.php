<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Warehouse\SaveWarehouseLoadoutItemsRequest;
use App\Http\Requests\Api\Warehouse\WarehouseLoadoutIndexRequest;
use App\Models\ShopOrder;
use App\Models\Warehouse;
use App\Services\Warehouse\WarehouseScopedLoadoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseScopedLoadoutController extends Controller
{
    public function __construct(
        private readonly WarehouseScopedLoadoutService $service,
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
}
