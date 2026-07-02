<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopInvoicePaymentRequest>
 */
class ShopInvoicePaymentRequestFactory extends Factory
{
    public function definition(): array
    {
        $invoice = ShopInvoice::factory()->create([
            'final_total' => 500.00,
            'paid_amount' => 100.00,
            'balance_amount' => 400.00,
        ]);

        return [
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $invoice->shop_id,
            'requested_by' => User::factory(),
            'request_type' => 'custom',
            'requested_amount' => 150.00,
            'approved_amount' => null,
            'status' => 'pending',
            'shop_note' => fake()->sentence(),
            'admin_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
