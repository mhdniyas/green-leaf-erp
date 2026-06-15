<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaserCartItem extends Model
{
    protected $fillable = [
        'purchaser_cart_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_total',
        'is_extra_purchase',
        'notes',
    ];

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
