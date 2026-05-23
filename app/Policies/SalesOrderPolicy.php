<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.order.view');
    }

    public function view(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('sales.order.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales.order.create');
    }

    public function update(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('sales.order.create');
    }

    public function confirm(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('sales.order.confirm');
    }

    public function dispatch(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('sales.order.confirm');
    }

    public function cancel(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('sales.order.cancel');
    }

    public function delete(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('sales.order.cancel');
    }
}
