<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeLeaveRequest;
use App\Models\User;

class EmployeeLeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.leave.view') || $user->can('hr.leave.submit-owned-shop');
    }

    public function view(User $user, EmployeeLeaveRequest $employeeLeaveRequest): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.leave.manage') || $user->can('hr.leave.submit-owned-shop');
    }

    public function update(User $user, EmployeeLeaveRequest $leaveRequest): bool
    {
        return $user->can('hr.leave.manage');
    }
}
