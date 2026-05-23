<?php

declare(strict_types=1);

namespace App\Enums\Purchasing;

enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Approval',
            self::Approved => 'Approved for Payment',
            self::Paid => 'Paid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 border-amber-200',
            self::Approved => 'bg-blue-100 text-blue-800 border-blue-200',
            self::Paid => 'bg-green-100 text-green-800 border-green-200',
        };
    }
}
