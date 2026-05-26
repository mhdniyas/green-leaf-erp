<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $categories = [
            'Leafy Vegetables',
            'Root Vegetables',
            'Fruit Vegetables',
            'Cruciferous',
            'Alliums',
            'Gourds',
            'Tubers',
            'Beans & Legumes',
        ];

        return [
            'name' => $this->faker->unique()->randomElement($categories),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
