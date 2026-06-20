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
