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
use Illuminate\Database\Query\Builder as QueryBuilder;
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

    public function scopeOpenWarehouseAdvance(
        Builder $query,
        int|array|null $warehouseId = null,
        ?int $productId = null
    ): Builder {
        return $query->where(function (Builder $typeQuery): void {
            $typeQuery->where('goods_received.receipt_type', 'warehouse_advance')
                ->orWhere(function (Builder $legacy): void {
                    $legacy->whereNull('goods_received.receipt_type')
                        ->whereNull('goods_received.purchase_order_id');
                });
        })
            ->where('goods_received.status', 'approved')
            ->where('goods_received.bill_status', 'bill_pending')
            ->whereDoesntHave('purchaseInvoices')
            ->whereHas('stockBatches', function (Builder $batchQuery) use ($productId): void {
                $batchQuery->where('warehouse_receive_pending', false)
                    ->when($productId !== null, fn (Builder $pq) => $pq->where('product_id', $productId));
            })
            ->when($warehouseId !== null, function (Builder $whQuery) use ($warehouseId): void {
                if (is_array($warehouseId)) {
                    $whQuery->where(function (Builder $w) use ($warehouseId): void {
                        $w->whereIn('goods_received.warehouse_id', $warehouseId)
                            ->orWhereIn('goods_received.destination_shop_id', $warehouseId);
                    });
                } else {
                    $whQuery->where(function (Builder $w) use ($warehouseId): void {
                        $w->where('goods_received.warehouse_id', $warehouseId)
                            ->orWhere('goods_received.destination_shop_id', $warehouseId);
                    });
                }
            })
            ->whereExists(function (QueryBuilder $itemQuery) use ($productId): void {
                $itemQuery->selectRaw('1')
                    ->from('goods_received_items as gri')
                    ->join('products as p', 'p.id', '=', 'gri.product_id')
                    ->whereColumn('gri.goods_received_id', 'goods_received.id')
                    ->whereNull('gri.deleted_at')
                    ->when($productId !== null, fn ($pq) => $pq->where('gri.product_id', $productId))
                    ->whereRaw('(
                        (
                            SELECT SUM(gri_inner.received_qty * (
                                CASE
                                    WHEN LOWER(TRIM(gri_inner.received_unit)) = LOWER(TRIM(p_inner.unit)) THEN 1.0
                                    ELSE COALESCE((
                                        SELECT pu.conversion_to_base
                                        FROM product_units pu
                                        WHERE pu.product_id = gri_inner.product_id
                                          AND LOWER(TRIM(pu.unit)) = LOWER(TRIM(gri_inner.received_unit))
                                        LIMIT 1
                                    ), 1.0)
                                END
                            ))
                            FROM goods_received_items gri_inner
                            JOIN products p_inner ON p_inner.id = gri_inner.product_id
                            WHERE gri_inner.goods_received_id = goods_received.id
                              AND gri_inner.product_id = gri.product_id
                              AND gri_inner.deleted_at IS NULL
                        )
                        -
                        COALESCE((
                            SELECT SUM(arm.base_qty)
                            FROM advance_receive_matches arm
                            WHERE arm.advance_goods_received_id = goods_received.id
                              AND arm.product_id = gri.product_id
                        ), 0.0)
                    ) > 0.0001');
            });
    }

    public function scopeNormalPurchase(Builder $query): Builder
    {
        return $query->where('goods_received.receipt_type', 'normal_purchase');
    }

    public function getReceivedBaseQtyAttribute(): float
    {
        if (array_key_exists('received_base_qty', $this->attributes)) {
            return round((float) $this->attributes['received_base_qty'], 3);
        }

        if ($this->relationLoaded('items')) {
            return round((float) $this->items->sum(function (GoodsReceivedItem $item): float {
                $prod = $item->relationLoaded('product') ? $item->product : Product::find($item->product_id);
                $conv = (float) ($prod?->conversionToBaseForUnit($item->received_unit) ?? 1.0);

                return (float) $item->received_qty * $conv;
            }), 3);
        }

        return round((float) $this->items()
            ->join('products', 'products.id', '=', 'goods_received_items.product_id')
            ->selectRaw('COALESCE(SUM(goods_received_items.received_qty * (
                CASE
                    WHEN LOWER(TRIM(goods_received_items.received_unit)) = LOWER(TRIM(products.unit)) THEN 1.0
                    ELSE COALESCE((
                        SELECT pu.conversion_to_base
                        FROM product_units pu
                        WHERE pu.product_id = goods_received_items.product_id
                          AND LOWER(TRIM(pu.unit)) = LOWER(TRIM(goods_received_items.received_unit))
                        LIMIT 1
                    ), 1.0)
                END
            )), 0.0) as base_qty')
            ->value('base_qty'), 3);
    }

    public function getBillMatchedBaseQtyAttribute(): float
    {
        if (array_key_exists('bill_matched_base_qty', $this->attributes)) {
            return round((float) $this->attributes['bill_matched_base_qty'], 3);
        }

        if ($this->relationLoaded('advanceMatchesAsAdvance')) {
            return round((float) $this->advanceMatchesAsAdvance->sum('base_qty'), 3);
        }

        return round((float) $this->advanceMatchesAsAdvance()->sum('base_qty'), 3);
    }

    public function getUnbilledBaseQtyAttribute(): float
    {
        return max(0.0, round($this->received_base_qty - $this->bill_matched_base_qty, 3));
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
