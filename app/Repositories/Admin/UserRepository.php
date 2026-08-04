<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
            ->with(['roles', 'shop'])
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

    public function paginateForUserAccess(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->userAccessQuery($search)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array{eligible_total:int, approved_total:int}
     */
    public function userAccessSummary(?string $search = null): array
    {
        return [
            'eligible_total' => $this->userAccessQuery()->count(),
            'approved_total' => $this->userAccessQuery($search)
                ->where('registration_status', 'approved')
                ->count(),
        ];
    }

    private function userAccessQuery(?string $search = null): Builder
    {
        return $this->query()
            ->select(['id', 'public_uuid', 'name', 'email', 'shop_id', 'registration_status', 'last_seen_at'])
            ->with(['roles:id,name', 'shop:id,name'])
            ->whereDoesntHave('roles', function (Builder $roleQuery): void {
                $roleQuery->where('name', 'admin');
            })
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('roles', function (Builder $roleQuery) use ($search): void {
                            $roleQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('shop', function (Builder $shopQuery) use ($search): void {
                            $shopQuery->where('name', 'like', "%{$search}%");
                        });
                });
            });
    }
}
