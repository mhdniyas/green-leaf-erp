<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopCashbookUiLayout extends Model
{
    protected $table = 'shop_cashbook_ui_layouts';

    protected $fillable = [
        'shop_id',
        'layout_data',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'layout_data' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
}
