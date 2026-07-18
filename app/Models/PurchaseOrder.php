<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Purchasing\POStatus;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'purchaser_cart_id',
        'po_number',
        'status',
        'order_date',
        'created_by',
        'notes',
        'fulfillment_type',
    ];

    protected $casts = [
        'status' => POStatus::class,
        'order_date' => 'date',
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
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceiveds(): HasMany
    {
        return $this->hasMany(GoodsReceived::class);
    }

    public function purchaserCart(): HasOne
    {
        return $this->hasOne(PurchaserCart::class);
    }

    public function getRouteKeyName(): string
    {
        return 'po_number';
    }

    // Computed
    public function getTotalAmountAttribute(): float
    {
        return $this->items->sum(fn ($item) => (float) $item->subtotal);
    }

    public function hasFinalLockedShopInvoices(): bool
    {
        $productIds = $this->items()->pluck('product_id');

        return ShopOrder::query()
            ->whereDate('business_date', $this->order_date)
            ->whereHas('items', function ($query) use ($productIds): void {
                $query->whereIn('product_id', $productIds);
            })
            ->where(function ($query): void {
                $query
                    ->where('delivery_review_status', 'approved')
                    ->orWhereIn('delivery_status', ['delivered', 'partially_delivered'])
                    ->orWhere('is_delivered', true)
                    ->orWhereHas('invoice', function ($invoiceQuery): void {
                        $invoiceQuery
                            ->whereIn('delivery_status', ['received_full', 'approved_after_discrepancy'])
                            ->orWhereIn('status', ['finalized', 'payment_pending', 'paid'])
                            ->orWhereIn('payment_status', ['partially_paid', 'paid'])
                            ->orWhereNotNull('payment_approved_at')
                            ->orWhere('paid_amount', '>', 0);
                    });
            })
            ->exists();
    }
}
