<?php

declare(strict_types=1);

namespace App\Enums\ShopInvoice;

enum ShopCreditNoteDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Credit (Reduces Shop Payable)',
            self::Debit => 'Debit (Increases Shop Payable)',
        };
    }
}
