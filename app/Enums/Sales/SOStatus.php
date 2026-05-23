<?php

declare(strict_types=1);

namespace App\Enums\Sales;

enum SOStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Dispatched = 'dispatched';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Confirmed => 'Confirmed',
            self::Dispatched => 'Dispatched',
            self::Invoiced => 'Invoiced',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-800 border-gray-200',
            self::Confirmed => 'bg-blue-100 text-blue-800 border-blue-200',
            self::Dispatched => 'bg-amber-100 text-amber-800 border-amber-200',
            self::Invoiced => 'bg-green-100 text-green-800 border-green-200',
            self::Cancelled => 'bg-red-100 text-red-800 border-red-200',
        };
    }

    public function canBeConfirmed(): bool
    {
        return $this === self::Draft;
    }

    public function canBeDispatched(): bool
    {
        return $this === self::Confirmed;
    }

    public function canBeInvoiced(): bool
    {
        return $this === self::Dispatched;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Draft, self::Confirmed], true);
    }
}
