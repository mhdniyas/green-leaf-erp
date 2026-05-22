<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\WastageEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WastageEntry>
 */
class WastageEntryFactory extends Factory
{
    protected $model = WastageEntry::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'batch_id' => StockBatch::factory(),
            'recorded_by' => User::factory(),
            'grade' => $this->faker->randomElement(ProductGrade::cases()),
            'quantity' => $this->faker->randomFloat(1, 1, 50),
            'cost_per_kg' => $this->faker->randomFloat(4, 0.50, 10.00),
            'reason' => $this->faker->randomElement(WastageReason::cases()),
            'wastage_date' => $this->faker->dateTimeBetween('-14 days', 'today'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
