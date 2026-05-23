<?php

declare(strict_types=1);

namespace App\Enums\Sales;

enum SalesInvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-gray-100 text-gray-800 border-gray-200',
            self::PartiallyPaid => 'bg-amber-100 text-amber-800 border-amber-200',
            self::Paid => 'bg-green-100 text-green-800 border-green-200',
            self::Overdue => 'bg-red-100 text-red-800 border-red-200',
        };
    }
}
