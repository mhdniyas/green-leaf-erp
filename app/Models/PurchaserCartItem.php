<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PurchaserCartItem extends Model
{
    protected $fillable = [
        'public_uuid',
        'purchaser_cart_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_total',
        'is_extra_purchase',
        'notes',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->public_uuid) {
                $item->public_uuid = (string) Str::uuid();
            }
        });
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
