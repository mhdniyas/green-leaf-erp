<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseFinanceAccountingOpening extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchaser_id',
        'supplier_id',
        'account_scope',
        'accounting_start_date',
        'opening_balance',
        'opening_balance_direction',
        'opening_credit_balance',
        'opening_credit_outstanding',
        'opening_advance_credit',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'accounting_start_date' => 'date',
            'opening_balance' => 'decimal:2',
            'opening_credit_balance' => 'decimal:2',
            'opening_credit_outstanding' => 'decimal:2',
            'opening_advance_credit' => 'decimal:2',
        ];
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get signed opening balance:
     * Positive if purchaser holds company cash / vendor owes company.
     * Negative if company owes purchaser / company owes vendor.
     */
    public function getSignedOpeningBalance(): float
    {
        $amount = abs((float) $this->opening_balance);

        return match ($this->opening_balance_direction) {
            'company_owes_purchaser', 'company_owes_vendor' => -$amount,
            'purchaser_holds_company_cash', 'vendor_owes_company' => $amount,
            default => 0.0,
        };
    }
}
