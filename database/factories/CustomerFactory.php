<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'type' => $this->faker->randomElement(['Retailer', 'Wholesaler', 'Restaurant', 'Supermarket']),
            'contact' => $this->faker->name().' ('.$this->faker->phoneNumber().')',
            'email' => $this->faker->optional()->companyEmail(),
            'address' => $this->faker->optional()->address(),
            'payment_terms' => $this->faker->randomElement(['COD', 'Net 7', 'Net 15', 'Net 30']),
            'credit_limit' => $this->faker->randomFloat(2, 0, 50000),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
