<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmployeeLeaveLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveLedgerEntry extends Model
{
    /** @use HasFactory<EmployeeLeaveLedgerEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'employee_category_leave_rule_id',
        'financial_year_start',
        'transaction_date',
        'entry_type',
        'credit',
        'debit',
        'source_type',
        'source_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'financial_year_start' => 'date',
            'transaction_date' => 'date',
            'credit' => 'decimal:2',
            'debit' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leaveRule(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategoryLeaveRule::class, 'employee_category_leave_rule_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
