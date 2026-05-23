<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Purchasing\POStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'po_number' => 'PO-'.now()->format('Ymd').'-'.str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $this->faker->randomElement(POStatus::cases()),
            'order_date' => $this->faker->dateTimeBetween('-30 days', 'today')->format('Y-m-d'),
            'created_by' => User::factory(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
