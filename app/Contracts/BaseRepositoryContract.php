<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;

interface BaseRepositoryContract
{
    public function all(): Collection;

    public function paginate(int $perPage = 15): Paginator;

    public function create(array $data): Model;

    public function update(Model $model, array $data): Model;

    public function delete(Model $model): bool;

    public function find(int $id): ?Model;

    public function findOrFail(int $id): Model;
}
