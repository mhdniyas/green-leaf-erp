<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\BusinessSetting;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

class AttendanceService
{
    public function getShopAttendanceCutoffTime(): string
    {
        return BusinessSetting::query()
            ->where('key', 'shop_attendance_cutoff_time')
            ->value('value') ?: '10:00';
    }

    public function formattedShopAttendanceCutoffTime(): string
    {
        $cutoffStr = $this->getShopAttendanceCutoffTime();

        try {
            return Carbon::createFromFormat('H:i', $cutoffStr)->format('g:i A');
        } catch (\Throwable) {
            return '10:00 AM';
        }
    }

    public function isShopAttendanceOpen(?Carbon $now = null, ?Carbon $attendanceDate = null): bool
    {
        $attendanceDate = $attendanceDate ?? today();
        if (! $attendanceDate->isToday()) {
            return false;
        }

        $now = $now ?? now('Asia/Kolkata');
        $cutoffStr = $this->getShopAttendanceCutoffTime();

        try {
            [$hours, $minutes] = explode(':', $cutoffStr);
            $cutoffDateTime = $now->copy()->setTime((int) $hours, (int) $minutes, 59);

            return $now->lessThanOrEqualTo($cutoffDateTime);
        } catch (\Throwable) {
            return true;
        }
    }

    public function canOwnerMarkAttendance(User $user, Employee $employee, Carbon $date, ?int $shopId, ?Carbon $now = null): bool
    {
        if (! $user->hasRole('shop')) {
            return false;
        }

        if (! $date->isToday()) {
            return false;
        }

        if (! $this->isShopAttendanceOpen($now, $date)) {
            return false;
        }

        if ($shopId === null) {
            return false;
        }

        if (! $user->ownedShopAssignments()->where('shop_id', $shopId)->exists()) {
            return false;
        }

        if ($employee->staff_area !== 'shop') {
            return false;
        }

        if ($employee->verification_status !== 'approved') {
            return false;
        }

        if ((int) $employee->default_shop_id === (int) $shopId) {
            return true;
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
