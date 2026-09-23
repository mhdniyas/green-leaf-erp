<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopPaymentCompanyPayableMatch extends Model
{
    protected $table = 'shop_payment_company_payable_matches';

    protected $fillable = [
        'shop_id',
        'payment_request_id',
        'payable_business_date',
        'amount',
        'status',
        'matched_by',
        'matched_at',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'payable_business_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'matched_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(ShopInvoicePaymentRequest::class, 'payment_request_id');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
