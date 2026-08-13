<?php

declare(strict_types=1);

namespace App\Enums\Cashbook;

enum FundingSource: string
{
    case Sales = 'sales';
    case Petty = 'petty';
    case Company = 'company';
    case CompanyLater = 'company_later';
    case Bank = 'bank';
    case External = 'external';
    case None = 'none';
}
