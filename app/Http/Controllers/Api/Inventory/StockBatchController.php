<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inventory;

use App\DTOs\Inventory\StockBatchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventory\StoreStockBatchRequest;
use App\Http\Resources\Inventory\StockBatchResource;
use App\Models\StockBatch;
use App\Services\Inventory\StockBatchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockBatchController extends Controller
{
    public function __construct(
        private readonly StockBatchService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $batches = $this->service->paginate(
            perPage: 15,
            status: $request->string('status')->toString() ?: null,
        );

        return ApiResponse::paginated(StockBatchResource::collection($batches));
    }

    public function store(StoreStockBatchRequest $request): JsonResponse
    {
        $batch = $this->service->create(
            StockBatchData::fromRequest($request),
            $request->user()->id
        );

        return ApiResponse::success(new StockBatchResource($batch->load('product')), 'Batch created successfully', 201);
    }

    public function show(StockBatch $stockBatch): JsonResponse
    {
        return ApiResponse::success(new StockBatchResource($stockBatch->load(['product', 'stockMovements', 'wastageEntries'])));
    }

    public function destroy(StockBatch $stockBatch): JsonResponse
    {
        $this->authorize('delete', $stockBatch);
        $this->service->delete($stockBatch);

        return ApiResponse::success(null, 'Batch deleted successfully');
    }
}
