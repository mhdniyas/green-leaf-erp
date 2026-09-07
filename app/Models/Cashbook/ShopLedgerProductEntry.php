<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopLedgerProductEntry extends Model
{
    protected $table = 'shop_ledger_product_entries';

    protected $fillable = [
        'shop_id',
        'business_date',
        'header_group_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit',
        'amount',
        'entered_by',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'business_date' => 'date:Y-m-d',
        'header_group_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'decimal:4',
        'amount' => 'decimal:2',
        'entered_by' => 'integer',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function headerGroup(): BelongsTo
    {
        return $this->belongsTo(ShopLedgerHeaderGroup::class, 'header_group_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
