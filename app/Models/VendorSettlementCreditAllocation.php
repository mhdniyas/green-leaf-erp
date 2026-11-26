<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSettlementCreditAllocation extends Model
{
    protected $attributes = [
        'is_reversed' => false,
    ];

    protected $fillable = [
        'vendor_settlement_id',
        'purchase_credit_note_id',
        'purchase_invoice_id',
        'amount',
        'is_reversed',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'vendor_settlement_id' => 'integer',
            'purchase_credit_note_id' => 'integer',
            'purchase_invoice_id' => 'integer',
            'amount' => 'decimal:2',
            'is_reversed' => 'boolean',
            'reversed_at' => 'datetime',
            'reversed_by' => 'integer',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(VendorSettlement::class, 'vendor_settlement_id');
    }

    public function purchaseCreditNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseCreditNote::class, 'purchase_credit_note_id');
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_reversed', false);
    }

    public function scopeReversed(Builder $query): Builder
    {
        return $query->where('is_reversed', true);
    }

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
