<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PayrollRunItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRunItem extends Model
{
    /** @use HasFactory<PayrollRunItemFactory> */
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'employee_category_id',
        'salary_type',
        'base_salary',
        'daily_wage',
        'present_days',
        'half_days',
        'paid_leave_days',
        'unpaid_leave_days',
        'absent_days',
        'payable_units',
        'green_leaf_payable_units',
        'client_shop_payable_units',
        'computed_amount',
        'green_leaf_computed_amount',
        'client_shop_computed_amount',
        'override_amount',
        'final_amount',
        'rule_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'daily_wage' => 'decimal:2',
            'present_days' => 'decimal:2',
            'half_days' => 'decimal:2',
            'paid_leave_days' => 'decimal:2',
            'unpaid_leave_days' => 'decimal:2',
            'absent_days' => 'decimal:2',
            'payable_units' => 'decimal:2',
            'green_leaf_payable_units' => 'decimal:2',
            'client_shop_payable_units' => 'decimal:2',
            'computed_amount' => 'decimal:2',
            'green_leaf_computed_amount' => 'decimal:2',
            'client_shop_computed_amount' => 'decimal:2',
            'override_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'rule_snapshot' => 'array',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class, 'employee_category_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    public function shopStaffPayments(): HasMany
    {
        return $this->hasMany(ShopStaffPayment::class);
    }

    public function paidAmount(): float
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();
        $shopStaffPayments = $this->relationLoaded('shopStaffPayments') ? $this->shopStaffPayments : $this->shopStaffPayments()->get();

        return round((float) $payments->sum('amount') + (float) $shopStaffPayments->sum('amount'), 2);
    }

    public function officePaidAmount(): float
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();

        return round((float) $payments->sum('amount'), 2);
    }

    public function shopPaidAmount(): float
    {
        $shopStaffPayments = $this->relationLoaded('shopStaffPayments') ? $this->shopStaffPayments : $this->shopStaffPayments()->get();

        return round((float) $shopStaffPayments->sum('amount'), 2);
    }

    public function remainingAmount(): float
    {
        return round(max(0, (float) $this->final_amount - $this->paidAmount()), 2);
    }

    public function greenLeafPayableAmount(): float
    {
        if ($this->override_amount !== null) {
            return round(max(0, (float) $this->final_amount - (float) $this->client_shop_computed_amount), 2);
        }

        return round((float) $this->green_leaf_computed_amount, 2);
    }

    public function clientShopPayableAmount(): float
    {
        return round((float) $this->client_shop_computed_amount, 2);
    }

    public function remainingGreenLeafAmount(): float
    {
        return round(max(0, $this->greenLeafPayableAmount() - $this->officePaidAmount()), 2);
    }

    public function remainingClientShopAmount(?int $shopId = null): float
    {
        $payments = $this->relationLoaded('shopStaffPayments') ? $this->shopStaffPayments : $this->shopStaffPayments()->get();

        if ($shopId !== null) {
            $payments = $payments->where('shop_id', $shopId);
        }

        return round(max(0, $this->clientShopPayableAmount() - (float) $payments->sum('amount')), 2);
    }
}
