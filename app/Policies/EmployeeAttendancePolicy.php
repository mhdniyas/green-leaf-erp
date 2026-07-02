<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeAttendance;
use App\Models\User;

class EmployeeAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.attendance.view') || $user->can('hr.attendance.mark-owned-shop');
    }

    public function view(User $user, EmployeeAttendance $employeeAttendance): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.attendance.manage') || $user->can('hr.attendance.mark-owned-shop');
    }
}
