<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPriceApproval extends Model
{
    use HasFactory;

    public string $movement_status = 'changed';

    public ?float $comparison_purchase_price = null;

    protected $fillable = [
        'product_id',
        'business_date',
        'purchase_price',
        'price_a',
        'price_b',
        'price_c',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'business_date' => 'date',
        'purchase_price' => 'decimal:4',
        'price_a' => 'decimal:2',
        'price_b' => 'decimal:2',
        'price_c' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
