<?php

declare(strict_types=1);

namespace App\Enums\Warehouse;

enum WarehouseCreditInventoryAction: string
{
    case In = 'in';
    case Out = 'out';
    case Damage = 'damage';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stock IN',
            self::Out => 'Stock OUT',
            self::Damage => 'Damage / Wastage',
        };
    }

    public function isDeduction(): bool
    {
        return match ($this) {
            self::Out, self::Damage => true,
            self::In => false,
        };
    }
}
