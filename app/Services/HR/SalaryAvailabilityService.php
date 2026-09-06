<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\DTOs\HR\SalaryAvailabilityData;
use App\DTOs\HR\SalaryPaymentDecision;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeaveRequest;
use App\Models\PayrollPayment;
use App\Models\ShopStaffPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SalaryAvailabilityService
{
    /**
     * Calculate salary availability for a single employee.
     */
    public function calculate(
        Employee $employee,
        CarbonImmutable $payrollMonth,
        CarbonImmutable $calculationDate,
        ?int $shopId = null,
        ?float $openingRecovery = null,
        ?float $shopAllocatedRecovery = null,
    ): SalaryAvailabilityData {
        $monthStart = $payrollMonth->startOfMonth();
        $monthEnd = $payrollMonth->endOfMonth();
        $calcCutoff = $calculationDate->lt($monthStart)
            ? $monthStart
            : ($calculationDate->gt($monthEnd) ? $monthEnd : $calculationDate);
        $monthStr = $monthStart->toDateString();

        $employee->loadMissing('category');

        // Query attendances for the cutoff window
        $attendances = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', $monthStart->toDateString())
            ->whereDate('attendance_date', '<=', $calcCutoff->toDateString())
            ->get();

        // Query approved leaves in window
        $leaves = EmployeeLeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $calcCutoff->toDateString())
            ->whereDate('end_date', '>=', $monthStart->toDateString())
            ->get();

        // Query shop staff payments for employee
        $shopPayments = ShopStaffPayment::query()
            ->with(['payrollRun', 'payrollRunItem.payrollRun', 'advanceRequest'])
            ->where('employee_id', $employee->id)
            ->where('status', 'paid')
            ->get();

        // Query company payments for employee
        $companyPayments = PayrollPayment::query()
            ->with(['payrollRun', 'payrollRunItem.payrollRun', 'advanceRequest'])
            ->where('employee_id', $employee->id)
            ->get();

        // Query advance requests for employee
        $advanceRequests = EmployeeAdvanceRequest::query()
            ->where('employee_id', $employee->id)
            ->get();

        return $this->computeAvailability(
            employee: $employee,
            monthStart: $monthStart,
            calcCutoff: $calcCutoff,
            shopId: $shopId,
            openingRecovery: $openingRecovery,
            shopAllocatedRecovery: $shopAllocatedRecovery,
            attendances: $attendances,
            leaves: $leaves,
            shopPayments: $shopPayments,
            companyPayments: $companyPayments,
            advanceRequests: $advanceRequests,
        );
    }

    /**
     * Batch calculate salary availability with a query count independent of employee count.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  array<int, float>  $openingRecoveries
     * @param  array<int, float>  $shopAllocatedRecoveries
     * @return Collection<int, SalaryAvailabilityData>
     */
    public function calculateForShop(
        Collection $employees,
        CarbonImmutable $payrollMonth,
        CarbonImmutable $calculationDate,
        int $shopId,
        array $openingRecoveries = [],
        array $shopAllocatedRecoveries = [],
    ): Collection {
        $employeeIds = $employees->pluck('id')->values()->all();

        if (empty($employeeIds)) {
            return collect();
        }

        $monthStart = $payrollMonth->startOfMonth();
        $monthEnd = $payrollMonth->endOfMonth();
        $calcCutoff = $calculationDate->lt($monthStart)
            ? $monthStart
            : ($calculationDate->gt($monthEnd) ? $monthEnd : $calculationDate);

        // Preload categories
        $employees->loadMissing('category');

        // Query 1: Attendances for all employees
        $attendances = EmployeeAttendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', '>=', $monthStart->toDateString())
            ->whereDate('attendance_date', '<=', $calcCutoff->toDateString())
            ->get()
            ->groupBy('employee_id');

        // Query 2 & 3: Approved leaves for all employees (with leaveType)
        $leaves = EmployeeLeaveRequest::query()
            ->with('leaveType')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $calcCutoff->toDateString())
            ->whereDate('end_date', '>=', $monthStart->toDateString())
            ->get()
            ->groupBy('employee_id');

        // Shop staff payments with all payroll attribution relations
        $shopPayments = ShopStaffPayment::query()
            ->with(['payrollRun', 'payrollRunItem.payrollRun', 'advanceRequest'])
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'paid')
            ->get()
            ->groupBy('employee_id');

        // Company payments with all payroll attribution relations
        $companyPayments = PayrollPayment::query()
            ->with(['payrollRun', 'payrollRunItem.payrollRun', 'advanceRequest'])
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->groupBy('employee_id');

        // Advance requests
        $advanceRequests = EmployeeAdvanceRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->groupBy('employee_id');

        $results = collect();

        foreach ($employees as $employee) {
            $empRecovery = $openingRecoveries[$employee->id] ?? null;
            $shopRecovery = $shopAllocatedRecoveries[$employee->id] ?? null;

            $empAttendances = $attendances->get($employee->id, collect());
            $empLeaves = $leaves->get($employee->id, collect());
            $empShopPayments = $shopPayments->get($employee->id, collect());
            $empCompPayments = $companyPayments->get($employee->id, collect());
            $empAdvRequests = $advanceRequests->get($employee->id, collect());

            $results->put(
                $employee->id,
                $this->computeAvailability(
                    employee: $employee,
                    monthStart: $monthStart,
                    calcCutoff: $calcCutoff,
                    shopId: $shopId,
                    openingRecovery: $empRecovery,
                    shopAllocatedRecovery: $shopRecovery,
                    attendances: $empAttendances,
                    leaves: $empLeaves,
                    shopPayments: $empShopPayments,
                    companyPayments: $empCompPayments,
                    advanceRequests: $empAdvRequests,
                )
            );
        }

        return $results;
    }

    /**
     * Evaluate a payment request decision.
     */
    public function evaluateDecision(
        SalaryAvailabilityData $availability,
        string $paymentType,
        float $requestedAmount,
    ): SalaryPaymentDecision {
        if (is_nan($requestedAmount) || is_infinite($requestedAmount) || $requestedAmount <= 0.0) {
            return new SalaryPaymentDecision(
                status: 'invalid',
                requestedAmount: $requestedAmount,
                allowedAmount: 0.0,
                reasons: ['The requested payment amount must be a positive finite number.'],
            );
        }

        if (! in_array($paymentType, ['salary', 'advance'], true)) {
            return new SalaryPaymentDecision(
                status: 'invalid',
                requestedAmount: $requestedAmount,
                allowedAmount: 0.0,
                reasons: ['Invalid payment type specified.'],
            );
        }

        if ($availability->dataQualityStatus === 'unknown' || $availability->hasConflictingLinks) {
            return new SalaryPaymentDecision(
                status: 'requires_hr',
                requestedAmount: $requestedAmount,
                allowedAmount: 0.0,
                reasons: array_merge(['HR review required due to unverified data.'], $availability->dataQualityReasons),
            );
        }

        if ($paymentType === 'advance') {
            $availableLimit = $availability->shopId !== null
                ? $availability->shopAvailableAdvance
                : $availability->employeeWideAvailableAdvance;

            if ($availableLimit === null) {
                return new SalaryPaymentDecision(
                    status: 'requires_hr',
                    requestedAmount: $requestedAmount,
                    allowedAmount: 0.0,
                    reasons: ['Advance availability is not determined.'],
                );
            }

            if ($requestedAmount - $availableLimit <= 0.009) {
                return new SalaryPaymentDecision(
                    status: 'allowed',
                    requestedAmount: $requestedAmount,
                    allowedAmount: round($requestedAmount, 2),
                    reasons: [],
                );
            }

            return new SalaryPaymentDecision(
                status: 'requires_hr',
                requestedAmount: $requestedAmount,
                allowedAmount: $availableLimit,
                reasons: [sprintf('Requested advance ₹%.2f exceeds manager available limit ₹%.2f.', $requestedAmount, $availableLimit)],
            );
        }

        // Salary Payment
        $remainingSalaryLimit = $availability->shopId !== null
            ? $availability->shopRemainingSalary
            : $availability->employeeWideRemainingSalary;

        if ($remainingSalaryLimit === null) {
            return new SalaryPaymentDecision(
                status: 'requires_hr',
                requestedAmount: $requestedAmount,
                allowedAmount: 0.0,
                reasons: ['Remaining salary is not determined.'],
            );
        }

        if ($requestedAmount - $remainingSalaryLimit <= 0.009) {
            return new SalaryPaymentDecision(
                status: 'allowed',
                requestedAmount: $requestedAmount,
                allowedAmount: round($requestedAmount, 2),
                reasons: [],
            );
        }

        return new SalaryPaymentDecision(
            status: 'invalid',
            requestedAmount: $requestedAmount,
            allowedAmount: $remainingSalaryLimit,
            reasons: [sprintf('Requested salary payment ₹%.2f exceeds remaining salary limit ₹%.2f.', $requestedAmount, $remainingSalaryLimit)],
        );
    }

    /**
     * Shared single calculation engine for both single and batch pipelines.
     */
    private function computeAvailability(
        Employee $employee,
        CarbonImmutable $monthStart,
        CarbonImmutable $calcCutoff,
        ?int $shopId,
        ?float $openingRecovery,
        ?float $shopAllocatedRecovery,
        Collection $attendances,
        Collection $leaves,
        Collection $shopPayments,
        Collection $companyPayments,
        Collection $advanceRequests,
    ): SalaryAvailabilityData {
        $calendarDays = $monthStart->daysInMonth;
        $salaryType = in_array((string) $employee->salary_type, ['monthly', 'daily_wage'], true)
            ? (string) $employee->salary_type
            : 'monthly';

        $monthlySalary = (float) $employee->monthly_salary;
        $dailyWage = (float) $employee->daily_wage;
        $dailyRate = $salaryType === 'daily_wage'
            ? $dailyWage
            : ($monthlySalary / max(1, $calendarDays));

        // Employee-wide attendance summary & payable units
        $empWideSummary = $this->summarizeAttendance(
            attendances: $attendances,
            leaves: $leaves,
            employee: $employee,
            periodStart: $monthStart,
            periodEnd: $calcCutoff,
            shopId: null,
        );
        $empWidePayableUnits = $this->calculatePayableUnits($empWideSummary, $employee);
        $employeeWideEarned = round($dailyRate * $empWidePayableUnits, 2);

        // Selected-shop attendance summary & payable units
        $shopEarned = null;
        $shopSummary = null;
        $shopPayableUnits = null;

        if ($shopId !== null) {
            $shopSummary = $this->summarizeAttendance(
                attendances: $attendances,
                leaves: $leaves,
                employee: $employee,
                periodStart: $monthStart,
                periodEnd: $calcCutoff,
                shopId: $shopId,
            );
            $shopPayableUnits = $this->calculatePayableUnits($shopSummary, $employee);
            $shopEarned = round($dailyRate * $shopPayableUnits, 2);
        }

        // Payout attribution & conflict detection
        $payoutAnalysis = $this->analyzePayouts(
            employeeId: $employee->id,
            monthStart: $monthStart,
            shopId: $shopId,
            shopPayments: $shopPayments,
            companyPayments: $companyPayments,
            advanceRequests: $advanceRequests,
        );

        $empWideAdvancesPaid = $payoutAnalysis['employee_wide_advances_paid'];
        $empWideSalaryPaid = $payoutAnalysis['employee_wide_salary_paid'];
        $shopAdvancesPaid = $shopId !== null ? $payoutAnalysis['shop_advances_paid'] : null;
        $shopSalaryPaid = $shopId !== null ? $payoutAnalysis['shop_salary_paid'] : null;
        $hasConflictingLinks = $payoutAnalysis['has_conflicting_links'];

        // Data quality and recovery checks
        $dataQualityReasons = $payoutAnalysis['unverified_reasons'];
        $dataQualityStatus = empty($dataQualityReasons) && ! $hasConflictingLinks ? 'verified' : 'unknown';

        if ($openingRecovery === null) {
            $dataQualityStatus = 'unknown';
            $dataQualityReasons[] = 'Opening recovery balance is unverified.';
        }

        if ($hasConflictingLinks) {
            $dataQualityStatus = 'unknown';
            $dataQualityReasons[] = 'Conflicting payout records detected.';
        }

        if ($shopId !== null && $openingRecovery !== null && $openingRecovery > 0.0 && $shopAllocatedRecovery === null) {
            $dataQualityStatus = 'unknown';
            $dataQualityReasons[] = 'recovery_allocation_unknown';
        }

        // Employee-wide calculations
        $advanceCeiling = round($employeeWideEarned * 0.50, 2);

        $empWideSignedAdvanceAvail = $openingRecovery !== null
            ? round($advanceCeiling - $empWideAdvancesPaid - $openingRecovery, 2)
            : null;

        $empWideSignedSalaryRemaining = $openingRecovery !== null
            ? round($employeeWideEarned - $empWideAdvancesPaid - $empWideSalaryPaid - $openingRecovery, 2)
            : null;

        $empWideAvailableAdvance = ($empWideSignedAdvanceAvail !== null && $empWideSignedSalaryRemaining !== null)
            ? round(max(0.0, min($empWideSignedAdvanceAvail, $empWideSignedSalaryRemaining)), 2)
            : null;

        $empWideRemainingSalary = $empWideSignedSalaryRemaining !== null
            ? round(max(0.0, $empWideSignedSalaryRemaining), 2)
            : null;

        // Selected-shop calculations
        $shopSignedAdvanceAvail = null;
        $shopSignedSalaryRemaining = null;
        $shopAvailableAdvance = null;
        $shopRemainingSalary = null;

        if ($shopId !== null && $shopEarned !== null && $shopAdvancesPaid !== null && $shopSalaryPaid !== null) {
            $effectiveShopRecovery = $shopAllocatedRecovery ?? 0.0;
            $shopCeiling = round($shopEarned * 0.50, 2);

            $shopSignedAdvanceAvail = round($shopCeiling - $shopAdvancesPaid - $effectiveShopRecovery, 2);
            $shopSignedSalaryRemaining = round($shopEarned - $shopAdvancesPaid - $shopSalaryPaid - $effectiveShopRecovery, 2);

            if ($empWideAvailableAdvance !== null) {
                $shopAvailableAdvance = round(max(0.0, min(
                    $empWideAvailableAdvance,
                    $shopSignedAdvanceAvail,
                    $shopSignedSalaryRemaining
                )), 2);
            }

            if ($empWideSignedSalaryRemaining !== null) {
                $shopRemainingSalary = round(max(0.0, min(
                    $empWideSignedSalaryRemaining,
                    $shopSignedSalaryRemaining
                )), 2);
            }
        }

        return new SalaryAvailabilityData(
            employeeId: $employee->id,
            shopId: $shopId,
            payrollMonth: $monthStart,
            calculationDate: $calcCutoff,
            salaryType: $salaryType,
            monthlySalary: $monthlySalary,
            dailyWage: $dailyWage,
            dailyRate: $dailyRate,
            calendarDays: $calendarDays,
            presentDays: (float) $empWideSummary['present_days'],
            halfDays: (float) $empWideSummary['half_days'],
            paidLeaveDays: (float) $empWideSummary['paid_leave_days'],
            unpaidLeaveDays: (float) $empWideSummary['unpaid_leave_days'],
            absentDays: (float) $empWideSummary['absent_days'],
            payableUnits: $empWidePayableUnits,
            employeeWideEarned: $employeeWideEarned,
            employeeWideAdvancesPaid: $empWideAdvancesPaid,
            employeeWideSalaryPaid: $empWideSalaryPaid,
            employeeWideOpeningRecovery: $openingRecovery,
            employeeWideSignedAdvanceAvailability: $empWideSignedAdvanceAvail,
            employeeWideSignedSalaryRemaining: $empWideSignedSalaryRemaining,
            employeeWideAvailableAdvance: $empWideAvailableAdvance,
            employeeWideRemainingSalary: $empWideRemainingSalary,
            shopEarned: $shopEarned,
            shopAdvancesPaid: $shopAdvancesPaid,
            shopSalaryPaid: $shopSalaryPaid,
            shopAllocatedRecovery: $shopAllocatedRecovery,
            shopSignedAdvanceAvailability: $shopSignedAdvanceAvail,
            shopSignedSalaryRemaining: $shopSignedSalaryRemaining,
            shopAvailableAdvance: $shopAvailableAdvance,
            shopRemainingSalary: $shopRemainingSalary,
            dataQualityStatus: $dataQualityStatus,
            hasConflictingLinks: $hasConflictingLinks,
            dataQualityReasons: $dataQualityReasons,
        );
    }

    /**
     * Unified attendance summarizer operating purely in-memory on preloaded collections.
     *
     * @return array{present_days: float, half_days: float, paid_leave_days: float, unpaid_leave_days: float, absent_days: float}
     */
    private function summarizeAttendance(
        Collection $attendances,
        Collection $leaves,
        Employee $employee,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?int $shopId = null,
    ): array {
        $attendanceByDate = $attendances
            ->keyBy(fn (EmployeeAttendance $att): string => $att->attendance_date->toDateString());

        // Expand approved leave requests into dates
        $approvedLeaveByDate = [];

        foreach ($leaves as $leave) {
            $cursor = CarbonImmutable::parse($leave->start_date);
            $end = CarbonImmutable::parse($leave->end_date);

            while ($cursor->lte($end)) {
                $approvedLeaveByDate[$cursor->toDateString()] = $leave;
                $cursor = $cursor->addDay();
            }
        }

        $presentDays = 0.0;
        $halfDays = 0.0;
        $absentDays = 0.0;
        $paidLeaveDays = 0.0;
        $employeePaidLeaveDays = 0.0;
        $paidLeaveLimit = max(0, (int) ($employee->category?->monthly_paid_leave_limit ?? 0));
        $unpaidLeaveDays = 0.0;

        $cursor = $periodStart;

        while ($cursor->lte($periodEnd)) {
            $dateStr = $cursor->toDateString();
            $attendance = $attendanceByDate->get($dateStr);

            if ($attendance === null) {
                if ($shopId === null) {
                    $absentDays++;
                }

                $cursor = $cursor->addDay();

                continue;
            }

            $dayPaid = 0.0;
            $dayUnpaid = 0.0;
            $dayAbsent = 0.0;
            if ($attendance->status === 'leave') {
                $this->accumulateLeaveDay(
                    attendance: $attendance,
                    approvedLeave: $approvedLeaveByDate[$dateStr] ?? null,
                    approvedPaidLikeLeaveCount: $dayPaid,
                    unpaidLeaveDays: $dayUnpaid,
                    absentDays: $dayAbsent,
                );
                if ($dayPaid > 0.0 && $employeePaidLeaveDays >= $paidLeaveLimit) {
                    $dayUnpaid += $dayPaid;
                    $dayPaid = 0.0;
                }
                $employeePaidLeaveDays += $dayPaid;
            }

            if ($shopId === null || (int) $attendance->shop_id === $shopId) {
                match ($attendance->status) {
                    'present' => $presentDays++,
                    'half_day' => $halfDays++,
                    'leave' => null,
                    default => $absentDays++,
                };
                $paidLeaveDays += $dayPaid;
                $unpaidLeaveDays += $dayUnpaid;
                $absentDays += $dayAbsent;
            }

            $cursor = $cursor->addDay();
        }

        return [
            'present_days' => $presentDays,
            'half_days' => $halfDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'absent_days' => $absentDays,
        ];
    }

    private function accumulateLeaveDay(
        EmployeeAttendance $attendance,
        ?EmployeeLeaveRequest $approvedLeave,
        float &$approvedPaidLikeLeaveCount,
        float &$unpaidLeaveDays,
        float &$absentDays,
    ): void {
        if ($approvedLeave !== null) {
            if ($approvedLeave->leaveType?->is_paid ?? true) {
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

    /**
     * Calculate payable units using all 5 category weights.
     *
     * @param  array{present_days: float, half_days: float, paid_leave_days: float, unpaid_leave_days: float, absent_days: float}  $summary
     */
    private function calculatePayableUnits(array $summary, Employee $employee): float
    {
        $category = $employee->category;
        $wPresent = (float) ($category?->present_day_weight ?? 1.0);
        $wHalf = (float) ($category?->half_day_weight ?? 0.5);
        $wPaidLeave = (float) ($category?->paid_leave_weight ?? 1.0);
        $wUnpaidLeave = (float) ($category?->excess_leave_weight ?? 0.0);
        $wAbsent = (float) ($category?->absent_day_weight ?? 0.0);

        return round(
            ($summary['present_days'] * $wPresent)
            + ($summary['half_days'] * $wHalf)
            + ($summary['paid_leave_days'] * $wPaidLeave)
            + ($summary['unpaid_leave_days'] * $wUnpaidLeave)
            + ($summary['absent_days'] * $wAbsent),
            2
        );
    }

    /**
     * Analyze shop staff payments, company payments, and advance requests for target payroll month.
     *
     * @return array{
     *     employee_wide_advances_paid: float,
     *     employee_wide_salary_paid: float,
     *     shop_advances_paid: float,
     *     shop_salary_paid: float,
     *     has_conflicting_links: bool,
     *     unverified_reasons: array<string>
     * }
     */
    private function analyzePayouts(
        int $employeeId,
        CarbonImmutable $monthStart,
        ?int $shopId,
        Collection $shopPayments,
        Collection $companyPayments,
        Collection $advanceRequests,
    ): array {
        $monthStr = $monthStart->toDateString();
        $empWideAdvances = 0.0;
        $empWideSalary = 0.0;
        $shopAdvances = 0.0;
        $shopSalary = 0.0;
        $hasConflictingLinks = false;
        $unverifiedReasons = [];

        // Process ShopStaffPayment records
        foreach ($shopPayments as $payment) {
            $attribution = $this->resolvePaymentPayrollMonth($payment);
            $resolvedMonth = $attribution['month'];
            $hasConflictingLinks = $hasConflictingLinks || $attribution['conflict'];

            if ($resolvedMonth === null) {
                $unverifiedReasons[] = $attribution['conflict']
                    ? "Shop payment #{$payment->id} has conflicting payroll month links."
                    : "Shop payment #{$payment->id} lacks payroll month attribution.";

                continue;
            }

            if ($resolvedMonth !== $monthStr) {
                continue;
            }

            $amt = (float) $payment->amount;
            $paymentShopId = (int) ($payment->shop_id ?? $payment->advanceRequest?->shop_id);

            if ($payment->payment_type === 'advance') {
                $empWideAdvances += $amt;

                if ($shopId !== null && $paymentShopId === $shopId) {
                    $shopAdvances += $amt;
                }
            } else {
                $empWideSalary += $amt;

                if ($shopId !== null && $paymentShopId === $shopId) {
                    $shopSalary += $amt;
                }
            }
        }

        // Process PayrollPayment (Company) records
        foreach ($companyPayments as $cPayment) {
            $attribution = $this->resolvePaymentPayrollMonth($cPayment);
            $resolvedMonth = $attribution['month'];
            $hasConflictingLinks = $hasConflictingLinks || $attribution['conflict'];

            if ($resolvedMonth === null) {
                $unverifiedReasons[] = $attribution['conflict']
                    ? "Company payment #{$cPayment->id} has conflicting payroll month links."
                    : "Company payment #{$cPayment->id} lacks payroll month attribution.";

                continue;
            }

            if ($resolvedMonth !== $monthStr) {
                continue;
            }

            $amt = (float) $cPayment->amount;
            $pType = (string) $cPayment->payment_type;
            $cPaymentShopId = (int) ($cPayment->shop_id ?? $cPayment->advanceRequest?->shop_id);

            if ($pType === 'advance') {
                $empWideAdvances += $amt;

                if ($shopId !== null && $cPaymentShopId === $shopId) {
                    $shopAdvances += $amt;
                }
            } elseif (in_array($pType, ['full', 'partial', 'custom', 'salary'], true)) {
                $empWideSalary += $amt;

                if ($shopId !== null && $cPaymentShopId === $shopId) {
                    $shopSalary += $amt;
                }
            } else {
                $hasConflictingLinks = true;
                $unverifiedReasons[] = "Unknown company payment type '{$pType}' on payment #{$cPayment->id}.";
            }
        }

        // Conflict check: if an advance request is linked to BOTH a shop payment AND a company payment
        $monthRequests = $advanceRequests->filter(function (EmployeeAdvanceRequest $req) use ($monthStr): bool {
            return $req->payroll_month && CarbonImmutable::parse($req->payroll_month)->startOfMonth()->toDateString() === $monthStr;
        });

        foreach ($monthRequests as $req) {
            if ($req->shop_staff_payment_id !== null && $req->payroll_payment_id !== null) {
                $hasConflictingLinks = true;
                $unverifiedReasons[] = "Advance request #{$req->id} is linked to both shop payment #{$req->shop_staff_payment_id} and company payment #{$req->payroll_payment_id}.";
            }
        }

        return [
            'employee_wide_advances_paid' => round($empWideAdvances, 2),
            'employee_wide_salary_paid' => round($empWideSalary, 2),
            'shop_advances_paid' => round($shopAdvances, 2),
            'shop_salary_paid' => round($shopSalary, 2),
            'has_conflicting_links' => $hasConflictingLinks,
            'unverified_reasons' => array_unique($unverifiedReasons),
        ];
    }

    /**
     * Resolve the payroll month (YYYY-MM-01) for a payment record via relations.
     *
     * @return array{month: ?string, conflict: bool}
     */
    private function resolvePaymentPayrollMonth(ShopStaffPayment|PayrollPayment $payment): array
    {
        $months = collect([
            $payment->payrollRunItem?->payrollRun?->period_start,
            $payment->payrollRun?->period_start,
            $payment->advanceRequest?->payroll_month,
        ])->filter()->map(fn ($date): string => CarbonImmutable::parse($date)->startOfMonth()->toDateString())->unique()->values();

        return [
            'month' => $months->count() === 1 ? $months->first() : null,
            'conflict' => $months->count() > 1,
        ];
    }
}
