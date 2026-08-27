<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorSettlement>
 */
class VendorSettlementFactory extends Factory
{
    protected $model = VendorSettlement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'actual_payment_amount' => $this->faker->randomFloat(2, 100, 5000),
            'settlement_discount_amount' => 0.00,
            'vendor_advance_used_amount' => 0.00,
            'new_vendor_advance_amount' => 0.00,
            'payment_method' => 'Cash',
            'payment_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'status' => 'approved',
            'reconciliation_status' => 'not_required',
            'is_finalized' => false,
            'created_by' => User::factory(),
        ];
    }
}
