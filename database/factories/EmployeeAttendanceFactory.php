<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAttendance>
 */
class EmployeeAttendanceFactory extends Factory
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
            'attendance_date' => today(),
            'status' => fake()->randomElement(['present', 'half_day', 'absent', 'leave']),
            'source' => 'admin',
        ];
    }
}
