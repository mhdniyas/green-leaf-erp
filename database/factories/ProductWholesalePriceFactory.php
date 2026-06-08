<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Inventory\ProductGrade;
use App\Models\Product;
use App\Models\ProductWholesalePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductWholesalePrice>
 */
class ProductWholesalePriceFactory extends Factory
{
    protected $model = ProductWholesalePrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = $this->faker->randomFloat(4, 10, 120);

        return [
            'product_id' => Product::factory(),
            'grade' => $this->faker->randomElement([ProductGrade::GradeA, ProductGrade::GradeB, ProductGrade::GradeC]),
            'weighted_average_cost' => $cost,
            'wholesale_price' => $cost,
            'sellable_quantity' => $this->faker->randomFloat(3, 20, 200),
            'total_cost' => $this->faker->randomFloat(4, 1000, 10000),
            'source_type' => 'factory',
            'calculated_at' => now(),
        ];
    }
}
