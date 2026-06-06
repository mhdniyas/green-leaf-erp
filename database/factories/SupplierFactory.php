<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'type' => $this->faker->randomElement(['Farmer', 'Market Agent', 'Importer', 'Co-operative']),
            'category' => 'own_purchase',
            'is_default_purchase' => false,
            'contact' => $this->faker->name().' ('.$this->faker->phoneNumber().')',
            'payment_terms' => $this->faker->randomElement(['COD', 'Net 7', 'Net 15', 'Net 30']),
            'quality_score' => 100.00,
        ];
    }
}
