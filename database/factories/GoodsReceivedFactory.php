<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceived>
 */
class GoodsReceivedFactory extends Factory
{
    protected $model = GoodsReceived::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'grn_number' => 'GRN-'.now()->format('Ymd').'-'.str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'received_by' => User::factory(),
            'received_at' => $this->faker->dateTimeBetween('-15 days', 'today')->format('Y-m-d'),
            'transport_cost' => $this->faker->randomFloat(2, 20, 200),
            'labour_cost' => $this->faker->randomFloat(2, 10, 100),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
