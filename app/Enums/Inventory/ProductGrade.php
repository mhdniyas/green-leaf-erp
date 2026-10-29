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
     * Get short display suffix for bill and invoice items (e.g. '(A)', '(B)', '(C)', '(Mixed)').
     */
    public function suffix(): string
    {
        return match ($this) {
            self::GradeA => '(A)',
            self::GradeB => '(B)',
            self::GradeC => '(C)',
            self::Unsorted => '(Mixed)',
            self::Damage => '(DMG)',
        };
    }

    /**
     * Format a product name with its grade suffix, preventing duplicate suffixes.
     */
    public static function formatNameWithGrade(string $productName, string|self|null $grade): string
    {
        $trimmedName = trim($productName);
        if ($grade === null || trim((string) ($grade instanceof self ? $grade->value : $grade)) === '') {
            return $trimmedName;
        }

        $resolved = self::tryResolve($grade);
        if (! $resolved) {
            return $trimmedName;
        }

        $suffix = $resolved->suffix();

        if (str_ends_with($trimmedName, $suffix) || str_ends_with($trimmedName, " ({$resolved->value})")) {
            return $trimmedName;
        }

        return "{$trimmedName} {$suffix}";
    }

    /**
     * Format grade for invoice display.
     */
    public static function formatForInvoice(string|self|null $grade): string
    {
        if ($grade === null || trim((string) ($grade instanceof self ? $grade->value : $grade)) === '') {
            return 'Not specified';
        }

        $resolved = self::tryResolve($grade);

        return match ($resolved) {
            self::GradeA => 'Grade A',
            self::GradeB => 'Grade B',
            self::GradeC => 'Grade C',
            self::Unsorted => 'Mixed',
            default => 'Not specified',
        };
    }

    /**
     * Badge CSS class for invoice grade display.
     */
    public static function invoiceBadgeClass(string|self|null $grade): string
    {
        $resolved = self::tryResolve($grade);

        return match ($resolved) {
            self::GradeA => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            self::GradeB => 'border-blue-200 bg-blue-50 text-blue-800',
            self::GradeC => 'border-amber-200 bg-amber-50 text-amber-800',
            self::Unsorted => 'border-purple-200 bg-purple-50 text-purple-800',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
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
