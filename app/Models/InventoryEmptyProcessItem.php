<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryEmptyProcessItem extends Model
{
    protected $fillable = ['process_id', 'product_id', 'status', 'error_message'];

    public function process(): BelongsTo
    {
        return $this->belongsTo(InventoryEmptyProcess::class, 'process_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
