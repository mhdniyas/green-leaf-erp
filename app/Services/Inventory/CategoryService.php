<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\DTOs\Inventory\CategoryData;
use App\Models\Category;
use App\Repositories\Inventory\CategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $repository,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->findActive($perPage);
    }

    public function allActive(): Collection
    {
        return $this->repository->findAllActive();
    }

    public function create(CategoryData $data): Category
    {
        return $this->repository->create($data->toArray());
    }

    public function update(Category $category, CategoryData $data): Category
    {
        return $this->repository->update($category, $data->toArray());
    }

    public function delete(Category $category): void
    {
        $this->repository->delete($category);
    }
}
