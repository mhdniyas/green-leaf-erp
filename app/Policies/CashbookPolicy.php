<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CashbookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isMainAdmin();
    }

    public function view(User $user): bool
    {
        return $user->isMainAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isMainAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isMainAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isMainAdmin();
    }

    public function manage(User $user): bool
    {
        return $user->isMainAdmin();
    }
}
