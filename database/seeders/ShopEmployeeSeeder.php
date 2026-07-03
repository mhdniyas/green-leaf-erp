<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class ShopEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $category = EmployeeCategory::query()->firstOrCreate(
            ['code' => 'other-shop'],
            [
                'name' => 'Shop Employees',
                'staff_area' => 'shop',
                'default_monthly_salary' => 18000,
                'monthly_paid_leave_limit' => 4,
                'present_day_weight' => 1,
                'half_day_weight' => 0.5,
                'paid_leave_weight' => 1,
                'excess_leave_weight' => 0,
                'absent_day_weight' => 0,
                'is_active' => true,
            ],
        );

        $shops = Shop::query()->orderBy('id')->get();

        if ($shops->isEmpty()) {
            $this->command?->warn('No shops found. Shop employees were not seeded.');

            return;
        }

        for ($index = 1; $index <= 30; $index++) {
            $shop = $shops->get(($index - 1) % $shops->count());

            Employee::query()->updateOrCreate(
                ['employee_code' => sprintf('SHOPEMP%03d', $index)],
                [
                    'default_shop_id' => $shop->id,
                    'employee_category_id' => $category->id,
                    'name' => sprintf('Shop Employee %02d', $index),
                    'phone' => sprintf('900000%04d', $index),
                    'email' => sprintf('shop.employee.%02d@greenleaf.com', $index),
                    'staff_area' => 'shop',
                    'employment_status' => 'active',
                    'joined_on' => today()->subDays($index),
                    'monthly_salary' => 18000 + (($index - 1) % 6) * 1000,
                    'is_user_linked' => false,
                    'notes' => 'Seeded shop employee record.',
                ],
            );
        }

        $this->command?->info('30 shop employees seeded.');
    }
}
