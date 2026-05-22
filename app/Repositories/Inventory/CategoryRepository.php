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
}
