<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopOrderChangeRequestItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopOrderChangeRequestItem extends Model
{
    /** @use HasFactory<ShopOrderChangeRequestItemFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_order_change_request_id',
        'product_id',
        'old_qty',
        'new_qty',
        'approved_qty',
        'delta_qty',
    ];

    protected function casts(): array
    {
        return [
            'old_qty' => 'decimal:2',
            'new_qty' => 'decimal:2',
            'approved_qty' => 'decimal:2',
            'delta_qty' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ShopOrderChangeRequest, $this>
     */
    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ShopOrderChangeRequest::class, 'shop_order_change_request_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
