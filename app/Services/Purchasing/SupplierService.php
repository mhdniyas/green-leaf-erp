<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\DTOs\Purchasing\SupplierData;
use App\Models\Supplier;
use App\Repositories\Purchasing\SupplierRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($data): Supplier {
            $payload = $data->toArray();

            if ($payload['is_default_purchase']) {
                $this->clearDefaultPurchaseSupplier();
            }

            return $this->repository->create($payload);
        });
    }

    public function update(Supplier $supplier, SupplierData $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data): Supplier {
            $payload = $data->toArray();

            if ($payload['is_default_purchase']) {
                $this->clearDefaultPurchaseSupplier($supplier->id);
            }

            if ($payload['category'] !== 'own_purchase') {
                $payload['is_default_purchase'] = false;
            }

            return $this->repository->update($supplier, $payload);
        });
    }

    public function delete(Supplier $supplier): void
    {
        $this->repository->delete($supplier);
    }

    private function clearDefaultPurchaseSupplier(?int $exceptSupplierId = null): void
    {
        Supplier::query()
            ->where('category', 'own_purchase')
            ->when($exceptSupplierId !== null, fn ($query) => $query->whereKeyNot($exceptSupplierId))
            ->update(['is_default_purchase' => false]);
    }
}
