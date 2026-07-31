<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPayableSettlement extends Model
{
    public const TypeAdjustAgainstShopPayment = 'adjust_against_shop_payment';

    public const TypeDirectCompanyPayment = 'direct_company_payment';

    protected $fillable = [
        'shop_accounting_entry_line_id',
        'shop_id',
        'settlement_type',
        'amount',
        'settlement_date',
        'shop_invoice_payment_request_id',
        'company_accounting_entry_id',
        'journal_entry_id',
        'payment_account_id',
        'reference',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'settlement_date' => 'date',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ShopAccountingEntryLine::class, 'shop_accounting_entry_line_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(ShopInvoicePaymentRequest::class, 'shop_invoice_payment_request_id');
    }

    public function companyAccountingEntry(): BelongsTo
    {
        return $this->belongsTo(CompanyAccountingEntry::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
