<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContractWorkerPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractWorkerPayment>
 */
class ContractWorkerPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paid_by' => User::factory(),
            'worker_name' => fake()->name(),
            'work_type' => fake()->word(),
            'worked_on' => today()->toDateString(),
            'paid_on' => today()->toDateString(),
            'amount' => fake()->randomFloat(2, 500, 5000),
            'payment_method' => 'cash',
        ];
    }
}
