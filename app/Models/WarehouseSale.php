<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WarehouseSaleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class WarehouseSale extends Model
{
    /** @use HasFactory<WarehouseSaleFactory> */
    use HasFactory, LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'invoice_number',
        'warehouse_id',
        'customer_id',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'business_date',
        'sold_by_user_id',
        'subtotal',
        'discount',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'notes',
        'confirmed_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $sale): void {
            $sale->uuid ??= (string) Str::uuid();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function getCancellationReasonAttribute(): ?string
    {
        return $this->cancel_reason;
    }

    // Relationships
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WarehouseCustomer::class, 'customer_id');
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseSaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(WarehouseSalePayment::class);
    }

    public function stockMovements(): HasManyThrough
    {
        return $this->hasManyThrough(
            StockMovement::class,
            WarehouseSaleItem::class,
            'warehouse_sale_id',
            'warehouse_sale_item_id',
            'id',
            'id'
        );
    }

    // Scopes
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('business_date', $date);
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    // Helpers
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function primaryPayment(): ?WarehouseSalePayment
    {
        return $this->payments->first();
    }

    public function isCashSale(): bool
    {
        return $this->payments->contains(fn (WarehouseSalePayment $p) => strtolower($p->payment_method) === 'cash');
    }

    public function isCreditSale(): bool
    {
        return $this->payments->contains(fn (WarehouseSalePayment $p) => strtolower($p->payment_method) === 'credit');
    }

    public function isUserHeldCash(): bool
    {
        return $this->payments->contains(fn (WarehouseSalePayment $p) => strtolower($p->payment_method) === 'cash' && $p->money_holder_type === 'user');
    }

    public function isCompanyHeldCash(): bool
    {
        return $this->payments->contains(fn (WarehouseSalePayment $p) => strtolower($p->payment_method) === 'cash' && $p->money_holder_type === 'company');
    }
}
