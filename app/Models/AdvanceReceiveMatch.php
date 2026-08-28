<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AdvanceReceiveMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AdvanceReceiveMatch extends Model
{
    /** @use HasFactory<AdvanceReceiveMatchFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'advance_receive_matches';

    protected $fillable = [
        'advance_goods_received_id',
        'advance_goods_received_item_id',
        'advance_stock_batch_id',
        'bill_goods_received_id',
        'bill_goods_received_item_id',
        'purchase_order_id',
        'purchase_order_item_id',
        'purchase_invoice_id',
        'product_id',
        'matched_qty',
        'matched_unit',
        'base_qty',
        'conversion_to_base',
        'confirmed_by',
        'confirmed_at',
        'client_submission_id',
        'notes',
    ];

    protected $casts = [
        'matched_qty' => 'decimal:3',
        'base_qty' => 'decimal:3',
        'conversion_to_base' => 'decimal:4',
        'confirmed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // Relationships
    public function advanceGoodsReceived(): BelongsTo
    {
        return $this->belongsTo(GoodsReceived::class, 'advance_goods_received_id');
    }

    public function advanceGoodsReceivedItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedItem::class, 'advance_goods_received_item_id');
    }

    public function advanceStockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'advance_stock_batch_id');
    }

    public function billGoodsReceived(): BelongsTo
    {
        return $this->belongsTo(GoodsReceived::class, 'bill_goods_received_id');
    }

    public function billGoodsReceivedItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedItem::class, 'bill_goods_received_item_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
