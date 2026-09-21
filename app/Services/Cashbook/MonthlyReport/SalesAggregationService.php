<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

use App\Models\DirectCompanySale;
use App\Models\PurchaseProductFilter;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\WarehouseSale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class SalesAggregationService
{
    /**
     * Calculate consolidated sales from Client/Owned Shops, Direct GL Bills, Direct Company Sales, and Warehouse Sales.
     *
     * @param array{
     *     total_client_sales: float,
     *     sales_by_heading: array<string, float>,
     *     daily_breakdown: array<string, mixed>
     * } $clientShopData
     * @return array{
     *     total_sales: float,
     *     client_sales: float,
     *     all_other_sales: float,
     *     direct_gl_bills_sales: float,
     *     direct_company_sales: float,
     *     warehouse_sales: float,
     *     fruits_sale: float,
     *     vegetables_sale: float,
     *     stationery_sale: float,
     *     other_sale: float,
     *     sales_by_category: array<string, float>,
     *     daily_breakdown: array<string, array{
     *         total_sales: float,
     *         client_sales: float,
     *         all_other_sales: float,
     *         direct_gl_bills: float,
     *         direct_company_sales: float,
     *         warehouse_sales: float,
     *         fruits_sale: float,
     *         vegetables_sale: float,
     *         stationery_sale: float,
     *         other_sale: float
     *     }>,
     *     direct_gl_invoices: Collection<int, ShopInvoice>,
     *     direct_company_sale_records: Collection<int, DirectCompanySale>,
     *     warehouse_sale_records: Collection<int, WarehouseSale>,
     *     unmapped_sales: float
     * }
     */
    public function calculate(string $startDate, string $endDate, array $dates, array $clientShopData): array
    {
        // 1. Resolve product filter memberships
        $activeFilters = PurchaseProductFilter::query()
            ->active()
            ->whereNotNull('monthly_report_group')
            ->with('filterItems')
            ->get();

        $fruitsProductIds = [];
        $vegProductIds = [];
        $stationeryProductIds = [];

        foreach ($activeFilters as $filter) {
            $productIds = $filter->getProductIds();
            match ($filter->monthly_report_group) {
                'fruits' => $fruitsProductIds = array_unique(array_merge($fruitsProductIds, $productIds)),
                'vegetables' => $vegProductIds = array_unique(array_merge($vegProductIds, $productIds)),
                'stationery' => $stationeryProductIds = array_unique(array_merge($stationeryProductIds, $productIds)),
                default => null,
            };
        }

        // Initialize daily buckets
        $dailyBreakdown = [];
        foreach ($dates as $date) {
            $clientDay = $clientShopData['daily_breakdown'][$date] ?? [];
            $clientSales = (float) ($clientDay['client_sales'] ?? 0.0);
            $clientHeadings = $clientDay['sales_by_heading'] ?? [];

            $dailyBreakdown[$date] = [
                'total_sales' => $clientSales,
                'client_sales' => $clientSales,
                'all_other_sales' => 0.0,
                'direct_gl_bills' => 0.0,
                'direct_company_sales' => 0.0,
                'warehouse_sales' => 0.0,
                'fruits_sale' => 0.0,
                'vegetables_sale' => 0.0,
                'stationery_sale' => 0.0,
                'other_sale' => 0.0,
            ];
        }

        $totalClientSales = (float) $clientShopData['total_client_sales'];
        $fruitsSale = 0.0;
        $vegSale = 0.0;
        $stationerySale = 0.0;
        $otherSale = 0.0;

        $totalDirectGl = 0.0;
        $totalDirectCompany = 0.0;
        $totalWarehouseSales = 0.0;
        $unmappedSales = 0.0;

        // 2. Shop Invoices (Allocate product line items for all active shop invoices)
        $allShopInvoices = ShopInvoice::query()
            ->with(['shop', 'items.product'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        $directGlInvoices = collect();

        foreach ($allShopInvoices as $inv) {
            $invTotal = round((float) $inv->final_total, 2);
            $invDate = $inv->business_date ? Carbon::parse($inv->business_date)->format('Y-m-d') : $startDate;
            $isDirectShop = ($inv->shop?->client_id === null);

            if ($isDirectShop) {
                $directGlInvoices->push($inv);
                $totalDirectGl = round($totalDirectGl + $invTotal, 2);

                if (isset($dailyBreakdown[$invDate])) {
                    $dailyBreakdown[$invDate]['direct_gl_bills'] = round($dailyBreakdown[$invDate]['direct_gl_bills'] + $invTotal, 2);
                    $dailyBreakdown[$invDate]['all_other_sales'] = round($dailyBreakdown[$invDate]['all_other_sales'] + $invTotal, 2);
                    $dailyBreakdown[$invDate]['total_sales'] = round($dailyBreakdown[$invDate]['total_sales'] + $invTotal, 2);
                }
            }

            // Allocate invoice items to categories
            $items = $inv->items;
            $itemCount = $items->count();

            if ($itemCount === 0 || $invTotal == 0.0) {
                $otherSale = round($otherSale + $invTotal, 2);
                $unmappedSales = round($unmappedSales + $invTotal, 2);
                if (isset($dailyBreakdown[$invDate])) {
                    $dailyBreakdown[$invDate]['other_sale'] = round($dailyBreakdown[$invDate]['other_sale'] + $invTotal, 2);
                }

                continue;
            }

            $grossSum = round((float) $items->sum(fn ($it) => (float) ($it->final_line_total ?: $it->line_subtotal ?: 0)), 2);
            $allocatedTotal = 0.0;
            $allocations = [];

            foreach ($items as $idx => $it) {
                $rawLine = (float) ($it->final_line_total ?: $it->line_subtotal ?: 0);
                $allocatedLine = ($grossSum > 0)
                    ? round(($invTotal * $rawLine) / $grossSum, 2)
                    : round($invTotal / $itemCount, 2);

                $allocations[$idx] = [
                    'product_id' => (int) $it->product_id,
                    'amount' => $allocatedLine,
                ];
                $allocatedTotal = round($allocatedTotal + $allocatedLine, 2);
            }

            // Adjust penny rounding residual to largest line
            $diff = round($invTotal - $allocatedTotal, 2);
            if ($diff != 0.0 && count($allocations) > 0) {
                $maxKey = 0;
                $maxAmount = -1.0;
                foreach ($allocations as $k => $alloc) {
                    if ($alloc['amount'] > $maxAmount) {
                        $maxAmount = $alloc['amount'];
                        $maxKey = $k;
                    }
                }
                $allocations[$maxKey]['amount'] = round($allocations[$maxKey]['amount'] + $diff, 2);
            }

            foreach ($allocations as $alloc) {
                $pid = $alloc['product_id'];
                $amt = $alloc['amount'];

                if (in_array($pid, $fruitsProductIds, true)) {
                    $fruitsSale = round($fruitsSale + $amt, 2);
                    if (isset($dailyBreakdown[$invDate])) {
                        $dailyBreakdown[$invDate]['fruits_sale'] = round($dailyBreakdown[$invDate]['fruits_sale'] + $amt, 2);
                    }
                } elseif (in_array($pid, $vegProductIds, true)) {
                    $vegSale = round($vegSale + $amt, 2);
                    if (isset($dailyBreakdown[$invDate])) {
                        $dailyBreakdown[$invDate]['vegetables_sale'] = round($dailyBreakdown[$invDate]['vegetables_sale'] + $amt, 2);
                    }
                } elseif (in_array($pid, $stationeryProductIds, true)) {
                    $stationerySale = round($stationerySale + $amt, 2);
                    if (isset($dailyBreakdown[$invDate])) {
                        $dailyBreakdown[$invDate]['stationery_sale'] = round($dailyBreakdown[$invDate]['stationery_sale'] + $amt, 2);
                    }
                } else {
                    $otherSale = round($otherSale + $amt, 2);
                    $unmappedSales = round($unmappedSales + $amt, 2);
                    if (isset($dailyBreakdown[$invDate])) {
                        $dailyBreakdown[$invDate]['other_sale'] = round($dailyBreakdown[$invDate]['other_sale'] + $amt, 2);
                    }
                }
            }
        }

        // 3. Direct Company Sales (DirectCompanySale where sale_status = 'confirmed')
        $directCompanySales = DirectCompanySale::query()
            ->with(['items.product'])
            ->where('sale_status', 'confirmed')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        foreach ($directCompanySales as $dcs) {
            $headerAmount = round((float) $dcs->amount, 2);
            $saleDate = $dcs->business_date ? Carbon::parse($dcs->business_date)->format('Y-m-d') : $startDate;
            $totalDirectCompany = round($totalDirectCompany + $headerAmount, 2);

            if (isset($dailyBreakdown[$saleDate])) {
                $dailyBreakdown[$saleDate]['direct_company_sales'] = round($dailyBreakdown[$saleDate]['direct_company_sales'] + $headerAmount, 2);
                $dailyBreakdown[$saleDate]['all_other_sales'] = round($dailyBreakdown[$saleDate]['all_other_sales'] + $headerAmount, 2);
                $dailyBreakdown[$saleDate]['total_sales'] = round($dailyBreakdown[$saleDate]['total_sales'] + $headerAmount, 2);
            }

            $items = $dcs->items;
            $itemCount = $items->count();

            if ($itemCount === 0 || $headerAmount == 0.0) {
                $otherSale = round($otherSale + $headerAmount, 2);
                $unmappedSales = round($unmappedSales + $headerAmount, 2);
                if (isset($dailyBreakdown[$saleDate])) {
                    $dailyBreakdown[$saleDate]['other_sale'] = round($dailyBreakdown[$saleDate]['other_sale'] + $headerAmount, 2);
                }

                continue;
            }

            $itemSum = round((float) $items->sum('line_total'), 2);
            $allocatedTotal = 0.0;
            $allocations = [];

            foreach ($items as $idx => $it) {
                $rawLine = (float) $it->line_total;
                $allocatedLine = ($itemSum > 0)
                    ? round(($headerAmount * $rawLine) / $itemSum, 2)
                    : round($headerAmount / $itemCount, 2);

                $allocations[$idx] = [
                    'product_id' => (int) $it->product_id,
                    'amount' => $allocatedLine,
                ];
                $allocatedTotal = round($allocatedTotal + $allocatedLine, 2);
            }

            $diff = round($headerAmount - $allocatedTotal, 2);
            if ($diff != 0.0 && count($allocations) > 0) {
                $maxKey = 0;
                $maxAmount = -1.0;
                foreach ($allocations as $k => $alloc) {
                    if ($alloc['amount'] > $maxAmount) {
                        $maxAmount = $alloc['amount'];
                        $maxKey = $k;
                    }
                }
                $allocations[$maxKey]['amount'] = round($allocations[$maxKey]['amount'] + $diff, 2);
            }

            foreach ($allocations as $alloc) {
                $pid = $alloc['product_id'];
                $amt = $alloc['amount'];

                if (in_array($pid, $fruitsProductIds, true)) {
                    $fruitsSale = round($fruitsSale + $amt, 2);
                    if (isset($dailyBreakdown[$saleDate])) {
                        $dailyBreakdown[$saleDate]['fruits_sale'] = round($dailyBreakdown[$saleDate]['fruits_sale'] + $amt, 2);
                    }
                } elseif (in_array($pid, $vegProductIds, true)) {
                    $vegSale = round($vegSale + $amt, 2);
                    if (isset($dailyBreakdown[$saleDate])) {
                        $dailyBreakdown[$saleDate]['vegetables_sale'] = round($dailyBreakdown[$saleDate]['vegetables_sale'] + $amt, 2);
                    }
                } elseif (in_array($pid, $stationeryProductIds, true)) {
                    $stationerySale = round($stationerySale + $amt, 2);
                    if (isset($dailyBreakdown[$saleDate])) {
                        $dailyBreakdown[$saleDate]['stationery_sale'] = round($dailyBreakdown[$saleDate]['stationery_sale'] + $amt, 2);
                    }
                } else {
                    $otherSale = round($otherSale + $amt, 2);
                    $unmappedSales = round($unmappedSales + $amt, 2);
                    if (isset($dailyBreakdown[$saleDate])) {
                        $dailyBreakdown[$saleDate]['other_sale'] = round($dailyBreakdown[$saleDate]['other_sale'] + $amt, 2);
                    }
                }
            }
        }

        // 4. Warehouse Sales (WarehouseSale where status = 'confirmed')
        $warehouseSales = WarehouseSale::query()
            ->with(['items.product', 'warehouse'])
            ->where('status', 'confirmed')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        foreach ($warehouseSales as $whs) {
            $headerTotal = round((float) $whs->total_amount, 2);
            $saleDate = $whs->business_date ? Carbon::parse($whs->business_date)->format('Y-m-d') : $startDate;
            $totalWarehouseSales = round($totalWarehouseSales + $headerTotal, 2);

            if (isset($dailyBreakdown[$saleDate])) {
                $dailyBreakdown[$saleDate]['warehouse_sales'] = round($dailyBreakdown[$saleDate]['warehouse_sales'] + $headerTotal, 2);
                $dailyBreakdown[$saleDate]['all_other_sales'] = round($dailyBreakdown[$saleDate]['all_other_sales'] + $headerTotal, 2);
                $dailyBreakdown[$saleDate]['total_sales'] = round($dailyBreakdown[$saleDate]['total_sales'] + $headerTotal, 2);
            }

            $items = $whs->items;
            $itemCount = $items->count();

            if ($itemCount === 0 || $headerTotal == 0.0) {
                $otherSale = round($otherSale + $headerTotal, 2);
                $unmappedSales = round($unmappedSales + $headerTotal, 2);
                if (isset($dailyBreakdown[$saleDate])) {
                    $dailyBreakdown[$saleDate]['other_sale'] = round($dailyBreakdown[$saleDate]['other_sale'] + $headerTotal, 2);
                }

                continue;
            }

            $itemSum = round((float) $items->sum('line_total'), 2);
            $allocatedTotal = 0.0;
            $allocations = [];

            foreach ($items as $idx => $it) {
                $rawLine = (float) $it->line_total;
                $allocatedLine = ($itemSum > 0)
                    ? round(($headerTotal * $rawLine) / $itemSum, 2)
                    : round($headerTotal / $itemCount, 2);

                $allocations[$idx] = [
                    'product_id' => (int) $it->product_id,
                    'amount' => $allocatedLine,
                ];
                $allocatedTotal = round($allocatedTotal + $allocatedLine, 2);
            }

            $diff = round($headerTotal - $allocatedTotal, 2);
            if ($diff != 0.0 && count($allocations) > 0) {
                $maxKey = 0;
                $maxAmount = -1.0;
                foreach ($allocations as $k => $alloc) {
                    if ($alloc['amount'] > $maxAmount) {
                        $maxAmount = $alloc['amount'];
                        $maxKey = $k;
                    }
                }
                $allocations[$maxKey]['amount'] = round($allocations[$maxKey]['amount'] + $diff, 2);
            }

            foreach ($allocations as $alloc) {
                $pid = $alloc['product_id'];
                $amt = $alloc['amount'];

                if (in_array($pid, $fruitsProductIds, true)) {
                    $fruitsSale = round($fruitsSale + $amt, 2);
                    if (isset($dailyBreakdown[$saleDate])) {
                        $dailyBreakdown[$saleDate]['fruits_sale'] = round($dailyBreakdown[$saleDate]['fruits_sale'] + $amt, 2);
                    }
                } elseif (in_array($pid, $vegProductIds, true)) {
                    $vegSale = round($vegSale + $amt, 2);
                    if (isset($dailyBreakdown[$saleDate])) {
                        $dailyBreakdown[$saleDate]['vegetables_sale'] = round($dailyBreakdown[$saleDate]['vegetables_sale'] + $amt, 2);
                    }
                } elseif (in_array($pid, $stationeryProductIds, true)) {
                    $stationerySale = round($stationerySale + $amt, 2);
                    if (isset($dailyBreakdown[$saleDate])) {
                        $dailyBreakdown[$saleDate]['stationery_sale'] = round($dailyBreakdown[$saleDate]['stationery_sale'] + $amt, 2);
                    }
                } else {
                    $otherSale = round($otherSale + $amt, 2);
                    $unmappedSales = round($unmappedSales + $amt, 2);
                    if (isset($dailyBreakdown[$saleDate])) {
                        $dailyBreakdown[$saleDate]['other_sale'] = round($dailyBreakdown[$saleDate]['other_sale'] + $amt, 2);
                    }
                }
            }
        }

        $allOtherSales = round($totalDirectGl + $totalDirectCompany + $totalWarehouseSales, 2);
        $totalSales = round($totalClientSales + $allOtherSales, 2);

        return [
            'total_sales' => $totalSales,
            'client_sales' => $totalClientSales,
            'all_other_sales' => $allOtherSales,
            'direct_gl_bills_sales' => $totalDirectGl,
            'direct_company_sales' => $totalDirectCompany,
            'warehouse_sales' => $totalWarehouseSales,
            'fruits_sale' => $fruitsSale,
            'vegetables_sale' => $vegSale,
            'stationery_sale' => $stationerySale,
            'other_sale' => $otherSale,
            'sales_by_category' => [
                ReportHeadingDictionary::FRUITS_SALE => $fruitsSale,
                ReportHeadingDictionary::VEGETABLES_SALE => $vegSale,
                ReportHeadingDictionary::STATIONERY_SALE => $stationerySale,
                ReportHeadingDictionary::OTHER_SALE => $otherSale,
            ],
            'daily_breakdown' => $dailyBreakdown,
            'direct_gl_invoices' => $directGlInvoices,
            'direct_company_sale_records' => $directCompanySales,
            'warehouse_sale_records' => $warehouseSales,
            'unmapped_sales' => $unmappedSales,
        ];
    }
}
