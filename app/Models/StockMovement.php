<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'product_id',
        'warehouse_id',
        'shop_order_item_id',
        'created_by',
        'grade',
        'type',
        'quantity',
        'cost_per_unit',
        'notes',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'shop_order_item_id' => 'integer',
        'grade' => ProductGrade::class,
        'type' => StockMovementType::class,
        'quantity' => 'decimal:3',
        'cost_per_unit' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shopOrderItem(): BelongsTo
    {
        return $this->belongsTo(ShopOrderItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Computed
    public function getTotalValueAttribute(): float
    {
        return (float) $this->quantity * (float) $this->cost_per_unit;
    }
}
