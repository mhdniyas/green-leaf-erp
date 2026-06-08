<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\ProductGrade;
use Database\Factories\DailyProductPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyProductPrice extends Model
{
    /** @use HasFactory<DailyProductPriceFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'shop_price_group_id',
        'grade',
        'selling_price',
        'price_source',
        'margin_percent',
        'manual_override',
        'override_reason',
        'changed_by',
    ];

    protected $casts = [
        'grade' => ProductGrade::class,
        'selling_price' => 'decimal:2',
        'margin_percent' => 'decimal:2',
        'manual_override' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    public function revisions(): HasMany
    {
        return $this->hasMany(DailyProductPriceRevision::class);
    }
}
