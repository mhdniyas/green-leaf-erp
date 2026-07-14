<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopAccountingCategory extends Model
{
    protected $fillable = [
        'shop_id',
        'type',
        'cash_effect',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cash_effect' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function entryLines(): HasMany
    {
        return $this->hasMany(ShopAccountingEntryLine::class);
    }
}
