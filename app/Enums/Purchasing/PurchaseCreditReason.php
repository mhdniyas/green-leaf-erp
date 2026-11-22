<?php

declare(strict_types=1);

namespace App\Enums\Purchasing;

enum PurchaseCreditReason: string
{
    case Return = 'return';
    case DamageWastage = 'damage_wastage';
    case Shortage = 'shortage';
    case PriceAdjustment = 'price_adjustment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Return => 'Product Return',
            self::DamageWastage => 'Damage / Wastage',
            self::Shortage => 'Weight / Quantity Shortage',
            self::PriceAdjustment => 'Price Adjustment / Discount',
            self::Other => 'Other',
        };
    }
}
