<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EmployeeCategory;
use Illuminate\Database\Seeder;

class EmployeeCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            [
                'name' => 'Office Staff',
                'code' => 'office',
                'staff_area' => 'office',
                'default_monthly_salary' => 24000,
                'monthly_paid_leave_limit' => 4,
            ],
            [
                'name' => 'Direct Board',
                'code' => 'direct-board',
                'staff_area' => 'shop',
                'default_monthly_salary' => 22000,
                'monthly_paid_leave_limit' => 6,
            ],
            [
                'name' => 'Other Shop Staff',
                'code' => 'other-shop',
                'staff_area' => 'shop',
                'default_monthly_salary' => 18000,
                'monthly_paid_leave_limit' => 4,
            ],
        ] as $category) {
            EmployeeCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                $category,
            );
        }
    }
}
