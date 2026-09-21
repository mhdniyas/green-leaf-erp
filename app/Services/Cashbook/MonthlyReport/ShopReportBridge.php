<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

use App\Models\Cashbook\ShopCashbookMonthConfigSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Services\Cashbook\PaymentsSettings\ShopPaymentsReportConfigService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

final class ShopReportBridge
{
    public function __construct(
        private readonly ShopPaymentsReportConfigService $reportConfigService,
    ) {}

    /**
     * Get all authorized client/owned shops (client_id IS NOT NULL).
     *
     * @return Collection<int, Shop>
     */
    public function getClientShops(): Collection
    {
        return Shop::query()
            ->with('client')
            ->whereNotNull('client_id')
            ->where('status', 'active')
            ->orderBy('client_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * Calculate normalized client and shop results for the given period.
     *
     * @return array{
     *     total_client_sales: float,
     *     total_product_expenses: float,
     *     total_operating_expenses: float,
     *     total_expenses: float,
     *     net_balance: float,
     *     sales_by_heading: array<string, float>,
     *     product_expenses_by_heading: array<string, float>,
     *     operating_expenses_by_heading: array<string, float>,
     *     daily_breakdown: array<string, array{
     *         client_sales: float,
     *         product_expenses: float,
     *         operating_expenses: float,
     *         total_expenses: float,
     *         balance: float,
     *         sales_by_heading: array<string, float>,
     *         product_expenses_by_heading: array<string, float>,
     *         operating_expenses_by_heading: array<string, float>
     *     }>,
     *     clients: array<int, array{
     *         client_id: int,
     *         client_name: string,
     *         sales: float,
     *         product_expenses: float,
     *         operating_expenses: float,
     *         total_expenses: float,
     *         balance: float,
     *         shops: array<int, array{
     *             shop_id: int,
     *             shop_name: string,
     *             shop_code: string,
     *             sales: float,
     *             product_expenses: float,
     *             operating_expenses: float,
     *             total_expenses: float,
     *             balance: float,
     *             headings: array<string, float>
     *         }>
     *     }>,
     *     transactions: BaseCollection<int, array<string, mixed>>
     * }
     */
    public function calculateClientShopsData(string $startDate, string $endDate, array $dates): array
    {
        $shops = $this->getClientShops();
        $shopIds = $shops->pluck('id')->all();

        if (empty($shopIds)) {
            $dailyBreakdown = [];
            foreach ($dates as $date) {
                $dailyBreakdown[$date] = [
                    'client_sales' => 0.0,
                    'product_expenses' => 0.0,
                    'operating_expenses' => 0.0,
                    'total_expenses' => 0.0,
                    'balance' => 0.0,
                    'sales_by_heading' => [
                        ReportHeadingDictionary::FRUITS_SALE => 0.0,
                        ReportHeadingDictionary::VEGETABLES_SALE => 0.0,
                        ReportHeadingDictionary::STATIONERY_SALE => 0.0,
                        ReportHeadingDictionary::OTHER_SALE => 0.0,
                    ],
                    'product_expenses_by_heading' => [
                        ReportHeadingDictionary::FRUITS_EXPENSE => 0.0,
                        ReportHeadingDictionary::VEGETABLES_EXPENSE => 0.0,
                        ReportHeadingDictionary::STATIONERY_EXPENSE => 0.0,
                        ReportHeadingDictionary::OTHER_PRODUCT_EXPENSE => 0.0,
                    ],
                    'operating_expenses_by_heading' => [
                        ReportHeadingDictionary::SALARY => 0.0,
                        ReportHeadingDictionary::RENT => 0.0,
                        ReportHeadingDictionary::VEHICLE_FUEL => 0.0,
                        ReportHeadingDictionary::FOOD_MESS => 0.0,
                        ReportHeadingDictionary::OTHER_EXPENSE => 0.0,
                    ],
                ];
            }

            return [
                'total_client_sales' => 0.0,
                'total_product_expenses' => 0.0,
                'total_operating_expenses' => 0.0,
                'total_expenses' => 0.0,
                'net_balance' => 0.0,
                'sales_by_heading' => [],
                'product_expenses_by_heading' => [],
                'operating_expenses_by_heading' => [],
                'daily_breakdown' => $dailyBreakdown,
                'clients' => [],
                'transactions' => collect(),
            ];
        }

        // Fetch all active settings for these shops
        $allSettings = ShopLedgerEntrySetting::query()
            ->with('entryType')
            ->whereIn('shop_id', $shopIds)
            ->get()
            ->groupBy('shop_id');

        // Fetch transactions for client shops
        $transactions = ShopLedgerTransaction::query()
            ->with(['entryType', 'shop.client'])
            ->whereIn('shop_id', $shopIds)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->whereNull('voided_at')
            ->orderBy('business_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $dailyBreakdown = [];
        foreach ($dates as $date) {
            $dailyBreakdown[$date] = [
                'client_sales' => 0.0,
                'product_expenses' => 0.0,
                'operating_expenses' => 0.0,
                'total_expenses' => 0.0,
                'balance' => 0.0,
                'sales_by_heading' => [
                    ReportHeadingDictionary::FRUITS_SALE => 0.0,
                    ReportHeadingDictionary::VEGETABLES_SALE => 0.0,
                    ReportHeadingDictionary::STATIONERY_SALE => 0.0,
                    ReportHeadingDictionary::OTHER_SALE => 0.0,
                ],
                'product_expenses_by_heading' => [
                    ReportHeadingDictionary::FRUITS_EXPENSE => 0.0,
                    ReportHeadingDictionary::VEGETABLES_EXPENSE => 0.0,
                    ReportHeadingDictionary::STATIONERY_EXPENSE => 0.0,
                    ReportHeadingDictionary::OTHER_PRODUCT_EXPENSE => 0.0,
                ],
                'operating_expenses_by_heading' => [
                    ReportHeadingDictionary::SALARY => 0.0,
                    ReportHeadingDictionary::RENT => 0.0,
                    ReportHeadingDictionary::VEHICLE_FUEL => 0.0,
                    ReportHeadingDictionary::FOOD_MESS => 0.0,
                    ReportHeadingDictionary::OTHER_EXPENSE => 0.0,
                ],
            ];
        }

        $salesByHeading = [
            ReportHeadingDictionary::FRUITS_SALE => 0.0,
            ReportHeadingDictionary::VEGETABLES_SALE => 0.0,
            ReportHeadingDictionary::STATIONERY_SALE => 0.0,
            ReportHeadingDictionary::OTHER_SALE => 0.0,
        ];
        $productExpensesByHeading = [
            ReportHeadingDictionary::FRUITS_EXPENSE => 0.0,
            ReportHeadingDictionary::VEGETABLES_EXPENSE => 0.0,
            ReportHeadingDictionary::STATIONERY_EXPENSE => 0.0,
            ReportHeadingDictionary::OTHER_PRODUCT_EXPENSE => 0.0,
        ];
        $operatingExpensesByHeading = [
            ReportHeadingDictionary::SALARY => 0.0,
            ReportHeadingDictionary::RENT => 0.0,
            ReportHeadingDictionary::VEHICLE_FUEL => 0.0,
            ReportHeadingDictionary::FOOD_MESS => 0.0,
            ReportHeadingDictionary::OTHER_EXPENSE => 0.0,
        ];

        $shopAccumulators = [];
        foreach ($shops as $shop) {
            $shopAccumulators[$shop->id] = [
                'shop_id' => (int) $shop->id,
                'client_id' => (int) $shop->client_id,
                'client_name' => (string) ($shop->client?->name ?? 'Unknown Client'),
                'shop_name' => (string) $shop->name,
                'shop_code' => (string) ($shop->code ?? (string) $shop->id),
                'sales' => 0.0,
                'product_expenses' => 0.0,
                'operating_expenses' => 0.0,
                'total_expenses' => 0.0,
                'balance' => 0.0,
                'headings' => [],
            ];
        }

        $normalizedTransactions = collect();
        $monthConfigCache = [];
        $snapshotCache = [];

        foreach ($transactions as $tx) {
            $shopId = (int) $tx->shop_id;
            $txDate = $tx->business_date ? $tx->business_date->format('Y-m-d') : $startDate;
            $month = Carbon::parse($txDate)->format('Y-m');

            $cacheKey = $shopId.'_'.$month;
            if (! array_key_exists($cacheKey, $monthConfigCache)) {
                $monthConfigCache[$cacheKey] = $this->reportConfigService->getConfigurationForMonth($shopId, $month);
                $snapshotCache[$cacheKey] = ShopCashbookMonthConfigSnapshot::query()
                    ->where('shop_id', $shopId)
                    ->where('month', $month)
                    ->first();
            }
            $monthHeadings = $monthConfigCache[$cacheKey];
            $monthSnapshot = $snapshotCache[$cacheKey];

            // Resolve snapshot setting if available
            $snapshotSetting = null;
            if ($monthSnapshot) {
                $snapSettings = $monthSnapshot->getSettings();
                foreach ($snapSettings as $sSetting) {
                    if ((int) ($sSetting['entry_type_id'] ?? 0) === (int) $tx->entry_type_id) {
                        $snapshotSetting = $sSetting;
                        break;
                    }
                }
            }

            $shopSetting = $allSettings->get($shopId)?->firstWhere('entry_type_id', $tx->entry_type_id);
            $bucket = null;

            // 1. Check if snapshot/month config explicitly maps this category or header
            if (! empty($monthHeadings)) {
                $settingId = $snapshotSetting ? ((int) ($snapshotSetting['id'] ?? 0)) : ($shopSetting ? (int) $shopSetting->id : null);
                $headerId = $snapshotSetting ? ($snapshotSetting['header_group_id'] ?? null) : ($shopSetting?->header_group_id ?: null);

                foreach ($monthHeadings as $hKey => $hConfig) {
                    $sources = (array) ($hConfig['sources'] ?? []);
                    foreach ($sources as $src) {
                        $sType = $src['type'] ?? '';
                        $sId = (int) ($src['id'] ?? 0);
                        if (($sType === 'category' && $settingId && $sId === $settingId) || ($sType === 'header' && $headerId && $sId === (int) $headerId)) {
                            $savedBucket = $snapshotSetting ? ($snapshotSetting['monthly_report_bucket'] ?? null) : $shopSetting?->monthly_report_bucket;
                            $bucket = match ($hKey) {
                                ShopPaymentsReportConfigService::HEADING_TOTAL_SALES => $savedBucket ?: ReportHeadingDictionary::OTHER_SALE,
                                ShopPaymentsReportConfigService::HEADING_RENT_EXPENSE => ReportHeadingDictionary::RENT,
                                ShopPaymentsReportConfigService::HEADING_CASH_PURCHASE => $savedBucket ?: ReportHeadingDictionary::OTHER_PRODUCT_EXPENSE,
                                ShopPaymentsReportConfigService::HEADING_OTHER_EXPENSE => $savedBucket ?: ReportHeadingDictionary::OTHER_EXPENSE,
                                default => null,
                            };
                            break 2;
                        }
                    }
                }
            }

            // 2. Fallback to snapshot setting bucket or live setting resolution
            if (! $bucket) {
                if ($snapshotSetting && ! empty($snapshotSetting['monthly_report_bucket'])) {
                    $bucket = $snapshotSetting['monthly_report_bucket'];
                } elseif ($shopSetting) {
                    $bucket = $shopSetting->resolveMonthlyReportBucket();
                }
            }

            if (! $bucket || $bucket === ReportHeadingDictionary::IGNORE) {
                continue;
            }

            $amount = round((float) $tx->amount, 2);

            $normalizedTransactions->push([
                'id' => $tx->id,
                'public_uuid' => $tx->public_uuid ?? (string) $tx->id,
                'business_date' => $txDate,
                'shop_id' => $shopId,
                'shop_name' => $shopAccumulators[$shopId]['shop_name'] ?? 'Shop #'.$shopId,
                'client_id' => $shopAccumulators[$shopId]['client_id'] ?? 0,
                'client_name' => $shopAccumulators[$shopId]['client_name'] ?? 'Client',
                'entry_type_id' => $tx->entry_type_id,
                'entry_type_name' => (string) ($shopSetting?->displayName() ?: $tx->entryType?->name ?: 'Entry #'.$tx->id),
                'report_bucket' => $bucket,
                'bucket_label' => ReportHeadingDictionary::getLabel($bucket),
                'amount' => $amount,
                'funding_source' => (string) ($tx->funding_source ?: 'sales'),
                'notes' => $tx->notes,
                'source_type' => 'Shop Cashbook',
                'reference' => $tx->reference_number ?? ('TX-'.$tx->id),
            ]);

            // Accumulate by heading and day
            if (ReportHeadingDictionary::isSale($bucket)) {
                $salesByHeading[$bucket] = round(($salesByHeading[$bucket] ?? 0.0) + $amount, 2);
                if (isset($dailyBreakdown[$txDate])) {
                    $dailyBreakdown[$txDate]['client_sales'] = round($dailyBreakdown[$txDate]['client_sales'] + $amount, 2);
                    $dailyBreakdown[$txDate]['sales_by_heading'][$bucket] = round(($dailyBreakdown[$txDate]['sales_by_heading'][$bucket] ?? 0.0) + $amount, 2);
                }
                if (isset($shopAccumulators[$shopId])) {
                    $shopAccumulators[$shopId]['sales'] = round($shopAccumulators[$shopId]['sales'] + $amount, 2);
                    $shopAccumulators[$shopId]['headings'][$bucket] = round(($shopAccumulators[$shopId]['headings'][$bucket] ?? 0.0) + $amount, 2);
                }
            } elseif (ReportHeadingDictionary::isProductExpense($bucket)) {
                $productExpensesByHeading[$bucket] = round(($productExpensesByHeading[$bucket] ?? 0.0) + $amount, 2);
                if (isset($dailyBreakdown[$txDate])) {
                    $dailyBreakdown[$txDate]['product_expenses'] = round($dailyBreakdown[$txDate]['product_expenses'] + $amount, 2);
                    $dailyBreakdown[$txDate]['product_expenses_by_heading'][$bucket] = round(($dailyBreakdown[$txDate]['product_expenses_by_heading'][$bucket] ?? 0.0) + $amount, 2);
                }
                if (isset($shopAccumulators[$shopId])) {
                    $shopAccumulators[$shopId]['product_expenses'] = round($shopAccumulators[$shopId]['product_expenses'] + $amount, 2);
                    $shopAccumulators[$shopId]['headings'][$bucket] = round(($shopAccumulators[$shopId]['headings'][$bucket] ?? 0.0) + $amount, 2);
                }
            } elseif (ReportHeadingDictionary::isOperatingExpense($bucket)) {
                $operatingExpensesByHeading[$bucket] = round(($operatingExpensesByHeading[$bucket] ?? 0.0) + $amount, 2);
                if (isset($dailyBreakdown[$txDate])) {
                    $dailyBreakdown[$txDate]['operating_expenses'] = round($dailyBreakdown[$txDate]['operating_expenses'] + $amount, 2);
                    $dailyBreakdown[$txDate]['operating_expenses_by_heading'][$bucket] = round(($dailyBreakdown[$txDate]['operating_expenses_by_heading'][$bucket] ?? 0.0) + $amount, 2);
                }
                if (isset($shopAccumulators[$shopId])) {
                    $shopAccumulators[$shopId]['operating_expenses'] = round($shopAccumulators[$shopId]['operating_expenses'] + $amount, 2);
                    $shopAccumulators[$shopId]['headings'][$bucket] = round(($shopAccumulators[$shopId]['headings'][$bucket] ?? 0.0) + $amount, 2);
                }
            }
        }

        // Finalize daily totals
        foreach ($dates as $date) {
            $dailyBreakdown[$date]['total_expenses'] = round($dailyBreakdown[$date]['product_expenses'] + $dailyBreakdown[$date]['operating_expenses'], 2);
            $dailyBreakdown[$date]['balance'] = round($dailyBreakdown[$date]['client_sales'] - $dailyBreakdown[$date]['total_expenses'], 2);
        }

        // Group shops by client
        $clientGroups = [];
        foreach ($shopAccumulators as $sAcc) {
            $sAcc['total_expenses'] = round($sAcc['product_expenses'] + $sAcc['operating_expenses'], 2);
            $sAcc['balance'] = round($sAcc['sales'] - $sAcc['total_expenses'], 2);

            $cId = $sAcc['client_id'];
            if (! isset($clientGroups[$cId])) {
                $clientGroups[$cId] = [
                    'client_id' => $cId,
                    'client_name' => $sAcc['client_name'],
                    'sales' => 0.0,
                    'product_expenses' => 0.0,
                    'operating_expenses' => 0.0,
                    'total_expenses' => 0.0,
                    'balance' => 0.0,
                    'shops' => [],
                ];
            }
            $clientGroups[$cId]['sales'] = round($clientGroups[$cId]['sales'] + $sAcc['sales'], 2);
            $clientGroups[$cId]['product_expenses'] = round($clientGroups[$cId]['product_expenses'] + $sAcc['product_expenses'], 2);
            $clientGroups[$cId]['operating_expenses'] = round($clientGroups[$cId]['operating_expenses'] + $sAcc['operating_expenses'], 2);
            $clientGroups[$cId]['total_expenses'] = round($clientGroups[$cId]['total_expenses'] + $sAcc['total_expenses'], 2);
            $clientGroups[$cId]['balance'] = round($clientGroups[$cId]['balance'] + $sAcc['balance'], 2);
            $clientGroups[$cId]['shops'][] = $sAcc;
        }

        $totalClientSales = round(array_sum($salesByHeading), 2);
        $totalProductExpenses = round(array_sum($productExpensesByHeading), 2);
        $totalOperatingExpenses = round(array_sum($operatingExpensesByHeading), 2);
        $totalExpenses = round($totalProductExpenses + $totalOperatingExpenses, 2);
        $netBalance = round($totalClientSales - $totalExpenses, 2);

        return [
            'total_client_sales' => $totalClientSales,
            'total_product_expenses' => $totalProductExpenses,
            'total_operating_expenses' => $totalOperatingExpenses,
            'total_expenses' => $totalExpenses,
            'net_balance' => $netBalance,
            'sales_by_heading' => $salesByHeading,
            'product_expenses_by_heading' => $productExpensesByHeading,
            'operating_expenses_by_heading' => $operatingExpensesByHeading,
            'daily_breakdown' => $dailyBreakdown,
            'clients' => array_values($clientGroups),
            'transactions' => collect($normalizedTransactions),
        ];
    }
}
