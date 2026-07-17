<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class JuneStaffAttendanceSeeder extends Seeder
{
    private const SPECIAL_DEMO_EMPLOYEE_CODES = [
        'DEMO-DB-001',
        'DEMO-OFF-ABS-001',
        'DEMO-SH-MIX-001',
    ];

    public function run(): void
    {
        $selectedMonth = Carbon::create(2026, 6, 1)->startOfMonth();
        $monthEnd = $selectedMonth->copy()->endOfMonth();
        $adminUser = User::query()->where('email', 'admin@greenleaf.com')->first();
        $shop = Shop::query()->orderBy('id')->first();

        $directBoardCategory = EmployeeCategory::query()->where('code', 'direct-board')->firstOrFail();
        $officeCategory = EmployeeCategory::query()->where('code', 'office')->firstOrFail();
        $shopCategory = EmployeeCategory::query()->where('code', 'other-shop')->firstOrFail();

        $directBoardEmployee = Employee::query()->updateOrCreate(
            ['employee_code' => 'DEMO-DB-001'],
            [
                'employee_category_id' => $directBoardCategory->id,
                'default_shop_id' => null,
                'name' => 'Direct Board June Demo',
                'phone' => '9100000001',
                'email' => 'direct.board.june@greenleaf.com',
                'staff_area' => 'office',
                'employment_status' => 'active',
                'joined_on' => '2026-01-01',
                'monthly_salary' => 60000,
                'is_user_linked' => false,
                'notes' => 'June demo staff record: 10 leave days, remaining days present.',
            ],
        );

        $officeAbsentEmployee = Employee::query()->updateOrCreate(
            ['employee_code' => 'DEMO-OFF-ABS-001'],
            [
                'employee_category_id' => $officeCategory->id,
                'default_shop_id' => null,
                'name' => 'Office All Absent Demo',
                'phone' => '9100000002',
                'email' => 'office.absent.june@greenleaf.com',
                'staff_area' => 'office',
                'employment_status' => 'active',
                'joined_on' => '2026-01-01',
                'monthly_salary' => (float) $officeCategory->default_monthly_salary,
                'is_user_linked' => false,
                'notes' => 'June demo staff record: absent all month.',
            ],
        );

        $shopMixedEmployee = Employee::query()->updateOrCreate(
            ['employee_code' => 'DEMO-SH-MIX-001'],
            [
                'employee_category_id' => $shopCategory->id,
                'default_shop_id' => $shop?->id,
                'name' => 'Shop Mixed June Demo',
                'phone' => '9100000003',
                'email' => 'shop.mixed.june@greenleaf.com',
                'staff_area' => 'shop',
                'employment_status' => 'active',
                'joined_on' => '2026-01-01',
                'monthly_salary' => 18000,
                'is_user_linked' => false,
                'notes' => 'June demo staff record: present, half-day, leave, and absent mix.',
            ],
        );

        $this->resetMonthAttendances($directBoardEmployee, $selectedMonth, $monthEnd);
        $this->resetMonthAttendances($officeAbsentEmployee, $selectedMonth, $monthEnd);
        $this->resetMonthAttendances($shopMixedEmployee, $selectedMonth, $monthEnd);
        $this->seedPresentMonthForExistingEmployees($selectedMonth, $monthEnd, $adminUser?->id);

        foreach (range(1, 30) as $day) {
            $attendanceDate = $selectedMonth->copy()->day($day);

            $this->seedAttendance(
                $directBoardEmployee,
                $attendanceDate,
                $day <= 10 ? 'leave' : 'present',
                $adminUser?->id,
                null,
                $day <= 10 ? 'Direct Board June demo paid/unpaid leave sample.' : 'Direct Board June demo working day.',
            );

            $this->seedAttendance(
                $officeAbsentEmployee,
                $attendanceDate,
                'absent',
                $adminUser?->id,
                null,
                'Office absent demo for June payroll validation.',
            );

            $shopMixedStatus = match (true) {
                $day <= 12 => 'present',
                $day <= 18 => 'half_day',
                $day <= 23 => 'leave',
                default => 'absent',
            };

            $this->seedAttendance(
                $shopMixedEmployee,
                $attendanceDate,
                $shopMixedStatus,
                $adminUser?->id,
                $shop?->id,
                'Shop mixed demo attendance for June payroll validation.',
            );
        }

        $this->command?->info('June staff attendance demo seeded: existing active staff as present, plus Direct Board leave, all-absent office, and mixed shop samples.');
    }

    private function seedPresentMonthForExistingEmployees(Carbon $monthStart, Carbon $monthEnd, ?int $markedBy): void
    {
        Employee::query()
            ->where('employment_status', 'active')
            ->whereNotIn('employee_code', self::SPECIAL_DEMO_EMPLOYEE_CODES)
            ->get()
            ->each(function (Employee $employee) use ($monthStart, $monthEnd, $markedBy): void {
                $this->resetMonthAttendances($employee, $monthStart, $monthEnd);

                $cursor = $monthStart->copy();

                while ($cursor->lte($monthEnd)) {
                    $this->seedAttendance(
                        $employee,
                        $cursor,
                        'present',
                        $markedBy,
                        $employee->staff_area === 'shop' ? $employee->default_shop_id : null,
                        'June demo attendance for manual payroll testing.',
                    );

                    $cursor->addDay();
                }
            });
    }

    private function resetMonthAttendances(Employee $employee, Carbon $monthStart, Carbon $monthEnd): void
    {
        EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->delete();
    }

    private function seedAttendance(
        Employee $employee,
        Carbon $attendanceDate,
        string $status,
        ?int $markedBy,
        ?int $shopId,
        string $notes,
    ): void {
        EmployeeAttendance::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'attendance_date' => $attendanceDate->toDateString(),
            ],
            [
                'status' => $status,
                'shop_id' => $shopId,
                'marked_by' => $markedBy,
                'marked_at' => $attendanceDate->copy()->setTime(9, 0),
                'source' => 'admin',
                'notes' => $notes,
            ],
        );
    }
}
