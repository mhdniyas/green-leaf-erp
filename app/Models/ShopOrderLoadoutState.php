<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopOrderLoadoutState extends Model
{
    protected $fillable = [
        'shop_order_id',
        'warehouse_id',
        'started_at',
        'initialized_at',
        'initialized_by',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'started_at' => 'datetime',
        'initialized_at' => 'datetime',
        'initialized_by' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function initializedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initialized_by');
    }
}
