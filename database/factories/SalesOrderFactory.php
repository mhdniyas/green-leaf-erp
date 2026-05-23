<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Sales\SOStatus;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'so_number' => 'SO-'.now()->format('Ymd').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => SOStatus::Draft,
            'order_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => SOStatus::Confirmed]);
    }

    public function dispatched(): static
    {
        return $this->state(['status' => SOStatus::Dispatched]);
    }

    public function invoiced(): static
    {
        return $this->state(['status' => SOStatus::Invoiced]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => SOStatus::Cancelled]);
    }
}
