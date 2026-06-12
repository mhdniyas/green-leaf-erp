<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'warehouse_tag',
        'shop_price_group_id',
        'status',
        'address',
        'contact_name',
        'contact_phone',
    ];

    public function priceGroup(): BelongsTo
    {
        return $this->belongsTo(ShopPriceGroup::class, 'shop_price_group_id');
    }

    /**
     * Get the users associated with the shop.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the orders placed by the shop.
     *
     * @return HasMany<ShopOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(ShopOrder::class);
    }

    /**
     * Get the presets defined for the shop.
     *
     * @return HasMany<ShopPreset, $this>
     */
    public function presets(): HasMany
    {
        return $this->hasMany(ShopPreset::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ShopInvoice::class);
    }
}
