<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseCreditNoteItemMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_credit_note_item_id',
        'stock_batch_id',
        'stock_movement_id',
        'quantity',
        'cost_per_unit',
        'reversal_stock_movement_id',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'warehouse_credit_note_item_id' => 'integer',
            'stock_batch_id' => 'integer',
            'stock_movement_id' => 'integer',
            'quantity' => 'decimal:3',
            'cost_per_unit' => 'decimal:4',
            'reversal_stock_movement_id' => 'integer',
            'reversed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(WarehouseCreditNoteItem::class, 'warehouse_credit_note_item_id');
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function reversalStockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'reversal_stock_movement_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null || $this->reversal_stock_movement_id !== null;
    }
}
