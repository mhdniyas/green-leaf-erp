<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopStaffPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShopStaffPayment extends Model
{
    /** @use HasFactory<ShopStaffPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'request_uuid',
        'payroll_run_id',
        'payroll_run_item_id',
        'employee_id',
        'shop_id',
        'employee_advance_request_id',
        'paid_by',
        'paid_on',
        'amount',
        'payment_type',
        'fund_source',
        'status',
        'notes',
    ];

    protected $attributes = [
        'payment_type' => 'salary',
        'fund_source' => 'petty_cash',
        'status' => 'paid',
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

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function advanceRequest(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdvanceRequest::class, 'employee_advance_request_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function cashbookLine(): HasOne
    {
        return $this->hasOne(ShopAccountingEntryLine::class, 'source_id')
            ->where('source_type', self::class);
    }
}
