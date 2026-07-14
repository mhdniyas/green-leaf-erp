<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\HrOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HrOverride>
 */
class HrOverrideFactory extends Factory
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
            'override_type' => fake()->randomElement(['attendance', 'leave_balance', 'payroll_item']),
            'related_type' => null,
            'related_id' => null,
            'old_values' => ['old' => 'value'],
            'new_values' => ['new' => 'value'],
            'reason' => fake()->sentence(),
            'overridden_by' => User::factory(),
            'overridden_at' => now(),
        ];
    }
}
