<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return User::class;
    }

    public function paginateFiltered(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return $this->query()
            ->with(['roles', 'permissions', 'shop'])
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($perPage);
    }
}
