<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PurchaseBusinessDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PurchaseBusinessDay extends Model
{
    /** @use HasFactory<PurchaseBusinessDayFactory> */
    use HasFactory, LogsActivity;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REOPENED = 'reopened';

    public const CLOSE_MODE_CLEAN = 'clean';

    public const CLOSE_MODE_WITH_PENDING = 'with_pending';

    public const CLOSE_MODE_CARRY_FORWARD = 'carry_forward';

    protected $table = 'purchase_business_days';

    protected $fillable = [
        'uuid',
        'business_date',
        'warehouse_id',
        'status',
        'close_mode',
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
        'close_note',
        'close_pending_snapshot',
        'first_close_pending_snapshot',
    ];

    protected $casts = [
        'business_date' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'close_pending_snapshot' => 'array',
        'first_close_pending_snapshot' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->opened_at)) {
                $model->opened_at = now();
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

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isReopened(): bool
    {
        return $this->status === self::STATUS_REOPENED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_REOPENED], true);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function purchaserCarts(): HasMany
    {
        return $this->hasMany(PurchaserCart::class, 'business_day_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'business_day_id');
    }

    public function goodsReceiveds(): HasMany
    {
        return $this->hasMany(GoodsReceived::class, 'business_day_id');
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class, 'business_day_id');
    }

    public function advanceReceiveMatches(): HasMany
    {
        return $this->hasMany(AdvanceReceiveMatch::class, 'business_day_id');
    }

    public function carryForwards(): HasMany
    {
        return $this->hasMany(PurchaseBusinessDayCarryForward::class, 'origin_business_day_id');
    }
}
