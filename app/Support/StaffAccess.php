<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

class StaffAccess
{
    public static function canViewDashboard(?User $user): bool
    {
        return $user !== null && $user->can('hr.employee.view');
    }

    public static function canViewEmployees(?User $user): bool
    {
        return self::canViewDashboard($user);
    }

    public static function canViewCategories(?User $user): bool
    {
        return self::canViewDashboard($user);
    }

    public static function canViewAttendance(?User $user): bool
    {
        return $user !== null && $user->can('hr.attendance.view');
    }

    public static function canViewLeaves(?User $user): bool
    {
        return $user !== null && $user->can('hr.leave.view');
    }

    public static function canViewPayroll(?User $user): bool
    {
        return $user !== null && $user->can('hr.payroll.view');
    }

    public static function canViewAdvancePayments(?User $user): bool
    {
        return self::canViewPayroll($user);
    }

    public static function canAccessAny(?User $user): bool
    {
        return self::canViewDashboard($user)
            || self::canViewAttendance($user)
            || self::canViewLeaves($user)
            || self::canViewPayroll($user);
    }

    public static function landingUrl(?User $user, ?string $date = null): ?string
    {
        if (! self::canAccessAny($user)) {
            return null;
        }

        $resolvedDate = $date ?: today()->toDateString();

        return match (true) {
            self::canViewDashboard($user) => route('admin.staff.index', ['date' => $resolvedDate]),
            self::canViewAttendance($user) => route('admin.staff.attendance', ['date' => $resolvedDate]),
            self::canViewLeaves($user) => route('admin.staff.leaves.index'),
            self::canViewPayroll($user) => route('admin.staff.payroll.index', ['payroll_month' => today()->format('Y-m')]),
            default => null,
        };
    }
}
