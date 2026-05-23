<?php

declare(strict_types=1);

namespace App\Enums\Purchasing;

enum POStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Received = 'received';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Received => 'Received',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-800 border-gray-200',
            self::Approved => 'bg-blue-100 text-blue-800 border-blue-200',
            self::Received => 'bg-amber-100 text-amber-800 border-amber-200',
            self::Closed => 'bg-green-100 text-green-800 border-green-200',
        };
    }
}
