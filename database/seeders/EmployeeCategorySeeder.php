<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EmployeeCategory;
use App\Models\EmployeeCategoryLeaveRule;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class EmployeeCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Office Staff',
                'code' => 'office',
                'staff_area' => 'office',
                'default_monthly_salary' => 24000,
                'monthly_paid_leave_limit' => 4,
            ],
            [
                'name' => 'Direct Board',
                'code' => 'direct-board',
                'staff_area' => 'office',
                'default_monthly_salary' => 60000,
                'monthly_paid_leave_limit' => 6,
            ],
            [
                'name' => 'Shop Employees',
                'code' => 'other-shop',
                'staff_area' => 'shop',
                'default_monthly_salary' => 18000,
                'monthly_paid_leave_limit' => 4,
            ],
        ];

        foreach ($categories as $category) {
            $employeeCategory = EmployeeCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                $category,
            );

            $leaveTypes = collect([
                [
                    'name' => 'Paid Leave',
                    'code' => LeaveType::CODE_PAID,
                    'is_paid' => true,
                    'carry_forward_allowed' => true,
                    'default_expiry_months' => null,
                    'annual_entitlement' => (float) $category['monthly_paid_leave_limit'] * 12,
                    'monthly_accrual_amount' => (float) $category['monthly_paid_leave_limit'],
                    'payroll_weight' => 1,
                ],
                [
                    'name' => 'Casual Leave',
                    'code' => LeaveType::CODE_CASUAL,
                    'is_paid' => true,
                    'carry_forward_allowed' => false,
                    'default_expiry_months' => null,
                    'annual_entitlement' => 0,
                    'monthly_accrual_amount' => 0,
                    'payroll_weight' => 1,
                ],
                [
                    'name' => 'Sick Leave',
                    'code' => LeaveType::CODE_SICK,
                    'is_paid' => true,
                    'carry_forward_allowed' => false,
                    'default_expiry_months' => null,
                    'annual_entitlement' => 0,
                    'monthly_accrual_amount' => 0,
                    'payroll_weight' => 1,
                ],
                [
                    'name' => 'Unpaid Leave',
                    'code' => LeaveType::CODE_UNPAID,
                    'is_paid' => false,
                    'carry_forward_allowed' => false,
                    'default_expiry_months' => null,
                    'annual_entitlement' => 0,
                    'monthly_accrual_amount' => 0,
                    'payroll_weight' => 0,
                ],
                [
                    'name' => 'Other',
                    'code' => LeaveType::CODE_OTHER,
                    'is_paid' => false,
                    'carry_forward_allowed' => false,
                    'default_expiry_months' => 12,
                    'annual_entitlement' => 0,
                    'monthly_accrual_amount' => 0,
                    'payroll_weight' => 0,
                ],
            ]);

            $leaveTypes->each(function (array $leaveTypeConfig) use ($employeeCategory): void {
                $leaveType = LeaveType::query()->updateOrCreate(
                    ['code' => $leaveTypeConfig['code']],
                    [
                        'name' => $leaveTypeConfig['name'],
                        'is_paid' => $leaveTypeConfig['is_paid'],
                        'is_active' => true,
                        'carry_forward_allowed' => $leaveTypeConfig['carry_forward_allowed'],
                        'default_expiry_months' => $leaveTypeConfig['default_expiry_months'],
                    ],
                );

                EmployeeCategoryLeaveRule::query()->updateOrCreate(
                    [
                        'employee_category_id' => $employeeCategory->id,
                        'leave_type_id' => $leaveType->id,
                    ],
                    [
                        'annual_entitlement' => $leaveTypeConfig['annual_entitlement'],
                        'monthly_accrual_amount' => $leaveTypeConfig['monthly_accrual_amount'] > 0 ? $leaveTypeConfig['monthly_accrual_amount'] : null,
                        'allocation_frequency' => $leaveTypeConfig['monthly_accrual_amount'] > 0 ? 'monthly' : 'annual_opening',
                        'carry_forward_allowed' => $leaveTypeConfig['carry_forward_allowed'],
                        'maximum_carry_forward_days' => $leaveTypeConfig['carry_forward_allowed'] ? $leaveTypeConfig['annual_entitlement'] : 0,
                        'carry_forward_expiry_months' => $leaveTypeConfig['default_expiry_months'],
                        'payroll_weight' => $leaveTypeConfig['payroll_weight'],
                        'negative_balance_allowed' => $leaveTypeConfig['code'] === LeaveType::CODE_UNPAID,
                        'notes' => 'Seeded default leave rule.',
                    ],
                );
            });
        }
    }
}
