<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopOrderRevisionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopOrderRevisionItem extends Model
{
    /** @use HasFactory<ShopOrderRevisionItemFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_order_revision_id',
        'product_id',
        /**
         * Stores the previously *approved* quantity at the time the revision was created
         * (sourced from ShopOrderItem::approved_qty ?? requested_qty).
         * Despite the field name, this is NOT the shop's original requested_qty —
         * it is the approved baseline, preserved for audit history.
         */
        'old_requested_qty',
        'new_requested_qty',
        'delta_qty',
        'final_approved_qty',
    ];

    protected $casts = [
        'old_requested_qty' => 'decimal:2',
        'new_requested_qty' => 'decimal:2',
        'delta_qty' => 'decimal:2',
        'final_approved_qty' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<ShopOrderRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ShopOrderRevision::class, 'shop_order_revision_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
