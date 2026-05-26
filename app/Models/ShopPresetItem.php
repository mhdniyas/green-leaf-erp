<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopPresetItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_preset_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    /**
     * Get the preset that owns this item.
     *
     * @return BelongsTo<ShopPreset, $this>
     */
    public function preset(): BelongsTo
    {
        return $this->belongsTo(ShopPreset::class, 'shop_preset_id');
    }

    /**
     * Get the product associated with this preset item.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
