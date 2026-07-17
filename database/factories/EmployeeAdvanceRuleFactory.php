<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmployeeAdvanceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAdvanceRule>
 */
class EmployeeAdvanceRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Default advance rule',
            'minimum_present_days' => 20,
            'advance_percent' => 50,
            'default_from_petty_cash' => true,
            'allow_negative_shop_balance' => true,
            'is_active' => true,
        ];
    }
}
