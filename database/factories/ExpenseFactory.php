<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_date' => now()->format('Y-m-d'),
            'account_id' => fn () => Account::where('type', 'expense')->first()?->id ?? Account::factory()->create(['type' => 'expense'])->id,
            'amount' => fake()->randomFloat(2, 50, 1000),
            'payment_method' => fake()->randomElement(['cash', 'bank']),
            'reference' => 'REF-'.fake()->unique()->numerify('#####'),
            'description' => fake()->sentence(),
            'recorded_by' => fn () => User::first()?->id ?? User::factory()->create()->id,
        ];
    }
}
