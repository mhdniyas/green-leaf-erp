<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRunItem>
 */
class PayrollRunItemFactory extends Factory
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
            'employee_id' => Employee::factory(),
            'employee_category_id' => EmployeeCategory::factory(),
            'base_salary' => 20000,
            'present_days' => 20,
            'half_days' => 2,
            'paid_leave_days' => 1,
            'unpaid_leave_days' => 0,
            'absent_days' => 1,
            'payable_units' => 22,
            'computed_amount' => 17600,
            'final_amount' => 17600,
            'rule_snapshot' => [
                'present_day_weight' => 1,
                'half_day_weight' => 0.5,
                'paid_leave_weight' => 1,
                'monthly_paid_leave_limit' => 4,
                'excess_leave_weight' => 0,
                'absent_day_weight' => 0,
            ],
        ];
    }
}
