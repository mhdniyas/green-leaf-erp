<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ShopCashbookRelation extends Model
{
    protected $fillable = [
        'public_uuid',
        'shop_id',
        'name',
        'relation_type',
        'enabled',
        'display_order',
        'settlement_source',
        'eligibility_rule',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'enabled' => 'boolean',
        'display_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ShopCashbookRelation $model) {
            if (empty($model->public_uuid)) {
                $model->public_uuid = (string) Str::uuid();
            }
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopCashbookRelationItem::class, 'relation_id')
            ->orderBy('display_order');
    }
}
