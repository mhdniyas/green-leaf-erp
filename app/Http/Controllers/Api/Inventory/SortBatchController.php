<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inventory;

use App\Actions\Inventory\ProcessBatchSortingAction;
use App\DTOs\Inventory\SortingData;
use App\Exceptions\Inventory\BatchAlreadySortedException;
use App\Exceptions\Inventory\SortingQuantityMismatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventory\SortBatchRequest;
use App\Http\Resources\Inventory\StockBatchResource;
use App\Models\StockBatch;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SortBatchController extends Controller
{
    public function __construct(
        private readonly ProcessBatchSortingAction $action,
    ) {}

    public function __invoke(SortBatchRequest $request, StockBatch $batch): JsonResponse
    {
        try {
            $data = SortingData::fromRequest($request);
            $sorted = $this->action->execute($batch, $data, $request->user()->id);

            return ApiResponse::success(
                new StockBatchResource($sorted),
                "Batch {$batch->reference} sorted successfully"
            );
        } catch (BatchAlreadySortedException $e) {
            return ApiResponse::error($e->getMessage(), code: 422);
        } catch (SortingQuantityMismatchException $e) {
            return ApiResponse::error($e->getMessage(), ['total' => $e->getMessage()], 422);
        }
    }
}
