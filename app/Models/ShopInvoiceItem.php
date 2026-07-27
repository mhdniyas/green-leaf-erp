<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_invoice_id',
        'shop_order_item_id',
        'product_id',
        'product_name',
        'unit',
        'approved_qty',
        'delivered_qty',
        'shortage_qty',
        'excess_qty',
        'unit_price',
        'line_subtotal',
        'shortage_amount',
        'excess_amount',
        'final_line_total',
    ];

    protected function casts(): array
    {
        return [
            'approved_qty' => 'decimal:2',
            'delivered_qty' => 'decimal:2',
            'shortage_qty' => 'decimal:2',
            'excess_qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'shortage_amount' => 'decimal:2',
            'excess_amount' => 'decimal:2',
            'final_line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ShopInvoice::class, 'shop_invoice_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(ShopOrderItem::class, 'shop_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
