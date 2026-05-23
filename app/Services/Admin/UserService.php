<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\DTOs\Admin\UserData;
use App\Models\User;
use App\Repositories\Admin\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {}

    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return $this->repository->paginateFiltered($perPage, $search);
    }

    public function create(UserData $data): User
    {
        return DB::transaction(function () use ($data) {
            /** @var User $user */
            $user = $this->repository->create($data->toArray());

            $user->syncRoles($data->roles);
            $user->syncPermissions($data->permissions);

            return $user;
        });
    }

    public function update(User $user, UserData $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user = $this->repository->update($user, $data->toArray());

            $user->syncRoles($data->roles);
            $user->syncPermissions($data->permissions);

            return $user;
        });
    }

    public function delete(User $user): void
    {
        $this->repository->delete($user);
    }
}
