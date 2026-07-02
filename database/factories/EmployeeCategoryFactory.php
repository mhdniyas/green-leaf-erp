<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmployeeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeCategory>
 */
class EmployeeCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'code' => fake()->unique()->slug(2),
            'staff_area' => fake()->randomElement(['office', 'shop']),
            'default_monthly_salary' => fake()->numberBetween(15000, 30000),
            'monthly_paid_leave_limit' => fake()->numberBetween(2, 6),
            'present_day_weight' => 1,
            'half_day_weight' => 0.5,
            'paid_leave_weight' => 1,
            'excess_leave_weight' => 0,
            'absent_day_weight' => 0,
            'is_active' => true,
        ];
    }
}
