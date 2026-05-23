<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\DTOs\Purchasing\SupplierData;
use App\Models\Supplier;
use App\Repositories\Purchasing\SupplierRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SupplierService
{
    public function __construct(
        private readonly SupplierRepository $repository,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function create(SupplierData $data): Supplier
    {
        return $this->repository->create($data->toArray());
    }

    public function update(Supplier $supplier, SupplierData $data): Supplier
    {
        return $this->repository->update($supplier, $data->toArray());
    }

    public function delete(Supplier $supplier): void
    {
        $this->repository->delete($supplier);
    }
}
