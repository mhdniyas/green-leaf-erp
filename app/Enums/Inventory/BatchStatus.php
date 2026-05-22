<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum BatchStatus: string
{
    case Pending = 'pending';
    case Sorted = 'sorted';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting Sort',
            self::Sorted => 'Sorted',
            self::Closed => 'Closed',
        };
    }

    public function canBeSorted(): bool
    {
        return $this === self::Pending;
    }
}
