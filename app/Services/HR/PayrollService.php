<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\DTOs\Finance\JournalEntryData;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeaveRequest;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use App\Services\Finance\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly HrOverrideService $hrOverrideService,
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
                    'status' => 'draft',
                    'generated_by' => $userId,
                    'finalized_by' => null,
                    'finalized_at' => null,
                    'journal_entry_id' => null,
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
                    'override_amount' => null,
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

            return $payrollRun->fresh(['items.employee', 'items.category', 'journalEntry']);
        });
    }

    public function updateOverride(PayrollRunItem $payrollRunItem, ?float $overrideAmount, User $actor, string $reason): PayrollRunItem
    {
        $overrideAmount = $overrideAmount !== null ? round($overrideAmount, 2) : null;
        $oldValues = [
            'override_amount' => $payrollRunItem->override_amount !== null ? (float) $payrollRunItem->override_amount : null,
            'final_amount' => (float) $payrollRunItem->final_amount,
        ];

        $payrollRunItem->forceFill([
            'override_amount' => $overrideAmount,
            'final_amount' => $overrideAmount ?? (float) $payrollRunItem->computed_amount,
        ])->save();

        $this->hrOverrideService->record(
            'payroll_item',
            $payrollRunItem->employee,
            $payrollRunItem,
            $oldValues,
            [
                'override_amount' => $overrideAmount,
                'final_amount' => (float) $payrollRunItem->final_amount,
            ],
            $reason,
            $actor,
        );

        $this->refreshRunTotals($payrollRunItem->payrollRun);

        return $payrollRunItem->fresh(['employee', 'category', 'payrollRun']);
    }

    public function finalize(PayrollRun $payrollRun, int $userId): PayrollRun
    {
        return DB::transaction(function () use ($payrollRun, $userId): PayrollRun {
            $payrollRun->loadMissing('items');

            if ($payrollRun->journalEntry()->exists()) {
                $payrollRun->journalEntry()->delete();
            }

            $this->refreshRunTotals($payrollRun);

            $journalEntry = (float) $payrollRun->gross_amount > 0
                ? $this->recordPayrollExpense($payrollRun, $userId)
                : null;

            $payrollRun->forceFill([
                'status' => 'finalized',
                'finalized_by' => $userId,
                'finalized_at' => now(),
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
        $approvedLeaveRequests = EmployeeLeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->get();

        $attendanceByDate = $attendances->keyBy(fn (EmployeeAttendance $attendance): string => $attendance->attendance_date->toDateString());
        $approvedLeaveByDate = [];

        foreach ($approvedLeaveRequests as $leaveRequest) {
            $cursor = $leaveRequest->start_date->copy();

            while ($cursor->lte($leaveRequest->end_date)) {
                $approvedLeaveByDate[$cursor->toDateString()] = $leaveRequest;
                $cursor->addDay();
            }
        }

        $presentDays = 0.0;
        $halfDays = 0.0;
        $absentDays = 0.0;
        $approvedPaidLikeLeaveCount = 0.0;
        $unpaidLeaveDays = 0.0;
        $cursor = $periodStart->copy();

        while ($cursor->lte($periodEnd)) {
            $attendance = $attendanceByDate->get($cursor->toDateString());

            if ($attendance === null) {
                $absentDays++;
                $cursor->addDay();

                continue;
            }

            match ($attendance->status) {
                'present' => $presentDays++,
                'half_day' => $halfDays++,
                'leave' => $this->accumulateLeaveDay(
                    $attendance,
                    $approvedLeaveByDate[$cursor->toDateString()] ?? null,
                    $approvedPaidLikeLeaveCount,
                    $unpaidLeaveDays,
                    $absentDays,
                ),
                default => $absentDays++,
            };

            $cursor->addDay();
        }

        $paidLeaveLimit = max(0, (int) $employee->category->monthly_paid_leave_limit);
        $paidLeaveDays = min($approvedPaidLikeLeaveCount, $paidLeaveLimit);
        $unpaidLeaveDays += max(0, $approvedPaidLikeLeaveCount - $paidLeaveDays);

        return [
            'present_days' => $presentDays,
            'half_days' => $halfDays,
            'paid_leave_days' => (float) $paidLeaveDays,
            'unpaid_leave_days' => (float) $unpaidLeaveDays,
            'absent_days' => $absentDays,
        ];
    }

    private function refreshRunTotals(PayrollRun $payrollRun): void
    {
        $payrollRun->loadMissing('items');

        $totalAmount = round((float) $payrollRun->items->sum('final_amount'), 2);

        $payrollRun->forceFill([
            'gross_amount' => $totalAmount,
            'net_amount' => $totalAmount,
        ])->save();
    }

    private function recordPayrollExpense(PayrollRun $payrollRun, int $userId): JournalEntry
    {
        $salaryExpenseAccount = Account::query()->firstOrCreate(
            ['code' => '5700'],
            [
                'name' => 'Salaries Expense',
                'type' => 'expense',
                'is_active' => true,
                'parent_id' => null,
            ],
        );
        $bankAccount = Account::query()->firstOrCreate(
            ['code' => '1020'],
            [
                'name' => 'Bank Account',
                'type' => 'asset',
                'is_active' => true,
                'parent_id' => null,
            ],
        );

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

    private function accumulateLeaveDay(
        EmployeeAttendance $attendance,
        ?EmployeeLeaveRequest $approvedLeaveRequest,
        float &$approvedPaidLikeLeaveCount,
        float &$unpaidLeaveDays,
        float &$absentDays,
    ): void {
        if ($approvedLeaveRequest !== null) {
            if ($approvedLeaveRequest->leaveType?->is_paid ?? true) {
                $approvedPaidLikeLeaveCount++;
            } else {
                $unpaidLeaveDays++;
            }

            return;
        }

        if ($attendance->isHrManaged()) {
            $approvedPaidLikeLeaveCount++;

            return;
        }

        $absentDays++;
    }
}
