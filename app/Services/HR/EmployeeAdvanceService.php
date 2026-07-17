<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAdvanceRule;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeAdvanceService
{
    public function __construct(
        private readonly PayrollService $payrollService,
        private readonly ShopEmployeeAssignmentService $assignmentService,
    ) {}

    /**
     * @return array{rule: EmployeeAdvanceRule, present_days: float, earned_amount: float, eligible_amount: float}
     */
    public function eligibility(Employee $employee, Carbon $month): array
    {
        $rule = EmployeeAdvanceRule::activeRule();
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $periodEnd = today()->lt($monthEnd) ? today() : $monthEnd;
        $summary = $this->payrollService->attendanceSummary($employee, $monthStart, $periodEnd);
        $presentDays = (float) $summary['present_days'];
        $dailyRate = (string) $employee->salary_type === 'daily_wage'
            ? (float) $employee->daily_wage
            : (float) $employee->monthly_salary / max(1, $monthStart->daysInMonth);
        $earnedAmount = round($dailyRate * $presentDays, 2);
        $eligibleAmount = $presentDays >= (float) $rule->minimum_present_days
            ? round($earnedAmount * ((float) $rule->advance_percent / 100), 2)
            : 0.0;

        return [
            'rule' => $rule,
            'present_days' => $presentDays,
            'earned_amount' => $earnedAmount,
            'eligible_amount' => $eligibleAmount,
        ];
    }

    public function recordShopSalaryPayment(Employee $employee, Shop $shop, float $amount, string $fundSource, Carbon $paidOn, User $actor, ?string $notes = null): ShopStaffPayment
    {
        $this->ensureShopEmployee($employee, $shop, $paidOn);

        $payrollRunItem = $this->payrollService->ensurePayrollRunItem(
            $employee,
            $paidOn->copy()->startOfMonth(),
            $paidOn->copy()->endOfMonth(),
            (int) $actor->id,
        );

        return ShopStaffPayment::query()->create([
            'payroll_run_id' => $payrollRunItem->payroll_run_id,
            'payroll_run_item_id' => $payrollRunItem->id,
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'paid_by' => $actor->id,
            'paid_on' => $paidOn->toDateString(),
            'amount' => round($amount, 2),
            'payment_type' => 'salary',
            'fund_source' => $fundSource,
            'status' => 'paid',
            'notes' => $notes,
        ])->fresh(['employee', 'shop', 'payrollRunItem']);
    }

    public function requestOrPayAdvance(Employee $employee, Shop $shop, float $amount, string $fundSource, Carbon $requestedOn, User $actor, ?string $note = null): EmployeeAdvanceRequest
    {
        $this->ensureShopEmployee($employee, $shop, $requestedOn);

        $month = $requestedOn->copy()->startOfMonth();
        $eligibility = $this->eligibility($employee, $month);
        /** @var EmployeeAdvanceRule $rule */
        $rule = $eligibility['rule'];
        $eligibleAmount = (float) $eligibility['eligible_amount'];
        $status = $amount <= $eligibleAmount ? 'approved' : 'pending';

        return DB::transaction(function () use ($employee, $shop, $amount, $fundSource, $requestedOn, $actor, $note, $rule, $eligibility, $eligibleAmount, $status, $month): EmployeeAdvanceRequest {
            $advanceRequest = EmployeeAdvanceRequest::query()->create([
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'employee_advance_rule_id' => $rule->id,
                'requested_by' => $actor->id,
                'requested_on' => $requestedOn->toDateString(),
                'payroll_month' => $month->toDateString(),
                'requested_amount' => round($amount, 2),
                'eligible_amount' => $eligibleAmount,
                'approved_amount' => $status === 'approved' ? round($amount, 2) : null,
                'fund_source' => $fundSource,
                'status' => $status,
                'rule_snapshot' => [
                    'minimum_present_days' => (int) $rule->minimum_present_days,
                    'advance_percent' => (float) $rule->advance_percent,
                    'present_days' => $eligibility['present_days'],
                    'earned_amount' => $eligibility['earned_amount'],
                    'eligible_amount' => $eligibleAmount,
                ],
                'request_note' => $note,
                'reviewed_by' => $status === 'approved' ? $actor->id : null,
                'reviewed_at' => $status === 'approved' ? now() : null,
            ]);

            if ($status === 'approved') {
                $payment = $this->payApprovedAdvance($advanceRequest, $actor);
                $advanceRequest->forceFill(['shop_staff_payment_id' => $payment->id])->save();
            }

            return $advanceRequest->fresh(['employee', 'shop', 'shopStaffPayment', 'requestedBy', 'reviewedBy']);
        });
    }

    public function review(EmployeeAdvanceRequest $advanceRequest, string $decision, float $approvedAmount, User $actor, ?string $note = null): EmployeeAdvanceRequest
    {
        if ($advanceRequest->status !== 'pending') {
            throw ValidationException::withMessages(['decision' => 'Only pending advance requests can be reviewed.']);
        }

        return DB::transaction(function () use ($advanceRequest, $decision, $approvedAmount, $actor, $note): EmployeeAdvanceRequest {
            if ($decision === 'reject') {
                $advanceRequest->forceFill([
                    'status' => 'rejected',
                    'approved_amount' => null,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                    'review_note' => $note,
                ])->save();

                return $advanceRequest->fresh(['employee', 'shop', 'requestedBy', 'reviewedBy']);
            }

            $advanceRequest->forceFill([
                'status' => 'approved',
                'approved_amount' => round($approvedAmount, 2),
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            $payment = $this->payApprovedAdvance($advanceRequest->fresh(['employee', 'shop']), $actor);
            $advanceRequest->forceFill(['shop_staff_payment_id' => $payment->id])->save();

            return $advanceRequest->fresh(['employee', 'shop', 'shopStaffPayment', 'requestedBy', 'reviewedBy']);
        });
    }

    private function payApprovedAdvance(EmployeeAdvanceRequest $advanceRequest, User $actor): ShopStaffPayment
    {
        $advanceRequest->loadMissing(['employee', 'shop']);
        $paidOn = $advanceRequest->requested_on ?? today();

        $payrollRunItem = $this->payrollService->ensurePayrollRunItem(
            $advanceRequest->employee,
            $advanceRequest->payroll_month->copy()->startOfMonth(),
            $advanceRequest->payroll_month->copy()->endOfMonth(),
            (int) $actor->id,
        );

        return ShopStaffPayment::query()->create([
            'payroll_run_id' => $payrollRunItem->payroll_run_id,
            'payroll_run_item_id' => $payrollRunItem->id,
            'employee_id' => $advanceRequest->employee_id,
            'shop_id' => $advanceRequest->shop_id,
            'employee_advance_request_id' => $advanceRequest->id,
            'paid_by' => $actor->id,
            'paid_on' => $paidOn->toDateString(),
            'amount' => round((float) ($advanceRequest->approved_amount ?? $advanceRequest->requested_amount), 2),
            'payment_type' => 'advance',
            'fund_source' => $advanceRequest->fund_source,
            'status' => 'paid',
            'notes' => $advanceRequest->request_note,
        ])->fresh(['employee', 'shop', 'advanceRequest']);
    }

    private function ensureShopEmployee(Employee $employee, Shop $shop, Carbon $date): void
    {
        if (! $this->assignmentService->isAssignedToShopOn($employee, $shop, $date)) {
            throw ValidationException::withMessages([
                'employee_id' => 'This employee is not assigned to the selected shop for this date.',
            ]);
        }
    }
}
