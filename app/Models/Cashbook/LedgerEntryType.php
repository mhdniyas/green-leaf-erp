<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Global, reusable entry types. Never create shop-specific types like
 * "Shop A Rent" — Rent stays Rent; the shop's ShopLedgerEntrySetting
 * controls its behaviour per shop.
 */
class LedgerEntryType extends Model
{
    protected $table = 'ledger_entry_types';

    protected $fillable = [
        'code', 'name', 'category', 'system_type', 'active', 'display_order',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function settings(): HasMany
    {
        return $this->hasMany(ShopLedgerEntrySetting::class, 'entry_type_id');
    }
}
