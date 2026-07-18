<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

class AttendanceService
{
    public function canOwnerMarkAttendance(User $user, Employee $employee, Carbon $date, ?int $shopId): bool
    {
        if (! $user->hasRole('shop')) {
            return false;
        }

        if (! $date->isToday()) {
            return false;
        }

        if ($shopId === null) {
            return false;
        }

        if (! $user->ownedShopAssignments()->where('shop_id', $shopId)->exists()) {
            return false;
        }

        if ((int) $user->employee?->id === (int) $employee->id) {
            return true;
        }

        if ($employee->staff_area !== 'shop') {
            return false;
        }

        return ShopEmployeeAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('shop_id', $shopId)
            ->where('status', 'active')
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date->toDateString());
            })
            ->exists();
    }

    public function upsert(
        Employee $employee,
        Carbon $date,
        string $status,
        User $actor,
        string $source,
        ?Shop $shop = null,
        ?string $notes = null,
    ): EmployeeAttendance {
        $attendance = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->first();

        if ($attendance === null) {
            $attendance = new EmployeeAttendance([
                'employee_id' => $employee->id,
                'attendance_date' => $date->toDateString(),
            ]);
        }

        $attendance->fill([
            'status' => $status,
            'shop_id' => $shop?->id,
            'marked_by' => $actor->id,
            'marked_at' => now(),
            'source' => $source,
            'notes' => $notes,
        ]);
        $attendance->save();

        return $attendance;
    }
}
