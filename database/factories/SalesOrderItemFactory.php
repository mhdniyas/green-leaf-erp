<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Inventory\ProductGrade;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrderItem>
 */
class SalesOrderItemFactory extends Factory
{
    protected $model = SalesOrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(),
            'product_id' => Product::factory(),
            'grade' => $this->faker->randomElement([ProductGrade::GradeA, ProductGrade::GradeB, ProductGrade::GradeC]),
            'quantity' => $this->faker->randomFloat(3, 1, 100),
            'unit_price' => $this->faker->randomFloat(4, 10, 200),
        ];
    }
}
