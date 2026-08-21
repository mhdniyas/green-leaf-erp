<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPaymentReconciliation extends Model
{
    protected $table = 'cashbook_company_payment_reconciliations';

    protected $fillable = [
        'payment_request_id',
        'shop_id',
        'company_account_id',
        'statement_entry_id',
        'journal_entry_id',
        'statement_amount',
        'cleared_amount',
        'difference_amount',
        'difference_action',
        'difference_entry_type_id',
        'difference_transaction_id',
        'status',
        'is_finalized',
        'finalized_at',
        'admin_note',
        'reconciled_by',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'statement_amount' => 'decimal:2',
            'cleared_amount' => 'decimal:2',
            'difference_amount' => 'decimal:2',
            'is_finalized' => 'boolean',
            'finalized_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(ShopInvoicePaymentRequest::class, 'payment_request_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function statementEntry(): BelongsTo
    {
        return $this->belongsTo(CompanyAccountStatementEntry::class, 'statement_entry_id');
    }

    public function differenceEntryType(): BelongsTo
    {
        return $this->belongsTo(LedgerEntryType::class, 'difference_entry_type_id');
    }

    public function differenceTransaction(): BelongsTo
    {
        return $this->belongsTo(ShopLedgerTransaction::class, 'difference_transaction_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
