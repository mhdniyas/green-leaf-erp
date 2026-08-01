<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaserBillUpdateRequest extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'purchaser_cart_id',
        'requested_by',
        'reviewed_by',
        'status',
        'current_business_date',
        'requested_business_date',
        'reason',
        'review_note',
        'reviewed_at',
        'expires_at',
    ];

    protected $casts = [
        'current_business_date' => 'date',
        'requested_business_date' => 'date',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(PurchaserCart::class, 'purchaser_cart_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isActiveApproval(): bool
    {
        return $this->status === 'approved'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
