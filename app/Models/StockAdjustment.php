<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'created_by', 'business_date', 'system_qty', 'counted_qty', 'variance_qty', 'category', 'notes'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'system_qty' => 'decimal:3',
            'counted_qty' => 'decimal:3',
            'variance_qty' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
