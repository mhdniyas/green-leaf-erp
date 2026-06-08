<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\DTOs\Inventory\WastageEntryData;
use App\Models\WastageEntry;
use App\Repositories\Inventory\WastageEntryRepository;
use App\Services\Finance\JournalService;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WastageService
{
    public function __construct(
        private readonly WastageEntryRepository $repository,
        private readonly JournalService $journalService,
        private readonly PriceBoardService $priceBoardService,
    ) {}

    public function paginate(int $perPage = 15, ?int $productId = null, ?string $date = null): LengthAwarePaginator
    {
        return $this->repository->paginateFiltered($perPage, $productId, $date);
    }

    public function record(WastageEntryData $data, int $userId): WastageEntry
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var WastageEntry $wastage */
            $wastage = $this->repository->create(array_merge(
                $data->toArray(),
                ['recorded_by' => $userId]
            ));

            // Post General Ledger entries
            $this->journalService->recordWastage($wastage);

            $this->priceBoardService->refreshWholesalePricesForProducts(
                [$wastage->product_id],
                'wastage',
                $wastage->id ? (string) $wastage->id : null
            );

            return $wastage;
        });
    }

    public function todayTotalCost(): float
    {
        return $this->repository->totalCostForPeriod(today()->toDateString(), today()->toDateString());
    }
}
