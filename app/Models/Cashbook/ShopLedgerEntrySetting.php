<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\ShopSupplier;
use App\Services\Cashbook\CashFlowResolutionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopLedgerEntrySetting extends Model
{
    protected $table = 'shop_ledger_entry_settings';

    protected $fillable = [
        'shop_id', 'entry_type_id', 'display_name', 'header_group_id', 'header_display_order', 'company_account_id', 'version', 'effective_from', 'effective_to',
        'enabled', 'show_in_summary', 'note_enabled', 'is_readonly', 'edit_policy', 'default_funding_source', 'allowed_funding_sources',
        'include_in_sales', 'include_in_income', 'include_in_expense', 'include_in_pl',
        'include_in_payable', 'payable_direction',
        'settlement_behavior', 'petty_behavior', 'company_pending_behavior',
        'generates_secondary_entry', 'secondary_entry_type_id',
        'secondary_amount_mode', 'secondary_amount_value', 'display_order',
        'is_vendor_purchase', 'vendor_purchase_payment_type', 'mirror_to_cashbook', 'vendor_access_mode', 'vendor_settlement_relation_id', 'sales_report_bucket',
    ];

    protected $casts = [
        'header_group_id' => 'integer',
        'header_display_order' => 'integer',
        'company_account_id' => 'integer',
        'vendor_settlement_relation_id' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'enabled' => 'boolean',
        'is_vendor_purchase' => 'boolean',
        'mirror_to_cashbook' => 'boolean',
        'show_in_summary' => 'boolean',
        'note_enabled' => 'boolean',
        'is_readonly' => 'boolean',
        'allowed_funding_sources' => 'array',
        'include_in_sales' => 'boolean',
        'include_in_income' => 'boolean',
        'include_in_expense' => 'boolean',
        'include_in_pl' => 'boolean',
        'include_in_payable' => 'boolean',
        'generates_secondary_entry' => 'boolean',
        'secondary_amount_value' => 'decimal:4',
    ];

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(LedgerEntryType::class, 'entry_type_id');
    }

    public function headerGroup(): BelongsTo
    {
        return $this->belongsTo(ShopLedgerHeaderGroup::class, 'header_group_id');
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function vendorSettlementRelation(): BelongsTo
    {
        return $this->belongsTo(ShopCashbookRelation::class, 'vendor_settlement_relation_id');
    }

    public function categoryVendorMappings(): HasMany
    {
        return $this->hasMany(CategoryVendorMapping::class, 'shop_ledger_entry_setting_id');
    }

    public function definedShopSuppliers(): BelongsToMany
    {
        return $this->belongsToMany(
            ShopSupplier::class,
            'category_vendor_mappings',
            'shop_ledger_entry_setting_id',
            'shop_supplier_id'
        )->withTimestamps();
    }

    public function secondaryEntryType(): BelongsTo
    {
        return $this->belongsTo(LedgerEntryType::class, 'secondary_entry_type_id');
    }

    public function relationItems(): HasMany
    {
        return $this->hasMany(ShopCashbookRelationItem::class, 'shop_ledger_entry_setting_id');
    }

    public function isDirectBankCollection(): bool
    {
        return $this->enabled
            && $this->company_account_id !== null
            && ($this->include_in_sales || $this->include_in_income || ($this->entryType && $this->entryType->category === 'income'));
    }

    /** Scope to settings effective on a given date. */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }

    public function isNoteEnabled(): bool
    {
        if ($this->note_enabled) {
            return true;
        }

        return (bool) ($this->headerGroup?->note_enabled ?? false);
    }

    public function requiresNote(): bool
    {
        return $this->entryType?->requiresNote() ?? false;
    }

    public function effectiveFundingSource(): string
    {
        return app(CashFlowResolutionService::class)->resolveFundingSource($this);
    }

    public function effectiveCompanyAccountId(): ?int
    {
        return app(CashFlowResolutionService::class)->resolveCompanyAccountId($this);
    }

    public function displayName(): string
    {
        if ($this->display_name !== null && trim($this->display_name) !== '') {
            return trim($this->display_name);
        }

        return $this->entryType?->name ?? 'Entry #'.$this->id;
    }

    public function isTodayOnly(): bool
    {
        return strtolower((string) ($this->edit_policy ?? 'past_days_allowed')) === 'today_only';
    }

    public function isPastDaysAllowed(): bool
    {
        return ! $this->isTodayOnly();
    }

    public function isVendorPurchaseCash(): bool
    {
        return $this->is_vendor_purchase
            && ($this->vendor_purchase_payment_type === 'cash' || $this->entryType?->code === 'vendor_purchase_cash');
    }

    public function isVendorPurchaseCredit(): bool
    {
        return $this->is_vendor_purchase
            && ($this->vendor_purchase_payment_type === 'credit' || $this->entryType?->code === 'vendor_purchase_credit');
    }

    public function resolveSalesReportBucket(): string
    {
        if ($this->sales_report_bucket !== null && trim((string) $this->sales_report_bucket) !== '' && $this->sales_report_bucket !== 'default') {
            return strtolower(trim((string) $this->sales_report_bucket));
        }

        $code = strtolower((string) ($this->entryType?->code ?? ''));
        $category = strtolower((string) ($this->entryType?->category ?? ''));

        if (in_array($category, ['transfer', 'settlement'], true) || in_array($code, [
            'sales_to_petty', 'company_to_petty', 'petty_to_company', 'sales_to_company',
            'company_to_shop', 'bank_to_petty', 'shop_to_supermarket', 'casio_delivery',
            'shop_paid_company', 'company_paid_shop', 'company_paid_vendor', 'petty_reimbursement',
        ], true)) {
            return 'ignore';
        }

        if (in_array($code, ['rent_expense', 'expense_rent', 'income_rent'], true)) {
            return 'rent';
        }

        if ($this->is_vendor_purchase || in_array($code, ['vendor_purchase', 'vendor_purchase_cash', 'vendor_purchase_credit', 'cash_purchase', 'purchase_bill'], true)) {
            return 'purchase';
        }

        if ($this->include_in_sales || $category === 'income' || in_array($code, ['cash_sales', 'card', 'paytm', 'upi', 'income_s_m_delivery', 'income_cp', 'other_income', 'excess_receipt'], true)) {
            return 'sales';
        }

        if ($this->include_in_expense || $category === 'expense') {
            return 'other_expense';
        }

        return 'ignore';
    }
}
