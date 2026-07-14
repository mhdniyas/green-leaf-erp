<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopCredit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopCredit>
 */
class ShopCreditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'type' => 'in',
            'amount' => fake()->randomFloat(2, 100, 5000),
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
            'business_date' => today()->toDateString(),
        ];
    }
}
