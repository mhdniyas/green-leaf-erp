<?php

declare(strict_types=1);

namespace App\Enums\ShopInvoice;

enum ShopCreditNoteLineType: string
{
    case ProductAdjustment = 'product_adjustment';
    case CommercialDiscount = 'commercial_discount';

    public function label(): string
    {
        return match ($this) {
            self::ProductAdjustment => 'Product Adjustment',
            self::CommercialDiscount => 'Commercial Discount',
        };
    }
}
