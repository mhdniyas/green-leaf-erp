<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmployeeAdvanceRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvanceRequest extends Model
{
    /** @use HasFactory<EmployeeAdvanceRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'shop_id',
        'employee_advance_rule_id',
        'payroll_payment_id',
        'shop_staff_payment_id',
        'requested_by',
        'reviewed_by',
        'requested_on',
        'payroll_month',
        'requested_amount',
        'eligible_amount',
        'approved_amount',
        'fund_source',
        'status',
        'rule_snapshot',
        'request_note',
        'review_note',
        'reviewed_at',
    ];

    protected $attributes = [
        'fund_source' => 'petty_cash',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'requested_on' => 'date',
            'payroll_month' => 'date',
            'requested_amount' => 'decimal:2',
            'eligible_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'rule_snapshot' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdvanceRule::class, 'employee_advance_rule_id');
    }

    public function payrollPayment(): BelongsTo
    {
        return $this->belongsTo(PayrollPayment::class);
    }

    public function shopStaffPayment(): BelongsTo
    {
        return $this->belongsTo(ShopStaffPayment::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
