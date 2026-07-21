<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShopOrder;
use App\Models\ShopOrderChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopOrderChangeRequest>
 */
class ShopOrderChangeRequestFactory extends Factory
{
    protected $model = ShopOrderChangeRequest::class;

    public function definition(): array
    {
        return [
            'shop_order_id' => ShopOrder::factory(),
            'shop_order_revision_id' => null,
            'type' => 'quantity_update',
            'status' => 'pending',
            'requested_by' => User::factory(),
            'requested_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'reason' => $this->faker->optional()->sentence(),
            'manager_note' => null,
        ];
    }
}
