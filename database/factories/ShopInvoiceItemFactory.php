<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopInvoiceItem>
 */
class ShopInvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $product = Product::factory()->create();

        return [
            'shop_invoice_id' => ShopInvoice::factory(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => $product->unit,
            'approved_qty' => 10,
            'delivered_qty' => 10,
            'shortage_qty' => 0,
            'unit_price' => 25,
            'line_subtotal' => 250,
            'shortage_amount' => 0,
            'final_line_total' => 250,
        ];
    }
}
