<?php

declare(strict_types=1);

namespace App\Services\Cashbook\CashFlow\Sources;

use App\DTOs\Cashbook\MoneyMovement;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\PayrollPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EmployeeCashFlowSource implements CashFlowSourceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MoneyMovement>
     */
    public function forMonth(string $month, array $filters = []): Collection
    {
        $start = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
        $end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();

        $movements = collect();

        // 1. Query Payroll Payments (Salaries & Advances)
        $paymentsQuery = PayrollPayment::query()
            ->with(['employee', 'companyAccount', 'shop'])
            ->whereBetween('paid_on', [$start, $end]);

        if (! empty($filters['employee_id'])) {
            $paymentsQuery->where('employee_id', (int) $filters['employee_id']);
        }

        $payments = $paymentsQuery->orderBy('paid_on')->orderBy('id')->get();
        $linkedAdvanceRequestIds = [];

        foreach ($payments as $payment) {
            if ($payment->employee_advance_request_id) {
                $linkedAdvanceRequestIds[] = $payment->employee_advance_request_id;
            }

            $date = $payment->paid_on ? $payment->paid_on->toDateString() : $start;
            $employeeName = $payment->employee?->name ?? 'Employee #'.$payment->employee_id;
            $employeeId = (int) $payment->employee_id;
            $amount = (float) $payment->amount;

            if ($amount <= 0) {
                continue;
            }

            $fundSource = $payment->fund_source ?: 'company_cash';
            if ($payment->companyAccount) {
                $fromEntityName = $payment->companyAccount->name ?: ($payment->companyAccount->bank_name ?: 'Company Account');
                $fromEntityType = 'company_bank';
                $fromEntityId = $payment->companyAccount->id;
            } elseif ($payment->shop) {
                $fromEntityName = $payment->shop->name ?: 'Shop #'.$payment->shop_id;
                $fromEntityType = 'shop';
                $fromEntityId = $payment->shop->id;
            } else {
                $fromEntityName = 'Company Cash Vault';
                $fromEntityType = 'company_vault';
                $fromEntityId = null;
            }

            $isAdvance = str_contains(strtolower((string) $payment->payment_type), 'advance');
            $movementType = $isAdvance ? 'employee_advance' : 'salary_payment';

            $movements->push(new MoneyMovement(
                date: $date,
                sourceType: 'employee',
                sourceId: $payment->id,
                fromEntityType: $fromEntityType,
                fromEntityId: $fromEntityId,
                fromEntityName: $fromEntityName,
                toEntityType: 'employee',
                toEntityId: $employeeId,
                toEntityName: $employeeName,
                amount: $amount,
                movementType: $movementType,
                category: $isAdvance ? 'advance' : 'salary',
                referenceType: 'payroll_payment',
                referenceId: $payment->id,
                referenceNumber: $payment->reference,
                notes: $payment->notes ?: "{$movementType} to {$employeeName}",
                metadata: [
                    'employee_id' => $employeeId,
                    'payment_type' => $payment->payment_type,
                    'fund_source' => $fundSource,
                ]
            ));
        }

        // 2. Query Approved Advance Requests not already recorded in PayrollPayment
        $advancesQuery = EmployeeAdvanceRequest::query()
            ->with(['employee'])
            ->where('status', 'approved')
            ->whereBetween('requested_on', [$start, $end]);

        if (! empty($linkedAdvanceRequestIds)) {
            $advancesQuery->whereNotIn('id', $linkedAdvanceRequestIds);
        }

        if (! empty($filters['employee_id'])) {
            $advancesQuery->where('employee_id', (int) $filters['employee_id']);
        }

        $advances = $advancesQuery->get();

        foreach ($advances as $adv) {
            $date = $adv->requested_on ? $adv->requested_on->toDateString() : $start;
            $employeeName = $adv->employee?->name ?? 'Employee #'.$adv->employee_id;
            $employeeId = (int) $adv->employee_id;
            $amount = (float) ($adv->approved_amount > 0 ? $adv->approved_amount : $adv->requested_amount);

            if ($amount <= 0) {
                continue;
            }

            $movements->push(new MoneyMovement(
                date: $date,
                sourceType: 'employee',
                sourceId: $adv->id,
                fromEntityType: 'company_vault',
                fromEntityId: null,
                fromEntityName: 'Company Cash / Float',
                toEntityType: 'employee',
                toEntityId: $employeeId,
                toEntityName: $employeeName,
                amount: $amount,
                movementType: 'employee_advance',
                category: 'advance',
                referenceType: 'employee_advance_request',
                referenceId: $adv->id,
                referenceNumber: $adv->request_uuid,
                notes: $adv->request_note ?: "Advance approved for {$employeeName}",
                metadata: [
                    'employee_id' => $employeeId,
                    'status' => 'approved',
                ]
            ));
        }

        return $movements;
    }

    /**
     * Compute opening advance balance held with employees prior to startDate.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function openingBalances(string $startDate, array $filters = []): array
    {
        $priorAdvances = DB::table('employee_advance_requests')
            ->where('status', 'approved')
            ->whereDate('requested_on', '<', $startDate)
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('employee_id', (int) $filters['employee_id']))
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(COALESCE(approved_amount, requested_amount)) as total_advances')
            ->get()
            ->keyBy('employee_id');

        $employees = Employee::query()
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('id', (int) $filters['employee_id']))
            ->get();

        $balances = [];
        foreach ($employees as $emp) {
            $advAmt = (float) ($priorAdvances->get($emp->id)?->total_advances ?? 0);
            if ($advAmt > 0) {
                $balances[$emp->id] = [
                    'employee_id' => $emp->id,
                    'employee_name' => $emp->name,
                    'opening_advance' => round($advAmt, 2),
                ];
            }
        }

        return $balances;
    }
}
