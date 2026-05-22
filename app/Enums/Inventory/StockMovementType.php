<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';
    case Wastage = 'wastage';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stock In',
            self::Out => 'Stock Out',
            self::Adjustment => 'Adjustment',
            self::Wastage => 'Wastage',
        };
    }

    public function isDeduction(): bool
    {
        return match ($this) {
            self::Out, self::Wastage => true,
            default => false,
        };
    }
}
