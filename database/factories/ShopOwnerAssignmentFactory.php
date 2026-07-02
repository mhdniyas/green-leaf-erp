<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopOwnerAssignment>
 */
class ShopOwnerAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shop_id' => Shop::factory(),
        ];
    }
}
