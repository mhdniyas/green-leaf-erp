<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ShopVendorReportService
{
    /**
     * Build vendor summary rows and overarching KPI summary for a shop.
     *
     * @param  array{
     *     start_date?: ?string,
     *     end_date?: ?string,
     *     date?: ?string,
     *     supplier_id?: ?int,
     *     purchaser_id?: ?int,
     *     payment_type?: ?string,
     *     search?: ?string,
     * } $filters
     * @return array{
     *     summary: array{
     *         total_purchase: float,
     *         cash_purchase: float,
     *         credit_purchase: float,
     *         credit_paid: float,
     *         credit_outstanding: float,
     *         vendor_count: int,
     *         invoice_count: int,
     *     },
     *     vendor_rows: Collection<int, array{
     *         supplier: Supplier,
     *         total_purchase: float,
     *         cash_purchase: float,
     *         credit_purchase: float,
     *         credit_paid: float,
     *         credit_outstanding: float,
     *         invoice_count: int,
     *         invoices: Collection<int, PurchaseInvoice>,
     *     }>
     * }
     */
    public function getShopVendorSummary(Shop $shop, array $filters = []): array
    {
        $query = PurchaseInvoice::query()
            ->with(['supplier', 'purchaserCart.items.product', 'shopVendorPayable'])
            ->notCancelled()
            ->where(function (Builder $q) use ($shop): void {
                $q->where('shop_id', $shop->id)
                    ->orWhereHas('purchaserCart', fn (Builder $cq) => $cq->where('destination_shop_id', $shop->id));
            });

        $this->applyFilters($query, $filters);

        /** @var Collection<int, PurchaseInvoice> $invoices */
        $invoices = $query->orderByDesc('created_at')->get();

        $vendorGroups = $invoices->groupBy('supplier_id');

        $vendorRows = $vendorGroups->map(function (Collection $vendorInvoices, int|string $supplierId): array {
            /** @var PurchaseInvoice $firstInvoice */
            $firstInvoice = $vendorInvoices->first();
            /** @var Supplier $supplier */
            $supplier = $firstInvoice->supplier;

            $cashPurchase = 0.0;
            $creditPurchase = 0.0;
            $creditPaid = 0.0;
            $creditOutstanding = 0.0;
            $totalPurchase = 0.0;

            foreach ($vendorInvoices as $inv) {
                $gross = (float) $inv->amount;
                $disc = (float) $inv->discount_amount;
                $net = max(0.0, round($gross - $disc, 2));
                $totalPurchase += $net;

                $isCredit = strcasecmp((string) $inv->payment_method, 'Credit') === 0;
                if ($isCredit) {
                    $creditPurchase += $net;
                    if ($inv->shopVendorPayable) {
                        $creditPaid += (float) $inv->shopVendorPayable->paid_amount;
                        $creditOutstanding += (float) $inv->shopVendorPayable->outstanding_amount;
                    } else {
                        $creditOutstanding += max(0.0, $net - (float) $inv->paid_amount);
                        $creditPaid += (float) $inv->paid_amount;
                    }
                } else {
                    $cashPurchase += $net;
                }
            }

            return [
                'supplier' => $supplier,
                'supplier_id' => (int) $supplierId,
                'supplier_name' => $supplier?->name ?? 'Unknown Vendor',
                'total_purchase' => round($totalPurchase, 2),
                'cash_purchase' => round($cashPurchase, 2),
                'credit_purchase' => round($creditPurchase, 2),
                'credit_paid' => round($creditPaid, 2),
                'credit_outstanding' => round($creditOutstanding, 2),
                'invoice_count' => $vendorInvoices->count(),
                'invoices' => $vendorInvoices,
            ];
        })->sortByDesc('credit_outstanding')->values();

        $summaryTotal = round((float) $vendorRows->sum('total_purchase'), 2);
        $summaryCash = round((float) $vendorRows->sum('cash_purchase'), 2);
        $summaryCredit = round((float) $vendorRows->sum('credit_purchase'), 2);
        $summaryCreditPaid = round((float) $vendorRows->sum('credit_paid'), 2);
        $summaryCreditOutstanding = round((float) $vendorRows->sum('credit_outstanding'), 2);

        return [
            'summary' => [
                'total_purchase' => $summaryTotal,
                'cash_purchase' => $summaryCash,
                'credit_purchase' => $summaryCredit,
                'credit_paid' => $summaryCreditPaid,
                'credit_outstanding' => $summaryCreditOutstanding,
                'vendor_count' => $vendorRows->count(),
                'invoice_count' => $invoices->count(),
            ],
            'vendor_rows' => $vendorRows,
        ];
    }

    /**
     * Get vendor drilldown details for a specific supplier and shop.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     supplier: Supplier,
     *     summary: array{
     *         total_purchase: float,
     *         cash_purchase: float,
     *         credit_purchase: float,
     *         credit_paid: float,
     *         credit_outstanding: float,
     *         invoice_count: int,
     *     },
     *     invoices: Collection<int, PurchaseInvoice>
     * }
     */
    public function getShopVendorDetail(Shop $shop, Supplier $supplier, array $filters = []): array
    {
        $filters['supplier_id'] = $supplier->id;
        $result = $this->getShopVendorSummary($shop, $filters);
        $vendorRow = $result['vendor_rows']->firstWhere('supplier_id', $supplier->id) ?? [
            'supplier' => $supplier,
            'total_purchase' => 0.0,
            'cash_purchase' => 0.0,
            'credit_purchase' => 0.0,
            'credit_paid' => 0.0,
            'credit_outstanding' => 0.0,
            'invoice_count' => 0,
            'invoices' => collect(),
        ];

        return [
            'supplier' => $supplier,
            'summary' => [
                'total_purchase' => $vendorRow['total_purchase'],
                'cash_purchase' => $vendorRow['cash_purchase'],
                'credit_purchase' => $vendorRow['credit_purchase'],
                'credit_paid' => $vendorRow['credit_paid'],
                'credit_outstanding' => $vendorRow['credit_outstanding'],
                'invoice_count' => $vendorRow['invoice_count'],
            ],
            'invoices' => $vendorRow['invoices'],
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['date'])) {
            $date = Carbon::parse((string) $filters['date'])->toDateString();
            $query->where(function (Builder $q) use ($date): void {
                $q->whereDate('created_at', $date)
                    ->orWhereHas('purchaserCart', fn (Builder $cq) => $cq->whereDate('business_date', $date));
            });
        } elseif (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $start = Carbon::parse((string) $filters['start_date'])->startOfDay();
            $end = Carbon::parse((string) $filters['end_date'])->endOfDay();
            $query->where(function (Builder $q) use ($start, $end): void {
                $q->whereBetween('created_at', [$start, $end])
                    ->orWhereHas('purchaserCart', fn (Builder $cq) => $cq->whereBetween('business_date', [$start->toDateString(), $end->toDateString()]));
            });
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        if (! empty($filters['purchaser_id'])) {
            $query->where('purchaser_submitted_by', (int) $filters['purchaser_id']);
        }

        if (! empty($filters['payment_type']) && $filters['payment_type'] !== 'all') {
            $query->where('payment_method', ucfirst(strtolower((string) $filters['payment_type'])));
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search): void {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }
    }
}
