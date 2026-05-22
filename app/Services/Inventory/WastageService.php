<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\DTOs\Inventory\WastageEntryData;
use App\Models\WastageEntry;
use App\Repositories\Inventory\WastageEntryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WastageService
{
    public function __construct(
        private readonly WastageEntryRepository $repository,
    ) {}

    public function paginate(int $perPage = 15, ?int $productId = null, ?string $date = null): LengthAwarePaginator
    {
        return $this->repository->paginateFiltered($perPage, $productId, $date);
    }

    public function record(WastageEntryData $data, int $userId): WastageEntry
    {
        return $this->repository->create(array_merge(
            $data->toArray(),
            ['recorded_by' => $userId]
        ));
    }

    public function todayTotalCost(): float
    {
        return $this->repository->totalCostForPeriod(today()->toDateString(), today()->toDateString());
    }
}
