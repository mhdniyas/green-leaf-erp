<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_start' => today()->startOfMonth(),
            'period_end' => today()->endOfMonth(),
            'status' => 'finalized',
            'gross_amount' => 0,
            'net_amount' => 0,
        ];
    }
}
