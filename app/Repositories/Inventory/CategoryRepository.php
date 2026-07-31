<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Models\Category;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Category::class;
    }

    public function findActive(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findAllActive(): Collection
    {
        return $this->query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function paginateFiltered(int $perPage = 15, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('name')
            ->paginate($perPage);
    }
}

