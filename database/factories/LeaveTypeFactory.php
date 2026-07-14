<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->slug(2),
            'is_paid' => fake()->boolean(70),
            'is_active' => true,
            'carry_forward_allowed' => fake()->boolean(40),
            'default_expiry_months' => fake()->optional()->numberBetween(1, 12),
        ];
    }
}
