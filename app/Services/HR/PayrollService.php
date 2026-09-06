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
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PayrollService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly HrOverrideService $hrOverrideService,
    ) {}

    public function generate(Carbon $periodStart, Carbon $periodEnd, int $userId): PayrollRun
    {
        return DB::transaction(function () use ($periodStart, $periodEnd, $userId): PayrollRun {
            $existing = PayrollRun::query()
                ->whereDate('period_start', $periodStart->toDateString())
                ->whereDate('period_end', $periodEnd->toDateString())
                ->first();

            if ($existing && $existing->status === 'finalized') {
                throw new RuntimeException('Finalized payroll runs cannot be regenerated.');
            }

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
                ->approved()
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
            $payrollRun = PayrollRun::query()
                ->whereKey($payrollRun->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payrollRun->status === 'finalized') {
                throw new RuntimeException('Payroll run is already finalized.');
            }

            $items = $payrollRun->items()
                ->with(['employee', 'payments', 'shopStaffPayments'])
                ->lockForUpdate()
                ->get();

            if ($payrollRun->journalEntry()->exists()) {
                $payrollRun->journalEntry()->delete();
            }

            JournalEntry::query()
                ->where('source_type', PayrollRun::class)
                ->where('source_id', $payrollRun->id)
                ->where('source_event', 'advance_clearing')
                ->each(fn (JournalEntry $entry) => $entry->delete());

            $this->refreshRunTotals($payrollRun);

            $totalCompanyAdvancesRecovered = 0.0;

            foreach ($items as $item) {
                // Find prior finalized payroll item's closing recovery
                $prevRunItem = PayrollRunItem::query()
                    ->select('payroll_run_items.*')
                    ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_run_items.payroll_run_id')
                    ->where('payroll_run_items.employee_id', $item->employee_id)
                    ->where('payroll_runs.status', 'finalized')
                    ->whereDate('payroll_runs.period_end', '<', $payrollRun->period_start)
                    ->orderByDesc('payroll_runs.period_end')
                    ->orderByDesc('payroll_run_items.id')
                    ->first();

                if ($prevRunItem !== null) {
                    $employeeName = $item->employee?->name ?? "ID {$item->employee_id}";
                    if ($prevRunItem->closing_recovery_amount === null) {
                        throw ValidationException::withMessages([
                            'payroll_run' => "Cannot finalize payroll: Employee {$employeeName} has an unverified or unknown historical recovery balance that must be resolved by HR before finalization.",
                        ]);
                    }
                    if ((float) $prevRunItem->closing_recovery_amount > 0.0 && $prevRunItem->closing_company_recovery_amount === null) {
                        throw ValidationException::withMessages([
                            'payroll_run' => "Cannot finalize payroll: Employee {$employeeName} has an unverified or unknown company recovery composition that must be resolved by HR before finalization.",
                        ]);
                    }
                }

                $openingRecovery = $prevRunItem !== null ? (float) $prevRunItem->closing_recovery_amount : 0.0;
                $openingCompanyRecovery = $prevRunItem !== null ? (float) ($prevRunItem->closing_company_recovery_amount ?? 0.0) : 0.0;
                $openingShopRecovery = max(0.0, round($openingRecovery - $openingCompanyRecovery, 2));

                $shopAdvances = (float) $item->shopStaffPayments()->where('payment_type', 'advance')->sum('amount');
                $companyAdvances = (float) $item->payments()->where('payment_type', 'advance')->sum('amount');
                $totalAdvances = round($shopAdvances + $companyAdvances, 2);

                $shopSalary = (float) $item->shopStaffPayments()->where('payment_type', 'salary')->sum('amount');
                $companySalary = (float) $item->payments()->where('payment_type', 'salary')->sum('amount');
                $totalSalaryPaid = round($shopSalary + $companySalary, 2);

                $grossPayable = (float) $item->final_amount;

                // Priority 1A: Opening shop recovery
                $openingShopRecovered = min($openingShopRecovery, $grossPayable);
                $salaryRemainingAfterShopOpening = max(0.0, round($grossPayable - $openingShopRecovered, 2));

                // Priority 1B: Opening company recovery (carried from prior months)
                $openingCompanyRecovered = min($openingCompanyRecovery, $salaryRemainingAfterShopOpening);
                $totalOpeningRecovered = round($openingShopRecovered + $openingCompanyRecovered, 2);

                // Priority 2: Current-month shop advances
                $salaryAvailableForShopAdvances = max(0.0, round($grossPayable - $totalOpeningRecovered, 2));
                $shopAdvancesRecovered = min($shopAdvances, $salaryAvailableForShopAdvances);

                // Priority 3: Current-month company advances
                $salaryAvailableForCurrentCompany = max(0.0, round($salaryAvailableForShopAdvances - $shopAdvancesRecovered, 2));
                $currentCompanyRecovered = min($companyAdvances, $salaryAvailableForCurrentCompany);

                // Total company advances recovered from salary this month (carried + current)
                $totalCompanyRecovered = round($openingCompanyRecovered + $currentCompanyRecovered, 2);
                $totalCompanyAdvancesRecovered += $totalCompanyRecovered;

                // Carry forward unapplied company component
                $closingCompanyRecovery = round(
                    ($openingCompanyRecovery - $openingCompanyRecovered) + ($companyAdvances - $currentCompanyRecovered),
                    2
                );

                $signedClosingBalance = round($grossPayable - $openingRecovery - $totalAdvances - $totalSalaryPaid, 2);
                $closingRecovery = $signedClosingBalance < 0 ? round(abs($signedClosingBalance), 2) : 0.0;

                $item->forceFill([
                    'opening_recovery_amount' => $openingRecovery,
                    'closing_recovery_amount' => $closingRecovery,
                    'opening_company_recovery_amount' => $openingCompanyRecovery,
                    'closing_company_recovery_amount' => $closingCompanyRecovery,
                    'rule_snapshot' => array_merge($item->rule_snapshot ?? [], [
                        'opening_recovery_amount' => $openingRecovery,
                        'opening_company_recovery_amount' => $openingCompanyRecovery,
                        'opening_shop_recovery_amount' => $openingShopRecovery,
                        'opening_company_recovered' => $openingCompanyRecovered,
                        'opening_shop_recovered' => $openingShopRecovered,
                        'advances_paid' => $totalAdvances,
                        'company_advances_paid' => $companyAdvances,
                        'company_advances_recovered' => $totalCompanyRecovered,
                        'current_company_recovered' => $currentCompanyRecovered,
                        'shop_advances_paid' => $shopAdvances,
                        'shop_advances_recovered' => $shopAdvancesRecovered,
                        'salary_paid' => $totalSalaryPaid,
                        'signed_closing_balance' => $signedClosingBalance,
                        'closing_recovery_amount' => $closingRecovery,
                        'closing_company_recovery_amount' => $closingCompanyRecovery,
                    ]),
                ])->save();
            }

            $journalEntry = (float) $payrollRun->gross_amount > 0
                ? $this->recordPayrollExpense($payrollRun, $userId)
                : null;

            if ($totalCompanyAdvancesRecovered > 0.0) {
                $this->recordCompanyAdvanceClearing($payrollRun, $totalCompanyAdvancesRecovered, $userId);
            }

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

        $paidLeaveLimit = max(0, (int) ($employee->category?->monthly_paid_leave_limit ?? 0));
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
            ($summary['present_days'] * (float) ($employee->category?->present_day_weight ?? 1.0))
            + ($summary['half_days'] * (float) ($employee->category?->half_day_weight ?? 0.5))
            + ($summary['paid_leave_days'] * (float) ($employee->category?->paid_leave_weight ?? 1.0))
            + ($summary['unpaid_leave_days'] * (float) ($employee->category?->excess_leave_weight ?? 0.0))
            + ($summary['absent_days'] * (float) ($employee->category?->absent_day_weight ?? 0.0)),
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
                'monthly_paid_leave_limit' => (int) ($employee->category?->monthly_paid_leave_limit ?? 0),
                'present_day_weight' => (float) ($employee->category?->present_day_weight ?? 1.0),
                'half_day_weight' => (float) ($employee->category?->half_day_weight ?? 0.5),
                'paid_leave_weight' => (float) ($employee->category?->paid_leave_weight ?? 1.0),
                'excess_leave_weight' => (float) ($employee->category?->excess_leave_weight ?? 0.0),
                'absent_day_weight' => (float) ($employee->category?->absent_day_weight ?? 0.0),
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
            ($summary['present_days'] * (float) ($employee->category?->present_day_weight ?? 1.0))
            + ($summary['half_days'] * (float) ($employee->category?->half_day_weight ?? 0.5))
            + ($summary['paid_leave_days'] * (float) ($employee->category?->paid_leave_weight ?? 1.0))
            + ($summary['unpaid_leave_days'] * (float) ($employee->category?->excess_leave_weight ?? 0.0))
            + ($summary['absent_days'] * (float) ($employee->category?->absent_day_weight ?? 0.0)),
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
        $salaryPayableAccount = Account::query()->firstOrCreate(
            ['code' => '2300'],
            [
                'name' => 'Salary Payable',
                'type' => 'liability',
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
                        'account_id' => (int) $salaryPayableAccount->id,
                        'type' => 'credit',
                        'amount' => (float) $payrollRun->gross_amount,
                    ],
                ],
                sourceType: PayrollRun::class,
                sourceId: $payrollRun->id,
                sourceEvent: 'payroll_accrual',
            ),
            $userId,
        );
    }

    private function recordCompanyAdvanceClearing(PayrollRun $payrollRun, float $amount, int $userId): ?JournalEntry
    {
        if ($amount <= 0.0) {
            return null;
        }

        $salaryPayableAccount = Account::query()->firstOrCreate(
            ['code' => '2300'],
            [
                'name' => 'Salary Payable',
                'type' => 'liability',
                'is_active' => true,
                'parent_id' => null,
            ],
        );
        $employeeAdvanceAccount = Account::query()->firstOrCreate(
            ['code' => '1600'],
            [
                'name' => 'Employee Advances',
                'type' => 'asset',
                'is_active' => true,
                'parent_id' => null,
            ],
        );

        return $this->journalService->createEntry(
            new JournalEntryData(
                entryDate: $payrollRun->period_end->format('Y-m-d'),
                reference: sprintf('PAYROLL-ADV-CLEAR-%s', $payrollRun->id),
                description: 'Advance recovery clearing for '.$payrollRun->period_start->format('F Y'),
                lines: [
                    [
                        'account_id' => (int) $salaryPayableAccount->id,
                        'type' => 'debit',
                        'amount' => round($amount, 2),
                    ],
                    [
                        'account_id' => (int) $employeeAdvanceAccount->id,
                        'type' => 'credit',
                        'amount' => round($amount, 2),
                    ],
                ],
                sourceType: PayrollRun::class,
                sourceId: $payrollRun->id,
                sourceEvent: 'advance_clearing',
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
