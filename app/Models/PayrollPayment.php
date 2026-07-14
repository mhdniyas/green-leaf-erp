<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PayrollPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayment extends Model
{
    /** @use HasFactory<PayrollPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'payroll_run_item_id',
        'employee_id',
        'journal_entry_id',
        'paid_by',
        'paid_on',
        'amount',
        'payment_method',
        'payment_type',
        'notes',
    ];

    protected $attributes = [
        'payment_method' => 'cash',
        'payment_type' => 'partial',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
        ];
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

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
