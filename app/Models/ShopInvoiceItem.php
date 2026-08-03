<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_invoice_id',
        'shop_order_item_id',
        'product_id',
        'product_name',
        'unit',
        'price_unit',
        'approved_qty',
        'price_quantity',
        'delivered_qty',
        'delivered_price_quantity',
        'shortage_qty',
        'shortage_price_quantity',
        'excess_qty',
        'excess_price_quantity',
        'unit_price',
        'line_subtotal',
        'shortage_amount',
        'excess_amount',
        'final_line_total',
    ];

    protected function casts(): array
    {
        return [
            'approved_qty' => 'decimal:2',
            'price_quantity' => 'decimal:4',
            'delivered_qty' => 'decimal:2',
            'delivered_price_quantity' => 'decimal:4',
            'shortage_qty' => 'decimal:2',
            'shortage_price_quantity' => 'decimal:4',
            'excess_qty' => 'decimal:2',
            'excess_price_quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'shortage_amount' => 'decimal:2',
            'excess_amount' => 'decimal:2',
            'final_line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ShopInvoice::class, 'shop_invoice_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(ShopOrderItem::class, 'shop_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Helper Methods
    // Following Golden Rule: Invoice → Billing unit (independent of inventory)

    /**
     * Get the approved quantity in base units (KG).
     * All internal quantities are in base units.
     *
     * @return float
     */
    public function getApprovedBaseQuantity(): float
    {
        return (float) $this->approved_qty;
    }

    /**
     * Get the delivered quantity in base units (KG).
     * All internal quantities are in base units.
     *
     * @return float
     */
    public function getDeliveredBaseQuantity(): float
    {
        return (float) $this->delivered_qty;
    }

    /**
     * Get the quantity used for billing (converted to price_unit).
     * This is independent of base units.
     *
     * @return float
     */
    public function getBillingQuantity(): float
    {
        return (float) $this->price_quantity;
    }

    /**
     * Get the delivered billing quantity (converted to price_unit).
     *
     * @return float
     */
    public function getDeliveredBillingQuantity(): float
    {
        return (float) $this->delivered_price_quantity;
    }

    /**
     * Get the unit used for billing.
     * This can be different from the base unit or order unit.
     *
     * @return string
     */
    public function getBillingUnit(): string
    {
        return (string) ($this->price_unit ?: $this->unit);
    }
}
