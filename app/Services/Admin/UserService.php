<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\DTOs\Admin\UserData;
use App\Models\User;
use App\Repositories\Admin\UserRepository;
use App\Services\HR\EmployeeSyncService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly EmployeeSyncService $employeeSyncService,
    ) {}

    public function paginate(int $perPage = 15, ?string $search = null, string $scope = 'all', ?string $role = null): LengthAwarePaginator
    {
        return $this->repository->paginateFiltered($perPage, $search, $scope, $role);
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    public function roleCounts(): array
    {
        return $this->repository->roleCounts();
    }

    public function paginateForUserAccess(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->repository->paginateForUserAccess($perPage, $search);
    }

    /**
     * @return array{eligible_total:int, approved_total:int}
     */
    public function userAccessSummary(?string $search = null): array
    {
        return $this->repository->userAccessSummary($search);
    }

    public function create(UserData $data): User
    {
        return DB::transaction(function () use ($data) {
            /** @var User $user */
            $user = $this->repository->create($data->toArray());

            $user->syncRoles($data->roles);
            $user->syncPermissions([]);
            $this->employeeSyncService->ensureForUser($user->fresh());

            return $user;
        });
    }

    public function update(User $user, UserData $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user = $this->repository->update($user, $data->toArray());

            $user->syncRoles($data->roles);
            $user->syncPermissions([]);
            $this->employeeSyncService->ensureForUser($user->fresh());

            return $user;
        });
    }

    public function delete(User $user): void
    {
        $this->repository->delete($user);
    }

    public function approve(User $user, User $approver): User
    {
        return DB::transaction(function () use ($user, $approver): User {
            $user->forceFill([
                'registration_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $approver->id,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if ($user->shop !== null) {
                $user->shop->update([
                    'status' => 'active',
                    'approved_at' => now(),
                ]);
            }

            $this->employeeSyncService->ensureForUser($user);

            return $user->fresh(['shop']);
        });
    }
}
