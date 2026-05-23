<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\DTOs\Sales\CustomerData;
use App\Models\Customer;
use App\Repositories\Sales\CustomerRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerService
{
    public function __construct(
        private readonly CustomerRepository $repository,
    ) {}

    public function paginate(int $perPage = 15, ?string $search = null, ?string $type = null): LengthAwarePaginator
    {
        return $this->repository->paginateFiltered($perPage, $search, $type);
    }

    public function all(): Collection
    {
        return $this->repository->query()->where('is_active', true)->orderBy('name')->get();
    }

    public function create(CustomerData $data): Customer
    {
        return $this->repository->create($data->toArray());
    }

    public function update(Customer $customer, CustomerData $data): Customer
    {
        return $this->repository->update($customer, $data->toArray());
    }

    public function delete(Customer $customer): void
    {
        $this->repository->delete($customer);
    }
}
