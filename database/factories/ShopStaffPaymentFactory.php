<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopStaffPayment>
 */
class ShopStaffPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_run_id' => PayrollRun::factory(),
            'payroll_run_item_id' => PayrollRunItem::factory(),
            'employee_id' => Employee::factory(),
            'shop_id' => Shop::factory(),
            'paid_by' => User::factory(),
            'paid_on' => today()->toDateString(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'payment_type' => 'salary',
            'fund_source' => 'petty_cash',
            'status' => 'paid',
        ];
    }
}
