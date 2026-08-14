<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopLedgerTransaction extends Model
{
    protected $table = 'shop_ledger_transactions';

    protected $fillable = [
        'shop_id', 'business_date', 'entry_type_id', 'amount', 'direction', 'funding_source',
        'affects_sales', 'affects_income', 'affects_expense', 'affects_pl',
        'pl_delta', 'settlement_delta', 'settlement_direction',
        'petty_delta', 'petty_direction',
        'company_pending_delta', 'company_pending_direction',
        'company_account_id', 'parent_transaction_id', 'generated_by_rule', 'status',
        'reference_type', 'reference_id', 'notes', 'entered_by', 'approved_by',
        'voided_by', 'voided_at', 'void_reason',
    ];

    protected $casts = [
        'business_date'         => 'date:Y-m-d',
        'amount'                => 'decimal:2',
        'affects_sales'         => 'boolean',
        'affects_income'        => 'boolean',
        'affects_expense'       => 'boolean',
        'affects_pl'            => 'boolean',
        'pl_delta'              => 'decimal:2',
        'settlement_delta'      => 'decimal:2',
        'petty_delta'           => 'decimal:2',
        'company_pending_delta' => 'decimal:2',
        'generated_by_rule'     => 'boolean',
        'voided_at'             => 'datetime',
    ];

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(LedgerEntryType::class, 'entry_type_id');
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_transaction_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'submitted' => 'Pending Approval',
            'posted' => 'Posted',
            'approved' => 'Approved',
            'closed' => 'Closed',
            'void' => 'Voided',
            default => str((string) $this->status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'approved' => 'success',
            'submitted' => 'warning',
            'closed', 'void' => 'neutral',
            default => 'neutral',
        };
    }

    public function canBeEditedByShopOwner(): bool
    {
        if (
            $this->reference_type === 'App\Models\ShopInvoice' ||
            $this->reference_type === \App\Models\ShopInvoice::class ||
            $this->reference_type === 'ShopInvoice' ||
            $this->entryType?->code === 'purchase_bill' ||
            $this->entry_type_code === 'purchase_bill'
        ) {
            return false;
        }

        return in_array($this->status, ['draft', 'submitted', 'posted'], true);
    }
}
