<?php

declare(strict_types=1);

namespace App\DTOs\ShopInvoice;

use App\DTOs\BaseDTO;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;

class ValidatedSourceLineItem extends BaseDTO
{
    public function __construct(
        public int $sourceShopInvoiceId,
        public int $shopInvoiceItemId,
        public int $productId,
        public string $productName,
        public string $productGrade,
        public string $unit,
        public ?string $priceUnit,
        public float $originalQuantity,
        public float $originalUnitPrice,
        public float $originalLineTotal,
        public float $remainingEligibleQuantity,
        public float $remainingEligibleAmount,
        public float $creditQuantity,
        public float $creditRate,
        public float $creditAmount,
        public ShopCreditNoteDirection $direction,
        public ShopCreditNoteInventoryAction $inventoryAction,
        public ?string $notes = null,
        public ?string $receivedGrade = null,
        public ?ShopCreditNoteReason $reasonType = null,
        public ?ShopInvoice $invoice = null,
        public ?ShopInvoiceItem $invoiceItem = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toCreditNoteItemPayload(): array
    {
        return [
            'shop_invoice_item_id' => $this->shopInvoiceItemId,
            'source_shop_invoice_id' => $this->sourceShopInvoiceId,
            'product_id' => $this->productId,
            'product_grade' => $this->productGrade,
            'received_grade' => $this->receivedGrade,
            'product_name' => $this->productName,
            'unit' => $this->unit,
            'price_unit' => $this->priceUnit,
            'original_quantity' => $this->originalQuantity,
            'original_unit_price' => $this->originalUnitPrice,
            'original_line_total' => $this->originalLineTotal,
            'adjusted_quantity' => $this->creditQuantity,
            'adjusted_unit_price' => $this->creditRate,
            'credit_rate' => $this->creditRate,
            'line_credit_amount' => $this->creditAmount,
            'direction' => $this->direction,
            'inventory_action' => $this->inventoryAction,
            'reason_type' => $this->reasonType,
            'notes' => $this->notes,
        ];
    }
}
