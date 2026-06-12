<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopInvoice>
 */
class ShopInvoiceFactory extends Factory
{
    public function definition(): array
    {
        $shop = Shop::create([
            'code' => fake()->unique()->bothify('SHOP###'),
            'name' => fake()->company(),
        ]);
        $creator = User::factory()->create();
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $creator->id,
        ]);

        return [
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-'.today()->format('Ymd').'-'.$shop->code,
            'business_date' => today()->toDateString(),
            'status' => 'generated',
            'delivery_status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 0,
            'shortage_total' => 0,
            'discount_total' => 0,
            'final_total' => 0,
            'paid_amount' => 0,
            'balance_amount' => 0,
            'generated_by' => $creator->id,
        ];
    }
}
