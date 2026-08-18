<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Cashbook\CompanyPaymentReconciliation;
use Database\Factories\ShopInvoicePaymentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopInvoicePaymentRequest extends Model
{
    /** @use HasFactory<ShopInvoicePaymentRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_invoice_id',
        'shop_id',
        'requested_by',
        'request_type',
        'payment_method',
        'payment_reference',
        'payment_date',
        'admin_verified_amount',
        'cheque_status',
        'cheque_bank_name',
        'cheque_date',
        'requested_amount',
        'approved_amount',
        'applied_amount',
        'credit_amount',
        'status',
        'reconciliation_status',
        'shop_note',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
        'reconciled_amount',
        'floating_amount',
        'shop_advance_amount',
        'last_reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'admin_verified_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'reconciled_amount' => 'decimal:2',
            'floating_amount' => 'decimal:2',
            'shop_advance_amount' => 'decimal:2',
            'payment_date' => 'date',
            'cheque_date' => 'date',
            'reviewed_at' => 'datetime',
            'last_reconciled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ShopInvoice::class, 'shop_invoice_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ShopInvoicePaymentAllocation::class, 'payment_request_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(CompanyPaymentReconciliation::class, 'payment_request_id');
    }

    public function allocatedAmount(): float
    {
        $allocations = $this->relationLoaded('allocations')
            ? $this->allocations
            : $this->allocations()->get(['amount']);

        return round((float) $allocations->sum(fn (ShopInvoicePaymentAllocation $allocation): float => (float) $allocation->amount), 2);
    }

    public function remainingCreditAmount(): float
    {
        if ($this->status !== 'approved') {
            return 0.0;
        }

        return round(max(0, (float) $this->credit_amount), 2);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Approved',
            'partially_reconciled' => 'Partially Reconciled',
            'rejected' => 'Rejected',
            default => 'Pending Approval',
        };
    }

    public function reconciliationStatusLabel(): string
    {
        return match ($this->reconciliation_status) {
            'floating' => 'Floating',
            'partially_reconciled' => 'Partially Reconciled',
            'reconciled' => 'Reconciled',
            'rejected' => 'Rejected',
            default => 'Pending',
        };
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Cash',
            'online_upi' => 'Online UPI',
            'cheque' => 'Cheque',
            default => 'Not set',
        };
    }

    public function chequeStatusLabel(): string
    {
        return match ($this->cheque_status) {
            'pending' => 'Pending',
            'deposited' => 'Deposited',
            'cleared' => 'Cleared',
            'bounced' => 'Bounced',
            default => $this->payment_method === 'cheque' ? 'Pending' : 'Not cheque',
        };
    }

    public function applicationLabel(): string
    {
        return match ($this->request_type) {
            'admin_client_balance' => 'Client Balance',
            'shop_balance' => 'Closing Balance',
            default => 'Bill Pending',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'approved' => 'success',
            'partially_reconciled' => 'warning',
            'rejected' => 'danger',
            default => 'warning',
        };
    }
}
