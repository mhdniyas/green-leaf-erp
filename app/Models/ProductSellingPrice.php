<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSellingPrice extends Model
{
    use HasFactory;

    public const string MODE_INHERITED = 'inherited';

    public const string MODE_CUSTOM_PERCENTAGE = 'custom_percentage';

    public const string MODE_FIXED_PRICE = 'fixed_price';

    protected $table = 'product_selling_prices';

    protected $fillable = [
        'product_id',
        'pricing_mode',
        'markup_percentage',
        'fixed_price',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'markup_percentage' => 'float',
        'fixed_price' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isInherited(): bool
    {
        return $this->pricing_mode === self::MODE_INHERITED;
    }

    public function isCustomPercentage(): bool
    {
        return $this->pricing_mode === self::MODE_CUSTOM_PERCENTAGE;
    }

    public function isFixedPrice(): bool
    {
        return $this->pricing_mode === self::MODE_FIXED_PRICE;
    }
}
