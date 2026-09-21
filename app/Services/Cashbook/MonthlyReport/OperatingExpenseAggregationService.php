<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

use App\Models\Cashbook\CashbookMonthlyReportExpenseMapping;
use App\Models\CompanyAccountingEntry;
use App\Models\OtherExpense;
use App\Models\ProcurementExpense;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class OperatingExpenseAggregationService
{
    /**
     * @param  array<string, mixed>  $shopOperatingExpensesByDate
     * @return array{
     *     total_operating_expenses: float,
     *     salary: float,
     *     rent: float,
     *     vehicle_fuel: float,
     *     food_mess: float,
     *     other_expense: float,
     *     totals_by_heading: array<string, float>,
     *     daily_matrix: array<string, array{
     *         date: string,
     *         formatted_date: string,
     *         salary: float,
     *         rent: float,
     *         vehicle_fuel: float,
     *         food_mess: float,
     *         other_expense: float,
     *         total: float
     *     }>,
     *     summary_by_category: array<int, array{
     *         category_name: string,
     *         normalized_heading: string,
     *         heading_label: string,
     *         source_type: string,
     *         transaction_count: int,
     *         amount: float,
     *         percentage: float
     *     }>,
     *     detailed_rows: Collection<int, array<string, mixed>>
     * }
     */
    public function calculate(string $startDate, string $endDate, array $dates, array $shopOperatingExpensesByDate = [], ?Collection $shopTransactions = null): array
    {
        // 1. Load custom expense mappings
        $mappings = CashbookMonthlyReportExpenseMapping::all()
            ->groupBy('source_type')
            ->map(fn ($group) => $group->keyBy('source_key'));

        $procurementMappings = $mappings->get('procurement_expense_category', collect());
        $otherExpMappings = $mappings->get('other_expense_category', collect());
        $companyMappings = $mappings->get('company_accounting_category', collect());

        $detailedRows = collect();

        // 2. Add Shop Cashbook operating transactions
        if ($shopTransactions !== null) {
            foreach ($shopTransactions as $stx) {
                $bucket = $stx['report_bucket'];
                if (ReportHeadingDictionary::isOperatingExpense($bucket)) {
                    $detailedRows->push([
                        'id' => 'shop_'.$stx['id'],
                        'source_id' => $stx['id'],
                        'source_type' => 'Shop Cashbook',
                        'business_date' => $stx['business_date'],
                        'original_category' => $stx['entry_type_name'],
                        'report_bucket' => $bucket,
                        'heading_label' => ReportHeadingDictionary::getLabel($bucket),
                        'entity_name' => ($stx['client_name'] ? $stx['client_name'].' / ' : '').$stx['shop_name'],
                        'description' => $stx['notes'] ?: $stx['entry_type_name'],
                        'reference' => $stx['reference'],
                        'amount' => (float) $stx['amount'],
                        'purchaser_id' => null,
                        'purchaser_name' => null,
                        'funding_source' => $stx['funding_source'] ?? 'sales',
                    ]);
                }
            }
        }

        // 3. Procurement Expenses
        $procurementExpenses = ProcurementExpense::query()
            ->with(['purchaser'])
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get();

        $linkedCompanyEntryIds = [];

        foreach ($procurementExpenses as $pe) {
            if ($pe->company_accounting_entry_id) {
                $linkedCompanyEntryIds[] = (int) $pe->company_accounting_entry_id;
            }

            $cat = (string) $pe->category;
            $customBucket = $procurementMappings->get($cat)?->report_bucket;

            $bucket = $customBucket ?: match ($cat) {
                ProcurementExpense::CategoryVehicle, ProcurementExpense::CategoryFuel, ProcurementExpense::CategoryTollParking => ReportHeadingDictionary::VEHICLE_FUEL,
                ProcurementExpense::CategoryFood => ReportHeadingDictionary::FOOD_MESS,
                default => ReportHeadingDictionary::OTHER_EXPENSE,
            };

            if ($bucket === ReportHeadingDictionary::IGNORE) {
                continue;
            }

            $amt = round((float) $pe->amount, 2);
            $expDate = $pe->expense_date ? Carbon::parse($pe->expense_date)->format('Y-m-d') : $startDate;

            $detailedRows->push([
                'id' => 'procurement_'.$pe->id,
                'source_id' => $pe->id,
                'source_type' => 'Procurement Expense',
                'business_date' => $expDate,
                'original_category' => $pe->categoryLabel(),
                'report_bucket' => $bucket,
                'heading_label' => ReportHeadingDictionary::getLabel($bucket),
                'entity_name' => $pe->purchaser?->name ?? 'Purchaser #'.$pe->user_id,
                'description' => $pe->note ?: 'Procurement '.$pe->categoryLabel(),
                'reference' => 'PE-'.$pe->id,
                'amount' => $amt,
                'purchaser_id' => $pe->user_id,
                'purchaser_name' => $pe->purchaser?->name,
                'funding_source' => $pe->funding_source ?? 'purchaser_advance',
            ]);
        }

        // 4. Other Purchaser Expenses
        $otherExpenses = OtherExpense::query()
            ->with(['purchaser'])
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get();

        foreach ($otherExpenses as $oe) {
            if ($oe->company_accounting_entry_id) {
                $linkedCompanyEntryIds[] = (int) $oe->company_accounting_entry_id;
            }

            $cat = (string) $oe->category;
            $customBucket = $otherExpMappings->get($cat)?->report_bucket;

            $bucket = $customBucket ?: match ($cat) {
                OtherExpense::CategoryTravel => ReportHeadingDictionary::VEHICLE_FUEL,
                default => ReportHeadingDictionary::OTHER_EXPENSE,
            };

            if ($bucket === ReportHeadingDictionary::IGNORE) {
                continue;
            }

            $amt = round((float) $oe->amount, 2);
            $expDate = $oe->expense_date ? Carbon::parse($oe->expense_date)->format('Y-m-d') : $startDate;

            $detailedRows->push([
                'id' => 'other_exp_'.$oe->id,
                'source_id' => $oe->id,
                'source_type' => 'Purchaser Other Expense',
                'business_date' => $expDate,
                'original_category' => $oe->categoryLabel(),
                'report_bucket' => $bucket,
                'heading_label' => ReportHeadingDictionary::getLabel($bucket),
                'entity_name' => $oe->purchaser?->name ?? 'Purchaser #'.$oe->user_id,
                'description' => $oe->note ?: 'Purchaser Expense '.$oe->categoryLabel(),
                'reference' => 'OE-'.$oe->id,
                'amount' => $amt,
                'purchaser_id' => $oe->user_id,
                'purchaser_name' => $oe->purchaser?->name,
                'funding_source' => $oe->funding_source ?? 'purchaser_advance',
            ]);
        }

        // 5. Residual Company Accounting Entries (type=expense, status=final, not linked to procurement/other expenses)
        $companyEntries = CompanyAccountingEntry::query()
            ->with(['category'])
            ->where('type', 'expense')
            ->where('status', 'final')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->when(! empty($linkedCompanyEntryIds), fn ($q) => $q->whereNotIn('id', array_unique($linkedCompanyEntryIds)))
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        foreach ($companyEntries as $ce) {
            $catId = (string) $ce->company_accounting_category_id;
            $catName = (string) ($ce->category?->name ?? 'Company Expense');
            $customBucket = $companyMappings->get($catId)?->report_bucket ?? $companyMappings->get($catName)?->report_bucket;

            // Default heuristics for unmapped company expense categories
            $bucket = $customBucket;
            if (! $bucket) {
                $lowerCat = strtolower($catName);
                if (str_contains($lowerCat, 'salary') || str_contains($lowerCat, 'payroll') || str_contains($lowerCat, 'wage')) {
                    $bucket = ReportHeadingDictionary::SALARY;
                } elseif (str_contains($lowerCat, 'rent')) {
                    $bucket = ReportHeadingDictionary::RENT;
                } elseif (str_contains($lowerCat, 'fuel') || str_contains($lowerCat, 'vehicle') || str_contains($lowerCat, 'transport')) {
                    $bucket = ReportHeadingDictionary::VEHICLE_FUEL;
                } elseif (str_contains($lowerCat, 'food') || str_contains($lowerCat, 'mess') || str_contains($lowerCat, 'canteen')) {
                    $bucket = ReportHeadingDictionary::FOOD_MESS;
                } else {
                    $bucket = ReportHeadingDictionary::OTHER_EXPENSE;
                }
            }

            if ($bucket === ReportHeadingDictionary::IGNORE) {
                continue;
            }

            $amt = round((float) $ce->amount, 2);
            $expDate = $ce->business_date ? Carbon::parse($ce->business_date)->format('Y-m-d') : $startDate;

            $detailedRows->push([
                'id' => 'company_'.$ce->id,
                'source_id' => $ce->id,
                'source_type' => 'Company Expense',
                'business_date' => $expDate,
                'original_category' => $catName,
                'report_bucket' => $bucket,
                'heading_label' => ReportHeadingDictionary::getLabel($bucket),
                'entity_name' => 'Company Office',
                'description' => $ce->description ?: $ce->reference ?: $catName,
                'reference' => $ce->reference ?: ('CE-'.$ce->id),
                'amount' => $amt,
                'purchaser_id' => null,
                'purchaser_name' => null,
                'funding_source' => 'company_cash',
            ]);
        }

        // 6. Aggregate by date and heading
        $dailyMatrix = [];
        foreach ($dates as $date) {
            $dailyMatrix[$date] = [
                'date' => $date,
                'formatted_date' => Carbon::parse($date)->format('d M Y, D'),
                'salary' => 0.0,
                'rent' => 0.0,
                'vehicle_fuel' => 0.0,
                'food_mess' => 0.0,
                'other_expense' => 0.0,
                'total' => 0.0,
            ];
        }

        $headingTotals = [
            ReportHeadingDictionary::SALARY => 0.0,
            ReportHeadingDictionary::RENT => 0.0,
            ReportHeadingDictionary::VEHICLE_FUEL => 0.0,
            ReportHeadingDictionary::FOOD_MESS => 0.0,
            ReportHeadingDictionary::OTHER_EXPENSE => 0.0,
        ];

        foreach ($detailedRows as $row) {
            $rDate = $row['business_date'];
            $bkt = $row['report_bucket'];
            $amt = (float) $row['amount'];

            if (isset($headingTotals[$bkt])) {
                $headingTotals[$bkt] = round($headingTotals[$bkt] + $amt, 2);
            }

            if (isset($dailyMatrix[$rDate])) {
                if (isset($dailyMatrix[$rDate][$bkt])) {
                    $dailyMatrix[$rDate][$bkt] = round($dailyMatrix[$rDate][$bkt] + $amt, 2);
                }
                $dailyMatrix[$rDate]['total'] = round($dailyMatrix[$rDate]['total'] + $amt, 2);
            }
        }

        $totalOperatingExpenses = round(array_sum($headingTotals), 2);

        // 7. Group summary by category
        $categorySummary = [];
        foreach ($detailedRows->groupBy('original_category') as $origCat => $group) {
            $catAmt = round((float) $group->sum('amount'), 2);
            $first = $group->first();
            $categorySummary[] = [
                'category_name' => (string) $origCat,
                'normalized_heading' => $first['report_bucket'],
                'heading_label' => $first['heading_label'],
                'source_type' => $first['source_type'],
                'transaction_count' => $group->count(),
                'amount' => $catAmt,
                'percentage' => $totalOperatingExpenses > 0 ? round(($catAmt / $totalOperatingExpenses) * 100, 1) : 0.0,
            ];
        }

        usort($categorySummary, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'total_operating_expenses' => $totalOperatingExpenses,
            'salary' => $headingTotals[ReportHeadingDictionary::SALARY],
            'rent' => $headingTotals[ReportHeadingDictionary::RENT],
            'vehicle_fuel' => $headingTotals[ReportHeadingDictionary::VEHICLE_FUEL],
            'food_mess' => $headingTotals[ReportHeadingDictionary::FOOD_MESS],
            'other_expense' => $headingTotals[ReportHeadingDictionary::OTHER_EXPENSE],
            'totals_by_heading' => $headingTotals,
            'daily_matrix' => $dailyMatrix,
            'summary_by_category' => $categorySummary,
            'detailed_rows' => $detailedRows->sortByDesc('business_date')->values(),
        ];
    }
}
