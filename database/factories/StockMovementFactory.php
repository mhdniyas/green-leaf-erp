<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'batch_id' => StockBatch::factory(),
            'product_id' => Product::factory(),
            'created_by' => User::factory(),
            'grade' => $this->faker->randomElement(ProductGrade::cases()),
            'type' => StockMovementType::In,
            'quantity' => $this->faker->randomFloat(1, 10, 300),
            'cost_per_unit' => $this->faker->randomFloat(4, 0.50, 15.00),
            'notes' => null,
        ];
    }
}
