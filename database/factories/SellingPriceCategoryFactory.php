<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SellingPriceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellingPriceCategory>
 */
class SellingPriceCategoryFactory extends Factory
{
    protected $model = SellingPriceCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Markup',
            'markup_percentage' => fake()->randomFloat(2, 5, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
