<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmployeeCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeCategory extends Model
{
    /** @use HasFactory<EmployeeCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'staff_area',
        'default_monthly_salary',
        'monthly_paid_leave_limit',
        'present_day_weight',
        'half_day_weight',
        'paid_leave_weight',
        'excess_leave_weight',
        'absent_day_weight',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'default_monthly_salary' => 'decimal:2',
            'monthly_paid_leave_limit' => 'integer',
            'present_day_weight' => 'decimal:2',
            'half_day_weight' => 'decimal:2',
            'paid_leave_weight' => 'decimal:2',
            'excess_leave_weight' => 'decimal:2',
            'absent_day_weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
