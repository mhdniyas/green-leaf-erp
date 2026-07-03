<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeaveRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class StaffDirectoryService
{
    public function paginateEmployees(?string $area = null, ?string $categoryCode = null, ?string $search = null): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['category', 'defaultShop', 'user.roles', 'user.ownedShopAssignments.shop'])
            ->when($area !== null && $area !== '', fn ($query) => $query->where('staff_area', $area))
            ->when($categoryCode !== null && $categoryCode !== '', fn ($query) => $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('code', $categoryCode)))
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $query->where(function ($employeeQuery) use ($search): void {
                    $employeeQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('employee_code', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(12);
    }

    /**
     * @return array<string, int>
     */
    public function statsForDate(Carbon $date): array
    {
        return [
            'total_employees' => Employee::query()->count(),
            'office_staff' => Employee::query()->where('staff_area', 'office')->count(),
            'shop_staff' => Employee::query()->where('staff_area', 'shop')->count(),
            'present_today' => EmployeeAttendance::query()->whereDate('attendance_date', $date)->where('status', 'present')->count(),
            'leave_today' => EmployeeAttendance::query()->whereDate('attendance_date', $date)->where('status', 'leave')->count(),
            'pending_leave_requests' => EmployeeLeaveRequest::query()->where('status', 'pending')->count(),
        ];
    }
}
