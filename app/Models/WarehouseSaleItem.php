<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WarehouseSaleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseSaleItem extends Model
{
    /** @use HasFactory<WarehouseSaleItemFactory> */
    use HasFactory;

    protected $fillable = [
        'warehouse_sale_id',
        'product_id',
        'grade',
        'entered_qty',
        'entered_unit',
        'normalized_qty',
        'normalized_unit',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'entered_qty' => 'decimal:3',
            'normalized_qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function warehouseSale(): BelongsTo
    {
        return $this->belongsTo(WarehouseSale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'warehouse_sale_item_id');
    }
}
