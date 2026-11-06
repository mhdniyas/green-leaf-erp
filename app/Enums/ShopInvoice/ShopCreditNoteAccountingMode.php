<?php

declare(strict_types=1);

namespace App\Enums\ShopInvoice;

enum ShopCreditNoteAccountingMode: string
{
    case LegacyInvoiceLinked = 'legacy_invoice_linked';
    case ShopLevel = 'shop_level';

    public function label(): string
    {
        return match ($this) {
            self::LegacyInvoiceLinked => 'Legacy Invoice-Linked',
            self::ShopLevel => 'Shop-Level Multi-Source',
        };
    }

    public function isLegacy(): bool
    {
        return $this === self::LegacyInvoiceLinked;
    }

    public function isShopLevel(): bool
    {
        return $this === self::ShopLevel;
    }
}
