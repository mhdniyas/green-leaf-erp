<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.customer.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('sales.customer.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales.customer.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('sales.customer.update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('sales.customer.delete');
    }
}
