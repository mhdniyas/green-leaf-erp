<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GoodsReceived;
use App\Models\User;

class GoodsReceivedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchasing.grn.view');
    }

    public function view(User $user, GoodsReceived $grn): bool
    {
        return $user->can('purchasing.grn.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchasing.grn.create');
    }
}
