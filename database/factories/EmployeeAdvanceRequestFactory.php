<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAdvanceRule;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAdvanceRequest>
 */
class EmployeeAdvanceRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'shop_id' => Shop::factory(),
            'employee_advance_rule_id' => EmployeeAdvanceRule::factory(),
            'requested_by' => User::factory(),
            'requested_on' => today()->toDateString(),
            'payroll_month' => today()->startOfMonth()->toDateString(),
            'requested_amount' => 1000,
            'eligible_amount' => 1000,
            'fund_source' => 'petty_cash',
            'status' => 'pending',
        ];
    }
}
