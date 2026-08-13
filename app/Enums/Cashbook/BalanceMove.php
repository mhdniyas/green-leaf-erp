<?php

declare(strict_types=1);

namespace App\Enums\Cashbook;

enum BalanceMove: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';
    case None = 'none';
}
