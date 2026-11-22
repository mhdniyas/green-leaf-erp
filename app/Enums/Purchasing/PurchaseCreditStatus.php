<?php

declare(strict_types=1);

namespace App\Enums\Purchasing;

enum PurchaseCreditStatus: string
{
    case Active = 'active';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Voided => 'Voided',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::Voided => 'bg-rose-100 text-rose-800 border-rose-200',
        };
    }
}
