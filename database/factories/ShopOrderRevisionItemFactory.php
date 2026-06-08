<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ShopOrderRevision;
use App\Models\ShopOrderRevisionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopOrderRevisionItem>
 */
class ShopOrderRevisionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_order_revision_id' => ShopOrderRevision::factory(),
            'product_id' => Product::factory(),
            'old_requested_qty' => 10.00,
            'new_requested_qty' => 12.00,
            'delta_qty' => 2.00,
        ];
    }
}
