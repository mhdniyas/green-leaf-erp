<?php

declare(strict_types=1);

namespace App\Enums\Purchasing;

enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Approval',
            self::Approved => 'Approved for Payment',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 border-amber-200',
            self::Approved => 'bg-blue-100 text-blue-800 border-blue-200',
            self::Paid => 'bg-green-100 text-green-800 border-green-200',
            self::Cancelled => 'bg-rose-100 text-rose-800 border-rose-200',
        };
    }
}
