<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\DTOs\Inventory\StockBatchData;
use App\Models\StockBatch;
use App\Repositories\Inventory\StockBatchRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
        $this->repository->delete($batch);
    }
}
