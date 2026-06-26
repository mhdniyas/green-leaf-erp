<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';
    case Wastage = 'wastage';
    case Sale = 'sale';
    case SaleReversal = 'sale_reversal';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stock In',
            self::Out => 'Stock Out',
            self::Adjustment => 'Adjustment',
            self::Wastage => 'Wastage',
            self::Sale => 'Sale',
            self::SaleReversal => 'Sale Reversal',
        };
    }

    public function isDeduction(): bool
    {
        return match ($this) {
            self::Out, self::Wastage, self::Sale, self::Adjustment => true,
            default => false,
        };
    }
}
