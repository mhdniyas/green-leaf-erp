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
        'created_by',
        'reference',
        'received_at',
        'total_kg',
        'cost_per_kg',
        'transport_cost',
        'labour_cost',
        'status',
        'notes',
        'sorted_at',
    ];

    protected $casts = [
        'received_at' => 'date',
        'total_kg' => 'decimal:3',
        'cost_per_kg' => 'decimal:4',
        'transport_cost' => 'decimal:2',
        'labour_cost' => 'decimal:2',
        'status' => BatchStatus::class,
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function canBeSorted(): bool
    {
        return $this->status->canBeSorted();
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
