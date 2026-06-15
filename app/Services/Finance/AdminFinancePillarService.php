<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\PurchaseInvoice;
use App\Models\ShopInvoice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminFinancePillarService
{
    /**
     * @return array{
     *     start_date:string,
     *     end_date:string,
     *     vendor:array<string, mixed>,
     *     sales:array<string, mixed>,
     *     pending_credit_requests:Collection<int, Supplier>
     * }
     */
    public function forPeriod(Carbon $startDate, Carbon $endDate): array
    {
        $vendorInvoices = $this->vendorInvoicesForPeriod($startDate, $endDate);
        $shopInvoices = $this->shopInvoicesForPeriod($startDate, $endDate);

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'vendor' => $this->buildVendorPillar($vendorInvoices),
            'sales' => $this->buildSalesPillar($shopInvoices),
            'pending_credit_requests' => Supplier::query()
                ->with(['creditApprovalRequestedBy'])
                ->whereNotNull('credit_approval_requested_at')
                ->where('credit_approved', false)
                ->latest('credit_approval_requested_at')
                ->limit(8)
                ->get(),
        ];
    }

    /**
     * @return array{
     *     date:string,
     *     summary:array<string, mixed>,
     *     vendor_rows:Collection<int, array<string, mixed>>,
     *     invoices:Collection<int, PurchaseInvoice>
     * }
     */
    public function vendorDailyDetail(Carbon $date): array
    {
        $invoices = $this->vendorInvoicesForPeriod($date, $date);
        $vendorRows = $this->buildVendorRows($invoices);

        return [
            'date' => $date->toDateString(),
            'summary' => $this->vendorSummary($invoices, $vendorRows),
            'vendor_rows' => $vendorRows,
            'invoices' => $invoices->sortByDesc('created_at')->values(),
        ];
    }

    /**
     * @return array{
     *     date:string,
     *     summary:array<string, mixed>,
     *     shop_rows:Collection<int, array<string, mixed>>,
     *     invoices:Collection<int, ShopInvoice>
     * }
     */
    public function salesDailyDetail(Carbon $date): array
    {
        $invoices = $this->shopInvoicesForPeriod($date, $date);
        $shopRows = $this->buildSalesRows($invoices);

        return [
            'date' => $date->toDateString(),
            'summary' => $this->salesSummary($invoices, $shopRows),
            'shop_rows' => $shopRows,
            'invoices' => $invoices->sortByDesc('id')->values(),
        ];
    }

    /**
     * @return Collection<int, PurchaseInvoice>
     */
    private function vendorInvoicesForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return PurchaseInvoice::query()
            ->with(['supplier.creditApprovalRequestedBy', 'supplier.creditApprovedBy', 'purchaserCart', 'goodsReceived'])
            ->where(function (Builder $query) use ($startDate, $endDate): void {
                $query
                    ->whereHas('purchaserCart', function (Builder $cartQuery) use ($startDate, $endDate): void {
                        $cartQuery
                            ->whereDate('business_date', '>=', $startDate)
                            ->whereDate('business_date', '<=', $endDate);
                    })
                    ->orWhere(function (Builder $manualInvoiceQuery) use ($startDate, $endDate): void {
                        $manualInvoiceQuery
                            ->whereNull('purchaser_cart_id')
                            ->whereDate('created_at', '>=', $startDate)
                            ->whereDate('created_at', '<=', $endDate);
                    });
            })
            ->latest('created_at')
            ->get();
    }

    /**
     * @return Collection<int, ShopInvoice>
     */
    private function shopInvoicesForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return ShopInvoice::query()
            ->with(['shop', 'order'])
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->latest('business_date')
            ->latest('id')
            ->get();
    }

    /**
     * @param  Collection<int, PurchaseInvoice>  $invoices
     * @return array<string, mixed>
     */
    private function buildVendorPillar(Collection $invoices): array
    {
        $vendorRows = $this->buildVendorRows($invoices);

        return [
            'summary' => $this->vendorSummary($invoices, $vendorRows),
            'rows' => $vendorRows,
            'chart' => $this->chartRows($vendorRows, 'vendor'),
            'daily_rows' => $this->buildVendorDailyRows($invoices),
        ];
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @return array<string, mixed>
     */
    private function buildSalesPillar(Collection $invoices): array
    {
        $salesRows = $this->buildSalesRows($invoices);

        return [
            'summary' => $this->salesSummary($invoices, $salesRows),
            'rows' => $salesRows,
            'chart' => $this->chartRows($salesRows, 'shop'),
            'daily_rows' => $this->buildSalesDailyRows($invoices),
        ];
    }

    /**
     * @param  Collection<int, PurchaseInvoice>  $invoices
     * @return Collection<int, array<string, mixed>>
     */
    private function buildVendorRows(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy(fn (PurchaseInvoice $invoice): string => (string) ($invoice->supplier_id ?? 'pending'))
            ->map(function (Collection $vendorInvoices): array {
                /** @var PurchaseInvoice $latestInvoice */
                $latestInvoice = $vendorInvoices->sortByDesc('created_at')->first();
                $vendorTotal = round((float) $vendorInvoices->sum('amount'), 2);
                $vendorPaid = round((float) $vendorInvoices->sum('paid_amount'), 2);
                $vendorOutstanding = round(max(0, $vendorTotal - $vendorPaid), 2);

                return [
                    'vendor' => $latestInvoice->supplier,
                    'invoice_count' => $vendorInvoices->count(),
                    'total_amount' => $vendorTotal,
                    'paid_amount' => $vendorPaid,
                    'outstanding_amount' => $vendorOutstanding,
                    'latest_invoice' => $latestInvoice,
                    'status' => $vendorOutstanding > 0 ? 'Due' : 'Settled',
                ];
            })
            ->sortByDesc('outstanding_amount')
            ->values();
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @return Collection<int, array<string, mixed>>
     */
    private function buildSalesRows(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy(fn (ShopInvoice $invoice): string => (string) ($invoice->shop_id ?? 'pending'))
            ->map(function (Collection $shopInvoices): array {
                /** @var ShopInvoice $latestInvoice */
                $latestInvoice = $shopInvoices->sortByDesc('id')->first();
                $shopTotal = round((float) $shopInvoices->sum('final_total'), 2);
                $shopPaid = round((float) $shopInvoices->sum('paid_amount'), 2);
                $shopOutstanding = round((float) $shopInvoices->sum('balance_amount'), 2);

                return [
                    'shop' => $latestInvoice->shop,
                    'invoice_count' => $shopInvoices->count(),
                    'total_amount' => $shopTotal,
                    'paid_amount' => $shopPaid,
                    'outstanding_amount' => $shopOutstanding,
                    'latest_invoice' => $latestInvoice,
                    'status' => $shopOutstanding > 0 ? 'Due' : 'Settled',
                ];
            })
            ->sortByDesc('outstanding_amount')
            ->values();
    }

    /**
     * @param  Collection<int, PurchaseInvoice>  $invoices
     * @param  Collection<int, array<string, mixed>>  $vendorRows
     * @return array<string, mixed>
     */
    private function vendorSummary(Collection $invoices, Collection $vendorRows): array
    {
        $totalAmount = round((float) $invoices->sum('amount'), 2);
        $paidAmount = round((float) $invoices->sum('paid_amount'), 2);
        $outstandingAmount = round(max(0, $totalAmount - $paidAmount), 2);

        return [
            'label' => 'Vendor Reports',
            'count' => $vendorRows->count(),
            'invoice_count' => $invoices->count(),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'settlement_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @param  Collection<int, array<string, mixed>>  $salesRows
     * @return array<string, mixed>
     */
    private function salesSummary(Collection $invoices, Collection $salesRows): array
    {
        $totalAmount = round((float) $invoices->sum('final_total'), 2);
        $paidAmount = round((float) $invoices->sum('paid_amount'), 2);
        $outstandingAmount = round((float) $invoices->sum('balance_amount'), 2);

        return [
            'label' => 'Sales Reports',
            'count' => $salesRows->count(),
            'invoice_count' => $invoices->count(),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'settlement_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, PurchaseInvoice>  $invoices
     * @return Collection<int, array<string, mixed>>
     */
    private function buildVendorDailyRows(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy(fn (PurchaseInvoice $invoice): string => $this->vendorBusinessDate($invoice))
            ->map(function (Collection $dayInvoices, string $date): array {
                $creditAmount = round((float) $dayInvoices->sum('amount'), 2);
                $debitAmount = round((float) $dayInvoices->sum('paid_amount'), 2);
                $balanceAmount = round(max(0, $creditAmount - $debitAmount), 2);
                $topVendor = $dayInvoices
                    ->groupBy('supplier_id')
                    ->sortByDesc(fn (Collection $vendorInvoices): float => (float) $vendorInvoices->sum('amount'))
                    ->first()?->first()?->supplier;

                return [
                    'date' => $date,
                    'invoice_count' => $dayInvoices->count(),
                    'credit_amount' => $creditAmount,
                    'debit_amount' => $debitAmount,
                    'balance_amount' => $balanceAmount,
                    'status' => $balanceAmount > 0 ? 'Due' : 'Settled',
                    'lead_label' => $topVendor?->name ?? 'Mixed vendors',
                ];
            })
            ->sortKeysDesc()
            ->values();
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @return Collection<int, array<string, mixed>>
     */
    private function buildSalesDailyRows(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy(fn (ShopInvoice $invoice): string => Carbon::parse((string) $invoice->business_date)->toDateString())
            ->map(function (Collection $dayInvoices, string $date): array {
                $creditAmount = round((float) $dayInvoices->sum('final_total'), 2);
                $debitAmount = round((float) $dayInvoices->sum('paid_amount'), 2);
                $balanceAmount = round((float) $dayInvoices->sum('balance_amount'), 2);
                $topShop = $dayInvoices
                    ->groupBy('shop_id')
                    ->sortByDesc(fn (Collection $shopInvoices): float => (float) $shopInvoices->sum('final_total'))
                    ->first()?->first()?->shop;

                return [
                    'date' => $date,
                    'invoice_count' => $dayInvoices->count(),
                    'credit_amount' => $creditAmount,
                    'debit_amount' => $debitAmount,
                    'balance_amount' => $balanceAmount,
                    'status' => $balanceAmount > 0 ? 'Pending Collection' : 'Settled',
                    'lead_label' => $topShop?->name ?? 'Mixed shops',
                ];
            })
            ->sortKeysDesc()
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array{label:string, amount:float, width:float}>
     */
    private function chartRows(Collection $rows, string $entityKey): Collection
    {
        $topRows = $rows->sortByDesc('total_amount')->take(6)->values();
        $maxAmount = max(1.0, (float) $topRows->max('total_amount'));

        return $topRows->map(function (array $row) use ($entityKey, $maxAmount): array {
            $entity = $row[$entityKey] ?? null;

            return [
                'label' => (string) ($entity?->name ?? 'Pending'),
                'amount' => (float) $row['total_amount'],
                'width' => max(6.0, round(((float) $row['total_amount'] / $maxAmount) * 100, 1)),
            ];
        });
    }

    private function vendorBusinessDate(PurchaseInvoice $invoice): string
    {
        if ($invoice->purchaserCart?->business_date) {
            return Carbon::parse((string) $invoice->purchaserCart->business_date)->toDateString();
        }

        return $invoice->created_at->toDateString();
    }
}
