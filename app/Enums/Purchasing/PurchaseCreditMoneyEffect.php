<?php

declare(strict_types=1);

namespace App\Enums\Purchasing;

enum PurchaseCreditMoneyEffect: string
{
    case VendorCredit = 'vendor_credit';
    case MoneyIn = 'in';
    case MoneyOut = 'out';

    public function label(): string
    {
        return match ($this) {
            self::VendorCredit => 'Vendor Credit (Adjust Payable)',
            self::MoneyIn => 'Money In (Cash/Bank Refund)',
            self::MoneyOut => 'Money Out (Payment/Adjustment)',
        };
    }
}
