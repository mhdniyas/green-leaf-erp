<?php

declare(strict_types=1);

namespace App\Enums\Purchasing;

enum POStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case SentToSupplier = 'sent_to_supplier';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Closed = 'closed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::SentToSupplier => 'Sent to Supplier',
            self::PartiallyReceived => 'Partially Received',
            self::Received => 'Received',
            self::Closed => 'Closed',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-800 border-gray-200',
            self::Approved => 'bg-blue-100 text-blue-800 border-blue-200',
            self::SentToSupplier => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            self::PartiallyReceived => 'bg-orange-100 text-orange-800 border-orange-200',
            self::Received => 'bg-amber-100 text-amber-800 border-amber-200',
            self::Closed => 'bg-green-100 text-green-800 border-green-200',
            self::Rejected => 'bg-red-100 text-red-800 border-red-200',
        };
    }
}
