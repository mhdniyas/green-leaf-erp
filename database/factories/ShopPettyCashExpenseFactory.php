<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopPettyCashExpense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopPettyCashExpense>
 */
class ShopPettyCashExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'business_date' => today()->toDateString(),
            'amount' => fake()->randomFloat(2, 100, 1000),
            'previous_amount' => null,
            'source' => 'auto',
            'created_by' => User::factory(),
            'updated_by' => null,
            'amount_changed_by' => null,
            'amount_changed_at' => null,
        ];
    }
}
