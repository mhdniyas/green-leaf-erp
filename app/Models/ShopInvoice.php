<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'shop_order_id',
        'invoice_number',
        'business_date',
        'status',
        'delivery_status',
        'payment_status',
        'subtotal',
        'shortage_total',
        'discount_total',
        'final_total',
        'paid_amount',
        'balance_amount',
        'delivery_note',
        'payment_note',
        'admin_price_note',
        'generated_by',
        'delivery_confirmed_by',
        'delivery_confirmed_at',
        'payment_approved_by',
        'payment_approved_at',
        'price_updated_by',
        'price_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'subtotal' => 'decimal:2',
            'shortage_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'final_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'delivery_confirmed_at' => 'datetime',
            'payment_approved_at' => 'datetime',
            'price_updated_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'invoice_number';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopInvoiceItem::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function paymentApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_approved_by');
    }

    public function priceUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'price_updated_by');
    }
}
