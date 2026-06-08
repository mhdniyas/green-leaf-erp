<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShopOrder;
use App\Models\ShopOrderRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopOrderRevision>
 */
class ShopOrderRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_order_id' => ShopOrder::factory(),
            'revision_no' => 2,
            'status' => 'pending',
            'reason' => $this->faker->sentence(),
            'requested_by' => User::factory(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
