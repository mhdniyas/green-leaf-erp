<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\User;

class ShopLedgerProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isMainAdmin();
    }

    public function view(User $user, ShopLedgerProfile $profile): bool
    {
        return $user->isMainAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isMainAdmin();
    }

    public function update(User $user, ShopLedgerProfile $profile): bool
    {
        return $user->isMainAdmin();
    }

    public function delete(User $user, ShopLedgerProfile $profile): bool
    {
        return $user->isMainAdmin();
    }
}
