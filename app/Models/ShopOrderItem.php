<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_order_id',
        'product_id',
        'requested_qty',
        'approved_qty',
        'unit',
        'notes',
        'fulfillment_type',
        'is_sorted',
        'sorted_at',
        'sorted_by',
        'sorting_status',
        'delivered_qty',
        'shortage_qty',
        'unit_cost',
        'shortage_value',
    ];

    protected $casts = [
        'requested_qty' => 'decimal:2',
        'approved_qty' => 'decimal:2',
        'is_sorted' => 'boolean',
        'sorted_at' => 'datetime',
        'delivered_qty' => 'decimal:2',
        'shortage_qty' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'shortage_value' => 'decimal:2',
    ];

    /**
     * Get the order that owns this item.
     *
     * @return BelongsTo<ShopOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }

    /**
     * Get the product represented by this item.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who sorted this item.
     *
     * @return BelongsTo<User, $this>
     */
    public function sortedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorted_by');
    }
}
