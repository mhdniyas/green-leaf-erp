<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopLedgerHeaderGroup extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'type',
        'cash_flow_mode',
        'company_account_id',
        'from_balance',
        'to_balance',
        'display_order',
        'enabled',
        'note_enabled',
        'product_tagging_enabled',
        'show_both_sides',
    ];

    protected $attributes = [
        'show_both_sides' => false,
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'company_account_id' => 'integer',
        'display_order' => 'integer',
        'enabled' => 'boolean',
        'note_enabled' => 'boolean',
        'product_tagging_enabled' => 'boolean',
        'show_both_sides' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function entrySettings(): HasMany
    {
        return $this->hasMany(ShopLedgerEntrySetting::class, 'header_group_id', 'id')
            ->orderBy('header_display_order');
    }

    public function allowedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'shop_ledger_header_products', 'header_group_id', 'product_id')
            ->withTimestamps();
    }

    public function isNoteEnabled(): bool
    {
        return (bool) $this->note_enabled;
    }

    public function hasProductTagging(): bool
    {
        return (bool) $this->product_tagging_enabled;
    }

    public function showsBothSides(): bool
    {
        return (bool) $this->show_both_sides;
    }
}
