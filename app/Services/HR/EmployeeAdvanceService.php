<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAdvanceRule;
use App\Models\JournalEntry;
use App\Models\PayrollPayment;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\StaffPaymentCashbookProjectionService;
use App\Services\Finance\OwnedShopAccountingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeAdvanceService
{
    public function __construct(
        private readonly PayrollService $payrollService,
        private readonly ShopEmployeeAssignmentService $assignmentService,
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
        private readonly SalaryAvailabilityService $salaryAvailabilityService,
        private readonly PayrollPaymentService $payrollPaymentService,
        private readonly StaffPaymentCashbookProjectionService $staffPaymentProjectionService,
        private readonly BalanceCalculator $balanceCalculator,
    ) {}

    /**
     * @return array{rule: EmployeeAdvanceRule, present_days: float, earned_amount: float, eligible_amount: float, already_advanced_amount: float, available_amount: float}
     */
    public function eligibility(Employee $employee, Carbon $month, ?int $shopId = null, ?bool $greenLeafOnly = null): array
    {
        $rule = EmployeeAdvanceRule::activeRule();
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $periodEnd = today()->lt($monthEnd) ? today() : $monthEnd;
        $summary = $this->payrollService->attendanceSummary($employee, $monthStart, $periodEnd, $shopId, $greenLeafOnly);
        $presentDays = (float) $summary['present_days'];
        $dailyRate = (string) $employee->salary_type === 'daily_wage'
            ? (float) $employee->daily_wage
            : (float) $employee->monthly_salary / max(1, $monthStart->daysInMonth);
        $earnedAmount = round($dailyRate * $presentDays, 2);
        $eligibleAmount = $presentDays >= (float) $rule->minimum_present_days
            ? round($earnedAmount * ((float) $rule->advance_percent / 100), 2)
            : 0.0;
        $alreadyAdvancedAmount = $this->approvedAdvanceAmount($employee, $monthStart, $shopId);
        $availableAmount = round(max(0, $eligibleAmount - $alreadyAdvancedAmount), 2);

        return [
            'rule' => $rule,
            'present_days' => $presentDays,
            'earned_amount' => $earnedAmount,
            'eligible_amount' => $eligibleAmount,
            'already_advanced_amount' => $alreadyAdvancedAmount,
            'available_amount' => $availableAmount,
        ];
    }

    public function resolveEmployeeRecoveryDebt(Employee $employee, CarbonImmutable $payrollMonth): ?float
    {
        $prevRunItem = PayrollRunItem::query()
            ->select('payroll_run_items.*')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_run_items.payroll_run_id')
            ->where('payroll_run_items.employee_id', $employee->id)
            ->where('payroll_runs.status', 'finalized')
            ->whereDate('payroll_runs.period_end', '<', $payrollMonth->toDateString())
            ->orderByDesc('payroll_runs.period_end')
            ->orderByDesc('payroll_run_items.id')
            ->first();

        if ($prevRunItem === null) {
            return 0.0;
        }

        if ($prevRunItem->closing_recovery_amount === null) {
            return null;
        }

        return round((float) $prevRunItem->closing_recovery_amount, 2);
    }

    /**
     * @param  array<int>  $employeeIds
     * @return array<int, ?float>
     */
    public function resolveEmployeesRecoveryDebt(array $employeeIds, CarbonImmutable $payrollMonth): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $rankedSubquery = PayrollRunItem::query()
            ->select([
                'payroll_run_items.employee_id',
                'payroll_run_items.closing_recovery_amount',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY payroll_run_items.employee_id ORDER BY payroll_runs.period_end DESC, payroll_run_items.id DESC) as rn'),
            ])
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_run_items.payroll_run_id')
            ->whereIn('payroll_run_items.employee_id', $employeeIds)
            ->where('payroll_runs.status', 'finalized')
            ->whereDate('payroll_runs.period_end', '<', $payrollMonth->toDateString());

        $latestItems = DB::query()
            ->fromSub($rankedSubquery, 'ranked_payroll_items')
            ->where('rn', 1)
            ->get()
            ->keyBy('employee_id');

        $result = [];
        foreach ($employeeIds as $employeeId) {
            $item = $latestItems->get($employeeId);
            if ($item === null) {
                $result[$employeeId] = 0.0;
            } elseif ($item->closing_recovery_amount === null) {
                $result[$employeeId] = null;
            } else {
                $result[$employeeId] = round((float) $item->closing_recovery_amount, 2);
            }
        }

        return $result;
    }

    private function validateShopSalaryPaymentFingerprint(
        ShopStaffPayment $existing,
        Employee $employee,
        Shop $shop,
        float $amount,
        string $fundSource,
        Carbon $paidOn,
    ): void {
        if (
            (int) $existing->employee_id !== (int) $employee->id
            || (int) $existing->shop_id !== (int) $shop->id
            || abs((float) $existing->amount - round($amount, 2)) > 0.009
            || $existing->paid_on?->toDateString() !== $paidOn->toDateString()
            || $existing->payment_type !== 'salary'
            || $existing->fund_source !== $fundSource
        ) {
            throw ValidationException::withMessages([
                'request_uuid' => 'This request UUID has already been used for a different payment.',
            ]);
        }
    }

    private function validateAdvanceRequestFingerprint(
        EmployeeAdvanceRequest $existing,
        Employee $employee,
        Shop $shop,
        float $amount,
        string $fundSource,
        Carbon $requestedOn,
        Carbon $month,
    ): void {
        if (
            (int) $existing->employee_id !== (int) $employee->id
            || (int) $existing->shop_id !== (int) $shop->id
            || abs((float) $existing->requested_amount - round($amount, 2)) > 0.009
            || $existing->requested_on?->toDateString() !== $requestedOn->toDateString()
            || $existing->payroll_month?->toDateString() !== $month->toDateString()
            || $existing->fund_source !== $fundSource
        ) {
            throw ValidationException::withMessages([
                'request_uuid' => 'This request UUID has already been used for a different advance request.',
            ]);
        }
    }

    public function recordShopSalaryPayment(
        Employee $employee,
        Shop $shop,
        float $amount,
        string $fundSource,
        Carbon $paidOn,
        User $actor,
        ?string $notes = null,
        ?string $requestUuid = null,
        ?Carbon $payrollMonth = null
    ): ShopStaffPayment {
        $effectivePayrollMonth = $payrollMonth ? $payrollMonth->copy()->startOfMonth() : $paidOn->copy()->startOfMonth();
        $payrollMonthEnd = $effectivePayrollMonth->copy()->endOfMonth()->startOfDay();

        // Salary payment can only be recorded on or after the last day of the target payroll month
        if ($paidOn->copy()->startOfDay()->lt($payrollMonthEnd)) {
            throw ValidationException::withMessages([
                'paid_on' => 'Salary payments can only be recorded on the last day of the month or later. Use an advance payment for earlier dates.',
            ]);
        }

        $this->ensureShopEmployee($employee, $shop, $paidOn);

        return DB::transaction(function () use ($employee, $shop, $amount, $fundSource, $paidOn, $actor, $notes, $requestUuid, $effectivePayrollMonth): ShopStaffPayment {
            Employee::query()->where('id', $employee->id)->lockForUpdate()->first();

            if ($requestUuid !== null && trim($requestUuid) !== '') {
                $existing = ShopStaffPayment::query()->where('request_uuid', $requestUuid)->lockForUpdate()->first();
                if ($existing) {
                    $this->validateShopSalaryPaymentFingerprint($existing, $employee, $shop, $amount, $fundSource, $paidOn);

                    return $existing->fresh(['employee', 'shop', 'payrollRunItem', 'cashbookLine.entry']);
                }
            }

            $month = $effectivePayrollMonth->copy()->startOfMonth();
            ShopStaffPayment::query()
                ->where('employee_id', $employee->id)
                ->whereDate('paid_on', '>=', $month->toDateString())
                ->lockForUpdate()
                ->get();

            $payrollMonthImmutable = CarbonImmutable::parse($month->toDateString());
            $calculationDate = CarbonImmutable::parse($paidOn->toDateString());
            $recoveryDebt = $this->resolveEmployeeRecoveryDebt($employee, $payrollMonthImmutable);
            $shopAllocatedRecovery = ($recoveryDebt !== null && $recoveryDebt <= 0.0001) ? 0.0 : null;

            $availability = $this->salaryAvailabilityService->calculate(
                $employee,
                $payrollMonthImmutable,
                $calculationDate,
                (int) $shop->id,
                openingRecovery: $recoveryDebt,
                shopAllocatedRecovery: $shopAllocatedRecovery,
            );

            $decision = $this->salaryAvailabilityService->evaluateDecision($availability, 'salary', $amount);

            if ($decision->status !== 'allowed') {
                throw ValidationException::withMessages([
                    'amount' => $decision->reasons[0] ?? 'The salary payment cannot be more than the remaining client-shop salary.',
                ]);
            }

            $payrollRunItem = $this->payrollService->ensurePayrollRunItem(
                $employee,
                $month,
                $paidOn->copy()->endOfMonth(),
                (int) $actor->id,
            );

            try {
                $payment = ShopStaffPayment::query()->create([
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
                    'request_uuid' => $requestUuid,
                ]);

                $this->ownedShopAccountingService->postShopStaffPaymentToCashbook($payment, (int) $actor->id);
                $this->staffPaymentProjectionService?->syncPayment($payment, (int) $actor->id);

                return $payment->fresh(['employee', 'shop', 'payrollRunItem', 'cashbookLine.entry']);
            } catch (QueryException $e) {
                if ($requestUuid !== null && trim($requestUuid) !== '') {
                    $winner = ShopStaffPayment::query()->where('request_uuid', $requestUuid)->first();
                    if ($winner) {
                        $this->validateShopSalaryPaymentFingerprint($winner, $employee, $shop, $amount, $fundSource, $paidOn);
                        $this->staffPaymentProjectionService?->syncPayment($winner, (int) $actor->id);

                        return $winner->fresh(['employee', 'shop', 'payrollRunItem', 'cashbookLine.entry']);
                    }
                }
                throw $e;
            }
        }, 3);
    }

    public function recordManualShopStaffPayment(PayrollRunItem $payrollRunItem, Shop $shop, float $amount, string $paymentType, string $fundSource, Carbon $paidOn, User $actor, ?string $notes = null): ShopStaffPayment
    {
        $payrollRunItem->loadMissing(['employee', 'shopStaffPayments', 'payments']);

        $remainingShopPayable = $this->remainingShopPayable($payrollRunItem, $payrollRunItem->employee, $shop, $paidOn);

        if ($paymentType === 'salary' && $amount - $remainingShopPayable > 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'The shop salary payment cannot be more than the remaining client-shop salary.',
            ]);
        }

        return DB::transaction(function () use ($payrollRunItem, $shop, $amount, $paymentType, $fundSource, $paidOn, $actor, $notes): ShopStaffPayment {
            $payment = ShopStaffPayment::query()->create([
                'payroll_run_id' => $payrollRunItem->payroll_run_id,
                'payroll_run_item_id' => $payrollRunItem->id,
                'employee_id' => $payrollRunItem->employee_id,
                'shop_id' => $shop->id,
                'paid_by' => $actor->id,
                'paid_on' => $paidOn->toDateString(),
                'amount' => round($amount, 2),
                'payment_type' => $paymentType,
                'fund_source' => $fundSource,
                'status' => 'paid',
                'notes' => $notes,
            ]);

            $this->ownedShopAccountingService->postShopStaffPaymentToCashbook($payment, (int) $actor->id);
            $this->staffPaymentProjectionService?->syncPayment($payment, (int) $actor->id);

            return $payment->fresh(['employee', 'shop', 'payrollRunItem', 'cashbookLine.entry']);
        });
    }

    public function requestOrPayAdvance(Employee $employee, Shop $shop, float $amount, string $fundSource, Carbon $requestedOn, User $actor, ?string $note = null, ?string $requestUuid = null): EmployeeAdvanceRequest
    {
        if ($requestedOn->day === $requestedOn->daysInMonth) {
            throw ValidationException::withMessages([
                'requested_on' => 'Advances cannot be given on the last day of the month. Use salary payment instead.',
            ]);
        }

        $this->ensureShopAdvanceEmployee($employee, $shop, $requestedOn);

        $month = $requestedOn->copy()->startOfMonth();
        $payrollMonth = CarbonImmutable::parse($month->toDateString());
        $calculationDate = CarbonImmutable::parse($requestedOn->toDateString());

        return DB::transaction(function () use ($employee, $shop, $amount, $fundSource, $requestedOn, $actor, $note, $payrollMonth, $calculationDate, $month, $requestUuid): EmployeeAdvanceRequest {
            Employee::query()->where('id', $employee->id)->lockForUpdate()->first();

            if ($requestUuid !== null && trim($requestUuid) !== '') {
                $existing = EmployeeAdvanceRequest::query()->where('request_uuid', $requestUuid)->lockForUpdate()->first();
                if ($existing) {
                    $this->validateAdvanceRequestFingerprint($existing, $employee, $shop, $amount, $fundSource, $requestedOn, $month);

                    return $existing->fresh(['employee', 'shop', 'shopStaffPayment', 'requestedBy', 'reviewedBy']);
                }
            }

            EmployeeAdvanceRequest::query()
                ->where('employee_id', $employee->id)
                ->whereDate('payroll_month', $month->toDateString())
                ->lockForUpdate()
                ->get();

            ShopStaffPayment::query()
                ->where('employee_id', $employee->id)
                ->whereDate('paid_on', '>=', $month->toDateString())
                ->lockForUpdate()
                ->get();

            $recoveryDebt = $this->resolveEmployeeRecoveryDebt($employee, $payrollMonth);
            $shopAllocatedRecovery = ($recoveryDebt !== null && $recoveryDebt <= 0.0001) ? 0.0 : null;

            $availability = $this->salaryAvailabilityService->calculate(
                $employee,
                $payrollMonth,
                $calculationDate,
                (int) $shop->id,
                openingRecovery: $recoveryDebt,
                shopAllocatedRecovery: $shopAllocatedRecovery,
            );

            $availableAmount = (float) ($availability->shopAvailableAdvance ?? $availability->employeeWideAvailableAdvance ?? 0.0);
            $earnedAmount = (float) ($availability->shopEarned ?? $availability->employeeWideEarned);
            $alreadyAdvancedAmount = (float) ($availability->shopAdvancesPaid ?? $availability->employeeWideAdvancesPaid);
            $eligibleAmount = round($earnedAmount * 0.5, 2);

            $decision = $this->salaryAvailabilityService->evaluateDecision($availability, 'advance', $amount);
            $status = $decision->status === 'allowed' ? 'approved' : 'pending';

            $rule = EmployeeAdvanceRule::activeRule();

            try {
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
                    'request_uuid' => $requestUuid,
                    'rule_snapshot' => [
                        'minimum_present_days' => (int) $rule->minimum_present_days,
                        'advance_percent' => (float) $rule->advance_percent,
                        'present_days' => $availability->presentDays,
                        'earned_amount' => $earnedAmount,
                        'eligible_amount' => $eligibleAmount,
                        'already_advanced_amount' => $alreadyAdvancedAmount,
                        'available_amount' => $availableAmount,
                        'data_quality_reasons' => $availability->dataQualityReasons,
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
            } catch (QueryException $e) {
                if ($requestUuid !== null && trim($requestUuid) !== '') {
                    $winner = EmployeeAdvanceRequest::query()->where('request_uuid', $requestUuid)->first();
                    if ($winner && ($winner->status !== 'approved' || $winner->shop_staff_payment_id !== null || $winner->payroll_payment_id !== null)) {
                        $this->validateAdvanceRequestFingerprint($winner, $employee, $shop, $amount, $fundSource, $requestedOn, $month);

                        return $winner->fresh(['employee', 'shop', 'shopStaffPayment', 'requestedBy', 'reviewedBy']);
                    }
                }
                throw $e;
            }
        }, 3);
    }

    public function review(
        EmployeeAdvanceRequest $advanceRequest,
        string $decision,
        float $approvedAmount,
        User $actor,
        ?string $note = null,
        ?string $finalFundSource = null,
        ?int $companyAccountId = null,
    ): EmployeeAdvanceRequest {
        return DB::transaction(function () use ($advanceRequest, $decision, $approvedAmount, $actor, $note, $finalFundSource, $companyAccountId): EmployeeAdvanceRequest {
            /** @var EmployeeAdvanceRequest $advanceRequest */
            $advanceRequest = EmployeeAdvanceRequest::query()
                ->whereKey($advanceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($advanceRequest->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Only pending advance requests can be reviewed.']);
            }

            if ($decision === 'reject') {
                if (empty(trim((string) $note))) {
                    throw ValidationException::withMessages(['review_note' => 'A review note is required when rejecting an advance request.']);
                }

                $advanceRequest->forceFill([
                    'status' => 'rejected',
                    'approved_amount' => null,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                    'review_note' => $note,
                ])->save();

                return $advanceRequest->fresh(['employee', 'shop', 'requestedBy', 'reviewedBy']);
            }

            if ($approvedAmount <= 0.0) {
                throw ValidationException::withMessages(['approved_amount' => 'The approved amount must be greater than zero.']);
            }

            if ($approvedAmount - (float) $advanceRequest->requested_amount > 0.009) {
                throw ValidationException::withMessages(['approved_amount' => 'The approved amount cannot exceed the requested amount.']);
            }

            $fundSource = $finalFundSource ?? $advanceRequest->fund_source;
            $isCompanyFunded = in_array($fundSource, ['company_cash', 'company_bank'], true);

            if ($isCompanyFunded) {
                if ($companyAccountId === null) {
                    throw ValidationException::withMessages(['company_account_id' => 'A company account is required for company-funded advances.']);
                }

                $companyAccount = CompanyAccount::query()->findOrFail($companyAccountId);
                if (! $companyAccount->enabled) {
                    throw ValidationException::withMessages(['company_account_id' => 'The selected company account is disabled or inactive.']);
                }

                if ($fundSource === 'company_cash' && $companyAccount->account_type !== 'cash') {
                    throw ValidationException::withMessages(['company_account_id' => 'The selected company account does not match the payment method.']);
                }

                if ($fundSource === 'company_bank' && $companyAccount->account_type !== 'bank') {
                    throw ValidationException::withMessages(['company_account_id' => 'The selected company account does not match the payment method.']);
                }
            } else {
                if ($companyAccountId !== null) {
                    throw ValidationException::withMessages(['company_account_id' => 'Company account must not be specified for shop-funded advances.']);
                }
            }

            $payrollMonth = CarbonImmutable::parse($advanceRequest->payroll_month->toDateString());
            $calcDate = CarbonImmutable::parse(($advanceRequest->requested_on ?? today())->toDateString());
            $recoveryDebt = $this->resolveEmployeeRecoveryDebt($advanceRequest->employee, $payrollMonth);
            $shopAllocatedRecovery = ($recoveryDebt !== null && $recoveryDebt <= 0.0001) ? 0.0 : null;
            $latestAvailability = $this->salaryAvailabilityService->calculate(
                $advanceRequest->employee,
                $payrollMonth,
                $calcDate,
                (int) $advanceRequest->shop_id,
                openingRecovery: $recoveryDebt,
                shopAllocatedRecovery: $shopAllocatedRecovery,
            );

            $reviewSnapshot = [
                'approver_id' => $actor->id,
                'approver_name' => $actor->name,
                'approved_at' => now()->toIso8601String(),
                'requested_source' => $advanceRequest->fund_source,
                'final_source' => $fundSource,
                'company_account_id' => $companyAccountId,
                'approved_amount' => round($approvedAmount, 2),
                'current_present_days' => $latestAvailability->presentDays,
                'current_earned_salary' => $latestAvailability->shopEarned ?? $latestAvailability->employeeWideEarned,
                'current_advances_paid' => $latestAvailability->shopAdvancesPaid ?? $latestAvailability->employeeWideAdvancesPaid,
                'current_salary_paid' => $latestAvailability->shopSalaryPaid ?? $latestAvailability->employeeWideSalaryPaid,
                'opening_recovery' => $latestAvailability->shopAllocatedRecovery ?? $latestAvailability->employeeWideOpeningRecovery,
                'current_available_amount' => $latestAvailability->shopAvailableAdvance ?? $latestAvailability->employeeWideAvailableAdvance ?? 0.0,
                'data_quality_reasons' => $latestAvailability->dataQualityReasons,
            ];

            $advanceRequest->forceFill([
                'status' => 'approved',
                'approved_amount' => round($approvedAmount, 2),
                'approved_fund_source' => $fundSource,
                'approved_company_account_id' => $companyAccountId,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                'review_snapshot' => $reviewSnapshot,
            ])->save();

            if ($isCompanyFunded) {
                $payrollRunItem = $this->payrollService->ensurePayrollRunItem(
                    $advanceRequest->employee,
                    $advanceRequest->payroll_month->copy()->startOfMonth(),
                    $advanceRequest->payroll_month->copy()->endOfMonth(),
                    (int) $actor->id,
                );

                $paymentMethod = $fundSource === 'company_cash' ? 'cash' : 'bank';

                $payrollPayment = $this->payrollPaymentService->record(
                    payrollRunItem: $payrollRunItem,
                    amount: round($approvedAmount, 2),
                    paymentMethod: $paymentMethod,
                    paymentType: 'advance',
                    paidOn: $advanceRequest->requested_on ? Carbon::parse($advanceRequest->requested_on->toDateString()) : today(),
                    actor: $actor,
                    notes: $note,
                    shop: $advanceRequest->shop,
                    fundSource: $fundSource,
                    advanceRequestId: $advanceRequest->id,
                    allowAdvanceOverage: true,
                    companyAccountId: $companyAccountId,
                    requestUuid: $advanceRequest->request_uuid ? (string) Str::uuid() : null,
                );

                $advanceRequest->forceFill(['payroll_payment_id' => $payrollPayment->id])->save();
            } else {
                $payment = $this->payApprovedAdvance($advanceRequest->fresh(['employee', 'shop']), $actor);
                $advanceRequest->forceFill(['shop_staff_payment_id' => $payment->id])->save();
            }

            return $advanceRequest->fresh(['employee', 'shop', 'shopStaffPayment', 'payrollPayment', 'requestedBy', 'reviewedBy']);
        }, 3);
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

        $existingPayment = ShopStaffPayment::query()->where('employee_advance_request_id', $advanceRequest->id)->first();
        if ($existingPayment) {
            return $existingPayment->fresh(['employee', 'shop', 'advanceRequest', 'cashbookLine.entry']);
        }

        $payment = ShopStaffPayment::query()->create([
            'payroll_run_id' => $payrollRunItem->payroll_run_id,
            'payroll_run_item_id' => $payrollRunItem->id,
            'employee_id' => $advanceRequest->employee_id,
            'shop_id' => $advanceRequest->shop_id,
            'employee_advance_request_id' => $advanceRequest->id,
            'paid_by' => $actor->id,
            'paid_on' => $paidOn->toDateString(),
            'amount' => round((float) ($advanceRequest->approved_amount ?? $advanceRequest->requested_amount), 2),
            'payment_type' => 'advance',
            'fund_source' => $advanceRequest->approved_fund_source ?? $advanceRequest->fund_source,
            'status' => 'paid',
            'notes' => $advanceRequest->request_note,
            'request_uuid' => $advanceRequest->request_uuid ? (string) Str::uuid() : null,
        ]);

        $this->ownedShopAccountingService->postShopStaffPaymentToCashbook($payment, (int) $actor->id);
        $this->staffPaymentProjectionService?->syncPayment($payment, (int) $actor->id);

        return $payment->fresh(['employee', 'shop', 'advanceRequest', 'cashbookLine.entry']);
    }

    private function ensureShopEmployee(Employee $employee, Shop $shop, Carbon $date): void
    {
        if (! $this->assignmentService->isAssignedToShopOn($employee, $shop, $date)) {
            throw ValidationException::withMessages([
                'employee_id' => 'This employee is not assigned to the selected shop for this date.',
            ]);
        }
    }

    private function ensureShopAdvanceEmployee(Employee $employee, Shop $shop, Carbon $date): void
    {
        if (! $this->assignmentService->hasWorkedAtShopOnOrBefore($employee, $shop, $date)) {
            throw ValidationException::withMessages([
                'employee_id' => 'This employee has not worked in the selected shop before this advance date.',
            ]);
        }
    }

    private function approvedAdvanceAmount(Employee $employee, Carbon $monthStart, ?int $shopId = null): float
    {
        return round((float) EmployeeAdvanceRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('payroll_month', $monthStart->toDateString())
            ->when($shopId !== null, fn ($query) => $query->where('shop_id', $shopId))
            ->where('status', 'approved')
            ->sum('approved_amount'), 2);
    }

    private function remainingShopPayable(PayrollRunItem $payrollRunItem, Employee $employee, Shop $shop, Carbon $date): float
    {
        $periodStart = $date->copy()->startOfMonth();
        $periodEnd = $date->copy()->endOfMonth();
        $payable = $this->payrollService->payableForAttendance($employee, $periodStart, $periodEnd, (int) $shop->id);
        $paid = (float) $payrollRunItem
            ->shopStaffPayments()
            ->where('shop_id', $shop->id)
            ->sum('amount');

        return round(max(0, (float) $payable['amount'] - $paid), 2);
    }

    public function updateAdvance(
        EmployeeAdvanceRequest $advanceRequest,
        float $amount,
        Carbon $requestedOn,
        User $actor,
        ?string $note = null
    ): EmployeeAdvanceRequest {
        return DB::transaction(function () use ($advanceRequest, $amount, $requestedOn, $actor, $note): EmployeeAdvanceRequest {
            /** @var EmployeeAdvanceRequest $advanceRequest */
            $advanceRequest = EmployeeAdvanceRequest::query()
                ->with(['employee', 'shop', 'shopStaffPayment', 'payrollPayment'])
                ->whereKey($advanceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = round($amount, 2);
            if ($amount <= 0.0) {
                throw ValidationException::withMessages([
                    'amount' => 'The advance amount must be greater than zero.',
                ]);
            }

            $month = $requestedOn->copy()->startOfMonth();

            if ($advanceRequest->status === 'pending') {
                $advanceRequest->fill([
                    'requested_amount' => $amount,
                    'requested_on' => $requestedOn->toDateString(),
                    'payroll_month' => $month->toDateString(),
                ]);
                if ($note !== null) {
                    $advanceRequest->request_note = $note;
                }
                $advanceRequest->save();
            } else {
                $advanceRequest->fill([
                    'requested_amount' => $amount,
                    'approved_amount' => $amount,
                    'requested_on' => $requestedOn->toDateString(),
                    'payroll_month' => $month->toDateString(),
                ]);
                if ($note !== null) {
                    $advanceRequest->review_note = $note;
                }
                $advanceRequest->save();

                if ($advanceRequest->shopStaffPayment instanceof ShopStaffPayment) {
                    $payment = $advanceRequest->shopStaffPayment;
                    $previousPaidOn = $payment->paid_on ? Carbon::parse($payment->paid_on->toDateString()) : null;
                    $shop = $payment->shop;

                    $payment->forceFill([
                        'amount' => $amount,
                        'paid_on' => $requestedOn->toDateString(),
                    ]);
                    if ($note !== null) {
                        $payment->notes = $note;
                    }
                    $payment->save();

                    if ($shop instanceof Shop && $shop->isOwnedAccountingEnabled()) {
                        $this->ownedShopAccountingService->postShopStaffPaymentToCashbook($payment, (int) $actor->id);
                        if ($previousPaidOn && $previousPaidOn->toDateString() !== $requestedOn->toDateString()) {
                            $this->ownedShopAccountingService->syncStoredClosingBalancesFromDate($shop, $previousPaidOn, (int) $actor->id);
                        }
                        $this->ownedShopAccountingService->syncStoredClosingBalancesFromDate($shop, $requestedOn, (int) $actor->id);
                    }

                    $this->staffPaymentProjectionService->syncPayment($payment, (int) $actor->id);
                }

                if ($advanceRequest->payrollPayment instanceof PayrollPayment) {
                    $payrollPayment = $advanceRequest->payrollPayment;
                    $payrollPayment->forceFill([
                        'amount' => $amount,
                        'paid_on' => $requestedOn->toDateString(),
                    ]);
                    if ($note !== null) {
                        $payrollPayment->notes = $note;
                    }
                    $payrollPayment->save();
                }
            }

            return $advanceRequest->fresh(['employee', 'shop', 'shopStaffPayment.cashbookLine.entry', 'payrollPayment', 'requestedBy', 'reviewedBy']);
        }, 3);
    }

    public function deleteAdvance(EmployeeAdvanceRequest $advanceRequest, User $actor): void
    {
        DB::transaction(function () use ($advanceRequest, $actor): void {
            /** @var EmployeeAdvanceRequest $advanceRequest */
            $advanceRequest = EmployeeAdvanceRequest::query()
                ->with(['employee', 'shop', 'shopStaffPayment', 'payrollPayment'])
                ->whereKey($advanceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($advanceRequest->shopStaffPayment instanceof ShopStaffPayment) {
                $payment = $advanceRequest->shopStaffPayment;
                $shop = $payment->shop;
                $paidOn = $payment->paid_on ? Carbon::parse($payment->paid_on->toDateString()) : null;

                $entryLines = ShopAccountingEntryLine::query()
                    ->where('source_type', ShopStaffPayment::class)
                    ->where('source_id', $payment->id)
                    ->get();

                foreach ($entryLines as $line) {
                    $line->delete();
                }

                if ($shop instanceof Shop && $paidOn && $shop->isOwnedAccountingEnabled()) {
                    $this->ownedShopAccountingService->syncStoredClosingBalancesFromDate($shop, $paidOn, (int) $actor->id);
                }

                $ledgerTx = ShopLedgerTransaction::query()
                    ->where('reference_type', ShopStaffPayment::class)
                    ->where('reference_id', $payment->id)
                    ->get();

                foreach ($ledgerTx as $tx) {
                    $txDate = $tx->business_date?->toDateString();
                    $txShopId = $tx->shop_id;
                    $tx->delete();
                    if ($txShopId && $txDate) {
                        $this->balanceCalculator->recalculate($txShopId, $txDate);
                    }
                }

                $payment->delete();
            }

            if ($advanceRequest->payrollPayment instanceof PayrollPayment) {
                $payrollPayment = $advanceRequest->payrollPayment;

                CompanyAccountStatementEntry::query()
                    ->where('source_type', PayrollPayment::class)
                    ->where('source_id', $payrollPayment->id)
                    ->update([
                        'source_type' => null,
                        'source_id' => null,
                        'journal_entry_id' => null,
                        'status' => 'unmatched',
                    ]);

                if ($payrollPayment->journal_entry_id) {
                    JournalEntry::query()->where('id', $payrollPayment->journal_entry_id)->delete();
                }

                $payrollPayment->delete();
            }

            $advanceRequest->delete();
        }, 3);
    }
}
