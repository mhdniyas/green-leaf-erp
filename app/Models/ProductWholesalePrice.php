<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\ProductGrade;
use Database\Factories\ProductWholesalePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductWholesalePrice extends Model
{
    /** @use HasFactory<ProductWholesalePriceFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'grade',
        'weighted_average_cost',
        'wholesale_price',
        'sellable_quantity',
        'total_cost',
        'source_type',
        'source_reference',
        'calculated_at',
    ];

    protected $casts = [
        'grade' => ProductGrade::class,
        'weighted_average_cost' => 'decimal:4',
        'wholesale_price' => 'decimal:4',
        'sellable_quantity' => 'decimal:3',
        'total_cost' => 'decimal:4',
        'calculated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
