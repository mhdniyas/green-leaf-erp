<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopAccountingEntryLine extends Model
{
    protected $fillable = [
        'shop_accounting_entry_id',
        'shop_accounting_category_id',
        'type',
        'amount',
        'description',
        'review_status',
        'review_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reviewStatusLabel(): string
    {
        return match ((string) $this->review_status) {
            'approved' => 'Approved',
            'recheck_required' => 'Needs Recheck',
            default => 'Pending Review',
        };
    }

    public function reviewStatusTone(): string
    {
        return match ((string) $this->review_status) {
            'approved' => 'success',
            'recheck_required' => 'danger',
            default => 'warning',
        };
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ShopAccountingEntry::class, 'shop_accounting_entry_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ShopAccountingCategory::class, 'shop_accounting_category_id');
    }
}
