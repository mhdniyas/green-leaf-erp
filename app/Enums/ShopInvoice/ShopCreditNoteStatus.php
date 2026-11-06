<?php

declare(strict_types=1);

namespace App\Enums\ShopInvoice;

enum ShopCreditNoteStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Voided => 'Voided',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-800 border-slate-200',
            self::Approved => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::Voided => 'bg-rose-100 text-rose-800 border-rose-200',
        };
    }
}
