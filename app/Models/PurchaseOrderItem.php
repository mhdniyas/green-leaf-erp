<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'purchase_unit',
        'packet_qty',
        'weight_per_packet',
        'actual_weight',
        'quantity',
        'unit_price',
        'price_basis',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'packet_qty' => 'decimal:3',
        'weight_per_packet' => 'decimal:3',
        'actual_weight' => 'decimal:3',
        'unit_price' => 'decimal:4',
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
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Computed Attributes
    // Following Golden Rule: PO → Purchase unit (BOX/BAG/KG)

    /**
     * Calculate the total subtotal for this purchase order item.
     * Respects the price_basis (per_unit vs per_kg).
     *
     * @return float
     */
    public function getSubtotalAttribute(): float
    {
        if ($this->price_basis === 'per_unit') {
            return $this->priced_unit_count * (float) $this->unit_price;
        }

        return $this->effective_weight * (float) $this->unit_price;
    }

    /**
     * Get the effective base quantity (KG) for this item.
     * Uses actual_weight if available, otherwise uses quantity.
     * Following Golden Rule: All base quantities in KG.
     *
     * @return float
     */
    public function getEffectiveWeightAttribute(): float
    {
        return $this->actual_weight !== null ? (float) $this->actual_weight : (float) $this->quantity;
    }

    /**
     * Get the count of units for pricing purposes.
     * Returns packet count for per_unit pricing, or KG for per_kg pricing.
     *
     * @return float
     */
    public function getPricedUnitCountAttribute(): float
    {
        if ($this->purchase_unit === 'kg') {
            return $this->effective_weight;
        }

        return (float) ($this->packet_qty ?? 0);
    }

    /**
     * Calculate the cost per KG based on the actual received quantity.
     * Converts per-unit pricing to per-kg pricing.
     *
     * @param  float  $receivedQuantity  Base quantity in KG
     * @return float Cost per KG
     */
    public function costPerKgForReceivedQuantity(float $receivedQuantity): float
    {
        if ($receivedQuantity <= 0.0) {
            return 0.0;
        }

        if ($this->price_basis === 'per_unit') {
            return round($this->subtotal / $receivedQuantity, 4);
        }

        return (float) $this->unit_price;
    }
}
