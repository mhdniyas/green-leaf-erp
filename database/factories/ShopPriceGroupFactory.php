<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShopPriceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopPriceGroup>
 */
class ShopPriceGroupFactory extends Factory
{
    protected $model = ShopPriceGroup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => strtoupper($this->faker->bothify('?')),
            'default_margin_percent' => $this->faker->randomFloat(2, 5, 15),
            'is_active' => true,
        ];
    }
}
