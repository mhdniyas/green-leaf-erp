<?php

declare(strict_types=1);

namespace App\Repositories\Sales;

use App\Models\Customer;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Customer::class;
    }

    public function paginateFiltered(int $perPage = 15, ?string $search = null, ?string $type = null): LengthAwarePaginator
    {
        return $this->query()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('name')
            ->paginate($perPage);
    }
}
