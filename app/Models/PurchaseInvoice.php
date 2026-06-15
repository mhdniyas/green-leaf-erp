<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Purchasing\InvoiceStatus;
use Database\Factories\PurchaseInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PurchaseInvoice extends Model
{
    /** @use HasFactory<PurchaseInvoiceFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    private static ?bool $hasPublicUuidColumn = null;

    protected $fillable = [
        'public_uuid',
        'goods_received_id',
        'supplier_id',
        'purchaser_cart_id',
        'invoice_number',
        'amount',
        'discount_amount',
        'status',
        'payment_method',
        'payment_status',
        'paid_amount',
        'payment_note',
        'payment_details',
        'purchaser_submitted_by',
        'purchaser_submitted_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'status' => InvoiceStatus::class,
        'purchaser_submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return static::hasPublicUuidColumn() ? 'public_uuid' : 'invoice_number';
    }

    public function getRouteKey(): mixed
    {
        if (static::hasPublicUuidColumn() && $this->public_uuid) {
            return $this->public_uuid;
        }

        return $this->invoice_number ?: $this->getKey();
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        $query = $this->newQuery()->where($field, $value);

        if ($field !== 'invoice_number') {
            $query->orWhere('invoice_number', $value);
        }

        return $query->first();
    }

    protected static function booted(): void
    {
        static::creating(function (self $purchaseInvoice): void {
            if (! $purchaseInvoice->public_uuid) {
                $purchaseInvoice->public_uuid = (string) Str::uuid();
            }
        });
    }

    public static function hasPublicUuidColumn(): bool
    {
        return self::$hasPublicUuidColumn ??= Schema::hasColumn('purchase_invoices', 'public_uuid');
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaserCart(): BelongsTo
    {
        return $this->belongsTo(PurchaserCart::class);
    }

    public function purchaserSubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_submitted_by');
    }
}
