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

    public function approve(User $user, ?GoodsReceived $grn = null): bool
    {
        if ($grn === null) {
            return $user->can('purchasing.grn.approve');
        }

        return $user->can('purchasing.grn.approve') && $grn->status === 'pending_approval';
    }

    public function reject(User $user, ?GoodsReceived $grn = null): bool
    {
        if ($grn === null) {
            return $user->can('purchasing.grn.approve');
        }

        return $user->can('purchasing.grn.approve') && $grn->status === 'pending_approval';
    }

    public function update(User $user, ?GoodsReceived $grn = null): bool
    {
        if ($grn === null) {
            return $user->can('purchasing.grn.create');
        }

        return $user->can('purchasing.grn.create') && in_array($grn->status, ['draft', 'rejected']);
    }
}
