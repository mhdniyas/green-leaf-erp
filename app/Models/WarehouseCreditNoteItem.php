<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Warehouse\WarehouseCreditInventoryAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseCreditNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_credit_note_id',
        'product_id',
        'grade',
        'inventory_action',
        'unit',
        'conversion_to_base',
        'quantity',
        'base_qty',
        'valuation_rate',
        'line_total',
        'fifo_cost_total',
        'wastage_entry_id',
        'notes',
    ];

    protected $attributes = [
        'grade' => ProductGrade::GradeA->value,
        'unit' => 'kg',
        'conversion_to_base' => 1.0000,
        'fifo_cost_total' => 0.00,
    ];

    protected function casts(): array
    {
        return [
            'warehouse_credit_note_id' => 'integer',
            'product_id' => 'integer',
            'grade' => ProductGrade::class,
            'inventory_action' => WarehouseCreditInventoryAction::class,
            'conversion_to_base' => 'decimal:4',
            'quantity' => 'decimal:3',
            'base_qty' => 'decimal:3',
            'valuation_rate' => 'decimal:4',
            'line_total' => 'decimal:2',
            'fifo_cost_total' => 'decimal:2',
            'wastage_entry_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(WarehouseCreditNote::class, 'warehouse_credit_note_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function wastageEntry(): BelongsTo
    {
        return $this->belongsTo(WastageEntry::class, 'wastage_entry_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(WarehouseCreditNoteItemMovement::class, 'warehouse_credit_note_item_id');
    }
}
