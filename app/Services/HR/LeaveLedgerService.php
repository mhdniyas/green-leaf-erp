<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\EmployeeCategoryLeaveRule;
use App\Models\EmployeeLeaveLedgerEntry;
use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;

class LeaveLedgerService
{
    public function balanceFor(Employee $employee, LeaveType $leaveType, Carbon $asOfDate): float
    {
        $financialYearStart = $this->financialYearStart($asOfDate);

        $this->ensureEntitlementsForDate($employee, $leaveType, $asOfDate);

        return round((float) EmployeeLeaveLedgerEntry::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->whereDate('financial_year_start', $financialYearStart->toDateString())
            ->whereDate('transaction_date', '<=', $asOfDate->toDateString())
            ->selectRaw('COALESCE(SUM(credit - debit), 0) as balance')
            ->value('balance'), 2);
    }

    public function recordApprovedLeave(EmployeeLeaveRequest $leaveRequest, User $actor): EmployeeLeaveLedgerEntry
    {
        $leaveRequest->loadMissing(['employee.category.leaveRules.leaveType', 'leaveType']);

        $leaveType = $leaveRequest->leaveType ?? LeaveType::query()->where('code', LeaveType::CODE_PAID)->firstOrFail();
        $transactionDate = $leaveRequest->start_date->copy();
        $leaveRule = $this->findRule($leaveRequest->employee, $leaveType, $transactionDate);

        $this->ensureEntitlementsForDate($leaveRequest->employee, $leaveType, $transactionDate);

        return EmployeeLeaveLedgerEntry::query()->updateOrCreate(
            [
                'source_type' => EmployeeLeaveRequest::class,
                'source_id' => $leaveRequest->id,
                'entry_type' => 'leave_consumed',
            ],
            [
                'employee_id' => $leaveRequest->employee_id,
                'leave_type_id' => $leaveType->id,
                'employee_category_leave_rule_id' => $leaveRule?->id,
                'financial_year_start' => $this->financialYearStart($transactionDate)->toDateString(),
                'transaction_date' => $transactionDate->toDateString(),
                'credit' => 0,
                'debit' => (float) $leaveRequest->start_date->diffInDays($leaveRequest->end_date) + 1,
                'notes' => 'Approved leave consumption.',
                'created_by' => $actor->id,
            ],
        );
    }

    public function reverseApprovedLeave(EmployeeLeaveRequest $leaveRequest, User $actor, string $reason): ?EmployeeLeaveLedgerEntry
    {
        $consumedEntry = EmployeeLeaveLedgerEntry::query()
            ->where('source_type', EmployeeLeaveRequest::class)
            ->where('source_id', $leaveRequest->id)
            ->where('entry_type', 'leave_consumed')
            ->first();

        if ($consumedEntry === null) {
            return null;
        }

        return EmployeeLeaveLedgerEntry::query()->firstOrCreate(
            [
                'source_type' => EmployeeLeaveRequest::class,
                'source_id' => $leaveRequest->id,
                'entry_type' => 'reversal',
            ],
            [
                'employee_id' => $consumedEntry->employee_id,
                'leave_type_id' => $consumedEntry->leave_type_id,
                'employee_category_leave_rule_id' => $consumedEntry->employee_category_leave_rule_id,
                'financial_year_start' => $consumedEntry->financial_year_start?->toDateString(),
                'transaction_date' => now()->toDateString(),
                'credit' => (float) $consumedEntry->debit,
                'debit' => 0,
                'notes' => 'Reversal: '.$reason,
                'created_by' => $actor->id,
            ],
        );
    }

    public function adjustBalance(Employee $employee, LeaveType $leaveType, Carbon $transactionDate, float $days, User $actor, string $reason): EmployeeLeaveLedgerEntry
    {
        $leaveRule = $this->findRule($employee, $leaveType, $transactionDate);
        $this->ensureEntitlementsForDate($employee, $leaveType, $transactionDate);

        return EmployeeLeaveLedgerEntry::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'employee_category_leave_rule_id' => $leaveRule?->id,
            'financial_year_start' => $this->financialYearStart($transactionDate)->toDateString(),
            'transaction_date' => $transactionDate->toDateString(),
            'entry_type' => 'hr_adjustment',
            'credit' => $days >= 0 ? round($days, 2) : 0,
            'debit' => $days < 0 ? round(abs($days), 2) : 0,
            'source_type' => Employee::class,
            'source_id' => $employee->id,
            'notes' => $reason,
            'created_by' => $actor->id,
        ]);
    }

    public function financialYearStart(Carbon $date): Carbon
    {
        $financialYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return Carbon::create($financialYear, 4, 1)->startOfDay();
    }

    private function ensureEntitlementsForDate(Employee $employee, LeaveType $leaveType, Carbon $asOfDate): void
    {
        $leaveRule = $this->findRule($employee, $leaveType, $asOfDate);

        if ($leaveRule === null) {
            return;
        }

        $financialYearStart = $this->financialYearStart($asOfDate);

        if ((float) ($leaveRule->monthly_accrual_amount ?? 0) > 0) {
            $cursor = $financialYearStart->copy();

            while ($cursor->lte($asOfDate->copy()->startOfMonth())) {
                EmployeeLeaveLedgerEntry::query()->firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'entry_type' => 'monthly_accrual',
                        'source_type' => 'monthly_accrual',
                        'source_id' => (int) $cursor->format('Ym'),
                    ],
                    [
                        'employee_category_leave_rule_id' => $leaveRule->id,
                        'financial_year_start' => $financialYearStart->toDateString(),
                        'transaction_date' => $cursor->copy()->startOfMonth()->toDateString(),
                        'credit' => (float) $leaveRule->monthly_accrual_amount,
                        'debit' => 0,
                        'notes' => 'Monthly leave accrual.',
                    ],
                );

                $cursor->addMonthNoOverflow();
            }

            return;
        }

        EmployeeLeaveLedgerEntry::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'entry_type' => 'opening_entitlement',
                'source_type' => 'financial_year_opening',
                'source_id' => (int) $financialYearStart->format('Ymd'),
            ],
            [
                'employee_category_leave_rule_id' => $leaveRule->id,
                'financial_year_start' => $financialYearStart->toDateString(),
                'transaction_date' => $financialYearStart->toDateString(),
                'credit' => (float) $leaveRule->annual_entitlement,
                'debit' => 0,
                'notes' => 'Financial year opening entitlement.',
            ],
        );
    }

    private function findRule(Employee $employee, LeaveType $leaveType, Carbon $date): ?EmployeeCategoryLeaveRule
    {
        $employee->loadMissing('category.leaveRules.leaveType');

        return $employee->category?->leaveRules
            ->filter(fn (EmployeeCategoryLeaveRule $rule): bool => (int) $rule->leave_type_id === (int) $leaveType->id)
            ->sortByDesc(fn (EmployeeCategoryLeaveRule $rule): string => $rule->effective_from?->toDateString() ?? '0000-00-00')
            ->first(fn (EmployeeCategoryLeaveRule $rule): bool => $rule->isActiveOn($date));
    }
}
