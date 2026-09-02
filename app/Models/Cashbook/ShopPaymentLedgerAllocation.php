<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use Database\Factories\Cashbook\ShopPaymentLedgerAllocationFactory;
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
        'reconciled_by',
        'batch_uuid',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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
}
