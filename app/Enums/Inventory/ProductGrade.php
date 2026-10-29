<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum ProductGrade: string
{
    case GradeA = 'A';
    case GradeB = 'B';
    case GradeC = 'C';
    case Damage = 'D';
    case Unsorted = 'U';

    public static function tryResolve(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim($value);

        return match (strtoupper($trimmed)) {
            'A', 'GRADE A', 'GRADEA' => self::GradeA,
            'B', 'GRADE B', 'GRADEB' => self::GradeB,
            'C', 'GRADE C', 'GRADEC' => self::GradeC,
            'D', 'DAMAGE' => self::Damage,
            'U', 'UNSORTED' => self::Unsorted,
            default => self::tryFrom($trimmed),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GradeA => 'Grade A — Premium',
            self::GradeB => 'Grade B — Standard',
            self::GradeC => 'Grade C — Economy',
            self::Damage => 'Damage — Write-off',
            self::Unsorted => 'Unsorted',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::GradeA => 'green',
            self::GradeB => 'blue',
            self::GradeC => 'amber',
            self::Damage => 'red',
            self::Unsorted => 'slate',
        };
    }

    public function isSellable(): bool
    {
        return $this !== self::Damage && $this !== self::Unsorted;
    }
}
