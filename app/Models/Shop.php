<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'status',
        'address',
        'contact_name',
        'contact_phone',
    ];

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
}
