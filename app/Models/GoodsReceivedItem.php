<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoodsReceivedItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
