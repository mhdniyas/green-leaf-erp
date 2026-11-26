<?php

declare(strict_types=1);

namespace App\Enums\Warehouse;

enum WarehouseCreditReason: string
{
    case ExcessFound = 'excess_found';
    case StockReturn = 'stock_return';
    case Shortage = 'shortage';
    case DamageWastage = 'damage_wastage';
    case Spoilage = 'spoilage';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ExcessFound => 'Excess Stock Found',
            self::StockReturn => 'Stock Return / Adjustment',
            self::Shortage => 'Weight / Quantity Shortage',
            self::DamageWastage => 'Damage / Wastage',
            self::Spoilage => 'Spoilage / Expiry',
            self::Other => 'Other',
        };
    }
}
