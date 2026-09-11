<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAdvanceMatchRunItem extends Model
{
    use HasFactory;

    protected $table = 'daily_advance_match_run_items';

    protected $fillable = [
        'run_id',
        'position',
        'goods_received_id',
        'purchase_order_id',
        'planned_base_qty',
        'status',
        'reason_code',
        'result_payload',
        'attempt_count',
        'last_attempted_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'planned_base_qty' => 'decimal:3',
        'result_payload' => 'array',
        'attempt_count' => 'integer',
        'last_attempted_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(DailyAdvanceMatchRun::class, 'run_id');
    }

    public function goodsReceived(): BelongsTo
    {
        return $this->belongsTo(GoodsReceived::class, 'goods_received_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
