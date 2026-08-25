<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use Database\Factories\PayrollPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use RuntimeException;

class PayrollPayment extends Model
{
    /** @use HasFactory<PayrollPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'payroll_run_item_id',
        'employee_id',
        'shop_id',
        'journal_entry_id',
        'company_account_id',
        'employee_advance_request_id',
        'request_uuid',
        'reference',
        'paid_by',
        'paid_on',
        'amount',
        'payment_method',
        'payment_type',
        'fund_source',
        'notes',
    ];

    protected $attributes = [
        'payment_method' => 'cash',
        'payment_type' => 'partial',
        'fund_source' => 'company_cash',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $payment): void {
            if (! $payment->isDirty(['amount', 'company_account_id', 'payment_method', 'paid_on', 'reference'])) {
                return;
            }

            if ($payment->cashbookMovement()->where('is_finalized', true)->exists()) {
                throw new RuntimeException('Finalized payroll payments cannot change amount, company account, method, date, or reference.');
            }
        });
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function payrollRunItem(): BelongsTo
    {
        return $this->belongsTo(PayrollRunItem::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function advanceRequest(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdvanceRequest::class, 'employee_advance_request_id');
    }

    public function cashbookMovement(): MorphOne
    {
        return $this->morphOne(CompanyAccountStatementEntry::class, 'sourceRecord', 'source_type', 'source_id');
    }
}
