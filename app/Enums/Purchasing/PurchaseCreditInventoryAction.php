<?php

declare(strict_types=1);

namespace App\Enums\Purchasing;

enum PurchaseCreditInventoryAction: string
{
    case Out = 'out';
    case In = 'in';
    case Damage = 'damage';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Out => 'Stock Out (Return to Vendor)',
            self::In => 'Stock In (Restock)',
            self::Damage => 'Damage / Wastage',
            self::None => 'None (Price / Commercial Adjustment Only)',
        };
    }
}
