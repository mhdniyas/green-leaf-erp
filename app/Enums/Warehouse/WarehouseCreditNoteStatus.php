<?php

declare(strict_types=1);

namespace App\Enums\Warehouse;

enum WarehouseCreditNoteStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Voided => 'Voided',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'amber',
            self::Active => 'green',
            self::Voided => 'red',
        };
    }
}
