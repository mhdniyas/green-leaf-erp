<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseCreditNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_credit_note_id',
        'purchaser_cart_item_id',
        'product_id',
        'grade',
        'quantity',
        'unit',
        'conversion_to_base',
        'base_qty',
        'unit_price',
        'line_total',
        'inventory_action',
        'stock_movement_id',
        'wastage_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'conversion_to_base' => 'decimal:6',
            'base_qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'inventory_action' => PurchaseCreditInventoryAction::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseCreditNote::class, 'purchase_credit_note_id');
    }

    public function purchaserCartItem(): BelongsTo
    {
        return $this->belongsTo(PurchaserCartItem::class, 'purchaser_cart_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function wastageEntry(): BelongsTo
    {
        return $this->belongsTo(WastageEntry::class, 'wastage_entry_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(PurchaseCreditNoteItemMovement::class, 'purchase_credit_note_item_id');
    }
}
