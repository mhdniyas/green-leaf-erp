<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPayment>
 */
class PayrollPaymentFactory extends Factory
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
            'journal_entry_id' => null,
            'paid_by' => User::factory(),
            'paid_on' => today(),
            'amount' => fake()->randomFloat(2, 500, 20000),
            'payment_method' => fake()->randomElement(['cash', 'bank']),
            'payment_type' => fake()->randomElement(['partial', 'full', 'custom']),
            'notes' => fake()->sentence(),
        ];
    }
}
