<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopCreditApplication;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopCreditApplication>
 */
class ShopCreditApplicationFactory extends Factory
{
    protected $model = ShopCreditApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_credit_note_id' => ShopCreditNote::factory(),
            'shop_invoice_id' => ShopInvoice::factory(),
            'shop_id' => fn (array $attributes) => ShopCreditNote::find($attributes['shop_credit_note_id'])?->shop_id ?? Shop::factory(),
            'amount' => 100.00,
            'applied_at' => now(),
            'applied_by' => User::factory(),
            'notes' => null,
            'reversed_at' => null,
            'reversed_by' => null,
            'reversal_reason' => null,
        ];
    }

    public function reversed(?string $reason = 'Administrative reversal'): static
    {
        return $this->state(fn () => [
            'reversed_at' => now(),
            'reversed_by' => User::factory(),
            'reversal_reason' => $reason,
        ]);
    }
}
