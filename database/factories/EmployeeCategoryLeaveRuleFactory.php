<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmployeeCategory;
use App\Models\EmployeeCategoryLeaveRule;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeCategoryLeaveRule>
 */
class EmployeeCategoryLeaveRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_category_id' => EmployeeCategory::factory(),
            'leave_type_id' => LeaveType::factory(),
            'annual_entitlement' => fake()->randomFloat(2, 0, 36),
            'monthly_accrual_amount' => fake()->optional()->randomFloat(2, 0.5, 3),
            'allocation_frequency' => fake()->randomElement(['monthly', 'annual_opening']),
            'carry_forward_allowed' => fake()->boolean(),
            'maximum_carry_forward_days' => fake()->randomFloat(2, 0, 12),
            'carry_forward_expiry_months' => fake()->optional()->numberBetween(1, 12),
            'payroll_weight' => fake()->randomElement([0, 0.5, 1]),
            'negative_balance_allowed' => false,
        ];
    }
}
