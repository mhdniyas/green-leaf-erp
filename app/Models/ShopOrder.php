<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShopOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'order_number',
        'state',
        'delivery_status',
        'payment_status',
        'business_date',
        'submitted_at',
        'deadline_at',
        'update_reason',
        'latest_revision_no',
        'has_pending_revision',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'manager_note',
        'is_allocation_completed',
        'sorting_notes',
        'is_delivered',
        'is_late',
        'delivered_at',
        'delivered_by',
        'delivery_notes',
        'cash_collected',
        'cash_discrepancy',
        'balance_amount',
        'finance_note',
        'total_shortage_value',
    ];

    protected $casts = [
        'business_date' => 'date',
        'submitted_at' => 'datetime',
        'deadline_at' => 'datetime',
        'has_pending_revision' => 'boolean',
        'reviewed_at' => 'datetime',
        'is_allocation_completed' => 'boolean',
        'is_delivered' => 'boolean',
        'is_late' => 'boolean',
        'delivered_at' => 'datetime',
        'cash_collected' => 'decimal:2',
        'cash_discrepancy' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'total_shortage_value' => 'decimal:2',
    ];

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (empty($order->order_number)) {
                $date = Carbon::parse($order->business_date)->format('Ymd');
                do {
                    $suffix = strtoupper(bin2hex(random_bytes(2)));
                    $orderNumber = "RQ-{$date}-{$suffix}";
                } while (self::where('order_number', $orderNumber)->exists());
                $order->order_number = $orderNumber;
            }
        });
    }

    /**
     * Check if this order is editable directly (before 9:30 PM of the day before delivery).
     */
    public function canEditDirectly(): bool
    {
        if (in_array($this->state, ['approved', 'rejected'], true) || $this->is_delivered) {
            return false;
        }

        // Cutoff is 9:30 PM (21:30) of the day before the target business date.
        $cutoff = Carbon::parse($this->business_date)->subDay()->setTime(21, 30, 0);

        return now()->lessThanOrEqualTo($cutoff);
    }

    /**
     * Get the shop that owns the order.
     *
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the user who created the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who reviewed the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the items in this order.
     *
     * @return HasMany<ShopOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class);
    }

    /**
     * @return HasMany<ShopOrderRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(ShopOrderRevision::class);
    }

    /**
     * @return HasOne<ShopOrderRevision, $this>
     */
    public function latestPendingRevision(): HasOne
    {
        return $this->hasOne(ShopOrderRevision::class)
            ->where('status', 'pending')
            ->latestOfMany('revision_no');
    }

    /**
     * @return HasOne<ShopOrderRevision, $this>
     */
    public function latestResolvedRevision(): HasOne
    {
        return $this->hasOne(ShopOrderRevision::class)
            ->whereIn('status', ['applied', 'rejected', 'blocked'])
            ->latestOfMany('revision_no');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(ShopInvoice::class);
    }

    public function nextRevisionNumber(): int
    {
        return max(1, (int) $this->latest_revision_no) + 1;
    }

    public function displayStateLabel(): string
    {
        $revisionNumber = max(1, (int) $this->latest_revision_no);
        $latestResolvedRevision = $this->relationLoaded('latestResolvedRevision') ? $this->latestResolvedRevision : null;

        if ($latestResolvedRevision && $latestResolvedRevision->revision_no === $revisionNumber) {
            return $latestResolvedRevision->resolvedLabel();
        }

        if ($revisionNumber > 1 && $this->has_pending_revision) {
            return "Update #{$revisionNumber} Pending";
        }

        return (string) str($this->state)->replace('_', ' ')->title();
    }

    public function hasLinkedPurchaseOrders(): bool
    {
        return PurchaseOrder::query()
            ->whereDate('order_date', $this->business_date)
            ->whereHas('items', function ($query): void {
                $query->whereIn('product_id', $this->items()->pluck('product_id'));
            })
            ->exists();
    }

    public function linkedPurchaseOrdersHaveGoodsReceived(): bool
    {
        return PurchaseOrder::query()
            ->whereDate('order_date', $this->business_date)
            ->whereHas('items', function ($query): void {
                $query->whereIn('product_id', $this->items()->pluck('product_id'));
            })
            ->whereHas('goodsReceiveds')
            ->exists();
    }

    public function warehouseWorkflowStage(): string
    {
        if ($this->delivery_status === 'pending_approval') {
            return 'pending_approval';
        }

        if ($this->is_delivered) {
            return $this->delivery_status ?: 'delivered';
        }

        if ($this->is_allocation_completed) {
            return 'in_transit';
        }

        $items = $this->relationLoaded('items') ? $this->items : null;
        if ($items && $items->contains(fn (ShopOrderItem $item): bool => in_array($item->sorting_status, ['allocated', 'loaded'], true))) {
            return 'packing';
        }

        return 'approved';
    }

    public function warehouseWorkflowLabel(): string
    {
        return match ($this->warehouseWorkflowStage()) {
            'packing' => 'Packing In Progress',
            'in_transit' => 'In Transit',
            'partially_delivered' => 'Partially Delivered',
            'delivery_issue' => 'Delivery Issue',
            'delivered' => 'Delivered',
            'pending_approval' => 'Pending Discrepancy Approval',
            default => 'Approved For Warehouse',
        };
    }

    public function warehouseWorkflowTone(): string
    {
        return match ($this->warehouseWorkflowStage()) {
            'packing' => 'warning',
            'in_transit' => 'info',
            'delivered' => 'success',
            'partially_delivered' => 'warning',
            'delivery_issue' => 'danger',
            'pending_approval' => 'warning',
            default => 'neutral',
        };
    }
}
