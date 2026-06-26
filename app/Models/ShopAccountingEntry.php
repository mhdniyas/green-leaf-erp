<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopAccountingEntry extends Model
{
    protected $fillable = [
        'shop_id',
        'business_date',
        'status',
        'opening_cash',
        'closing_cash',
        'notes',
        'created_by',
        'updated_by',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
        'shop_reply_note',
    ];

    protected $casts = [
        'business_date' => 'date',
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ShopAccountingEntryLine::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'submitted' => 'Pending Approval',
            'approved' => 'Approved',
            'recheck_required' => 'Recheck Required',
            default => str((string) $this->status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'approved' => 'success',
            'recheck_required' => 'danger',
            'submitted' => 'warning',
            default => 'neutral',
        };
    }

    public function canBeEditedByShopOwner(): bool
    {
        return in_array((string) $this->status, ['draft', 'recheck_required'], true);
    }
}
