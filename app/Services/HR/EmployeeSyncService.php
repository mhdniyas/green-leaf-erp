<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EmployeeSyncService
{
    public function ensureForUser(User $user): Employee
    {
        $category = $this->resolveCategoryForUser($user);
        $employee = $user->employee()->with('category')->firstOrNew();
        $shouldUseCategorySalary = ! $employee->exists
            || $employee->monthly_salary === null
            || (
                $employee->category !== null
                && (float) $employee->monthly_salary === (float) $employee->category->default_monthly_salary
            );

        $employee->forceFill([
            'employee_code' => $employee->employee_code ?: $this->employeeCodeForUser($user),
            'user_id' => $user->id,
            'default_shop_id' => $user->shop_id,
            'employee_category_id' => $category->id,
            'name' => $user->name,
            'email' => $user->email,
            'staff_area' => $category->staff_area,
            'employment_status' => 'active',
            'joined_on' => $employee->joined_on ?? $user->created_at?->toDateString() ?? now()->toDateString(),
            'monthly_salary' => $shouldUseCategorySalary ? $category->default_monthly_salary : $employee->monthly_salary,
            'is_user_linked' => true,
        ]);

        $employee->save();

        return $employee;
    }

    /**
     * @return Collection<int, Employee>
     */
    public function syncExistingUsers(): Collection
    {
        return User::query()
            ->with('roles')
            ->get()
            ->map(fn (User $user): Employee => $this->ensureForUser($user));
    }

    private function resolveCategoryForUser(User $user): EmployeeCategory
    {
        $roleCategoryMap = [
            'shop' => 'other-shop',
            'admin' => 'office',
            'hr_manager' => 'office',
            'purchase' => 'office',
            'purchaser' => 'office',
            'warehouse_receiver' => 'office',
        ];

        $resolvedCode = collect($roleCategoryMap)
            ->first(fn (string $categoryCode, string $roleName): bool => $user->hasRole($roleName));
        $code = $resolvedCode ?? ($user->shop_id !== null ? 'other-shop' : 'office');
        $defaults = $code === 'office'
            ? [
                'name' => 'Office Staff',
                'staff_area' => 'office',
                'default_monthly_salary' => 24000,
            ]
            : [
                'name' => 'Shop Employees',
                'staff_area' => 'shop',
                'default_monthly_salary' => 18000,
            ];

        return EmployeeCategory::query()->firstOrCreate(
            ['code' => $code],
            array_merge($defaults, [
                'monthly_paid_leave_limit' => $code === 'office' ? 4 : 4,
                'present_day_weight' => 1,
                'half_day_weight' => 0.5,
                'paid_leave_weight' => 1,
                'excess_leave_weight' => 0,
                'absent_day_weight' => 0,
                'is_active' => true,
            ]),
        );
    }

    private function employeeCodeForUser(User $user): string
    {
        return 'USR-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(2));
    }
}
