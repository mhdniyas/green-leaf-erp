<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Purchasing\InvoiceStatus;
use Database\Factories\PurchaseInvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'purchase_source',
        'invoice_number',
        'amount',
        'discount_amount',
        'status',
        'payment_method',
        'payment_paid_by',
        'payment_status',
        'paid_amount',
        'payment_note',
        'payment_details',
        'purchaser_submitted_by',
        'purchaser_submitted_at',
        'notes',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'cancellation_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'status' => InvoiceStatus::class,
        'purchaser_submitted_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function isGreenLeafDirectPurchase(): bool
    {
        return in_array($this->purchase_source, ['green_leaf_direct_purchase', 'mixed'], true)
            || $this->purchaserCart?->isGreenLeafDirectPurchase() === true;
    }

    public function purchaseSourceLabel(): string
    {
        return match ($this->purchase_source) {
            'green_leaf_direct_purchase' => 'Green Leaf Direct Purchase',
            'mixed' => 'Green Leaf Direct Purchase + Shop Demand',
            default => $this->purchaserCart?->purchaseSourceLabel() ?? 'Shop Demand',
        };
    }

    public function paymentPaidByLabel(): string
    {
        return match ($this->payment_paid_by) {
            'company' => 'Company',
            'vendor_credit' => 'Vendor Credit',
            default => 'Purchaser',
        };
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', InvoiceStatus::Cancelled->value);
    }

    public function isCancelled(): bool
    {
        return $this->status === InvoiceStatus::Cancelled;
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

    public function payments(): HasMany
    {
        return $this->hasMany(PurchaseInvoicePayment::class);
    }

    public function vendorSettlementAllocations(): HasMany
    {
        return $this->hasMany(VendorSettlementAllocation::class);
    }

    public function settlementTotal(): float
    {
        return round((float) $this->vendorSettlementAllocations()->sum('total_settled'), 2);
    }

    public function settlementOutstanding(): float
    {
        return round(max(0, ((float) $this->amount - (float) $this->discount_amount) - $this->settlementTotal()), 2);
    }

    public function purchaserSubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_submitted_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function itemsGrossTotal(): float
    {
        if ($this->purchaserCart?->items?->isNotEmpty()) {
            return round((float) $this->purchaserCart->items->sum(
                fn ($item) => (float) $item->quantity * (float) $item->unit_price
            ), 2);
        }

        if ($this->goodsReceived?->items?->isNotEmpty()) {
            return round((float) $this->goodsReceived->items->sum(
                fn ($item) => (float) $item->received_qty * (float) ($item->purchaseOrderItem?->unit_price ?? 0)
            ), 2);
        }

        return (float) $this->amount;
    }

    public function hasCalculationError(): bool
    {
        $grossTotal = $this->itemsGrossTotal();
        if ($grossTotal <= 0) {
            return false;
        }

        return abs($grossTotal - (float) $this->amount) > 0.01;
    }
}
