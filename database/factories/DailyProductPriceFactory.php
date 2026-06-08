<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Inventory\ProductGrade;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\ShopPriceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyProductPrice>
 */
class DailyProductPriceFactory extends Factory
{
    protected $model = DailyProductPrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'shop_price_group_id' => ShopPriceGroup::factory(),
            'grade' => ProductGrade::GradeA,
            'selling_price' => $this->faker->randomFloat(2, 15, 180),
            'price_source' => 'manual',
            'margin_percent' => null,
            'manual_override' => true,
        ];
    }
}
