<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeCategoryLeaveRule;
use App\Models\EmployeeLeaveLedgerEntry;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeLeaveLedgerEntry>
 */
class EmployeeLeaveLedgerEntryFactory extends Factory
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
            'leave_type_id' => LeaveType::factory(),
            'employee_category_leave_rule_id' => EmployeeCategoryLeaveRule::factory(),
            'financial_year_start' => '2026-04-01',
            'transaction_date' => today(),
            'entry_type' => fake()->randomElement(['opening_entitlement', 'monthly_accrual', 'leave_consumed']),
            'credit' => 1,
            'debit' => 0,
            'source_type' => null,
            'source_id' => null,
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
