<?php

declare(strict_types=1);

namespace App\Enums\ShopInvoice;

enum ShopCreditNoteInventoryAction: string
{
    case None = 'none';
    case ReturnToStock = 'return_to_stock';
    case WriteOffWastage = 'write_off_wastage';
    case DeductFromStock = 'deduct_from_stock';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No Inventory Effect',
            self::ReturnToStock => 'Return to Stock',
            self::WriteOffWastage => 'Write Off as Wastage',
            self::DeductFromStock => 'Deduct Extra from Warehouse',
        };
    }
}
