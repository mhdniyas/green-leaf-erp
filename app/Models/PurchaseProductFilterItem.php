<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseProductFilterItem extends Model
{
    protected $fillable = [
        'filter_id',
        'product_id',
    ];

    protected $casts = [
        'filter_id' => 'integer',
        'product_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function filter(): BelongsTo
    {
        return $this->belongsTo(PurchaseProductFilter::class, 'filter_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
