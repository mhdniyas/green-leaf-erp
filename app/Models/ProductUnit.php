<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductUnit extends Model
{
    public const AVAILABLE_UNITS = ['kg', 'box', 'piece', 'bag', 'bunch', 'full_bunch', 'packet', 'crate', 'tray', 'roll', 'chaak'];

    public const UNIT_ALIASES = [
        'pc' => 'piece',
        'pcs' => 'piece',
        'pcs.' => 'piece',
        'pieces' => 'piece',
        'full bunch' => 'full_bunch',
        'full-bunch' => 'full_bunch',
        'fullbunch' => 'full_bunch',
    ];

    private static ?bool $hasPublicUuidColumn = null;

    protected $fillable = [
        'public_uuid',
        'product_id',
        'unit',
        'label',
        'conversion_to_base',
        'is_base',
        'is_orderable',
        'sort_order',
    ];

    protected $casts = [
        'conversion_to_base' => 'decimal:4',
        'is_base' => 'boolean',
        'is_orderable' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return static::hasPublicUuidColumn() ? 'public_uuid' : $this->getKeyName();
    }

    protected static function booted(): void
    {
        static::creating(function (self $unit): void {
            if (static::hasPublicUuidColumn() && ! $unit->public_uuid) {
                $unit->public_uuid = (string) Str::uuid();
            }
        });
    }

    public static function hasPublicUuidColumn(): bool
    {
        return self::$hasPublicUuidColumn ??= Schema::hasColumn('product_units', 'public_uuid');
    }

    public static function normalizeUnit(?string $unit): string
    {
        $normalized = mb_strtolower(trim((string) $unit));

        return self::UNIT_ALIASES[$normalized] ?? $normalized;
    }

    /**
     * @return array<int, string>
     */
    public static function databaseUnitsFor(string $unit): array
    {
        $normalized = self::normalizeUnit($unit);
        $aliases = collect(self::UNIT_ALIASES)
            ->filter(fn (string $aliasUnit): bool => $aliasUnit === $normalized)
            ->keys()
            ->all();

        return array_values(array_unique([$normalized, ...$aliases]));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
