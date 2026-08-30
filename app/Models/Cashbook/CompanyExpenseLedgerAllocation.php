<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyExpenseLedgerAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_statement_entry_id',
        'payment_request_id',
        'shop_id',
        'shop_ledger_transaction_id',
        'allocated_amount',
        'allocation_date',
        'status',
        'notes',
        'allocated_by',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'allocation_date' => 'date',
            'reversed_at' => 'datetime',
        ];
    }

    public function statementEntry(): BelongsTo
    {
        return $this->belongsTo(CompanyAccountStatementEntry::class, 'company_statement_entry_id');
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(ShopInvoicePaymentRequest::class, 'payment_request_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(ShopLedgerTransaction::class, 'shop_ledger_transaction_id');
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
