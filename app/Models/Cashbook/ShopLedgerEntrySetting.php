<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Services\Cashbook\CashFlowResolutionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopLedgerEntrySetting extends Model
{
    protected $table = 'shop_ledger_entry_settings';

    protected $fillable = [
        'shop_id', 'entry_type_id', 'display_name', 'header_group_id', 'header_display_order', 'company_account_id', 'version', 'effective_from', 'effective_to',
        'enabled', 'note_enabled', 'default_funding_source', 'allowed_funding_sources',
        'include_in_sales', 'include_in_income', 'include_in_expense', 'include_in_pl',
        'include_in_payable', 'payable_direction',
        'settlement_behavior', 'petty_behavior', 'company_pending_behavior',
        'generates_secondary_entry', 'secondary_entry_type_id',
        'secondary_amount_mode', 'secondary_amount_value', 'display_order',
    ];

    protected $casts = [
        'header_group_id' => 'integer',
        'header_display_order' => 'integer',
        'company_account_id' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'enabled' => 'boolean',
        'note_enabled' => 'boolean',
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
            && ($this->include_in_income || $this->include_in_sales || ($this->entryType && $this->entryType->category === 'income'));
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
}
