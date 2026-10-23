<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inventory;

use App\Actions\Inventory\ProcessPartialSegregationAction;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventory\ReverseSegregationRequest;
use App\Http\Requests\Api\Inventory\SegregateBatchRequest;
use App\Http\Resources\Inventory\StockBatchResource;
use App\Models\StockBatch;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SegregateBatchController extends Controller
{
    public function __construct(
        private readonly ProcessPartialSegregationAction $action,
    ) {}

    public function segregate(SegregateBatchRequest $request, StockBatch $batch): JsonResponse
    {
        try {
            $validated = $request->validated();
            $targetGrade = ProductGrade::tryResolve($validated['target_grade']) ?? ProductGrade::from($validated['target_grade']);
            $quantity = (float) $validated['quantity'];
            $notes = $validated['notes'] ?? null;
            $clientSubmissionId = $validated['client_submission_id'] ?? null;
            $reason = isset($validated['reason']) ? WastageReason::tryFrom($validated['reason']) : null;

            $result = $this->action->execute(
                $batch,
                $targetGrade,
                $quantity,
                (int) $request->user()->id,
                $notes,
                $clientSubmissionId,
                $reason
            );

            return ApiResponse::success(
                [
                    'parent' => new StockBatchResource($result['parent']),
                    'child' => $result['child'] ? new StockBatchResource($result['child']) : null,
                    'wastage' => $result['wastage'] ? $result['wastage'] : null,
                ],
                $result['child']
                    ? "Batch {$batch->reference} partially segregated into Grade {$targetGrade->value}"
                    : "Recorded {$quantity} kg unsellable damage from batch {$batch->reference}"
            );
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        }
    }

    public function reverse(ReverseSegregationRequest $request, StockBatch $batch): JsonResponse
    {
        try {
            $validated = $request->validated();
            $quantity = (float) $validated['quantity'];
            $notes = $validated['notes'] ?? null;
            $clientSubmissionId = $validated['client_submission_id'] ?? null;

            $result = $this->action->reverse(
                $batch,
                $quantity,
                (int) $request->user()->id,
                $notes,
                $clientSubmissionId
            );

            return ApiResponse::success(
                [
                    'parent' => new StockBatchResource($result['parent']),
                    'child' => new StockBatchResource($result['child']),
                ],
                "Reversed {$quantity} kg from batch {$batch->reference}"
            );
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        }
    }
}
