<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    public const CODE_PAID = 'paid-leave';

    public const CODE_CASUAL = 'casual-leave';

    public const CODE_SICK = 'sick-leave';

    public const CODE_UNPAID = 'unpaid-leave';

    public const CODE_OTHER = 'other';

    protected $fillable = [
        'name',
        'code',
        'is_paid',
        'is_active',
        'carry_forward_allowed',
        'default_expiry_months',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'carry_forward_allowed' => 'boolean',
            'default_expiry_months' => 'integer',
        ];
    }

    public function categoryRules(): HasMany
    {
        return $this->hasMany(EmployeeCategoryLeaveRule::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(EmployeeLeaveRequest::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(EmployeeLeaveLedgerEntry::class);
    }
}
