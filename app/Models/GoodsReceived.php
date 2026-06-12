<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoodsReceivedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'grn_number',
        'status',
        'rejection_remarks',
        'received_by',
        'approved_by',
        'updated_by',
        'received_at',
        'approved_at',
        'transport_cost',
        'labour_cost',
        'notes',
        'is_extra',
    ];

    protected $casts = [
        'received_at' => 'date',
        'approved_at' => 'datetime',
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

        return $query->first();
    }

    protected static function booted(): void
    {
        static::creating(function (self $goodsReceived): void {
            if (! $goodsReceived->public_uuid) {
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

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceivedItem::class, 'goods_received_id');
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class, 'goods_received_id');
    }
}
