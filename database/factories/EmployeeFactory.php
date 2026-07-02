<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => strtoupper(fake()->unique()->bothify('EMP###??')),
            'employee_category_id' => EmployeeCategory::factory(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('##########'),
            'email' => fake()->safeEmail(),
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'joined_on' => today()->subDays(fake()->numberBetween(5, 50)),
            'monthly_salary' => fake()->numberBetween(15000, 30000),
            'is_user_linked' => false,
        ];
    }
}
