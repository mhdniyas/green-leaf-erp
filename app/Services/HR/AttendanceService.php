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

        if ($employee->staff_area !== 'shop') {
            return false;
        }

        if (! $date->isToday()) {
            return false;
        }

        if ($shopId === null) {
            return false;
        }

        return $user->ownedShopAssignments()->where('shop_id', $shopId)->exists()
            && ShopEmployeeAssignment::query()
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
        return EmployeeAttendance::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'attendance_date' => $date->toDateString(),
            ],
            [
                'status' => $status,
                'shop_id' => $shop?->id,
                'marked_by' => $actor->id,
                'marked_at' => now(),
                'source' => $source,
                'notes' => $notes,
            ],
        );
    }
}
