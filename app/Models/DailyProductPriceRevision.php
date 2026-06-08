<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\ProductGrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyProductPriceRevision extends Model
{
    protected $fillable = [
        'daily_product_price_id',
        'product_id',
        'shop_price_group_id',
        'grade',
        'old_price',
        'new_price',
        'old_margin_percent',
        'new_margin_percent',
        'change_type',
        'reason',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'grade' => ProductGrade::class,
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'old_margin_percent' => 'decimal:2',
        'new_margin_percent' => 'decimal:2',
        'changed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dailyProductPrice(): BelongsTo
    {
        return $this->belongsTo(DailyProductPrice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shopPriceGroup(): BelongsTo
    {
        return $this->belongsTo(ShopPriceGroup::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
