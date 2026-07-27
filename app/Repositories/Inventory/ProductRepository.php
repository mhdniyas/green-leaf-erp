<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Models\Product;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Product::class;
    }

    public function paginateFiltered(int $perPage = 15, ?int $categoryId = null, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->query()
            ->with(['category', 'statusChangedBy:id,name'])
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('is_active')
            ->ordered()
            ->paginate($perPage);
    }

    public function findAllActive(): Collection
    {
        return $this->query()
            ->with(['category'])
            ->where('is_active', true)
            ->ordered()
            ->get();
    }

    public function findBySkuOrFail(string $sku): Product
    {
        return $this->query()->where('sku', $sku)->firstOrFail();
    }
}
