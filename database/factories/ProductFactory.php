<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $vegetables = [
            'Tomato', 'Spinach', 'Carrot', 'Onion', 'Potato', 'Cabbage',
            'Broccoli', 'Cauliflower', 'Cucumber', 'Bell Pepper', 'Chilli',
            'Garlic', 'Ginger', 'Bitter Gourd', 'Lady Finger', 'Eggplant',
            'Spring Onion', 'Coriander', 'Mint', 'Capsicum',
        ];

        $name = $this->faker->unique()->randomElement($vegetables);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'sku' => strtoupper(Str::slug($name, '-')).'-'.$this->faker->numerify('###'),
            'unit' => $this->faker->randomElement(['kg', 'kg', 'kg', 'box', 'bunch']),
            'description' => $this->faker->optional()->sentence(),
            'base_price' => $this->faker->randomFloat(2, 10, 150),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
