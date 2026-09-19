<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoice;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ShopSalesReportService
{
    /**
     * Generate complete read-only Sales Report for a shop over the specified period.
     *
     * @return array{
     *     period: array{
     *         start: string,
     *         end: string,
     *         month: string,
     *         mode: string,
     *         formatted_range: string,
     *         label: string
     *     },
     *     summary: array{
     *         total_sales: float,
     *         total_rent: float,
     *         total_purchase: float,
     *         total_other_expense: float,
     *         total_expenses: float,
     *         net_total: float,
     *         gl_bills_total: float
     *     },
     *     daily_rows: array<int, array{
     *         date: string,
     *         formatted_date: string,
     *         day_name: string,
     *         sales: float,
     *         rent: float,
     *         purchase: float,
     *         other_expense: float,
     *         total_expenses: float,
     *         net_balance: float,
     *         details: array<int, array{
     *             id: int,
     *             entry_type_id: int,
     *             name: string,
     *             bucket: string,
     *             amount: float,
     *             funding_source: string,
     *             notes: ?string
     *         }>
     *     }>,
     *     bucket_totals: array<string, float>
     * }
     */
    public function generate(
        ShopLedgerProfile|Shop|int $shopOrId,
        string $startDate,
        string $endDate,
        string $periodMode = 'month',
        ?string $month = null
    ): array {
        $shopId = is_int($shopOrId) ? $shopOrId : (int) $shopOrId->shop_id;
        $monthStr = $month ?: Carbon::parse($startDate)->format('Y-m');

        // Load active entry settings for shop to map entry types to report buckets
        $settings = ShopLedgerEntrySetting::query()
            ->with('entryType')
            ->where('shop_id', $shopId)
            ->get();

        $settingsByEntryTypeId = $settings->keyBy('entry_type_id');

        // Fetch active ledger transactions in the period
        $transactions = ShopLedgerTransaction::query()
            ->with('entryType')
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->whereNull('voided_at')
            ->orderBy('business_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Informational reference: GL Bills (System Invoices)
        $glBillsTotal = round((float) ShopInvoice::query()
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->sum('final_total'), 2);

        $txsByDate = $transactions->groupBy(fn (ShopLedgerTransaction $tx): string => (string) ($tx->business_date?->toDateString() ?: $startDate));

        $periodDates = CarbonPeriod::create($startDate, $endDate);
        $dailyRows = [];

        $totalSales = 0.0;
        $totalRent = 0.0;
        $totalPurchase = 0.0;
        $totalOtherExpense = 0.0;

        foreach ($periodDates as $dateCarbon) {
            $dateStr = $dateCarbon->toDateString();
            $dayTxs = $txsByDate->get($dateStr, collect());

            $daySales = 0.0;
            $dayRent = 0.0;
            $dayPurchase = 0.0;
            $dayOtherExpense = 0.0;
            $dayDetails = [];

            foreach ($dayTxs as $tx) {
                /** @var ShopLedgerTransaction $tx */
                $setting = $settingsByEntryTypeId->get($tx->entry_type_id);
                $bucket = $setting ? $setting->resolveSalesReportBucket() : $this->fallbackBucketForTx($tx);

                $amount = round((float) $tx->amount, 2);

                if ($bucket === 'ignore') {
                    continue;
                }

                match ($bucket) {
                    'sales' => $daySales += $amount,
                    'rent' => $dayRent += $amount,
                    'purchase' => $dayPurchase += $amount,
                    'other_expense' => $dayOtherExpense += $amount,
                    default => null,
                };

                $dayDetails[] = [
                    'id' => $tx->id,
                    'entry_type_id' => $tx->entry_type_id,
                    'name' => (string) ($setting?->displayName() ?: $tx->entryType?->name ?: 'Entry #'.$tx->id),
                    'bucket' => $bucket,
                    'amount' => $amount,
                    'funding_source' => (string) ($tx->funding_source ?: 'sales'),
                    'notes' => $tx->notes,
                ];
            }

            $daySales = round($daySales, 2);
            $dayRent = round($dayRent, 2);
            $dayPurchase = round($dayPurchase, 2);
            $dayOtherExpense = round($dayOtherExpense, 2);
            $dayTotalExpenses = round($dayRent + $dayPurchase + $dayOtherExpense, 2);
            $dayNetBalance = round($daySales - $dayTotalExpenses, 2);

            $dailyRows[] = [
                'date' => $dateStr,
                'formatted_date' => $dateCarbon->format('d M Y'),
                'day_name' => $dateCarbon->format('l'),
                'sales' => $daySales,
                'rent' => $dayRent,
                'purchase' => $dayPurchase,
                'other_expense' => $dayOtherExpense,
                'total_expenses' => $dayTotalExpenses,
                'net_balance' => $dayNetBalance,
                'details' => $dayDetails,
            ];

            $totalSales += $daySales;
            $totalRent += $dayRent;
            $totalPurchase += $dayPurchase;
            $totalOtherExpense += $dayOtherExpense;
        }

        // Default sort latest date on top (descending)
        usort($dailyRows, fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        $totalSales = round($totalSales, 2);
        $totalRent = round($totalRent, 2);
        $totalPurchase = round($totalPurchase, 2);
        $totalOtherExpense = round($totalOtherExpense, 2);
        $totalExpenses = round($totalRent + $totalPurchase + $totalOtherExpense, 2);
        $netTotal = round($totalSales - $totalExpenses, 2);

        $formattedRange = $startDate === $endDate
            ? Carbon::parse($startDate)->format('d M Y')
            : Carbon::parse($startDate)->format('d M Y').' – '.Carbon::parse($endDate)->format('d M Y');

        $periodLabel = match ($periodMode) {
            'day' => 'Day: '.Carbon::parse($startDate)->format('d M Y'),
            'custom' => 'Custom: '.$formattedRange,
            default => Carbon::parse($monthStr.'-01')->format('F Y'),
        };

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'month' => $monthStr,
                'mode' => $periodMode,
                'formatted_range' => $formattedRange,
                'label' => $periodLabel,
            ],
            'summary' => [
                'total_sales' => $totalSales,
                'total_rent' => $totalRent,
                'total_purchase' => $totalPurchase,
                'total_other_expense' => $totalOtherExpense,
                'total_expenses' => $totalExpenses,
                'net_total' => $netTotal,
                'gl_bills_total' => $glBillsTotal,
            ],
            'daily_rows' => $dailyRows,
            'bucket_totals' => [
                'sales' => $totalSales,
                'rent' => $totalRent,
                'purchase' => $totalPurchase,
                'other_expense' => $totalOtherExpense,
            ],
        ];
    }

    private function fallbackBucketForTx(ShopLedgerTransaction $tx): string
    {
        $code = strtolower((string) ($tx->entryType?->code ?? ''));
        $category = strtolower((string) ($tx->entryType?->category ?? ''));

        if (in_array($category, ['transfer', 'settlement'], true) || in_array($code, [
            'sales_to_petty', 'company_to_petty', 'petty_to_company', 'sales_to_company',
            'company_to_shop', 'bank_to_petty', 'shop_to_supermarket', 'casio_delivery',
            'shop_paid_company', 'company_paid_shop', 'company_paid_vendor', 'petty_reimbursement',
        ], true)) {
            return 'ignore';
        }

        if (in_array($code, ['rent_expense', 'expense_rent', 'income_rent'], true)) {
            return 'rent';
        }

        if (in_array($code, ['vendor_purchase', 'vendor_purchase_cash', 'vendor_purchase_credit', 'cash_purchase', 'purchase_bill'], true)) {
            return 'purchase';
        }

        if ($tx->affects_sales || $tx->affects_income || $category === 'income' || $tx->direction === 'income') {
            return 'sales';
        }

        if ($tx->affects_expense || $category === 'expense' || $tx->direction === 'expense') {
            return 'other_expense';
        }

        return 'ignore';
    }
}
