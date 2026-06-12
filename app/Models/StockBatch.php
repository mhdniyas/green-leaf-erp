<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\BatchStatus;
use Database\Factories\StockBatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class StockBatch extends Model
{
    /** @use HasFactory<StockBatchFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'created_by',
        'reference',
        'received_at',
        'total_kg',
        'cost_per_kg',
        'transport_cost',
        'labour_cost',
        'status',
        'warehouse_receive_pending',
        'warehouse_confirmed_at',
        'warehouse_confirmed_by',
        'notes',
        'sorted_at',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'received_at' => 'date',
        'total_kg' => 'decimal:3',
        'cost_per_kg' => 'decimal:4',
        'transport_cost' => 'decimal:2',
        'labour_cost' => 'decimal:2',
        'status' => BatchStatus::class,
        'warehouse_receive_pending' => 'boolean',
        'warehouse_confirmed_at' => 'datetime',
        'sorted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouseConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warehouse_confirmed_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'batch_id');
    }

    public function wastageEntries(): HasMany
    {
        return $this->hasMany(WastageEntry::class, 'batch_id');
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', BatchStatus::Pending);
    }

    public function scopeSorted(Builder $query): Builder
    {
        return $query->where('status', BatchStatus::Sorted);
    }

    // Computed
    public function getTotalLandedCostAttribute(): float
    {
        return (float) $this->total_kg * (float) $this->cost_per_kg
            + (float) $this->transport_cost
            + (float) $this->labour_cost;
    }

    /**
     * Get the allocated quantity for this specific batch based on daily shop order allocations.
     */
    public function getAllocatedQtyAttribute(): float
    {
        $date = $this->received_at->format('Y-m-d');

        // Find the total allocated quantity of this product for this business date
        $totalAllocated = (float) ShopOrderItem::where('product_id', $this->product_id)
            ->whereHas('order', function ($query) use ($date) {
                $query->whereDate('business_date', $date)
                    ->where('state', 'approved');
            })
            ->whereIn('sorting_status', ['allocated', 'loaded'])
            ->sum('approved_qty');

        if ($totalAllocated <= 0) {
            return 0.0;
        }

        // Fetch all batches of this product received on this date, ordered by ID (FIFO)
        $batches = self::where('product_id', $this->product_id)
            ->whereDate('received_at', $date)
            ->orderBy('id', 'asc')
            ->get();

        $allocatedForThisBatch = 0.0;
        $remainingAllocated = $totalAllocated;

        foreach ($batches as $b) {
            $wasted = (float) $b->wastageEntries()->sum('quantity');
            $maxAllocatable = max(0.0, (float) $b->total_kg - $wasted);
            $allocated = min($maxAllocatable, $remainingAllocated);

            if ($b->id === $this->id) {
                $allocatedForThisBatch = $allocated;
                break;
            }

            $remainingAllocated -= $allocated;
            if ($remainingAllocated <= 0) {
                break;
            }
        }

        return $allocatedForThisBatch;
    }

    /**
     * Get the remaining quantity of this batch.
     */
    public function getRemainingQtyAttribute(): float
    {
        $wasted = (float) $this->wastageEntries()->sum('quantity');
        $allocated = $this->allocated_qty;

        return max(0.0, (float) $this->total_kg - $wasted - $allocated);
    }

    public function canBeSorted(): bool
    {
        return $this->status->canBeSorted();
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
