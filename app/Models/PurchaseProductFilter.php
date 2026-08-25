<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseProductFilter extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $filter): void {
            if (empty($filter->uuid)) {
                $filter->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function filterItems(): HasMany
    {
        return $this->hasMany(PurchaseProductFilterItem::class, 'filter_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'purchase_product_filter_items', 'filter_id', 'product_id')
            ->withTimestamps();
    }

    /**
     * Return an ordered array of product IDs belonging to this filter.
     *
     * @return array<int, int>
     */
    public function getProductIds(): array
    {
        return $this->filterItems()->orderBy('product_id')->pluck('product_id')->map(fn ($id) => (int) $id)->all();
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
