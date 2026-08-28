<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoodsReceivedFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class GoodsReceived extends Model
{
    /** @use HasFactory<GoodsReceivedFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    private static ?bool $hasPublicUuidColumn = null;

    protected $table = 'goods_received';

    protected $fillable = [
        'public_uuid',
        'purchase_order_id',
        'destination_shop_id',
        'warehouse_id',
        'purchaser_cart_id',
        'grn_number',
        'status',
        'bill_status',
        'bill_number',
        'rejection_remarks',
        'received_by',
        'approved_by',
        'updated_by',
        'matched_by',
        'matched_at',
        'received_at',
        'approved_at',
        'transport_cost',
        'labour_cost',
        'notes',
        'is_extra',
        'purchase_grade',
        'receipt_type',
        'client_submission_id',
        'submission_payload_hash',
    ];

    protected $attributes = [
        'purchase_grade' => 'A',
        'bill_status' => 'bill_available',
    ];

    protected $casts = [
        'received_at' => 'date',
        'approved_at' => 'datetime',
        'matched_at' => 'datetime',
        'transport_cost' => 'decimal:2',
        'labour_cost' => 'decimal:2',
        'is_extra' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return static::hasPublicUuidColumn() ? 'public_uuid' : 'grn_number';
    }

    public function getRouteKey(): mixed
    {
        if (static::hasPublicUuidColumn() && $this->public_uuid) {
            return $this->public_uuid;
        }

        return $this->grn_number ?: $this->getKey();
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        $query = $this->newQuery()->where($field, $value);

        if ($field !== 'grn_number') {
            $query->orWhere('grn_number', $value);
        }

        if (is_numeric($value)) {
            $query->orWhere($this->getKeyName(), (int) $value);
        }

        return $query->first();
    }

    protected static function booted(): void
    {
        static::creating(function (self $goodsReceived): void {
            if (static::hasPublicUuidColumn() && ! $goodsReceived->public_uuid) {
                $goodsReceived->public_uuid = (string) Str::uuid();
            }
        });
    }

    public static function hasPublicUuidColumn(): bool
    {
        return self::$hasPublicUuidColumn ??= Schema::hasColumn('goods_received', 'public_uuid');
    }

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

    public function destinationShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'destination_shop_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceivedItem::class, 'goods_received_id');
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class, 'goods_received_id');
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class, 'goods_received_id');
    }

    public function advanceMatchesAsAdvance(): HasMany
    {
        return $this->hasMany(AdvanceReceiveMatch::class, 'advance_goods_received_id');
    }

    public function advanceMatchesAsBill(): HasMany
    {
        return $this->hasMany(AdvanceReceiveMatch::class, 'bill_goods_received_id');
    }

    public function billReconciliation(): HasOne
    {
        return $this->hasOne(BillReconciliation::class, 'goods_received_id');
    }

    public function isBillPending(): bool
    {
        return $this->bill_status === 'bill_pending' || ! $this->purchaseInvoices()->exists();
    }

    public function scopeWarehouseAdvance(Builder $query): Builder
    {
        return $query->where('goods_received.receipt_type', 'warehouse_advance');
    }

    public function scopeNormalPurchase(Builder $query): Builder
    {
        return $query->where('goods_received.receipt_type', 'normal_purchase');
    }

    public function isWarehouseAdvance(): bool
    {
        return $this->receipt_type === 'warehouse_advance';
    }

    public function isNormalPurchase(): bool
    {
        return $this->receipt_type === 'normal_purchase';
    }

    public function sourceLabel(): string
    {
        if ($this->isWarehouseAdvance() || $this->purchase_order_id === null) {
            return 'ADVANCE';
        }

        if ($this->relationLoaded('billReconciliation') && $this->billReconciliation !== null) {
            return strtoupper($this->billReconciliation->source_type);
        }

        if ($this->purchaseOrder?->purchaserCart?->purchase_source === 'green_leaf_direct_purchase') {
            return 'DIRECT PURCHASE';
        }

        return 'NORMAL PO';
    }
}
