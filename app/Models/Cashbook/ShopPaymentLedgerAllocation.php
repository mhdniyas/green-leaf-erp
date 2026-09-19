<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use Database\Factories\Cashbook\ShopPaymentLedgerAllocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopPaymentLedgerAllocation extends Model
{
    /** @use HasFactory<ShopPaymentLedgerAllocationFactory> */
    use HasFactory;

    protected $fillable = [
        'payment_request_id',
        'shop_id',
        'shop_ledger_transaction_id',
        'amount',
        'status',
        'reconciled_by',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
        'batch_uuid',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(ShopInvoicePaymentRequest::class, 'payment_request_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(ShopLedgerTransaction::class, 'shop_ledger_transaction_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }
}
