<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class BillReconciliationLine extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'bill_reconciliation_lines';

    protected $fillable = [
        'bill_reconciliation_id',
        'purchase_order_item_id',
        'product_id',
        'bill_qty',
        'bill_unit',
        'bill_base_qty',
        'advance_matched_qty',
        'advance_matched_unit',
        'advance_matched_base_qty',
        'new_receive_qty',
        'new_receive_unit',
        'new_receive_base_qty',
        'relevant_loadout_qty',
        'unbilled_loadout_qty',
        'reconciled_qty',
        'reconciled_base_qty',
        'difference_status',
    ];

    protected $casts = [
        'bill_qty' => 'decimal:3',
        'bill_base_qty' => 'decimal:3',
        'advance_matched_qty' => 'decimal:3',
        'advance_matched_base_qty' => 'decimal:3',
        'new_receive_qty' => 'decimal:3',
        'new_receive_base_qty' => 'decimal:3',
        'relevant_loadout_qty' => 'decimal:3',
        'unbilled_loadout_qty' => 'decimal:3',
        'reconciled_qty' => 'decimal:3',
        'reconciled_base_qty' => 'decimal:3',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function billReconciliation(): BelongsTo
    {
        return $this->belongsTo(BillReconciliation::class, 'bill_reconciliation_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function advanceMatches(): HasMany
    {
        return $this->hasMany(AdvanceReceiveMatch::class, 'bill_reconciliation_line_id');
    }
}
