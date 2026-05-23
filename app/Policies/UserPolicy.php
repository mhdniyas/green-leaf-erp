<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.user.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('admin.user.view');
    }

    public function create(User $user): bool
    {
        return $user->can('admin.user.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('admin.user.update');
    }

    public function delete(User $user, User $model): bool
    {
        // A user cannot delete themselves
        return $user->can('admin.user.delete') && $user->id !== $model->id;
    }
}
