<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum ProductGrade: string
{
    case GradeA = 'A';
    case GradeB = 'B';
    case GradeC = 'C';
    case Damage = 'D';

    public function label(): string
    {
        return match ($this) {
            self::GradeA => 'Grade A — Premium',
            self::GradeB => 'Grade B — Standard',
            self::GradeC => 'Grade C — Economy',
            self::Damage => 'Damage — Write-off',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::GradeA => 'green',
            self::GradeB => 'blue',
            self::GradeC => 'amber',
            self::Damage => 'red',
        };
    }

    public function isSellable(): bool
    {
        return $this !== self::Damage;
    }
}
