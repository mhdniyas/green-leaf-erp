<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Exports\PurchaserReportArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Reports\PurchaserReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaserReportController extends Controller
{
    public function __construct(
        private readonly PurchaserReportService $reports,
        private readonly PurchaserBusinessDayService $businessDay,
    ) {}

    public function salesSummary(Request $request): View
    {
        $filters = $this->filters($request);

        return view('purchasing.purchaser.reports.sales-summary', $this->viewData(
            $request,
            $filters,
            $this->reports->salesSummary($filters),
        ));
    }

    public function itemSummary(Request $request): View
    {
        $filters = $this->filters($request);

        return view('purchasing.purchaser.reports.item-summary', $this->viewData(
            $request,
            $filters,
            $this->reports->itemSummary($filters),
        ));
    }

    public function specialPrices(Request $request): View
    {
        $this->authorizePurchaserAccess($request);

        $filters = $this->specialPricesFilters($request);
        $report = $this->reports->specialPricesReport($filters);

        return view('purchasing.purchaser.reports.special-prices', [
            'report' => $report,
            'filters' => $filters,
            'selectedRange' => $request->string('range')->toString() ?: ($request->filled('date') ? 'custom' : 'today'),
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name', 'code']),
            'hasCategorySettings' => ($filters['category_ids'] ?? null) !== null,
        ]);
    }

    public function specialPricesCsv(Request $request): StreamedResponse
    {
        $this->authorizePurchaserAccess($request);

        $filters = $this->specialPricesFilters($request);
        $rows = $this->specialPricesExportRows($this->reports->specialPricesExport($filters));

        return $this->csv($rows, $this->filename('purchaser-special-prices', $filters, 'csv'));
    }

    public function salesSummaryCsv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->salesExportRows($this->reports->salesSummaryExport($filters));

        return $this->csv($rows, $this->filename('purchaser-sales-summary', $filters, 'csv'));
    }

    public function itemSummaryCsv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->itemExportRows($this->reports->itemSummaryExport($filters));

        return $this->csv($rows, $this->filename('purchaser-item-summary', $filters, 'csv'));
    }

    public function salesSummaryExcel(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);

        return Excel::download(
            new PurchaserReportArrayExport($this->salesExportRows($this->reports->salesSummaryExport($filters)), 'Sales Summary'),
            $this->filename('purchaser-sales-summary', $filters, 'xlsx'),
        );
    }

    public function itemSummaryExcel(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);

        return Excel::download(
            new PurchaserReportArrayExport($this->itemExportRows($this->reports->itemSummaryExport($filters)), 'Item Summary'),
            $this->filename('purchaser-item-summary', $filters, 'xlsx'),
        );
    }

    public function salesSummaryPdf(Request $request): View
    {
        $filters = $this->filters($request);

        return view('purchasing.purchaser.reports.sales-summary-pdf', [
            'filters' => $filters,
            'report' => $this->reports->salesSummaryExport($filters),
        ]);
    }

    public function itemSummaryPdf(Request $request): View
    {
        $filters = $this->filters($request);

        return view('purchasing.purchaser.reports.item-summary-pdf', [
            'filters' => $filters,
            'report' => $this->reports->itemSummaryExport($filters),
        ]);
    }

    /** @return array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int,sort:string,direction:string,category_ids:?array<int, int>} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['today', 'yesterday', 'week', 'month', 'custom'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'status' => ['nullable', Rule::in(['all', 'open', 'approved', 'generated', 'delivery_review', 'finalized', 'payment_pending', 'paid'])],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in(['sku', 'product', 'name', 'category', 'unit', 'quantity', 'billed_quantity', 'sales', 'line_sales_amount', 'shops', 'shop_count', 'invoices', 'invoice_count', 'balance'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
        ]);

        $range = (string) ($validated['range'] ?? 'today');
        [$dateFrom, $dateTo] = $this->dates($range, $validated);

        if ($dateFrom->diffInDays($dateTo) > 366) {
            abort(422, 'The report period may not exceed 366 days.');
        }

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'shop_id' => isset($validated['shop_id']) ? (int) $validated['shop_id'] : null,
            'status' => isset($validated['status']) ? (string) $validated['status'] : null,
            'search' => trim((string) ($validated['search'] ?? '')),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 25),
            'sort' => (string) ($validated['sort'] ?? 'sku'),
            'direction' => strtolower((string) ($validated['direction'] ?? 'asc')),
            'category_ids' => $request->user()?->hasAssignedCategoryFilter()
                ? $request->user()->assignedCategoryIds()
                : null,
        ];
    }

    /** @param array<string, mixed> $validated @return array{Carbon, Carbon} */
    private function dates(string $range, array $validated): array
    {
        $today = $this->businessDay->operationalDate();

        return match ($range) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'week' => [$today->copy()->startOfWeek(), $today],
            'month' => [$today->copy()->startOfMonth(), $today],
            'custom' => [
                Carbon::createFromFormat('Y-m-d', (string) ($validated['date_from'] ?? $today->toDateString())),
                Carbon::createFromFormat('Y-m-d', (string) ($validated['date_to'] ?? $validated['date_from'] ?? $today->toDateString())),
            ],
            default => [$today, $today],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function viewData(Request $request, array $filters, array $report): array
    {
        return [
            'report' => $report,
            'filters' => $filters,
            'selectedRange' => $request->string('range')->toString() ?: 'today',
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name', 'code']),
            'hasCategorySettings' => ($filters['category_ids'] ?? null) !== null,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function csv(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $file = fopen('php://output', 'w');

            if ($file === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array<int, mixed>>
     */
    private function salesExportRows(array $report): array
    {
        $totals = $report['totals'];
        $rows = [
            ['Sales Summary'],
            ['Total Sales', $totals['total_sales'], 'Paid', $totals['paid_amount'], 'Outstanding', $totals['outstanding_amount']],
            ['Total Shops', $totals['total_shops'], 'Total Invoices', $totals['total_invoices']],
            [],
            ['Shop', 'Code', 'Invoices', 'Sales', 'Paid', 'Outstanding'],
        ];

        foreach ($report['shops'] as $shop) {
            $rows[] = [
                $shop['shop_name'],
                $shop['shop_code'],
                $shop['invoice_count'],
                $shop['total_sales'],
                $shop['paid_amount'],
                $shop['outstanding_amount'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array<int, mixed>>
     */
    private function itemExportRows(array $report): array
    {
        $summary = $report['summary'];
        $rows = [
            ['Item Summary'],
            ['Products', $summary['distinct_products'], 'Product Units', $summary['product_unit_rows'], 'Invoice Lines', $summary['invoice_lines']],
            [],
            ['SL', 'Product', 'SKU', 'Category', 'Unit', 'Quantity', 'Sales', 'Invoice Lines', 'Invoices', 'Shops'],
        ];

        foreach ($report['items'] as $index => $item) {
            $rows[] = [
                $index + 1,
                $item['product_name'],
                $item['product_sku'] ?? '',
                $item['category_name'] ?? 'Uncategorized',
                $item['unit'],
                $item['billed_quantity'],
                $item['line_sales_amount'],
                $item['invoice_line_count'],
                $item['invoice_count'],
                $item['shop_count'],
            ];
        }

        return $rows;
    }

    /**
     * @return array{date_from:string,date_to:string,shop_id:?int,status:string,search:string,page:int,per_page:int,sort:string,direction:string,operational_date:string,category_ids:?array<int, int>}
     */
    private function specialPricesFilters(Request $request): array
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['today', 'yesterday', 'week', 'month', 'custom'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'grade' => ['nullable', 'string', Rule::in(['all', 'A', 'B', 'C', 'a', 'b', 'c'])],
            'status' => ['nullable', Rule::in(['all', 'applied', 'not_applied', 'approved', 'draft', 'expired'])],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in(['product', 'name', 'sku', 'shop', 'grade', 'date', 'price', 'special_price', 'status', 'updated_at'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
        ]);

        $today = $this->businessDay->operationalDate();
        $range = (string) ($validated['range'] ?? ($request->filled('date') ? 'custom' : 'today'));

        if ($request->filled('date') && ! $request->filled('date_from')) {
            $dateParsed = Carbon::createFromFormat('Y-m-d', (string) $validated['date']);
            $dateFrom = $dateParsed;
            $dateTo = $dateParsed;
        } else {
            [$dateFrom, $dateTo] = $this->dates($range, $validated);
        }

        if ($dateFrom->diffInDays($dateTo) > 366) {
            abort(422, 'The report period may not exceed 366 days.');
        }

        $grade = strtoupper(trim((string) ($validated['grade'] ?? '')));
        $gradeFilter = in_array($grade, ['A', 'B', 'C'], true) ? $grade : 'all';

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'shop_id' => isset($validated['shop_id']) ? (int) $validated['shop_id'] : null,
            'grade' => $gradeFilter,
            'status' => (string) ($validated['status'] ?? 'all'),
            'search' => trim((string) ($validated['search'] ?? '')),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 25),
            'sort' => (string) ($validated['sort'] ?? 'product'),
            'direction' => strtolower((string) ($validated['direction'] ?? 'asc')),
            'operational_date' => $today->toDateString(),
            'category_ids' => $request->user()?->hasAssignedCategoryFilter()
                ? $request->user()->assignedCategoryIds()
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array<int, mixed>>
     */
    private function specialPricesExportRows(array $report): array
    {
        $rows = [
            ['Special Price Report'],
            ['Date Range', ($report['items'][0]['business_date'] ?? 'N/A')],
            ['Total Records', count($report['items'])],
            [],
            ['Product', 'SKU', 'Category', 'Unit', 'Shop', 'Shop Code', 'Price Group', 'Grade', 'Normal Price', 'Special Price', 'Difference', 'Date', 'Status', 'Updated By', 'Updated At', 'Reason'],
        ];

        foreach ($report['items'] as $item) {
            $rows[] = [
                $item['product_name'],
                $item['product_sku'],
                $item['category_name'],
                $item['price_unit'],
                $item['shop_name'],
                $item['shop_code'],
                $item['price_group'],
                $item['grade_label'],
                $item['normal_price'] !== null ? number_format((float) $item['normal_price'], 2, '.', '') : 'N/A',
                number_format((float) $item['special_price'], 2, '.', ''),
                $item['difference'] !== null ? number_format((float) $item['difference'], 2, '.', '') : 'N/A',
                $item['business_date'],
                $item['status_label'],
                $item['updater_name'] ?? 'N/A',
                $item['updated_at_formatted'] ?? 'N/A',
                $item['reason'] ?? '',
            ];
        }

        return $rows;
    }

    private function authorizePurchaserAccess(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole('purchaser')
            || $request->user()?->hasRole('purchase')
            || $request->user()?->hasRole('admin')
            || $request->user()?->can('purchaser.reports.sales.view'),
            403,
            'Unauthorized access.'
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filename(string $prefix, array $filters, string $extension): string
    {
        return "{$prefix}-{$filters['date_from']}_{$filters['date_to']}.{$extension}";
    }
}
