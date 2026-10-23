<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum ProductGrade: string
{
    case GradeA = 'A';
    case GradeB = 'B';
    case GradeC = 'C';
    case Damage = 'DMG';
    case Unsorted = 'U';

    public static function tryResolve(string|self|null $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim((string) $value);

        return match (strtoupper($trimmed)) {
            'A', 'GRADE A', 'GRADEA' => self::GradeA,
            'B', 'GRADE B', 'GRADEB' => self::GradeB,
            'C', 'GRADE C', 'GRADEC' => self::GradeC,
            'DMG', 'DAMAGE', 'WASTAGE' => self::Damage,
            'D', 'GRADE D', 'GRADED' => self::Damage, // Legacy compatibility: historical damage was occasionally recorded as 'D'
            'U', 'UNSORTED', 'MIXED', 'MIXED / UNSEGREGATED', 'UNSEGREGATED' => self::Unsorted,
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
            self::Unsorted => 'Mixed / Unsegregated',
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

    public function isDirectGraded(): bool
    {
        return in_array($this, [self::GradeA, self::GradeB, self::GradeC], true);
    }

    /**
     * Get list of valid commercial product grades (A, B, C).
     *
     * @return array<int, self>
     */
    public static function commercialGrades(): array
    {
        return [self::GradeA, self::GradeB, self::GradeC];
    }
}
