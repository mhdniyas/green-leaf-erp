<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_order_id',
        'product_id',
        'product_grade',
        'requested_qty',
        'approved_qty',
        'loaded_qty',
        'shop_reported_received_qty',
        'shop_reported_missing_qty',
        'shop_reported_excess_qty',
        'shop_reported_damaged_qty',
        'shop_reported_returned_qty',
        'shop_verified_by',
        'shop_verified_at',
        'shop_verification_note',
        'loadout_discrepancy_type',
        'loadout_discrepancy_note',
        'unit',
        'locked_price_group_id',
        'locked_selling_price',
        'locked_price_source',
        'line_total',
        'notes',
        'fulfillment_type',
        'is_sorted',
        'sorted_at',
        'sorted_by',
        'sorting_status',
        'delivered_qty',
        'shortage_qty',
        'excess_qty',
        'unit_cost',
        'shortage_value',
        'excess_value',
        'delivery_discrepancy_type',
        'delivery_discrepancy_note',
    ];

    protected $casts = [
        'requested_qty' => 'decimal:2',
        'approved_qty' => 'decimal:2',
        'loaded_qty' => 'decimal:2',
        'shop_reported_received_qty' => 'decimal:2',
        'shop_reported_missing_qty' => 'decimal:2',
        'shop_reported_excess_qty' => 'decimal:2',
        'shop_reported_damaged_qty' => 'decimal:2',
        'shop_reported_returned_qty' => 'decimal:2',
        'shop_verified_at' => 'datetime',
        'locked_selling_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'is_sorted' => 'boolean',
        'sorted_at' => 'datetime',
        'delivered_qty' => 'decimal:2',
        'shortage_qty' => 'decimal:2',
        'excess_qty' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'shortage_value' => 'decimal:2',
        'excess_value' => 'decimal:2',
    ];

    /**
     * Get the order that owns this item.
     *
     * @return BelongsTo<ShopOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }

    /**
     * Get the product represented by this item.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lockedPriceGroup(): BelongsTo
    {
        return $this->belongsTo(ShopPriceGroup::class, 'locked_price_group_id');
    }

    /**
     * Get the user who sorted this item.
     *
     * @return BelongsTo<User, $this>
     */
    public function sortedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorted_by');
    }

    public function purchaserCorrectionRequests(): HasMany
    {
        return $this->hasMany(PurchaserCorrectionRequest::class);
    }

    public function warehouseWorkflowStage(): string
    {
        $order = $this->relationLoaded('order') ? $this->order : null;

        if ($order?->delivery_status === 'pending_approval') {
            return 'pending_approval';
        }

        if ($order?->is_delivered) {
            if ((float) $this->delivered_qty > 0 && (float) $this->shortage_qty > 0) {
                return 'partially_delivered';
            }

            return (float) $this->delivered_qty > 0 ? 'delivered' : 'delivery_issue';
        }

        if ($this->sorting_status === 'loaded' || $order?->is_allocation_completed) {
            return 'in_transit';
        }

        if ($this->sorting_status === 'allocated') {
            return 'packing';
        }

        return 'approved';
    }

    public function warehouseWorkflowLabel(): string
    {
        return match ($this->warehouseWorkflowStage()) {
            'packing' => 'Allocated',
            'in_transit' => 'Loaded',
            'partially_delivered' => 'Partial Delivery',
            'delivered' => 'Delivered',
            'delivery_issue' => 'Delivery Issue',
            'pending_approval' => 'Awaiting Admin Review',
            default => 'Approved',
        };
    }

    public function warehouseWorkflowTone(): string
    {
        return match ($this->warehouseWorkflowStage()) {
            'packing' => 'warning',
            'in_transit' => 'info',
            'delivered' => 'success',
            'delivery_issue' => 'danger',
            'partially_delivered' => 'warning',
            'pending_approval' => 'warning',
            default => 'neutral',
        };
    }
}
