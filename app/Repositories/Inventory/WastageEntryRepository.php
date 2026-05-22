<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Models\WastageEntry;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WastageEntryRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return WastageEntry::class;
    }

    public function paginateFiltered(int $perPage = 15, ?int $productId = null, ?string $date = null): LengthAwarePaginator
    {
        return $this->query()
            ->with(['product', 'recordedBy', 'batch'])
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($date, fn ($q) => $q->whereDate('wastage_date', $date))
            ->orderByDesc('wastage_date')
            ->paginate($perPage);
    }

    public function totalCostForPeriod(string $from, string $to): float
    {
        return (float) $this->query()
            ->whereBetween('wastage_date', [$from, $to])
            ->selectRaw('SUM(quantity * cost_per_kg) as total')
            ->value('total');
    }
}
