<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopCreditNote>
 */
class ShopCreditNoteFactory extends Factory
{
    protected $model = ShopCreditNote::class;

    public function definition(): array
    {
        return [
            'credit_note_number' => 'SCN-'.fake()->unique()->numerify('20261105-SHOP####-##'),
            'shop_id' => fn (array $attributes) => ShopInvoice::find($attributes['shop_invoice_id'])?->shop_id ?? ShopInvoice::factory(),
            'shop_invoice_id' => ShopInvoice::factory(),
            'credit_note_date' => today()->toDateString(),
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'status' => ShopCreditNoteStatus::Draft,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'subtotal' => 0.00,
            'total_credit_amount' => 0.00,
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
            'voided_by' => null,
            'voided_at' => null,
            'void_reason' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShopCreditNoteStatus::Approved,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function voided(string $reason = 'Cancelled by admin'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShopCreditNoteStatus::Voided,
            'voided_by' => User::factory(),
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);
    }

    public function forInvoice(ShopInvoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $invoice->shop_id,
            'shop_invoice_id' => $invoice->id,
            'credit_note_date' => $invoice->business_date?->toDateString() ?? today()->toDateString(),
            'credit_note_number' => ShopCreditNote::generateCreditNoteNumber($invoice),
        ]);
    }
}
