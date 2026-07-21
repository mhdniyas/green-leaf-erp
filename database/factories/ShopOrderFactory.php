<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopOrder>
 */
class ShopOrderFactory extends Factory
{
    protected $model = ShopOrder::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'state' => 'submitted',
            'delivery_status' => 'pending_delivery',
            'payment_status' => 'unpaid',
            'business_date' => now()->addDay()->toDateString(),
            'submitted_at' => now(),
            'deadline_at' => now()->addHours(2),
            'created_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'state' => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    public function late(): static
    {
        return $this->state([
            'is_late' => true,
        ]);
    }
}
