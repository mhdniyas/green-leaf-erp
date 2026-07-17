<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GoodsReceived;
use App\Models\User;

class GoodsReceivedPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('purchasing.grn.view') || $user->can('warehouse.receive.view');
    }

    public function view(User $user, GoodsReceived $grn): bool
    {
        return $user->can('purchasing.grn.view') || $user->can('warehouse.receive.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchasing.grn.create') || $user->can('warehouse.receive.confirm');
    }

    public function recheck(User $user, ?GoodsReceived $grn = null): bool
    {
        if ($grn === null) {
            return $user->hasRole('admin');
        }

        return $user->hasRole('admin') && $grn->status === 'approved';
    }

    public function update(User $user, ?GoodsReceived $grn = null): bool
    {
        if ($grn === null) {
            return $user->can('purchasing.grn.create') || $user->can('warehouse.receive.confirm');
        }

        return ($user->can('purchasing.grn.create') || $user->can('warehouse.receive.confirm'))
            && in_array($grn->status, ['draft', 'recheck_required'], true);
    }
}
