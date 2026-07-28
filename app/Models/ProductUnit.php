<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductUnit extends Model
{
    public const AVAILABLE_UNITS = ['kg', 'box', 'piece', 'bag', 'bunch', 'packet', 'crate', 'tray'];

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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
