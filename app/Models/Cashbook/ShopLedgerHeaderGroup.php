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
        'display_order',
        'enabled',
        'product_tagging_enabled',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'display_order' => 'integer',
        'enabled' => 'boolean',
        'product_tagging_enabled' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
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
}
