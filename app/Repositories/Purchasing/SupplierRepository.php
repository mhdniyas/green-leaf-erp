<?php

declare(strict_types=1);

namespace App\Repositories\Purchasing;

use App\Models\Supplier;
use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SupplierRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Supplier::class;
    }

    public function paginate(int $perPage = 15, ?User $user = null): LengthAwarePaginator
    {
        $user ??= auth()->user();
        $query = $user ? $user->scopedSuppliersQuery() : $this->query();

        return $query->with('purchaseInvoices')->paginate($perPage);
    }

    public function all(?User $user = null): Collection
    {
        $user ??= auth()->user();
        $query = $user ? $user->scopedSuppliersQuery() : $this->query();

        return $query->get();
    }
}
