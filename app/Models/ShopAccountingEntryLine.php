<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopAccountingEntryLine extends Model
{
    public const FundingSales = 'sales';

    public const FundingPetty = 'petty';

    public const FundingCompany = 'company';

    public const PayablePending = 'pending';

    public const PayableApproved = 'approved';

    public const PayableRejected = 'rejected';

    public const PayableCancelled = 'cancelled';

    public const SettlementUnsettled = 'unsettled';

    public const SettlementPartial = 'partially_settled';

    public const SettlementSettled = 'settled';

    protected $fillable = [
        'shop_accounting_entry_id',
        'shop_accounting_category_id',
        'type',
        'cash_effect',
        'is_loan_entry',
        'funding_source',
        'company_payable_status',
        'company_payable_amount',
        'company_approved_amount',
        'company_settled_amount',
        'company_approved_by',
        'company_approved_at',
        'company_rejected_by',
        'company_rejected_at',
        'company_rejection_reason',
        'company_settlement_status',
        'amount',
        'description',
        'review_status',
        'review_note',
        'source_type',
        'source_id',
        'source_event',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'company_payable_amount' => 'decimal:2',
        'company_approved_amount' => 'decimal:2',
        'company_settled_amount' => 'decimal:2',
        'cash_effect' => 'boolean',
        'is_loan_entry' => 'boolean',
        'company_approved_at' => 'datetime',
        'company_rejected_at' => 'datetime',
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

    public function remainingCompanyPayableAmount(): float
    {
        $approved = (float) ($this->company_approved_amount ?? $this->company_payable_amount ?? $this->amount);
        $settled = (float) ($this->company_settled_amount ?? 0);

        return round(max(0, $approved - $settled), 2);
    }

    public function refreshSettlementStatus(): void
    {
        $remaining = $this->remainingCompanyPayableAmount();
        $settled = (float) ($this->company_settled_amount ?? 0);

        $this->company_settlement_status = match (true) {
            $settled <= 0.0 => self::SettlementUnsettled,
            $remaining <= 0.0 => self::SettlementSettled,
            default => self::SettlementPartial,
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_rejected_by');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(CompanyPayableSettlement::class, 'shop_accounting_entry_line_id');
    }
}
