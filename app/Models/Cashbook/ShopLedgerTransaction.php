<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Enums\Cashbook\TransactionStatus;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopStaffPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

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
        'business_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'affects_sales' => 'boolean',
        'affects_income' => 'boolean',
        'affects_expense' => 'boolean',
        'affects_pl' => 'boolean',
        'pl_delta' => 'decimal:2',
        'settlement_delta' => 'decimal:2',
        'petty_delta' => 'decimal:2',
        'company_pending_delta' => 'decimal:2',
        'generated_by_rule' => 'boolean',
        'voided_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

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

    public function paymentLedgerAllocations(): HasMany
    {
        return $this->hasMany(ShopPaymentLedgerAllocation::class, 'shop_ledger_transaction_id');
    }

    public function companyExpenseAllocations(): HasMany
    {
        return $this->hasMany(CompanyExpenseLedgerAllocation::class, 'shop_ledger_transaction_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function statementEntries(): HasMany
    {
        return $this->hasMany(CompanyAccountStatementEntry::class, 'source_id')
            ->where('source_type', self::class);
    }

    public function isReconciled(): bool
    {
        return CompanyAccountStatementEntry::query()
            ->where('source_type', self::class)
            ->where('source_id', $this->id)
            ->where('is_finalized', true)
            ->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            TransactionStatus::Void->value,
            TransactionStatus::Reversed->value,
            'void',
            'voided',
            'reversed',
        ]);
    }

    public function secureRouteKey(): string
    {
        return rtrim(strtr(base64_encode(Crypt::encryptString('shop-ledger:'.$this->getKey())), '+/', '-_'), '=');
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
            $this->reference_type === ShopInvoice::class ||
            $this->reference_type === 'ShopInvoice'
        ) {
            return false;
        }

        if ($this->isReconciled()) {
            return false;
        }

        return in_array($this->status, ['draft', 'submitted', 'posted'], true);
    }

    public function isGlBill(): bool
    {
        if ($this->reference_type === PurchaseInvoice::class) {
            return true;
        }

        if (
            $this->reference_type === 'App\Models\ShopInvoice' ||
            $this->reference_type === ShopInvoice::class ||
            $this->reference_type === 'ShopInvoice'
        ) {
            return true;
        }

        $code = $this->entryType?->code;
        if (in_array($code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true)) {
            return true;
        }

        $name = strtolower((string) ($this->entryType?->name ?? ''));

        return str_contains($name, 'gl bill');
    }

    public function isSalary(): bool
    {
        if (
            $this->reference_type === 'App\Models\ShopStaffPayment' ||
            $this->reference_type === ShopStaffPayment::class ||
            $this->reference_type === 'ShopStaffPayment'
        ) {
            return true;
        }

        $code = $this->entryType?->code;
        if (in_array($code, ['salary', 'staff_advance', 'salary_advance', 'salary_adjustment', 'advance_recovery'], true)) {
            return true;
        }

        $name = strtolower((string) ($this->entryType?->name ?? ''));

        return str_contains($name, 'salary') || str_contains($name, 'staff advance');
    }

    public function isProtectedSalaryOrGlBill(): bool
    {
        return $this->isGlBill() || $this->isSalary();
    }

    public function isManualCashbookEntry(): bool
    {
        return ! $this->generated_by_rule
            && $this->reference_type === null
            && $this->reference_id === null
            && ! $this->isProtectedSalaryOrGlBill();
    }
}
