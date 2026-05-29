<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoodsReceivedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class GoodsReceived extends Model
{
    /** @use HasFactory<GoodsReceivedFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'goods_received';

    protected $fillable = [
        'purchase_order_id',
        'grn_number',
        'status',
        'received_by',
        'received_at',
        'transport_cost',
        'labour_cost',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'date',
        'transport_cost' => 'decimal:2',
        'labour_cost' => 'decimal:2',
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
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceivedItem::class, 'goods_received_id');
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class, 'goods_received_id');
    }
}
