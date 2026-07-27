<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
        'default_warehouse_id',
        'public_uuid',
        'name',
        'sku',
        'unit',
        'description',
        'base_price',
        'buffer_qty',
        'carryover_enabled',
        'vendor_price',
        'image',
        'is_active',
        'status_changed_by',
        'status_changed_at',
    ];

    private static ?bool $hasPublicUuidColumn = null;

    protected $casts = [
        'default_warehouse_id' => 'integer',
        'base_price' => 'decimal:2',
        'buffer_qty' => 'decimal:2',
        'carryover_enabled' => 'boolean',
        'vendor_price' => 'decimal:4',
        'is_active' => 'boolean',
        'status_changed_at' => 'datetime',
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

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
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

    public function orderUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('sort_order')->orderBy('id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot(['last_price', 'last_purchased_at'])
            ->withTimestamps();
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        return $query
            ->orderByRaw(self::numericSkuPriorityExpression('sku', $driver))
            ->orderByRaw(self::numericSkuValueExpression('sku', $driver))
            ->orderBy('sku')
            ->orderBy('name');
    }

    public function getSkuSortValueAttribute(): string
    {
        return self::sortableSku($this->sku);
    }

    public static function sortableSku(string $sku): string
    {
        if (preg_match('/^\d+$/', $sku) === 1) {
            return '0'.str_pad($sku, 12, '0', STR_PAD_LEFT);
        }

        return '1'.strtoupper($sku);
    }

    public static function numericSkuPriorityExpression(string $column, string $driver): string
    {
        $isNumeric = self::numericSkuCondition($column, $driver);

        return "CASE WHEN {$isNumeric} THEN 0 ELSE 1 END";
    }

    public static function numericSkuValueExpression(string $column, string $driver): string
    {
        $isNumeric = self::numericSkuCondition($column, $driver);
        $castType = $driver === 'sqlite' ? 'INTEGER' : 'UNSIGNED';

        return "CASE WHEN {$isNumeric} THEN CAST({$column} AS {$castType}) END";
    }

    private static function numericSkuCondition(string $column, string $driver): string
    {
        if ($driver === 'sqlite') {
            return "{$column} GLOB '[0-9]*' AND {$column} NOT GLOB '*[^0-9]*'";
        }

        return "{$column} REGEXP '^[0-9]+$'";
    }

    public function getImageUrl(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    public function getRouteKeyName(): string
    {
        return static::hasPublicUuidColumn() ? 'public_uuid' : 'sku';
    }

    public function getRouteKey(): mixed
    {
        if (static::hasPublicUuidColumn() && $this->public_uuid) {
            return $this->public_uuid;
        }

        return parent::getRouteKey();
    }

    public function conversionToBaseForUnit(?string $unit): float
    {
        $normalizedUnit = strtolower(trim((string) ($unit ?: $this->unit)));

        if ($normalizedUnit === strtolower((string) $this->unit)) {
            return 1.0;
        }

        $units = $this->relationLoaded('orderUnits') ? $this->orderUnits : $this->orderUnits()->get();
        $matchedUnit = $units->first(fn (ProductUnit $productUnit): bool => strtolower($productUnit->unit) === $normalizedUnit);

        return $matchedUnit ? (float) $matchedUnit->conversion_to_base : 1.0;
    }

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            if (static::hasPublicUuidColumn() && ! $product->public_uuid) {
                $product->public_uuid = (string) Str::uuid();
            }
        });
    }

    private static function hasPublicUuidColumn(): bool
    {
        return self::$hasPublicUuidColumn ??= Schema::hasColumn('products', 'public_uuid');
    }
}
