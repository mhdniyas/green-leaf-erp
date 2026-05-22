<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use Database\Factories\WastageEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class WastageEntry extends Model
{
    /** @use HasFactory<WastageEntryFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'product_id',
        'batch_id',
        'recorded_by',
        'grade',
        'quantity',
        'cost_per_kg',
        'reason',
        'wastage_date',
        'notes',
    ];

    protected $casts = [
        'grade' => ProductGrade::class,
        'reason' => WastageReason::class,
        'quantity' => 'decimal:3',
        'cost_per_kg' => 'decimal:4',
        'wastage_date' => 'date',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Scopes
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('wastage_date', $date);
    }

    // Computed
    public function getTotalCostAttribute(): float
    {
        return (float) $this->quantity * (float) $this->cost_per_kg;
    }
}
