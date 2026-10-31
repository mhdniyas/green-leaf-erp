<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MonthlyStaffAttendanceSummaryService
{
    /**
     * @param  Collection<int, int>|array<int, int>|null  $allowedShopIds
     * @return array{
     *     rows: Collection<int, array<string, mixed>>,
     *     totals: array{total_employees: int, present: int, absent: int, leave: int, half_day: int, other: int, total_other_attendance: int},
     *     shop_summaries: Collection<int, array<string, mixed>>,
     *     shop_totals: array{total_shops: int, total_employees: int, present: int, absent: int, leave: int, half_day: int, other: int, total_other: int}
     * }
     */
    public function getMonthlySummary(
        ?int $shopId,
        Carbon $calendarMonth,
        ?string $categoryCode = null,
        string $search = '',
        Collection|array|null $allowedShopIds = null,
    ): array {
        $monthStart = $calendarMonth->copy()->startOfMonth();
        $monthEnd = $calendarMonth->copy()->endOfMonth();

        $shopsQuery = Shop::query()->orderBy('name');
        if ($allowedShopIds !== null) {
            $allowedIds = is_array($allowedShopIds) ? $allowedShopIds : $allowedShopIds->all();
            $shopsQuery->whereIn('id', $allowedIds);
        } else {
            $shopsQuery->ownedForStaff();
        }

        $authorizedShops = $shopsQuery->get();
        $authorizedShopIds = $authorizedShops->pluck('id')->all();

        if (empty($authorizedShopIds)) {
            return $this->emptyResult();
        }

        if ($shopId !== null && $shopId > 0) {
            if (! in_array($shopId, $authorizedShopIds, true)) {
                return $this->emptyResult();
            }
            $targetShopIds = [$shopId];
            $targetShops = $authorizedShops->where('id', $shopId)->values();
        } else {
            $targetShopIds = $authorizedShopIds;
            $targetShops = $authorizedShops;
        }

        $employeesQuery = Employee::query()
            ->approved()
            ->where('staff_area', 'shop')
            ->where('employment_status', 'active')
            ->with(['category', 'defaultShop', 'shopAssignments.shop'])
            ->where(function ($query) use ($targetShopIds, $monthStart, $monthEnd): void {
                $query->whereIn('default_shop_id', $targetShopIds)
                    ->orWhereHas('shopAssignments', function ($assignQuery) use ($targetShopIds, $monthStart, $monthEnd): void {
                        $assignQuery->whereIn('shop_id', $targetShopIds)
                            ->where(function ($sub) use ($monthStart): void {
                                $sub->whereNull('effective_to')
                                    ->orWhereDate('effective_to', '>=', $monthStart->toDateString());
                            })
                            ->where(function ($sub) use ($monthEnd): void {
                                $sub->whereNull('effective_from')
                                    ->orWhereDate('effective_from', '<=', $monthEnd->toDateString());
                            });
                    })
                    ->orWhereHas('attendances', function ($attQuery) use ($targetShopIds, $monthStart, $monthEnd): void {
                        $attQuery->whereIn('shop_id', $targetShopIds)
                            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                    });
            })
            ->when($categoryCode !== null && $categoryCode !== '' && $categoryCode !== 'all', function ($query) use ($categoryCode): void {
                $query->whereHas('category', fn ($catQ) => $catQ->where('code', $categoryCode));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', '%'.$search.'%')
                        ->orWhere('employee_code', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            });

        $employees = $employeesQuery->orderBy('name')->get();

        if ($employees->isEmpty()) {
            return $this->emptyResult();
        }

        $allAttendanceRecords = EmployeeAttendance::query()
            ->with(['shop', 'markedBy', 'employee.category'])
            ->whereIn('shop_id', $targetShopIds)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->orderBy('attendance_date')
            ->get();

        $attendancesByEmpAndShop = $allAttendanceRecords->groupBy(
            fn (EmployeeAttendance $att): string => $att->employee_id.'_'.$att->shop_id
        );

        $rows = collect();

        foreach ($employees as $employee) {
            $relevantShopIds = collect();

            if ($employee->default_shop_id !== null && in_array((int) $employee->default_shop_id, $targetShopIds, true)) {
                $relevantShopIds->push((int) $employee->default_shop_id);
            }

            foreach ($employee->shopAssignments as $assignment) {
                if (in_array((int) $assignment->shop_id, $targetShopIds, true)) {
                    $effFrom = $assignment->effective_from ? Carbon::parse($assignment->effective_from)->startOfDay() : null;
                    $effTo = $assignment->effective_to ? Carbon::parse($assignment->effective_to)->endOfDay() : null;
                    if (($effTo === null || $effTo->gte($monthStart)) && ($effFrom === null || $effFrom->lte($monthEnd))) {
                        $relevantShopIds->push((int) $assignment->shop_id);
                    }
                }
            }

            $empAttendances = $allAttendanceRecords->where('employee_id', $employee->id);
            foreach ($empAttendances as $att) {
                if (in_array((int) $att->shop_id, $targetShopIds, true)) {
                    $relevantShopIds->push((int) $att->shop_id);
                }
            }

            $relevantShopIds = $relevantShopIds->unique()->values();

            foreach ($relevantShopIds as $empShopId) {
                $shop = $targetShops->firstWhere('id', $empShopId);
                /** @var Collection<int, EmployeeAttendance> $empRecords */
                $empRecords = $attendancesByEmpAndShop->get($employee->id.'_'.$empShopId, collect());

                $uniqueRecords = $empRecords->unique(
                    fn (EmployeeAttendance $att): string => $att->attendance_date instanceof Carbon
                        ? $att->attendance_date->toDateString()
                        : substr((string) $att->attendance_date, 0, 10)
                );

                $present = 0;
                $absent = 0;
                $leave = 0;
                $halfDay = 0;
                $other = 0;
                $statusCounts = [];

                foreach ($uniqueRecords as $record) {
                    $status = (string) $record->status;
                    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

                    match ($status) {
                        'present' => $present++,
                        'absent' => $absent++,
                        'leave' => $leave++,
                        'half_day' => $halfDay++,
                        default => $other++,
                    };
                }

                $recordDetails = $uniqueRecords->sortBy('attendance_date')->values()->map(function (EmployeeAttendance $record): array {
                    $dateStr = $record->attendance_date instanceof Carbon
                        ? $record->attendance_date->format('d M Y')
                        : Carbon::parse((string) $record->attendance_date)->format('d M Y');
                    $dayName = $record->attendance_date instanceof Carbon
                        ? $record->attendance_date->format('D')
                        : Carbon::parse((string) $record->attendance_date)->format('D');

                    $statusLabel = match ($record->status) {
                        'present' => 'Present',
                        'half_day' => 'Half Day',
                        'leave' => 'Leave',
                        'absent' => 'Absent',
                        default => str_replace('_', ' ', ucfirst((string) $record->status)),
                    };

                    return [
                        'date' => $dateStr,
                        'day' => $dayName,
                        'status' => $record->status,
                        'status_label' => $statusLabel,
                        'marked_at' => $record->marked_at?->timezone('Asia/Kolkata')->format('g:i A') ?? '—',
                        'marked_by' => $record->markedBy?->name ?? '—',
                        'source' => ucfirst($record->source ?? 'shop_owner'),
                        'notes' => $record->notes ?? '',
                    ];
                })->all();

                $rows->push([
                    'employee' => $employee,
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'employee_code' => $employee->employee_code,
                    'category_name' => $employee->category?->name ?? 'Staff',
                    'photo_url' => $employee->photo_url,
                    'shop' => $shop,
                    'shop_id' => $shop?->id,
                    'shop_name' => $shop?->name ?? '—',
                    'shop_code' => $shop?->code ?? '',
                    'present' => $present,
                    'absent' => $absent,
                    'leave' => $leave,
                    'half_day' => $halfDay,
                    'other' => $other,
                    'total_marked' => $uniqueRecords->count(),
                    'status_counts' => $statusCounts,
                    'record_details' => $recordDetails,
                ]);
            }
        }

        $sortedRows = $rows->sortBy([
            ['shop_name', 'asc'],
            ['employee_name', 'asc'],
        ])->values();

        // Unique employees count across all rows (prevents double-counting if an employee was transferred across shops in the month)
        $uniqueEmployeesCount = $sortedRows->pluck('employee_id')->unique()->count();

        $totals = [
            'total_employees' => $uniqueEmployeesCount,
            'present' => (int) $sortedRows->sum('present'),
            'absent' => (int) $sortedRows->sum('absent'),
            'leave' => (int) $sortedRows->sum('leave'),
            'half_day' => (int) $sortedRows->sum('half_day'),
            'other' => (int) $sortedRows->sum('other'),
            'total_other_attendance' => (int) $sortedRows->sum('half_day') + (int) $sortedRows->sum('other'),
        ];

        $shopSummaries = $targetShops->map(function (Shop $sh) use ($sortedRows): array {
            $shopRows = $sortedRows->where('shop_id', $sh->id);
            $empCount = $shopRows->pluck('employee_id')->unique()->count();
            $p = (int) $shopRows->sum('present');
            $a = (int) $shopRows->sum('absent');
            $l = (int) $shopRows->sum('leave');
            $h = (int) $shopRows->sum('half_day');
            $o = (int) $shopRows->sum('other');

            return [
                'shop' => $sh,
                'shop_id' => $sh->id,
                'shop_name' => $sh->name,
                'shop_code' => $sh->code,
                'employee_count' => $empCount,
                'employees_count' => $empCount,
                'present' => $p,
                'absent' => $a,
                'leave' => $l,
                'half_day' => $h,
                'other' => $o,
                'total_other' => $h + $o,
                'total_other_attendance' => $h + $o,
            ];
        })->filter(fn (array $s): bool => $s['employees_count'] > 0 || $s['present'] > 0)->values();

        $shopTotals = [
            'total_shops' => $shopSummaries->count(),
            'employee_count' => $uniqueEmployeesCount,
            'total_employees' => $uniqueEmployeesCount,
            'present' => (int) $shopSummaries->sum('present'),
            'absent' => (int) $shopSummaries->sum('absent'),
            'leave' => (int) $shopSummaries->sum('leave'),
            'half_day' => (int) $shopSummaries->sum('half_day'),
            'other' => (int) $shopSummaries->sum('other'),
            'total_other' => (int) $shopSummaries->sum('total_other'),
            'total_other_attendance' => (int) $shopSummaries->sum('total_other'),
        ];

        return [
            'rows' => $sortedRows,
            'totals' => $totals,
            'shop_summaries' => $shopSummaries,
            'shop_totals' => $shopTotals,
        ];
    }

    /**
     * @return array{
     *     rows: Collection<int, array<string, mixed>>,
     *     totals: array{total_employees: int, present: int, absent: int, leave: int, half_day: int, other: int, total_other_attendance: int},
     *     shop_summaries: Collection<int, array<string, mixed>>,
     *     shop_totals: array{total_shops: int, total_employees: int, present: int, absent: int, leave: int, half_day: int, other: int, total_other: int}
     * }
     */
    private function emptyResult(): array
    {
        return [
            'rows' => collect(),
            'totals' => [
                'total_employees' => 0,
                'present' => 0,
                'absent' => 0,
                'leave' => 0,
                'half_day' => 0,
                'other' => 0,
                'total_other_attendance' => 0,
            ],
            'shop_summaries' => collect(),
            'shop_totals' => [
                'total_shops' => 0,
                'employee_count' => 0,
                'total_employees' => 0,
                'present' => 0,
                'absent' => 0,
                'leave' => 0,
                'half_day' => 0,
                'other' => 0,
                'total_other' => 0,
                'total_other_attendance' => 0,
            ],
        ];
    }
}
