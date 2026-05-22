<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum WastageReason: string
{
    case Rotten = 'rotten';
    case TransitDamage = 'transit_damage';
    case Expired = 'expired';
    case Unsold = 'unsold';
    case Shrinkage = 'shrinkage';
    case SortingDamage = 'sorting_damage';

    public function label(): string
    {
        return match ($this) {
            self::Rotten => 'Rotten / Spoiled',
            self::TransitDamage => 'Transit Damage',
            self::Expired => 'Expired',
            self::Unsold => 'Unsold / Aged Out',
            self::Shrinkage => 'Weight Shrinkage',
            self::SortingDamage => 'Sorting / Grading Damage',
        };
    }
}
