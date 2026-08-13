<?php

declare(strict_types=1);

namespace App\Enums\Cashbook;

enum EntryCategory: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Settlement = 'settlement';
}
