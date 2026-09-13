<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoodsReceivedItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class GoodsReceivedItem extends Model
{
    /** @use HasFactory<GoodsReceivedItemFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'goods_received_id',
        'purchase_order_item_id',
        'product_id',
        'grade',
        'received_unit',
        'received_packet_qty',
        'received_weight_per_packet',
        'received_qty',
        'variance',
        'purchased_qty',
        'discrepancy_type',
        'discrepancy_note',
    ];

    protected $attributes = [
        'grade' => 'A',
    ];

    protected $casts = [
        'received_qty' => 'decimal:3',
        'variance' => 'decimal:3',
        'purchased_qty' => 'decimal:3',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (GoodsReceivedItem $item): void {
            if (empty($item->received_unit)) {
                if ($item->purchase_order_item_id && $item->relationLoaded('purchaseOrderItem') && $item->purchaseOrderItem?->purchase_unit) {
                    $item->received_unit = $item->purchaseOrderItem->purchase_unit;
                } elseif ($item->purchase_order_item_id && ($poItem = PurchaseOrderItem::find($item->purchase_order_item_id)) && $poItem->purchase_unit) {
                    $item->received_unit = $poItem->purchase_unit;
                } elseif ($item->product_id && $item->relationLoaded('product') && $item->product?->unit) {
                    $item->received_unit = $item->product->unit;
                } elseif ($item->product_id && ($prod = Product::find($item->product_id)) && $prod->unit) {
                    $item->received_unit = $prod->unit;
                } else {
                    $item->received_unit = 'kg';
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // Relationships
    public function goodsReceived(): BelongsTo
    {
        return $this->belongsTo(GoodsReceived::class, 'goods_received_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function advanceMatchesAsAdvance(): HasMany
    {
        return $this->hasMany(AdvanceReceiveMatch::class, 'advance_goods_received_item_id');
    }

    public function advanceMatchesAsBill(): HasMany
    {
        return $this->hasMany(AdvanceReceiveMatch::class, 'bill_goods_received_item_id');
    }
}
