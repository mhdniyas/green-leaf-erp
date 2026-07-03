<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_code',
        'user_id',
        'default_shop_id',
        'employee_category_id',
        'name',
        'phone',
        'email',
        'staff_area',
        'employment_status',
        'joined_on',
        'monthly_salary',
        'is_user_linked',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'monthly_salary' => 'decimal:2',
            'is_user_linked' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'employee_code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'default_shop_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class, 'employee_category_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(EmployeeLeaveRequest::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function assignedShops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_employee_assignments')
            ->withTimestamps();
    }

    public function shopAssignments(): HasMany
    {
        return $this->hasMany(ShopEmployeeAssignment::class);
    }
}
