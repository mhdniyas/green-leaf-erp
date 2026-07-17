<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('purchasing.supplier.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('purchasing.supplier.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchasing.supplier.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('purchasing.supplier.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasRole('admin');
    }
}
