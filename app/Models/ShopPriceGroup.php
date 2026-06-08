<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopPriceGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopPriceGroup extends Model
{
    /** @use HasFactory<ShopPriceGroupFactory> */
    use HasFactory;

    public const OWN = 'own';

    public const PARTNERSHIP = 'partnership';

    public const OTHERS = 'others';

    protected $fillable = [
        'relationship_type',
        'name',
        'default_margin_percent',
        'is_active',
    ];

    protected $casts = [
        'default_margin_percent' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function relationshipTypes(): array
    {
        return [
            self::OWN => 'Own',
            self::PARTNERSHIP => 'Partnership',
            self::OTHERS => 'Others',
        ];
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function sellingPrices(): HasMany
    {
        return $this->hasMany(DailyProductPrice::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DailyProductPriceRevision::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getDisplayNameAttribute(): string
    {
        return (self::relationshipTypes()[$this->relationship_type] ?? ucfirst((string) $this->relationship_type)).' / '.$this->name;
    }
}
