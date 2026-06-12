<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Inventory\BatchStatus;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBatch>
 */
class StockBatchFactory extends Factory
{
    protected $model = StockBatch::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'product_id' => Product::factory(),
            'created_by' => User::factory(),
            'reference' => 'BATCH-'.now()->format('Ymd').'-'.str_pad((string) $counter++, 3, '0', STR_PAD_LEFT),
            'received_at' => $this->faker->dateTimeBetween('-30 days', 'today'),
            'total_kg' => $this->faker->randomFloat(1, 50, 1000),
            'cost_per_kg' => $this->faker->randomFloat(2, 0.50, 10.00),
            'transport_cost' => $this->faker->randomFloat(2, 0, 200),
            'labour_cost' => $this->faker->randomFloat(2, 0, 100),
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
            'warehouse_confirmed_at' => null,
            'warehouse_confirmed_by' => null,
            'notes' => $this->faker->optional()->sentence(),
            'sorted_at' => null,
        ];
    }

    public function sorted(): static
    {
        return $this->state([
            'status' => BatchStatus::Sorted,
            'sorted_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state([
            'status' => BatchStatus::Pending,
            'sorted_at' => null,
        ]);
    }

    public function warehousePending(): static
    {
        return $this->state([
            'warehouse_receive_pending' => true,
            'warehouse_confirmed_at' => null,
            'warehouse_confirmed_by' => null,
        ]);
    }

    public function warehouseConfirmed(): static
    {
        return $this->state([
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => User::factory(),
        ]);
    }
}
