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

    public static function tryResolve(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['damaged_at_shop', 'damage_at_shop', 'wastage', 'damaged', 'write_off_wastage'], true)) {
            return self::WriteOffWastage;
        }

        if (in_array($normalized, ['return_to_stock', 'return_to_warehouse', 'return', 'stock_return'], true)) {
            return self::ReturnToStock;
        }

        if (in_array($normalized, ['none', 'no_inventory_effect'], true)) {
            return self::None;
        }

        if (in_array($normalized, ['deduct_from_stock', 'deduct_extra'], true)) {
            return self::DeductFromStock;
        }

        return self::tryFrom($normalized);
    }
}
