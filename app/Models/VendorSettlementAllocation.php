<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSettlementAllocation extends Model
{
    protected $attributes = [
        'is_reversed' => false,
    ];

    protected $fillable = [
        'vendor_settlement_id',
        'purchase_invoice_id',
        'cash_allocated',
        'advance_allocated',
        'discount_allocated',
        'total_settled',
        'is_reversed',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'cash_allocated' => 'decimal:2',
            'advance_allocated' => 'decimal:2',
            'discount_allocated' => 'decimal:2',
            'total_settled' => 'decimal:2',
            'is_reversed' => 'boolean',
            'reversed_at' => 'datetime',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(VendorSettlement::class, 'vendor_settlement_id');
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * Scope query to only active (non-reversed) allocations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_reversed', false);
    }

    /**
     * Scope query to reversed allocations.
     */
    public function scopeReversed(Builder $query): Builder
    {
        return $query->where('is_reversed', true);
    }

    /**
     * Soft reverse this allocation auditably.
     */
    public function reverse(User|int|null $user, string $reason): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        $this->update([
            'is_reversed' => true,
            'reversed_at' => now(),
            'reversed_by' => $userId,
            'reversal_reason' => $reason,
        ]);
    }
}
