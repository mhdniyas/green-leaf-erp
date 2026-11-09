<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopCreditNoteReceipt extends Model
{
    use HasFactory;

    protected $table = 'shop_credit_note_receipts';

    protected $fillable = [
        'shop_credit_note_id',
        'shop_credit_note_item_id',
        'product_id',
        'warehouse_id',
        'stock_movement_id',
        'reversal_stock_movement_id',
        'wastage_entry_id',
        'received_grade',
        'received_quantity',
        'received_by',
        'received_at',
        'status',
        'remarks',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'received_quantity' => 'decimal:4',
            'received_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(ShopCreditNote::class, 'shop_credit_note_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ShopCreditNoteItem::class, 'shop_credit_note_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function reversalStockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'reversal_stock_movement_id');
    }

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function wastageEntry(): BelongsTo
    {
        return $this->belongsTo(WastageEntry::class, 'wastage_entry_id');
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isDamaged(): bool
    {
        return $this->status === 'damaged';
    }

    public function isDisputed(): bool
    {
        return $this->status === 'disputed';
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['verified', 'damaged'])->whereNull('reversed_at');
    }
}
