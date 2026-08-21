<?php

declare(strict_types=1);

namespace App\Repositories\Purchasing;

use App\Models\Supplier;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Supplier::class;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()->with('purchaseInvoices')->paginate($perPage);
    }
}
