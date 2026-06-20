<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

class UserRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return User::class;
    }

    public function paginateFiltered(int $perPage = 15, ?string $search = null, string $scope = 'all', ?string $role = null): LengthAwarePaginator
    {
        return $this->query()
            ->with(['roles', 'permissions', 'shop'])
            ->when($scope === 'pending', function ($query) {
                $query->where('registration_status', 'pending');
            })
            ->when($role, function ($query) use ($role) {
                $query->whereHas('roles', function ($roleQuery) use ($role): void {
                    $roleQuery->where('name', $role);
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderByRaw("CASE WHEN registration_status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    public function roleCounts(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'count' => User::query()->role($role->name)->count(),
            ])
            ->all();
    }
}
