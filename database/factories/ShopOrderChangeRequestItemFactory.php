<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ShopOrderChangeRequest;
use App\Models\ShopOrderChangeRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopOrderChangeRequestItem>
 */
class ShopOrderChangeRequestItemFactory extends Factory
{
    protected $model = ShopOrderChangeRequestItem::class;

    public function definition(): array
    {
        $oldQuantity = $this->faker->randomFloat(2, 1, 20);
        $newQuantity = $this->faker->randomFloat(2, 1, 20);

        return [
            'shop_order_change_request_id' => ShopOrderChangeRequest::factory(),
            'product_id' => Product::factory(),
            'old_qty' => $oldQuantity,
            'new_qty' => $newQuantity,
            'approved_qty' => null,
            'delta_qty' => round($newQuantity - $oldQuantity, 2),
        ];
    }
}
