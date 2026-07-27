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
                $payload = $this->payrollItemPayload($employee, $periodStart, $periodEnd);
                $grossAmount += (float) $payload['green_leaf_computed_amount'];

                $payrollRun->items()->create($payload);
            }

            $payrollRun->forceFill([
                'gross_amount' => round($grossAmount, 2),
                'net_amount' => round($grossAmount, 2),
            ])->save();

            return $payrollRun->fresh(['items.employee', 'items.category', 'journalEntry']);
        });
    }

    public function ensurePayrollRunItem(Employee $employee, Carbon $periodStart, Carbon $periodEnd, int $userId): PayrollRunItem
    {
        return DB::transaction(function () use ($employee, $periodStart, $periodEnd, $userId): PayrollRunItem {
            $payrollRun = PayrollRun::query()
                ->whereDate('period_start', $periodStart->toDateString())
                ->whereDate('period_end', $periodEnd->toDateString())
                ->first();

            if ($payrollRun === null) {
                $payrollRun = PayrollRun::query()->create([
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'status' => 'draft',
                    'generated_by' => $userId,
                    'gross_amount' => 0,
                    'net_amount' => 0,
                ]);
            }

            $payrollRunItem = PayrollRunItem::query()->firstOrNew([
                'payroll_run_id' => $payrollRun->id,
                'employee_id' => $employee->id,
            ]);

            if ($payrollRunItem->exists && $payrollRun->status !== 'draft') {
                return $payrollRunItem->fresh(['payrollRun', 'employee', 'payments']);
            }

            $existingOverrideAmount = $payrollRunItem->exists ? $payrollRunItem->override_amount : null;
            $payrollRunItem->fill($this->payrollItemPayload($employee->loadMissing('category'), $periodStart, $periodEnd));

            if ($payrollRunItem->exists) {
                $payrollRunItem->override_amount = $existingOverrideAmount;
                $payrollRunItem->final_amount = $existingOverrideAmount ?? $payrollRunItem->computed_amount;
            }

            $payrollRunItem->save();
            $this->refreshRunTotals($payrollRun);

            return $payrollRunItem->fresh(['payrollRun', 'employee', 'payments']);
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
    public function attendanceSummary(Employee $employee, Carbon $periodStart, Carbon $periodEnd, ?int $shopId = null, ?bool $greenLeafOnly = null): array
    {
        /** @var Collection<int, EmployeeAttendance> $attendances */
        $attendances = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', $periodStart->toDateString())
            ->whereDate('attendance_date', '<=', $periodEnd->toDateString())
            ->when($shopId !== null, fn ($query) => $query->where('shop_id', $shopId))
            ->when($greenLeafOnly === true, fn ($query) => $query->whereNull('shop_id'))
            ->when($greenLeafOnly === false, fn ($query) => $query->whereNotNull('shop_id'))
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
        $countMissingAsAbsent = $shopId === null && $greenLeafOnly === null;

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
                if ($countMissingAsAbsent) {
                    $absentDays++;
                }

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

    /**
     * @return array{summary: array{present_days: float, half_days: float, paid_leave_days: float, unpaid_leave_days: float, absent_days: float}, payable_units: float, amount: float}
     */
    public function payableForAttendance(Employee $employee, Carbon $periodStart, Carbon $periodEnd, ?int $shopId = null, ?bool $greenLeafOnly = null): array
    {
        $employee->loadMissing('category');
        $summary = $this->attendanceSummary($employee, $periodStart, $periodEnd, $shopId, $greenLeafOnly);
        $payableUnits = $this->payableUnitsForSummary($summary, $employee);
        $salaryType = in_array((string) $employee->salary_type, ['monthly', 'daily_wage'], true)
            ? (string) $employee->salary_type
            : 'monthly';
        $daysInPeriod = $this->daysInPeriod($periodStart, $periodEnd);
        $amount = $salaryType === 'daily_wage'
            ? round((float) $employee->daily_wage * $payableUnits, 2)
            : round(((float) $employee->monthly_salary / $daysInPeriod) * $payableUnits, 2);

        return [
            'summary' => $summary,
            'payable_units' => $payableUnits,
            'amount' => $amount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollItemPayload(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        $employee->loadMissing('category');

        $summary = $this->attendanceSummary($employee, $periodStart, $periodEnd);
        $greenLeafPayable = $this->payableForAttendance($employee, $periodStart, $periodEnd, greenLeafOnly: true);
        $clientShopPayable = $this->payableForAttendance($employee, $periodStart, $periodEnd, greenLeafOnly: false);
        $greenLeafSummary = $greenLeafPayable['summary'];
        $clientShopSummary = $clientShopPayable['summary'];
        $baseSalary = (float) $employee->monthly_salary;
        $dailyWage = (float) $employee->daily_wage;
        $salaryType = in_array((string) $employee->salary_type, ['monthly', 'daily_wage'], true)
            ? (string) $employee->salary_type
            : 'monthly';
        $payableUnits = round(
            ($summary['present_days'] * (float) $employee->category->present_day_weight)
            + ($summary['half_days'] * (float) $employee->category->half_day_weight)
            + ($summary['paid_leave_days'] * (float) $employee->category->paid_leave_weight)
            + ($summary['unpaid_leave_days'] * (float) $employee->category->excess_leave_weight)
            + ($summary['absent_days'] * (float) $employee->category->absent_day_weight),
            2,
        );
        $greenLeafPayableUnits = $greenLeafPayable['payable_units'];
        $clientShopPayableUnits = $clientShopPayable['payable_units'];
        $daysInPeriod = $this->daysInPeriod($periodStart, $periodEnd);
        $computedAmount = $salaryType === 'daily_wage'
            ? round($dailyWage * $payableUnits, 2)
            : round(($baseSalary / $daysInPeriod) * $payableUnits, 2);
        $greenLeafComputedAmount = $greenLeafPayable['amount'];
        $clientShopComputedAmount = $clientShopPayable['amount'];

        return [
            'employee_id' => $employee->id,
            'employee_category_id' => $employee->employee_category_id,
            'salary_type' => $salaryType,
            'base_salary' => $baseSalary,
            'daily_wage' => $dailyWage,
            'present_days' => $summary['present_days'],
            'half_days' => $summary['half_days'],
            'paid_leave_days' => $summary['paid_leave_days'],
            'unpaid_leave_days' => $summary['unpaid_leave_days'],
            'absent_days' => $summary['absent_days'],
            'payable_units' => $payableUnits,
            'green_leaf_payable_units' => $greenLeafPayableUnits,
            'client_shop_payable_units' => $clientShopPayableUnits,
            'computed_amount' => $computedAmount,
            'green_leaf_computed_amount' => $greenLeafComputedAmount,
            'client_shop_computed_amount' => $clientShopComputedAmount,
            'override_amount' => null,
            'final_amount' => $computedAmount,
            'rule_snapshot' => [
                'salary_type' => $salaryType,
                'daily_wage' => $dailyWage,
                'monthly_paid_leave_limit' => (int) $employee->category->monthly_paid_leave_limit,
                'present_day_weight' => (float) $employee->category->present_day_weight,
                'half_day_weight' => (float) $employee->category->half_day_weight,
                'paid_leave_weight' => (float) $employee->category->paid_leave_weight,
                'excess_leave_weight' => (float) $employee->category->excess_leave_weight,
                'absent_day_weight' => (float) $employee->category->absent_day_weight,
                'green_leaf_summary' => $greenLeafSummary,
                'client_shop_summary' => $clientShopSummary,
            ],
        ];
    }

    /**
     * @param  array{present_days: float, half_days: float, paid_leave_days: float, unpaid_leave_days: float, absent_days: float}  $summary
     */
    private function payableUnitsForSummary(array $summary, Employee $employee): float
    {
        return round(
            ($summary['present_days'] * (float) $employee->category->present_day_weight)
            + ($summary['half_days'] * (float) $employee->category->half_day_weight)
            + ($summary['paid_leave_days'] * (float) $employee->category->paid_leave_weight)
            + ($summary['unpaid_leave_days'] * (float) $employee->category->excess_leave_weight)
            + ($summary['absent_days'] * (float) $employee->category->absent_day_weight),
            2,
        );
    }

    private function refreshRunTotals(PayrollRun $payrollRun): void
    {
        $payrollRun->loadMissing('items');

        $totalAmount = round((float) $payrollRun->items->sum(fn (PayrollRunItem $item): float => $item->greenLeafPayableAmount()), 2);

        $payrollRun->forceFill([
            'gross_amount' => $totalAmount,
            'net_amount' => $totalAmount,
        ])->save();
    }

    private function daysInPeriod(Carbon $periodStart, Carbon $periodEnd): int
    {
        return max(1, (int) $periodStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay()) + 1);
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
