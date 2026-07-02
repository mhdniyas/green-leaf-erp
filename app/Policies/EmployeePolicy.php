<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.employee.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('hr.employee.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.employee.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('hr.employee.update');
    }
}
