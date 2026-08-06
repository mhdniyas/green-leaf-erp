<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SortSheetPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'sort_order',
        'surface',
        'warehouse_id',
        'price_group_id',
        'shop_id',
        'category_ids',
        'product_ids',
        'separate_category_pages',
        'page_break_category_ids',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'category_ids' => 'array',
            'product_ids' => 'array',
            'separate_category_pages' => 'boolean',
            'page_break_category_ids' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (SortSheetPreset $preset) {
            if (empty($preset->uuid)) {
                $preset->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function priceGroup(): BelongsTo
    {
        return $this->belongsTo(ShopPriceGroup::class, 'price_group_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
