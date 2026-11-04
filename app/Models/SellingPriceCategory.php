<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SellingPriceCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'selling_price_categories';

    protected $fillable = [
        'name',
        'markup_percentage',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'markup_percentage' => 'float',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'selling_price_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Resolve effective markup percentage. If inactive, falls back to the provided fallback or global setting.
     */
    public function effectiveMarkupPercentage(?float $fallback = null): float
    {
        if ($this->is_active) {
            return (float) $this->markup_percentage;
        }

        if ($fallback !== null) {
            return $fallback;
        }

        $rawGlobal = BusinessSetting::query()
            ->where('key', 'selling_price_default_percentage')
            ->value('value');

        return ($rawGlobal !== null && is_numeric($rawGlobal) && (float) $rawGlobal >= 0)
            ? (float) $rawGlobal
            : 25.0;
    }
}
