<?php

declare(strict_types=1);

namespace App\Models\Purchasing;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaserBusinessDaySubmissionItem extends Model
{
    use HasFactory;

    protected $table = 'purchaser_business_day_submission_items';

    protected $fillable = [
        'submission_id',
        'product_id',
        'warehouse_id',
        'product_name',
        'sku',
        'unit',
        'ownership_source',
        'received_qty',
        'billed_qty',
        'pending_qty',
        'excess_qty',
        'coverage_percentage',
        'unit_mismatch',
        'is_fully_covered',
        'status_at_submission',
        'item_details_snapshot',
    ];

    protected $casts = [
        'submission_id' => 'integer',
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'received_qty' => 'decimal:3',
        'billed_qty' => 'decimal:3',
        'pending_qty' => 'decimal:3',
        'excess_qty' => 'decimal:3',
        'coverage_percentage' => 'decimal:2',
        'unit_mismatch' => 'boolean',
        'is_fully_covered' => 'boolean',
        'item_details_snapshot' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(PurchaserBusinessDaySubmission::class, 'submission_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
