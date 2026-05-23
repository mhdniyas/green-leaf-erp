<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseInvoice>
 */
class PurchaseInvoiceFactory extends Factory
{
    protected $model = PurchaseInvoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goods_received_id' => GoodsReceived::factory(),
            'supplier_id' => Supplier::factory(),
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'status' => $this->faker->randomElement(InvoiceStatus::cases()),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
