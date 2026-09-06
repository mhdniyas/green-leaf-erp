<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\EmployeeAdvanceRequest;
use App\Models\PayrollPayment;
use App\Models\ShopStaffPayment;

class SalaryStage2PreflightService
{
    /**
     * Inspect historical payout links without changing any records.
     *
     * Company duplicates are reported separately because instalments are currently allowed.
     *
     * @return array{duplicate_shop_request_ids: array<int, int>, company_instalment_request_ids: array<int, int>, cross_table_request_ids: array<int, int>, conflicting_forward_link_request_ids: array<int, int>, conflicting_reverse_shop_payment_ids: array<int, int>, has_blocking_issues: bool}
     */
    public function inspect(): array
    {
        $duplicateShopRequestIds = $this->duplicateRequestIds(ShopStaffPayment::class);
        $companyInstalmentRequestIds = $this->duplicateRequestIds(PayrollPayment::class);
        $shopRequestIds = ShopStaffPayment::query()->whereNotNull('employee_advance_request_id')->distinct()->pluck('employee_advance_request_id');
        $crossTableRequestIds = PayrollPayment::query()
            ->whereNotNull('employee_advance_request_id')
            ->whereIn('employee_advance_request_id', $shopRequestIds)
            ->distinct()
            ->pluck('employee_advance_request_id')
            ->map(fn ($id): int => (int) $id)->values()->all();

        $conflictingForwardLinkRequestIds = EmployeeAdvanceRequest::query()
            ->where(function ($query): void {
                $query->whereNotNull('shop_staff_payment_id')->whereNotExists(function ($payment): void {
                    $payment->selectRaw('1')->from((new ShopStaffPayment)->getTable())
                        ->whereColumn('shop_staff_payments.id', 'employee_advance_requests.shop_staff_payment_id')
                        ->whereColumn('shop_staff_payments.employee_advance_request_id', 'employee_advance_requests.id');
                });
            })
            ->orWhere(function ($query): void {
                $query->whereNotNull('payroll_payment_id')->whereNotExists(function ($payment): void {
                    $payment->selectRaw('1')->from((new PayrollPayment)->getTable())
                        ->whereColumn('payroll_payments.id', 'employee_advance_requests.payroll_payment_id')
                        ->whereColumn('payroll_payments.employee_advance_request_id', 'employee_advance_requests.id');
                });
            })
            ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

        $conflictingReverseShopPaymentIds = ShopStaffPayment::query()
            ->whereNotNull('employee_advance_request_id')
            ->whereNotExists(function ($request): void {
                $request->selectRaw('1')->from((new EmployeeAdvanceRequest)->getTable())
                    ->whereColumn('employee_advance_requests.id', 'shop_staff_payments.employee_advance_request_id')
                    ->whereColumn('employee_advance_requests.shop_staff_payment_id', 'shop_staff_payments.id');
            })
            ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

        return [
            'duplicate_shop_request_ids' => $duplicateShopRequestIds,
            'company_instalment_request_ids' => $companyInstalmentRequestIds,
            'cross_table_request_ids' => $crossTableRequestIds,
            'conflicting_forward_link_request_ids' => $conflictingForwardLinkRequestIds,
            'conflicting_reverse_shop_payment_ids' => $conflictingReverseShopPaymentIds,
            'has_blocking_issues' => $duplicateShopRequestIds !== [] || $crossTableRequestIds !== [] || $conflictingForwardLinkRequestIds !== [] || $conflictingReverseShopPaymentIds !== [],
        ];
    }

    /** @return array<int, int> */
    private function duplicateRequestIds(string $paymentModel): array
    {
        return $paymentModel::query()
            ->whereNotNull('employee_advance_request_id')
            ->select('employee_advance_request_id')
            ->groupBy('employee_advance_request_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('employee_advance_request_id')
            ->map(fn ($id): int => (int) $id)->values()->all();
    }
}
