<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyInventoryCloseLine extends Model
{
    protected $fillable = [
        'business_date',
        'product_id',
        'grade',
        'closing_qty',
        'wastage_qty',
        'carryover_qty',
        'negative_note',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'closing_qty' => 'decimal:3',
            'wastage_qty' => 'decimal:3',
            'carryover_qty' => 'decimal:3',
            'closed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
