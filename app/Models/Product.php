<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model implements AuditableContract
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'unit',
        'description',
        'base_price',
        'image',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
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
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function wastageEntries(): HasMany
    {
        return $this->hasMany(WastageEntry::class);
    }

    public function dailyPrices(): HasMany
    {
        return $this->hasMany(DailyProductPrice::class);
    }

    public function wholesalePrices(): HasMany
    {
        return $this->hasMany(ProductWholesalePrice::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getImageUrl(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'sku';
    }
}
