<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class BillReconciliation extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'bill_reconciliations';

    protected $fillable = [
        'purchase_order_id',
        'goods_received_id',
        'warehouse_id',
        'source_type',
        'status',
        'total_bill_base_qty',
        'total_matched_base_qty',
        'total_new_receive_base_qty',
        'confirmed_by',
        'confirmed_at',
        'client_submission_id',
        'submission_payload_hash',
        'notes',
    ];

    protected $casts = [
        'total_bill_base_qty' => 'decimal:3',
        'total_matched_base_qty' => 'decimal:3',
        'total_new_receive_base_qty' => 'decimal:3',
        'confirmed_at' => 'datetime',
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceived(): BelongsTo
    {
        return $this->belongsTo(GoodsReceived::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillReconciliationLine::class, 'bill_reconciliation_id');
    }

    public function advanceMatches(): HasMany
    {
        return $this->hasMany(AdvanceReceiveMatch::class, 'bill_reconciliation_id');
    }
}
