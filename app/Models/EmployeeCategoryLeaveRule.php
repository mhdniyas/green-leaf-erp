<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmployeeCategoryLeaveRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EmployeeCategoryLeaveRule extends Model
{
    /** @use HasFactory<EmployeeCategoryLeaveRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_category_id',
        'leave_type_id',
        'annual_entitlement',
        'monthly_accrual_amount',
        'allocation_frequency',
        'carry_forward_allowed',
        'maximum_carry_forward_days',
        'carry_forward_expiry_months',
        'carry_forward_expiry_date',
        'payroll_weight',
        'negative_balance_allowed',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'annual_entitlement' => 'decimal:2',
            'monthly_accrual_amount' => 'decimal:2',
            'carry_forward_allowed' => 'boolean',
            'maximum_carry_forward_days' => 'decimal:2',
            'carry_forward_expiry_months' => 'integer',
            'carry_forward_expiry_date' => 'date',
            'payroll_weight' => 'decimal:2',
            'negative_balance_allowed' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class, 'employee_category_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function isActiveOn(Carbon $date): bool
    {
        $effectiveFrom = $this->effective_from;
        $effectiveTo = $this->effective_to;

        if ($effectiveFrom !== null && $date->lt($effectiveFrom->copy()->startOfDay())) {
            return false;
        }

        if ($effectiveTo !== null && $date->gt($effectiveTo->copy()->endOfDay())) {
            return false;
        }

        return true;
    }
}
