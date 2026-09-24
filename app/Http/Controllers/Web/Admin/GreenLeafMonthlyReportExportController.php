<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Exports\Cashbook\DynamicSectionReportMultiSheetExport;
use App\Exports\Cashbook\MonthlyReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MonthlyReportPeriodRequest;
use App\Models\User;
use App\Services\Cashbook\MonthlyReport\DynamicSectionReportService;
use App\Services\Cashbook\MonthlyReport\GreenLeafMonthlyReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GreenLeafMonthlyReportExportController extends Controller
{
    public function __construct(
        private readonly GreenLeafMonthlyReportService $reportService,
        private readonly DynamicSectionReportService $dynamicSectionReportService,
    ) {}

    public function exportSectionReportsCsv(MonthlyReportPeriodRequest $request): StreamedResponse
    {
        $this->ensureAuthorized($request);

        $sectionKey = $request->query('section');
        $validSectionKey = is_string($sectionKey) && $sectionKey !== '' && $sectionKey !== 'all' ? $sectionKey : null;
        $data = $this->dynamicSectionReportService->buildReport($request->validated(), $validSectionKey);

        $filenameKey = $validSectionKey ?: 'all';
        $filename = "green-leaf-section-reports-{$filenameKey}-{$data['period']['start_date']}-to-{$data['period']['end_date']}.csv";

        return response()->streamDownload(function () use ($data, $validSectionKey): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            }

            $sanitize = function (array $row): array {
                return array_map(function ($val) {
                    if (is_string($val)) {
                        $trimmed = trim($val);
                        if (str_starts_with($trimmed, '=') || str_starts_with($trimmed, '+') || str_starts_with($trimmed, '-') || str_starts_with($trimmed, '@')) {
                            return "'".$val;
                        }
                    }

                    return $val;
                }, $row);
            };

            $sections = $data['sections'] ?? [];

            if ($validSectionKey && isset($sections[$validSectionKey])) {
                $sec = $sections[$validSectionKey];
                $isTrading = ($sec['type'] === 'trading');

                fputcsv($file, [$sec['name'].' - Section Report ('.$data['period']['start_date'].' to '.$data['period']['end_date'].')']);
                fputcsv($file, []);

                if ($isTrading) {
                    fputcsv($file, ['Date', 'Sale (₹)', 'Purchase (₹)', 'Other Expense (₹)', 'Total Expense (₹)', 'Balance (₹)']);
                    foreach ($sec['daily_rows'] as $d) {
                        fputcsv($file, $sanitize([
                            $d['formatted_date'],
                            number_format($d['sale'], 2, '.', ''),
                            number_format($d['purchase'], 2, '.', ''),
                            number_format($d['other_expense'], 2, '.', ''),
                            number_format($d['total_expense'], 2, '.', ''),
                            number_format($d['balance'], 2, '.', ''),
                        ]));
                    }
                    fputcsv($file, $sanitize([
                        'MONTHLY TOTAL',
                        number_format($sec['summary']['sales'], 2, '.', ''),
                        number_format($sec['summary']['purchases'], 2, '.', ''),
                        number_format($sec['summary']['other_expenses'], 2, '.', ''),
                        number_format($sec['summary']['total_expenses'], 2, '.', ''),
                        number_format($sec['summary']['balance'], 2, '.', ''),
                    ]));
                } else {
                    fputcsv($file, ['Date', 'Expense (₹)']);
                    foreach ($sec['daily_rows'] as $d) {
                        fputcsv($file, $sanitize([
                            $d['formatted_date'],
                            number_format($d['expense'], 2, '.', ''),
                        ]));
                    }
                    fputcsv($file, $sanitize([
                        'MONTHLY TOTAL',
                        number_format($sec['summary']['total_expenses'], 2, '.', ''),
                    ]));
                }
            } else {
                // Full Multi-Section Export
                fputcsv($file, ['Green Leaf Monthly Section Reports - All Sections ('.$data['period']['start_date'].' to '.$data['period']['end_date'].')']);
                fputcsv($file, []);

                // Summary Section
                fputcsv($file, ['--- OVERALL SUMMARY ---']);
                fputcsv($file, ['Section', 'Type', 'Sales (₹)', 'Purchases / Direct Exp (₹)', 'Other Expenses (₹)', 'Total Expenses (₹)', 'Balance (₹)']);
                foreach ($sections as $sec) {
                    $isTrading = ($sec['type'] === 'trading');
                    fputcsv($file, $sanitize([
                        $sec['name'],
                        $isTrading ? 'Trading / Product' : 'Operating Overhead',
                        $isTrading ? number_format($sec['summary']['sales'], 2, '.', '') : '—',
                        $isTrading ? number_format($sec['summary']['purchases'], 2, '.', '') : '—',
                        $isTrading ? number_format($sec['summary']['other_expenses'], 2, '.', '') : number_format($sec['summary']['total_expenses'], 2, '.', ''),
                        number_format($sec['summary']['total_expenses'], 2, '.', ''),
                        $isTrading ? number_format($sec['summary']['balance'], 2, '.', '') : '—',
                    ]));
                }
                $overall = $data['overall_summary'] ?? [];
                fputcsv($file, $sanitize([
                    'TOTAL',
                    'All Sections',
                    number_format($overall['total_sales'] ?? 0, 2, '.', ''),
                    number_format($overall['total_purchases'] ?? 0, 2, '.', ''),
                    number_format($overall['total_operating_expenses'] ?? 0, 2, '.', ''),
                    number_format($overall['total_expenses'] ?? 0, 2, '.', ''),
                    number_format($overall['balance'] ?? 0, 2, '.', ''),
                ]));

                // Detailed tables per section
                foreach ($sections as $sec) {
                    fputcsv($file, []);
                    fputcsv($file, ['--- '.$sec['name'].' ---']);
                    $isTrading = ($sec['type'] === 'trading');
                    if ($isTrading) {
                        fputcsv($file, ['Date', 'Sale (₹)', 'Purchase (₹)', 'Other Expense (₹)', 'Total Expense (₹)', 'Balance (₹)']);
                        foreach ($sec['daily_rows'] as $d) {
                            fputcsv($file, $sanitize([
                                $d['formatted_date'],
                                number_format($d['sale'], 2, '.', ''),
                                number_format($d['purchase'], 2, '.', ''),
                                number_format($d['other_expense'], 2, '.', ''),
                                number_format($d['total_expense'], 2, '.', ''),
                                number_format($d['balance'], 2, '.', ''),
                            ]));
                        }
                        fputcsv($file, $sanitize([
                            'MONTHLY TOTAL',
                            number_format($sec['summary']['sales'], 2, '.', ''),
                            number_format($sec['summary']['purchases'], 2, '.', ''),
                            number_format($sec['summary']['other_expenses'], 2, '.', ''),
                            number_format($sec['summary']['total_expenses'], 2, '.', ''),
                            number_format($sec['summary']['balance'], 2, '.', ''),
                        ]));
                    } else {
                        fputcsv($file, ['Date', 'Expense (₹)']);
                        foreach ($sec['daily_rows'] as $d) {
                            fputcsv($file, $sanitize([
                                $d['formatted_date'],
                                number_format($d['expense'], 2, '.', ''),
                            ]));
                        }
                        fputcsv($file, $sanitize([
                            'MONTHLY TOTAL',
                            number_format($sec['summary']['total_expenses'], 2, '.', ''),
                        ]));
                    }
                }
            }

            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportSectionReportsExcel(MonthlyReportPeriodRequest $request): BinaryFileResponse
    {
        $this->ensureAuthorized($request);

        $sectionKey = $request->query('section');
        $validSectionKey = is_string($sectionKey) && $sectionKey !== '' && $sectionKey !== 'all' ? $sectionKey : null;
        $data = $this->dynamicSectionReportService->buildReport($request->validated(), $validSectionKey);

        $filenameKey = $validSectionKey ?: 'all';
        $filename = "green-leaf-section-reports-{$filenameKey}-{$data['period']['start_date']}-to-{$data['period']['end_date']}.xlsx";

        return Excel::download(new DynamicSectionReportMultiSheetExport($data, $validSectionKey), $filename);
    }

    public function exportSectionReportsPdf(MonthlyReportPeriodRequest $request): mixed
    {
        $this->ensureAuthorized($request);

        $sectionKey = $request->query('section');
        $validSectionKey = is_string($sectionKey) && $sectionKey !== '' && $sectionKey !== 'all' ? $sectionKey : null;
        $data = $this->dynamicSectionReportService->buildReport($request->validated(), $validSectionKey);

        $filenameKey = $validSectionKey ?: 'all';
        $filename = "green-leaf-section-reports-{$filenameKey}-{$data['period']['start_date']}-to-{$data['period']['end_date']}.pdf";

        $viewName = 'admin.cashbook.monthly-report.pdf.section-reports';

        if ($request->boolean('download', false) || $request->input('download') === '1') {
            return Pdf::loadView($viewName, $data)
                ->setPaper('a4', 'landscape')
                ->setOption(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true])
                ->download($filename);
        }

        return view($viewName, $data);
    }

    public function printSectionReports(MonthlyReportPeriodRequest $request): View
    {
        $this->ensureAuthorized($request);

        $sectionKey = $request->query('section');
        $validSectionKey = is_string($sectionKey) && $sectionKey !== '' && $sectionKey !== 'all' ? $sectionKey : null;
        $data = $this->dynamicSectionReportService->buildReport($request->validated(), $validSectionKey);

        return view('admin.cashbook.monthly-report.print.section-reports', $data);
    }

    public function exportCsv(MonthlyReportPeriodRequest $request, string $report): StreamedResponse
    {
        $this->ensureAuthorized($request);

        $data = $this->resolveReportData($report, $request->validated());
        [$headings, $rows] = $this->buildExportRows($report, $data);
        $filename = "green-leaf-monthly-report-{$report}-{$data['period']['start_date']}-to-{$data['period']['end_date']}.csv";

        return response()->streamDownload(function () use ($headings, $rows): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            }

            // Formula injection neutralization
            $sanitize = function (array $row): array {
                return array_map(function ($val) {
                    if (is_string($val)) {
                        $trimmed = trim($val);
                        if (str_starts_with($trimmed, '=') || str_starts_with($trimmed, '+') || str_starts_with($trimmed, '-') || str_starts_with($trimmed, '@')) {
                            return "'".$val;
                        }
                    }

                    return $val;
                }, $row);
            };

            fputcsv($file, $headings);
            foreach ($rows as $row) {
                fputcsv($file, $sanitize($row));
            }

            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportExcel(MonthlyReportPeriodRequest $request, string $report): BinaryFileResponse
    {
        $this->ensureAuthorized($request);

        $data = $this->resolveReportData($report, $request->validated());
        [$headings, $rows] = $this->buildExportRows($report, $data);
        $filename = "green-leaf-monthly-report-{$report}-{$data['period']['start_date']}-to-{$data['period']['end_date']}.xlsx";

        return Excel::download(new MonthlyReportExport($headings, $rows, ucfirst(str_replace('-', ' ', $report))), $filename);
    }

    public function exportPdf(MonthlyReportPeriodRequest $request, string $report): mixed
    {
        $this->ensureAuthorized($request);

        $data = $this->resolveReportData($report, $request->validated());
        $filename = "green-leaf-monthly-report-{$report}-{$data['period']['start_date']}-to-{$data['period']['end_date']}.pdf";

        $viewName = match ($report) {
            'sale-split' => 'admin.cashbook.monthly-report.pdf.sale-split',
            'other-expenses' => 'admin.cashbook.monthly-report.pdf.other-expenses',
            'expense-report' => 'admin.cashbook.monthly-report.pdf.expense-report',
            default => 'admin.cashbook.monthly-report.pdf.overview',
        };

        if ($request->boolean('download', false) || $request->input('download') === '1') {
            return Pdf::loadView($viewName, $data)
                ->setPaper('a4', 'landscape')
                ->setOption(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true])
                ->download($filename);
        }

        return view($viewName, $data);
    }

    private function resolveReportData(string $report, array $inputs): array
    {
        return match ($report) {
            'sale-split' => $this->reportService->saleSplit($inputs),
            'other-expenses' => $this->reportService->otherExpenses($inputs),
            'expense-report' => $this->reportService->expenseReport($inputs),
            default => $this->reportService->overview($inputs),
        };
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function buildExportRows(string $report, array $data): array
    {
        if ($report === 'overview') {
            $headings = ['Date', 'Client Sales (₹)', 'All Other Sales (₹)', 'Total Sales (₹)', 'Expenses (₹)', 'Balance (₹)'];
            $rows = [];
            foreach ($data['daily_rows'] as $d) {
                $rows[] = [
                    $d['date'],
                    number_format($d['client_sales'], 2, '.', ''),
                    number_format($d['all_other_sales'], 2, '.', ''),
                    number_format($d['total_sales'], 2, '.', ''),
                    number_format($d['total_expenses'], 2, '.', ''),
                    number_format($d['balance'], 2, '.', ''),
                ];
            }
            $rows[] = [
                'TOTAL',
                number_format($data['summary']['client_sales'], 2, '.', ''),
                number_format($data['summary']['all_other_sales'], 2, '.', ''),
                number_format($data['summary']['total_sales'], 2, '.', ''),
                number_format($data['summary']['total_expenses'], 2, '.', ''),
                number_format($data['summary']['balance'], 2, '.', ''),
            ];

            return [$headings, $rows];
        }

        if ($report === 'sale-split') {
            $headings = [
                'Date',
                'Fruits Sale (₹)', 'Fruits Expense (₹)',
                'Veg Sale (₹)', 'Veg Expense (₹)',
                'Stationery Sale (₹)', 'Stationery Expense (₹)',
                'Other Sale (₹)', 'Other Product Exp (₹)', 'Other Expenses (₹)',
                'Total Sales (₹)', 'Total Expenses (₹)', 'Balance (₹)',
            ];
            $rows = [];
            foreach ($data['daily_rows'] as $d) {
                $rows[] = [
                    $d['date'],
                    number_format($d['fruits_sale'], 2, '.', ''),
                    number_format($d['fruits_expense'], 2, '.', ''),
                    number_format($d['veg_sale'], 2, '.', ''),
                    number_format($d['veg_expense'], 2, '.', ''),
                    number_format($d['stationery_sale'], 2, '.', ''),
                    number_format($d['stationery_expense'], 2, '.', ''),
                    number_format($d['other_sale'], 2, '.', ''),
                    number_format($d['other_product_expense'], 2, '.', ''),
                    number_format($d['other_expenses'], 2, '.', ''),
                    number_format($d['total_sales'], 2, '.', ''),
                    number_format($d['total_expenses'], 2, '.', ''),
                    number_format($d['balance'], 2, '.', ''),
                ];
            }
            $rows[] = [
                'TOTAL',
                number_format($data['summary']['fruits_sale'], 2, '.', ''),
                number_format($data['summary']['fruits_expense'], 2, '.', ''),
                number_format($data['summary']['veg_sale'], 2, '.', ''),
                number_format($data['summary']['veg_expense'], 2, '.', ''),
                number_format($data['summary']['stationery_sale'], 2, '.', ''),
                number_format($data['summary']['stationery_expense'], 2, '.', ''),
                number_format($data['summary']['other_sale'], 2, '.', ''),
                number_format($data['summary']['other_product_expense'], 2, '.', ''),
                number_format($data['summary']['other_expenses'], 2, '.', ''),
                number_format($data['summary']['total_sales'], 2, '.', ''),
                number_format($data['summary']['total_expenses'], 2, '.', ''),
                number_format($data['summary']['balance'], 2, '.', ''),
            ];

            return [$headings, $rows];
        }

        if ($report === 'expense-report') {
            $headings = ['Date', 'Salary (₹)', 'Rent (₹)', 'Vehicle/Fuel (₹)', 'Food/Mess (₹)', 'Others (₹)', 'Total (₹)'];
            $rows = [];
            foreach ($data['daily_matrix'] as $d) {
                $rows[] = [
                    $d['date'],
                    number_format($d['salary'], 2, '.', ''),
                    number_format($d['rent'], 2, '.', ''),
                    number_format($d['vehicle_fuel'], 2, '.', ''),
                    number_format($d['food_mess'], 2, '.', ''),
                    number_format($d['other_expense'], 2, '.', ''),
                    number_format($d['total'], 2, '.', ''),
                ];
            }
            $rows[] = [
                'TOTAL',
                number_format($data['totals']['salary'] ?? 0, 2, '.', ''),
                number_format($data['totals']['rent'] ?? 0, 2, '.', ''),
                number_format($data['totals']['vehicle_fuel'] ?? 0, 2, '.', ''),
                number_format($data['totals']['food_mess'] ?? 0, 2, '.', ''),
                number_format($data['totals']['other_expense'] ?? 0, 2, '.', ''),
                number_format($data['total_operating_expenses'], 2, '.', ''),
            ];

            return [$headings, $rows];
        }

        // other-expenses
        $headings = ['Date', 'Original Category', 'Normalized Heading', 'Source', 'Entity / Client / Shop / Purchaser', 'Description', 'Reference', 'Amount (₹)'];
        $rows = [];
        $detailedRows = $data['all_detailed_rows'] ?? $data['detailed_rows'];
        foreach ($detailedRows as $r) {
            $rows[] = [
                $r['business_date'],
                $r['original_category'],
                $r['heading_label'],
                $r['source_type'],
                $r['entity_name'],
                $r['description'],
                $r['reference'],
                number_format($r['amount'], 2, '.', ''),
            ];
        }

        return [$headings, $rows];
    }

    private function ensureAuthorized(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && (
                $user->isMainAdmin()
                || $user->hasRole('admin')
                || $user->hasRole('accounts')
                || $user->hasRole('accountant')
                || $user->hasRole('account')
                || $user->hasRole('manager')
                || (isset($user->is_admin) && $user->is_admin)
                || $user->can('cashbook.monthly-report.export')
                || $user->can('cashbook.monthly-report.view')
                || $user->can('accounting.report.export')
                || $user->can('accounting.report.view')
                || $user->can('finance.dashboard.view')
                || $user->can('accounting.dashboard.view')
                || $user->can('accounting.ledger.view')
            ),
            403,
            'Unauthorized export action.'
        );
    }
}
