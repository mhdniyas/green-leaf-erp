<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CasioStaffLocalSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()->where('code', 'AV_CASIO')->orWhere('name', 'Casio')->first();
        if (! $shop) {
            $this->command->error('Casio shop not found.');

            return;
        }

        $category = EmployeeCategory::query()->where('name', 'Shop Employees')->first()
            ?? EmployeeCategory::query()->first();

        $admin = User::query()->where('email', 'admin@greenleaf.com')->first()
            ?? User::query()->first();

        $employeesData = [
            [
                'code' => 'EMP-CASIO-01',
                'name' => 'Rahul Sharma',
                'monthly_salary' => 22000.00,
                'days_present_august' => 31, // 30+ full month
            ],
            [
                'code' => 'EMP-CASIO-02',
                'name' => 'Anand Kumar',
                'monthly_salary' => 18000.00,
                'days_present_august' => 31, // 30+ full month
            ],
            [
                'code' => 'EMP-CASIO-03',
                'name' => 'Suresh Nair',
                'monthly_salary' => 22000.00,
                'days_present_august' => 26, // 26 days
            ],
            [
                'code' => 'EMP-CASIO-04',
                'name' => 'Deepak Menon',
                'monthly_salary' => 18000.00,
                'days_present_august' => 26, // 26 days
            ],
        ];

        foreach ($employeesData as $data) {
            $employee = Employee::query()->updateOrCreate(
                ['employee_code' => $data['code']],
                [
                    'default_shop_id' => $shop->id,
                    'employee_category_id' => $category?->id,
                    'name' => $data['name'],
                    'staff_area' => 'shop',
                    'phone' => '98765'.rand(10000, 99999),
                    'employment_status' => 'active',
                    'verification_status' => 'verified',
                    'joined_on' => '2026-01-01',
                    'monthly_salary' => $data['monthly_salary'],
                    'salary_type' => 'monthly',
                    'daily_wage' => round($data['monthly_salary'] / 31, 2),
                    'submitted_by' => $admin?->id,
                    'reviewed_by' => $admin?->id,
                    'reviewed_at' => now(),
                ]
            );

            // August 2026 attendance (2026-08-01 to 2026-08-31)
            $augustDays = Carbon::create(2026, 8, 1)->daysInMonth;
            for ($day = 1; $day <= $augustDays; $day++) {
                $dateStr = sprintf('2026-08-%02d', $day);
                $status = ($day <= $data['days_present_august']) ? 'present' : 'absent';

                EmployeeAttendance::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'attendance_date' => $dateStr,
                    ],
                    [
                        'shop_id' => $shop->id,
                        'status' => $status,
                        'marked_by' => $admin?->id,
                        'marked_at' => Carbon::parse($dateStr.' 09:00:00'),
                        'source' => 'shop_owner',
                    ]
                );
            }

            // September 2026 attendance (2026-09-01 to 2026-09-06)
            for ($day = 1; $day <= 6; $day++) {
                $dateStr = sprintf('2026-09-%02d', $day);
                EmployeeAttendance::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'attendance_date' => $dateStr,
                    ],
                    [
                        'shop_id' => $shop->id,
                        'status' => 'present',
                        'marked_by' => $admin?->id,
                        'marked_at' => Carbon::parse($dateStr.' 09:00:00'),
                        'source' => 'shop_owner',
                    ]
                );
            }
        }
    }
}
