<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopLedgerEntrySetting extends Model
{
    protected $table = 'shop_ledger_entry_settings';

    protected $fillable = [
        'shop_id', 'entry_type_id', 'version', 'effective_from', 'effective_to',
        'enabled', 'default_funding_source', 'allowed_funding_sources',
        'include_in_sales', 'include_in_income', 'include_in_expense', 'include_in_pl',
        'include_in_payable',
        'settlement_behavior', 'petty_behavior', 'company_pending_behavior',
        'generates_secondary_entry', 'secondary_entry_type_id',
        'secondary_amount_mode', 'secondary_amount_value', 'display_order',
    ];

    protected $casts = [
        'effective_from'            => 'date',
        'effective_to'              => 'date',
        'enabled'                   => 'boolean',
        'allowed_funding_sources'   => 'array',
        'include_in_sales'          => 'boolean',
        'include_in_income'         => 'boolean',
        'include_in_expense'        => 'boolean',
        'include_in_pl'             => 'boolean',
        'include_in_payable'        => 'boolean',
        'generates_secondary_entry' => 'boolean',
        'secondary_amount_value'    => 'decimal:4',
    ];

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(LedgerEntryType::class, 'entry_type_id');
    }

    public function secondaryEntryType(): BelongsTo
    {
        return $this->belongsTo(LedgerEntryType::class, 'secondary_entry_type_id');
    }

    /** Scope to settings effective on a given date. */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }
}
