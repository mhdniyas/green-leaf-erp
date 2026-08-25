<?php

namespace App\Models;

use Database\Factories\DirectCompanySaleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectCompanySaleItem extends Model
{
    /** @use HasFactory<DirectCompanySaleItemFactory> */
    use HasFactory;

    protected $fillable = [
        'direct_company_sale_id',
        'product_id',
        'warehouse_id',
        'unit',
        'quantity',
        'conversion_to_base',
        'base_quantity',
        'unit_rate',
        'line_total',
        'price_source',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'conversion_to_base' => 'decimal:4',
            'base_quantity' => 'decimal:3',
            'unit_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(DirectCompanySale::class, 'direct_company_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
