<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\DTOs\Inventory\StockBatchData;
use App\Enums\Inventory\StockMovementType;
use App\Models\StockBatch;
use App\Repositories\Inventory\StockBatchRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class StockBatchService
{
    public function __construct(
        private readonly StockBatchRepository $repository,
    ) {}

    public function paginate(int $perPage = 15, ?string $status = null): LengthAwarePaginator
    {
        return $this->repository->paginateFiltered($perPage, null, $status);
    }

    public function pendingBatches(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->findPending($perPage);
    }

    public function create(StockBatchData $data, int $userId): StockBatch
    {
        $reference = $this->repository->generateReference();

        return $this->repository->create(array_merge(
            $data->toArray(),
            [
                'created_by' => $userId,
                'reference' => $reference,
                'status' => 'pending',
            ]
        ));
    }

    public function delete(StockBatch $batch): void
    {
        if ($batch->segregatedBatches()->exists()) {
            throw ValidationException::withMessages([
                'batch' => 'Cannot delete batch with active segregated child batches. Reverse segregations first.',
            ]);
        }

        if ($batch->stockMovements()->whereIn('type', [
            StockMovementType::Out->value,
            StockMovementType::Sale->value,
        ])->exists()) {
            throw ValidationException::withMessages([
                'batch' => 'Cannot delete batch with recorded stock dispatches.',
            ]);
        }

        $this->repository->delete($batch);
    }
}
