<?php

declare(strict_types=1);

namespace App\Enums\Cashbook;

enum TransactionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Posted = 'posted';
    case Approved = 'approved';
    case Closed = 'closed';
    case Void = 'void';
    case Reversed = 'reversed';
}
