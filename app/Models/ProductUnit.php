<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    protected $fillable = [
        'product_id',
        'unit',
        'label',
        'conversion_to_base',
        'is_base',
        'is_orderable',
        'sort_order',
    ];

    protected $casts = [
        'conversion_to_base' => 'decimal:4',
        'is_base' => 'boolean',
        'is_orderable' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
