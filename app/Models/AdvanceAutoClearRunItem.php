<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceAutoClearRunItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_id',
        'position',
        'execution_mode',
        'purchase_order_id',
        'source_goods_received_id',
        'planned_base_qty',
        'status',
        'result_goods_received_id',
        'reason_code',
        'result_payload',
        'attempt_count',
        'last_attempted_at',
    ];

    protected $casts = [
        'planned_base_qty' => 'decimal:3',
        'result_payload' => 'array',
        'attempt_count' => 'integer',
        'last_attempted_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AdvanceAutoClearRun::class, 'run_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function sourceGoodsReceived(): BelongsTo
    {
        return $this->belongsTo(GoodsReceived::class, 'source_goods_received_id');
    }

    public function resultGoodsReceived(): BelongsTo
    {
        return $this->belongsTo(GoodsReceived::class, 'result_goods_received_id');
    }
}
