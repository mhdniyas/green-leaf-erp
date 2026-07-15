<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopPettyCashExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopPettyCashExpense extends Model
{
    /** @use HasFactory<ShopPettyCashExpenseFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'business_date',
        'amount',
        'previous_amount',
        'source',
        'created_by',
        'updated_by',
        'amount_changed_by',
        'amount_changed_at',
    ];

    protected $attributes = [
        'source' => 'auto',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'amount' => 'decimal:2',
            'previous_amount' => 'decimal:2',
            'amount_changed_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function amountChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amount_changed_by');
    }

    public function isManual(): bool
    {
        return $this->source === 'manual';
    }
}
