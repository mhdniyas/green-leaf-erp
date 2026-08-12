<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PurchaserCartItem extends Model
{
    private static ?bool $hasPublicUuidColumn = null;

    protected $fillable = [
        'public_uuid',
        'purchaser_cart_id',
        'product_id',
        'grade',
        'quantity',
        'unit_price',
        'line_total',
        'is_extra_purchase',
        'notes',
    ];

    protected $attributes = [
        'grade' => 'A',
    ];

    public function getRouteKeyName(): string
    {
        return static::hasPublicUuidColumn() ? 'public_uuid' : $this->getKeyName();
    }

    public function getRouteKey(): mixed
    {
        if (static::hasPublicUuidColumn() && $this->public_uuid) {
            return $this->public_uuid;
        }

        return $this->getKey();
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (static::hasPublicUuidColumn() && ! $item->public_uuid) {
                $item->public_uuid = (string) Str::uuid();
            }
        });
    }

    public static function hasPublicUuidColumn(): bool
    {
        return self::$hasPublicUuidColumn ??= Schema::hasColumn('purchaser_cart_items', 'public_uuid');
    }

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:2',
        'is_extra_purchase' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(PurchaserCart::class, 'purchaser_cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
