<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\DTOs\Finance\JournalEntryData;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Services\Finance\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    public function generate(Carbon $periodStart, Carbon $periodEnd, int $userId): PayrollRun
    {
        return DB::transaction(function () use ($periodStart, $periodEnd, $userId): PayrollRun {
            $payrollRun = PayrollRun::query()->updateOrCreate(
                [
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ],
                [
                    'status' => 'finalized',
                    'generated_by' => $userId,
                    'finalized_by' => $userId,
                    'finalized_at' => now(),
                ],
            );

            if ($payrollRun->journalEntry()->exists()) {
                $payrollRun->journalEntry()->delete();
            }

            $payrollRun->items()->delete();

            $employees = Employee::query()
                ->with('category')
                ->where('employment_status', 'active')
                ->get();

            $grossAmount = 0.0;

            foreach ($employees as $employee) {
                $summary = $this->attendanceSummary($employee, $periodStart, $periodEnd);
                $baseSalary = (float) $employee->monthly_salary;
                $payableUnits = round(
                    ($summary['present_days'] * (float) $employee->category->present_day_weight)
                    + ($summary['half_days'] * (float) $employee->category->half_day_weight)
                    + ($summary['paid_leave_days'] * (float) $employee->category->paid_leave_weight)
                    + ($summary['unpaid_leave_days'] * (float) $employee->category->excess_leave_weight)
                    + ($summary['absent_days'] * (float) $employee->category->absent_day_weight),
                    2,
                );
                $daysInPeriod = max(1, $periodStart->diffInDays($periodEnd) + 1);
                $computedAmount = round(($baseSalary / $daysInPeriod) * $payableUnits, 2);
                $grossAmount += $computedAmount;

                $payrollRun->items()->create([
                    'employee_id' => $employee->id,
                    'employee_category_id' => $employee->employee_category_id,
                    'base_salary' => $baseSalary,
                    'present_days' => $summary['present_days'],
                    'half_days' => $summary['half_days'],
                    'paid_leave_days' => $summary['paid_leave_days'],
                    'unpaid_leave_days' => $summary['unpaid_leave_days'],
                    'absent_days' => $summary['absent_days'],
                    'payable_units' => $payableUnits,
                    'computed_amount' => $computedAmount,
                    'final_amount' => $computedAmount,
                    'rule_snapshot' => [
                        'monthly_paid_leave_limit' => (int) $employee->category->monthly_paid_leave_limit,
                        'present_day_weight' => (float) $employee->category->present_day_weight,
                        'half_day_weight' => (float) $employee->category->half_day_weight,
                        'paid_leave_weight' => (float) $employee->category->paid_leave_weight,
                        'excess_leave_weight' => (float) $employee->category->excess_leave_weight,
                        'absent_day_weight' => (float) $employee->category->absent_day_weight,
                    ],
                ]);
            }

            $payrollRun->forceFill([
                'gross_amount' => round($grossAmount, 2),
                'net_amount' => round($grossAmount, 2),
            ])->save();

            $journalEntry = $grossAmount > 0 ? $this->recordPayrollExpense($payrollRun, $userId) : null;

            $payrollRun->forceFill([
                'journal_entry_id' => $journalEntry?->id,
            ])->save();

            return $payrollRun->fresh(['items.employee', 'items.category', 'journalEntry']);
        });
    }

    /**
     * @return array{present_days: float, half_days: float, paid_leave_days: float, unpaid_leave_days: float, absent_days: float}
     */
    public function attendanceSummary(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        /** @var Collection<int, EmployeeAttendance> $attendances */
        $attendances = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', $periodStart->toDateString())
            ->whereDate('attendance_date', '<=', $periodEnd->toDateString())
            ->get();

        $leaveCount = (int) $attendances->where('status', 'leave')->count();
        $paidLeaveLimit = max(0, (int) $employee->category->monthly_paid_leave_limit);
        $paidLeaveDays = min($leaveCount, $paidLeaveLimit);
        $unpaidLeaveDays = max(0, $leaveCount - $paidLeaveDays);

        return [
            'present_days' => (float) $attendances->where('status', 'present')->count(),
            'half_days' => (float) $attendances->where('status', 'half_day')->count(),
            'paid_leave_days' => (float) $paidLeaveDays,
            'unpaid_leave_days' => (float) $unpaidLeaveDays,
            'absent_days' => (float) $attendances->where('status', 'absent')->count(),
        ];
    }

    private function recordPayrollExpense(PayrollRun $payrollRun, int $userId): JournalEntry
    {
        $salaryExpenseAccount = Account::query()->where('code', '5700')->first();
        $bankAccount = Account::query()->where('code', '1020')->first();

        if (! $salaryExpenseAccount || ! $bankAccount) {
            throw new RuntimeException('Payroll expense accounts are missing. Seed the chart of accounts first.');
        }

        return $this->journalService->createEntry(
            new JournalEntryData(
                entryDate: $payrollRun->period_end->format('Y-m-d'),
                reference: sprintf('PAYROLL-%s-%s', $payrollRun->period_start->format('Ymd'), $payrollRun->period_end->format('Ymd')),
                description: 'Payroll finalized for '.$payrollRun->period_start->format('F Y'),
                lines: [
                    [
                        'account_id' => (int) $salaryExpenseAccount->id,
                        'type' => 'debit',
                        'amount' => (float) $payrollRun->gross_amount,
                    ],
                    [
                        'account_id' => (int) $bankAccount->id,
                        'type' => 'credit',
                        'amount' => (float) $payrollRun->gross_amount,
                    ],
                ],
            ),
            $userId,
        );
    }
}
