<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'created_by',
    ];

    /**
     * Get the shop that owns this preset.
     *
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the user who created this preset.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the items within this preset.
     *
     * @return HasMany<ShopPresetItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShopPresetItem::class);
    }
}
