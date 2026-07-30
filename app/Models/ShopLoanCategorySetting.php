<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopLoanCategorySetting extends Model
{
    public const EffectUseLoan = 'use_loan';

    protected $fillable = [
        'shop_id',
        'shop_accounting_category_id',
        'effect',
        'default_daily_amount',
        'cashbook_offset_enabled',
    ];

    protected function casts(): array
    {
        return [
            'default_daily_amount' => 'decimal:2',
            'cashbook_offset_enabled' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ShopAccountingCategory::class, 'shop_accounting_category_id');
    }
}
