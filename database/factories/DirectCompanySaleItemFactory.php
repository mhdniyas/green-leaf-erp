<?php

namespace Database\Factories;

use App\Models\DirectCompanySale;
use App\Models\DirectCompanySaleItem;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DirectCompanySaleItem>
 */
class DirectCompanySaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'direct_company_sale_id' => DirectCompanySale::factory(),
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'unit' => 'kg',
            'quantity' => 1,
            'conversion_to_base' => 1,
            'base_quantity' => 1,
            'unit_rate' => 100,
            'line_total' => 100,
            'price_source' => 'normal',
        ];
    }
}
