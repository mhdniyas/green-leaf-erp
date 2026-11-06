<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Models\Product;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopInvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopCreditNoteItem>
 */
class ShopCreditNoteItemFactory extends Factory
{
    protected $model = ShopCreditNoteItem::class;

    public function definition(): array
    {
        $product = Product::factory()->create();

        return [
            'shop_credit_note_id' => ShopCreditNote::factory(),
            'shop_invoice_item_id' => null,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'product_name' => $product->name,
            'unit' => $product->unit ?? 'kg',
            'price_unit' => $product->unit ?? 'kg',
            'original_quantity' => 10.0000,
            'original_unit_price' => 50.00,
            'original_line_total' => 500.00,
            'adjusted_quantity' => 2.0000,
            'adjusted_unit_price' => 50.00,
            'line_credit_amount' => 100.00,
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'notes' => null,
        ];
    }

    public function forInvoiceItem(
        ShopInvoiceItem $item,
        float $adjustedQty = 1.0,
        ?float $adjustedPrice = null,
        ShopCreditNoteDirection $direction = ShopCreditNoteDirection::Credit,
        ShopCreditNoteInventoryAction $inventoryAction = ShopCreditNoteInventoryAction::None,
    ): static {
        $price = $adjustedPrice ?? (float) $item->unit_price;
        $lineAmount = round($adjustedQty * $price, 2);

        return $this->state(fn (array $attributes) => [
            'shop_invoice_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_grade' => $item->product_grade ?? 'A',
            'product_name' => $item->product_name,
            'unit' => $item->unit,
            'price_unit' => $item->price_unit,
            'original_quantity' => (float) ($item->delivered_qty ?? $item->approved_qty ?? 0),
            'original_unit_price' => (float) $item->unit_price,
            'original_line_total' => (float) ($item->final_line_total ?? $item->line_subtotal ?? 0),
            'adjusted_quantity' => $adjustedQty,
            'adjusted_unit_price' => $price,
            'line_credit_amount' => $lineAmount,
            'direction' => $direction,
            'inventory_action' => $inventoryAction,
        ]);
    }
}
