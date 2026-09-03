<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\ShopEmployeeAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupLegacyStaffCommand extends Command
{
    protected $signature = 'hr:cleanup-legacy-staff {--dry-run : Execute dry run audit without modifying database}';

    protected $description = 'Audits and safely removes old generated USR-* user employees and STAFF-* dummy shop staff';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('=====================================================');
        $this->info($dryRun ? '--- HR CLEANUP LEGACY STAFF (DRY RUN AUDIT MODE) ---' : '--- HR CLEANUP LEGACY STAFF (EXECUTION MODE) ---');
        $this->info('=====================================================');

        $usersBefore = User::query()->count();
        $employeesBefore = Employee::query()->count();
        $usrEmployeesBefore = Employee::query()->where('employee_code', 'like', 'USR-%')->count();
        $staffEmployeesBefore = Employee::query()->where('employee_code', 'like', 'STAFF-%')->count();
        $attendanceBefore = EmployeeAttendance::query()->count();
        $assignmentsBefore = ShopEmployeeAssignment::query()->count();
        $payrollItemsBefore = DB::table('payroll_run_items')->count();
        $payrollPaymentsBefore = DB::table('payroll_payments')->count();

        $this->line(sprintf('Users Total (MUST REMAIN UNCHANGED): %d', $usersBefore));
        $this->line(sprintf('Employees Total:                     %d', $employeesBefore));
        $this->line(sprintf(' - USR-* Employees:                  %d', $usrEmployeesBefore));
        $this->line(sprintf(' - STAFF-* Employees:                %d', $staffEmployeesBefore));
        $this->line(sprintf('Attendance Records:                  %d', $attendanceBefore));
        $this->line(sprintf('Shop Employee Assignments:          %d', $assignmentsBefore));
        $this->line(sprintf('Payroll Items:                       %d', $payrollItemsBefore));
        $this->line(sprintf('Payroll Payments:                    %d', $payrollPaymentsBefore));
        $this->line('');

        $candidates = Employee::query()
            ->with(['user', 'defaultShop'])
            ->withCount([
                'attendances',
                'payrollItems',
                'payrollPayments',
                'shopStaffPayments',
                'leaveRequests',
                'advanceRequests',
                'shopAssignments',
                'leaveLedgerEntries',
                'hrOverrides',
            ])
            ->where(function ($q): void {
                $q->where('employee_code', 'like', 'USR-%')
                    ->orWhere('employee_code', 'like', 'STAFF-%')
                    ->orWhere('email', 'like', 'staff-%@greenleaf.local')
                    ->orWhere('name', 'like', '%Staff 1%')
                    ->orWhere('name', 'like', '%Staff 2%')
                    ->orWhere('name', 'like', '%Staff 3%');
            })
            ->orderBy('id')
            ->get();

        $rows = [];
        $toDeleteUsr = collect();
        $toDeleteStaff = collect();
        $toKeep = collect();

        foreach ($candidates as $emp) {
            $isUsrCode = str_starts_with($emp->employee_code, 'USR-');
            $isStaffCode = str_starts_with($emp->employee_code, 'STAFF-')
                || str_contains($emp->email ?? '', 'staff-')
                || str_contains(strtolower($emp->name), 'staff ');

            $hasRealHistory = ($emp->payroll_items_count > 0)
                || ($emp->payroll_payments_count > 0)
                || ($emp->shop_staff_payments_count > 0)
                || ($emp->leave_requests_count > 0)
                || ($emp->advance_requests_count > 0);

            if ($isUsrCode) {
                if (! $hasRealHistory) {
                    $action = 'DELETE GENERATED USER EMPLOYEE';
                    $toDeleteUsr->push($emp);
                } else {
                    $action = 'MANUAL REVIEW - HAS REAL HR HISTORY';
                    $toKeep->push($emp);
                }
            } elseif ($isStaffCode) {
                if (! $hasRealHistory) {
                    $action = 'DELETE DUMMY STAFF';
                    $toDeleteStaff->push($emp);
                } else {
                    $action = 'MANUAL REVIEW - HAS REAL HR HISTORY';
                    $toKeep->push($emp);
                }
            } else {
                $action = 'MANUAL REVIEW';
                $toKeep->push($emp);
            }

            $salaryText = $emp->salary_type === 'daily_wage'
                ? 'Daily: Rs. '.number_format((float) $emp->daily_wage, 0)
                : 'Monthly: Rs. '.number_format((float) $emp->monthly_salary, 0);

            $rows[] = [
                'ID' => $emp->id,
                'Code' => $emp->employee_code,
                'Name' => str($emp->name)->limit(22)->toString(),
                'User' => $emp->user?->email ? str($emp->user->email)->limit(22)->toString() : 'NONE',
                'Shop' => $emp->defaultShop?->name ? str($emp->defaultShop->name)->limit(15)->toString() : 'NONE',
                'Salary' => $salaryText,
                'Att' => $emp->attendances_count,
                'PayItems' => $emp->payroll_items_count,
                'Leave' => $emp->leave_requests_count,
                'Action' => $action,
            ];
        }

        $this->table(
            ['ID', 'Code', 'Name', 'User', 'Shop', 'Salary', 'Att', 'PayItems', 'Leave', 'Action'],
            $rows
        );

        $this->info('');
        $this->info(sprintf('Summary of Classification:'));
        $this->info(sprintf(' - USR-* Employees to Delete:    %d', $toDeleteUsr->count()));
        $this->info(sprintf(' - STAFF-* Employees to Delete:  %d', $toDeleteStaff->count()));
        $this->info(sprintf(' - Manual Review (Kept):          %d', $toKeep->count()));
        $this->info('');

        if ($dryRun) {
            $this->warn('DRY RUN COMPLETE. No records were modified in the database.');

            return 0;
        }

        $allToDelete = $toDeleteUsr->merge($toDeleteStaff);

        if ($allToDelete->isEmpty()) {
            $this->info('No generated legacy staff records found to delete.');

            return 0;
        }

        DB::transaction(function () use ($allToDelete): void {
            foreach ($allToDelete as $emp) {
                // Delete child dummy relationships safely across all 9 FK tables
                DB::table('employee_attendances')->where('employee_id', $emp->id)->delete();
                DB::table('shop_employee_assignments')->where('employee_id', $emp->id)->delete();
                DB::table('payroll_run_items')->where('employee_id', $emp->id)->delete();
                DB::table('payroll_payments')->where('employee_id', $emp->id)->delete();
                DB::table('shop_staff_payments')->where('employee_id', $emp->id)->delete();
                DB::table('employee_leave_requests')->where('employee_id', $emp->id)->delete();
                DB::table('employee_leave_ledger_entries')->where('employee_id', $emp->id)->delete();
                DB::table('employee_advance_requests')->where('employee_id', $emp->id)->delete();
                DB::table('hr_overrides')->where('employee_id', $emp->id)->delete();

                // Delete the Employee row (User account is untouched!)
                $emp->delete();
            }
        });

        $usersAfter = User::query()->count();
        $employeesAfter = Employee::query()->count();
        $usrEmployeesAfter = Employee::query()->where('employee_code', 'like', 'USR-%')->count();
        $staffEmployeesAfter = Employee::query()->where('employee_code', 'like', 'STAFF-%')->count();
        $attendanceAfter = EmployeeAttendance::query()->count();
        $assignmentsAfter = ShopEmployeeAssignment::query()->count();
        $payrollItemsAfter = DB::table('payroll_run_items')->count();
        $payrollPaymentsAfter = DB::table('payroll_payments')->count();

        $this->info('=====================================================');
        $this->info('--- CLEANUP EXECUTION COMPLETED SUCCESSFULLY ---');
        $this->info('=====================================================');
        $this->line(sprintf('Users Total (UNCHANGED):          %d -> %d', $usersBefore, $usersAfter));
        $this->line(sprintf('Employees Total:                   %d -> %d', $employeesBefore, $employeesAfter));
        $this->line(sprintf(' - USR-* Employees:                %d -> %d', $usrEmployeesBefore, $usrEmployeesAfter));
        $this->line(sprintf(' - STAFF-* Employees:              %d -> %d', $staffEmployeesBefore, $staffEmployeesAfter));
        $this->line(sprintf('Attendance Records:                %d -> %d', $attendanceBefore, $attendanceAfter));
        $this->line(sprintf('Shop Assignments:                  %d -> %d', $assignmentsBefore, $assignmentsAfter));
        $this->line(sprintf('Payroll Items:                     %d -> %d', $payrollItemsBefore, $payrollItemsAfter));
        $this->line(sprintf('Payroll Payments:                  %d -> %d', $payrollPaymentsBefore, $payrollPaymentsAfter));

        return 0;
    }
}
