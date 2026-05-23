<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceivedItem>
 */
class GoodsReceivedItemFactory extends Factory
{
    protected $model = GoodsReceivedItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goods_received_id' => GoodsReceived::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'product_id' => Product::factory(),
            'received_qty' => $this->faker->randomFloat(3, 50, 1000),
            'variance' => 0.000,
        ];
    }
}
