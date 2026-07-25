<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopCashMovementCategory extends Model
{
    protected $fillable = [
        'name',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function credits(): HasMany
    {
        return $this->hasMany(ShopCredit::class, 'shop_cash_movement_category_id');
    }

    public static function defaultCategory(): self
    {
        return self::query()->firstOrCreate(
            ['is_default' => true],
            [
                'name' => 'Petty Cash',
                'is_active' => true,
            ],
        );
    }
}
