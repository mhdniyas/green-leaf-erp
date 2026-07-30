<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentAllocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CompanySummaryReportService
{
    /**
     * @return array<string, mixed>
     */
    public function report(Carbon $date): array
    {
        $selectedDate = $date->copy()->startOfDay();
        $monthStart = $selectedDate->copy()->startOfMonth();
        $monthEnd = $selectedDate->copy()->endOfMonth();
        $carryStart = $monthStart->copy()->subMonthsNoOverflow(5)->startOfMonth();

        return [
            'date' => $selectedDate,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'daily' => $this->periodSummary($selectedDate, $selectedDate),
            'monthly' => $this->periodSummary($monthStart, $monthEnd),
            'daily_rows' => $this->dailyRows($monthStart, $monthEnd),
            'carry_rows' => $this->carryRows($carryStart, $monthEnd),
            'supplier_due_rows' => $this->supplierDueRows($monthEnd),
            'shop_due_rows' => $this->shopDueRows($monthEnd),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function periodSummary(Carbon $startDate, Carbon $endDate): array
    {
        $supplierBills = $this->supplierBillsForPeriod($startDate, $endDate);
        $shopBills = $this->shopBillsForPeriod($startDate, $endDate);
        $supplierPayments = $this->supplierPaymentsForPeriod($startDate, $endDate);
        $shopCollections = $this->shopCollectionsForPeriod($startDate, $endDate);
        $shopDiscounts = $this->shopDiscountsForPeriod($startDate, $endDate);

        $expenseBills = round((float) $supplierBills->sum('amount'), 2);
        $expensePaid = round((float) $supplierPayments->sum('amount'), 2);
        $expenseDiscount = round((float) $supplierPayments->sum('discount_amount'), 2);
        $incomeBills = round($shopBills->sum(fn (ShopInvoice $invoice): float => $this->shopInvoiceGrossAmount($invoice)), 2);
        $incomeCollected = round((float) $shopCollections->sum('amount'), 2);
        $incomeDiscount = round((float) $shopDiscounts->sum('discount_total'), 2);

        return [
            'supplier_bill_count' => $supplierBills->count(),
            'shop_invoice_count' => $shopBills->count(),
            'expense_bills' => $expenseBills,
            'expense_paid' => $expensePaid,
            'expense_discount' => $expenseDiscount,
            'expense_pending' => round(max(0, $expenseBills - $expensePaid - $expenseDiscount), 2),
            'income_bills' => $incomeBills,
            'income_collected' => $incomeCollected,
            'income_discount' => $incomeDiscount,
            'income_pending' => round(max(0, $incomeBills - $incomeCollected - $incomeDiscount), 2),
            'net_billed' => round($incomeBills - $expenseBills, 2),
            'net_cash' => round($incomeCollected - $expensePaid, 2),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function dailyRows(Carbon $startDate, Carbon $endDate): Collection
    {
        $rows = collect();

        for ($cursor = $startDate->copy(); $cursor->lte($endDate); $cursor->addDay()) {
            $summary = $this->periodSummary($cursor, $cursor);

            $rows->push(array_merge($summary, [
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('d M'),
            ]));
        }

        return $rows;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function carryRows(Carbon $startMonth, Carbon $endMonth): Collection
    {
        $rows = collect();
        $supplierOpening = $this->supplierPendingBefore($startMonth);
        $shopOpening = $this->shopPendingBefore($startMonth);

        for ($cursor = $startMonth->copy(); $cursor->lte($endMonth); $cursor->addMonthNoOverflow()) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $summary = $this->periodSummary($monthStart, $monthEnd);

            $supplierClosing = round(max(
                0,
                $supplierOpening
                    + (float) $summary['expense_bills']
                    - (float) $summary['expense_paid']
                    - (float) $summary['expense_discount']
            ), 2);
            $shopClosing = round(max(
                0,
                $shopOpening
                    + (float) $summary['income_bills']
                    - (float) $summary['income_collected']
                    - (float) $summary['income_discount']
            ), 2);

            $rows->push(array_merge($summary, [
                'month' => $monthStart->format('Y-m'),
                'label' => $monthStart->format('M Y'),
                'supplier_opening_pending' => $supplierOpening,
                'supplier_closing_pending' => $supplierClosing,
                'shop_opening_pending' => $shopOpening,
                'shop_closing_pending' => $shopClosing,
            ]));

            $supplierOpening = $supplierClosing;
            $shopOpening = $shopClosing;
        }

        return $rows;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function supplierDueRows(Carbon $asOfDate): Collection
    {
        return PurchaseInvoice::query()
            ->with(['supplier', 'purchaserCart'])
            ->whereDate('created_at', '<=', $asOfDate)
            ->get()
            ->map(function (PurchaseInvoice $invoice): array {
                $discount = round((float) $invoice->payments()->sum('discount_amount'), 2);
                $paid = round((float) $invoice->payments()->sum('amount'), 2);
                $fallbackPaid = $paid > 0.0 ? $paid : round((float) $invoice->paid_amount, 2);
                $fallbackDiscount = $discount > 0.0 ? $discount : round((float) $invoice->discount_amount, 2);
                $pending = round(max(0, (float) $invoice->amount - $fallbackPaid - $fallbackDiscount), 2);

                return [
                    'invoice' => $invoice,
                    'party' => $invoice->supplier?->name ?? 'Supplier pending',
                    'business_date' => $this->supplierBillDate($invoice),
                    'bill_amount' => round((float) $invoice->amount, 2),
                    'paid_amount' => $fallbackPaid,
                    'discount_amount' => $fallbackDiscount,
                    'pending_amount' => $pending,
                ];
            })
            ->filter(fn (array $row): bool => (float) $row['pending_amount'] > 0.0)
            ->sortByDesc('pending_amount')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function shopDueRows(Carbon $asOfDate): Collection
    {
        return ShopInvoice::query()
            ->with(['shop'])
            ->whereDate('business_date', '<=', $asOfDate)
            ->where('balance_amount', '>', 0)
            ->orderByDesc('balance_amount')
            ->limit(20)
            ->get()
            ->map(fn (ShopInvoice $invoice): array => [
                'invoice' => $invoice,
                'party' => $invoice->shop?->name ?? 'Shop pending',
                'business_date' => $invoice->business_date?->toDateString(),
                'bill_amount' => $this->shopInvoiceGrossAmount($invoice),
                'paid_amount' => round((float) $invoice->paid_amount, 2),
                'discount_amount' => round((float) $invoice->discount_total, 2),
                'pending_amount' => round((float) $invoice->balance_amount, 2),
            ]);
    }

    private function supplierPendingBefore(Carbon $date): float
    {
        $billAmount = round((float) PurchaseInvoice::query()
            ->where(function (Builder $query) use ($date): void {
                $query
                    ->whereHas('purchaserCart', fn (Builder $cartQuery) => $cartQuery->whereDate('business_date', '<', $date))
                    ->orWhere(function (Builder $manualInvoiceQuery) use ($date): void {
                        $manualInvoiceQuery
                            ->whereNull('purchaser_cart_id')
                            ->whereDate('created_at', '<', $date);
                    });
            })
            ->sum('amount'), 2);
        $paidAmount = round((float) PurchaseInvoicePayment::query()
            ->whereDate('payment_date', '<', $date)
            ->sum('amount'), 2);
        $discountAmount = round((float) PurchaseInvoicePayment::query()
            ->whereDate('payment_date', '<', $date)
            ->sum('discount_amount'), 2);

        return round(max(0, $billAmount - $paidAmount - $discountAmount), 2);
    }

    private function shopPendingBefore(Carbon $date): float
    {
        $billAmount = round($this->shopBillsBefore($date)->sum(fn (ShopInvoice $invoice): float => $this->shopInvoiceGrossAmount($invoice)), 2);
        $paidAmount = round((float) ShopInvoicePaymentAllocation::query()
            ->whereDate('created_at', '<', $date)
            ->sum('amount'), 2);
        $discountAmount = round((float) ShopInvoice::query()
            ->whereNotNull('discount_approved_at')
            ->whereDate('discount_approved_at', '<', $date)
            ->sum('discount_total'), 2);

        return round(max(0, $billAmount - $paidAmount - $discountAmount), 2);
    }

    /**
     * @return Collection<int, PurchaseInvoice>
     */
    private function supplierBillsForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return PurchaseInvoice::query()
            ->with(['supplier', 'purchaserCart'])
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
            ->get();
    }

    /**
     * @return Collection<int, ShopInvoice>
     */
    private function shopBillsForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return ShopInvoice::query()
            ->with(['shop'])
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->get();
    }

    /**
     * @return Collection<int, ShopInvoice>
     */
    private function shopBillsBefore(Carbon $date): Collection
    {
        return ShopInvoice::query()
            ->whereDate('business_date', '<', $date)
            ->get();
    }

    /**
     * @return Collection<int, PurchaseInvoicePayment>
     */
    private function supplierPaymentsForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return PurchaseInvoicePayment::query()
            ->with(['purchaseInvoice.supplier'])
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->get();
    }

    /**
     * @return Collection<int, ShopInvoicePaymentAllocation>
     */
    private function shopCollectionsForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return ShopInvoicePaymentAllocation::query()
            ->with(['invoice.shop'])
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->get();
    }

    /**
     * @return Collection<int, ShopInvoice>
     */
    private function shopDiscountsForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return ShopInvoice::query()
            ->whereNotNull('discount_approved_at')
            ->whereDate('discount_approved_at', '>=', $startDate)
            ->whereDate('discount_approved_at', '<=', $endDate)
            ->get();
    }

    private function supplierBillDate(PurchaseInvoice $invoice): string
    {
        if ($invoice->purchaserCart?->business_date) {
            return $invoice->purchaserCart->business_date->toDateString();
        }

        return $invoice->created_at?->toDateString() ?? today()->toDateString();
    }

    private function shopInvoiceGrossAmount(ShopInvoice $invoice): float
    {
        return round(
            (float) $invoice->subtotal
                - (float) $invoice->shortage_total
                + (float) $invoice->excess_total,
            2
        );
    }
}
