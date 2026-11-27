<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Exports\ShopCreditNotes\ShopCreditNoteDetailedExport;
use App\Exports\ShopCreditNotes\ShopCreditNoteSummaryExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\ShopCreditNoteReportRequest;
use App\Models\User;
use App\Services\ShopInvoices\ShopCreditNoteReportService;
use App\Support\CashbookAccess;
use App\Support\SpreadsheetSanitizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ShopCreditNoteReportController extends Controller
{
    public function __construct(
        private readonly ShopCreditNoteReportService $reportService,
    ) {}

    /**
     * Display the Shop Credit Note Report page (Detailed & Summary tabs).
     */
    public function index(ShopCreditNoteReportRequest $request): View
    {
        $this->ensureAuthorized($request->user());

        $filters = $this->reportService->resolveFilters($request->validated(), $request->user());
        $shops = $this->reportService->getAuthorizedShops($filters['authorized_shop_ids']);

        $report = $filters['report_type'] === 'summary'
            ? $this->reportService->buildSummaryReport($filters)
            : $this->reportService->buildDetailedReport($filters);

        return view('admin.cashbook.reports.shops.credit_note_report', [
            'report' => $report,
            'filters' => $filters,
            'shops' => $shops,
        ]);
    }

    /**
     * Export Detailed Shop Credit Note Report as A4 PDF.
     */
    public function exportDetailedPdf(ShopCreditNoteReportRequest $request): Response
    {
        $this->ensureAuthorized($request->user());

        $filters = $this->reportService->resolveFilters($request->validated(), $request->user());
        $report = $this->reportService->buildDetailedReport($filters);

        $filename = SpreadsheetSanitizer::buildFilename(
            'Shop-Credit-Notes-Detailed',
            $filters['from_date'],
            $filters['to_date'],
            'pdf',
            $filters['shop_code']
        );

        return Pdf::loadView('admin.cashbook.reports.shops.pdf.credit_note_detailed', [
            'report' => $report,
        ])->setPaper('a4', 'portrait')->download($filename);
    }

    /**
     * Export Summary Shop Credit Note Report as A4 PDF.
     */
    public function exportSummaryPdf(ShopCreditNoteReportRequest $request): Response
    {
        $this->ensureAuthorized($request->user());

        $filters = $this->reportService->resolveFilters($request->validated(), $request->user());
        $report = $this->reportService->buildSummaryReport($filters);

        $filename = SpreadsheetSanitizer::buildFilename(
            'Shop-Credit-Notes-Summary',
            $filters['from_date'],
            $filters['to_date'],
            'pdf',
            $filters['shop_code']
        );

        return Pdf::loadView('admin.cashbook.reports.shops.pdf.credit_note_summary', [
            'report' => $report,
        ])->setPaper('a4', 'portrait')->download($filename);
    }

    /**
     * Export Detailed Shop Credit Note Workbook (.xlsx).
     */
    public function exportDetailedExcel(ShopCreditNoteReportRequest $request): BinaryFileResponse
    {
        $this->ensureAuthorized($request->user());

        $filters = $this->reportService->resolveFilters($request->validated(), $request->user());
        $report = $this->reportService->buildDetailedReport($filters);

        $filename = SpreadsheetSanitizer::buildFilename(
            'Shop-Credit-Notes-Detailed',
            $filters['from_date'],
            $filters['to_date'],
            'xlsx',
            $filters['shop_code']
        );

        return Excel::download(new ShopCreditNoteDetailedExport($report), $filename);
    }

    /**
     * Export Summary Shop Credit Note Workbook (.xlsx).
     */
    public function exportSummaryExcel(ShopCreditNoteReportRequest $request): BinaryFileResponse
    {
        $this->ensureAuthorized($request->user());

        $filters = $this->reportService->resolveFilters($request->validated(), $request->user());
        $report = $this->reportService->buildSummaryReport($filters);

        $filename = SpreadsheetSanitizer::buildFilename(
            'Shop-Credit-Notes-Summary',
            $filters['from_date'],
            $filters['to_date'],
            'xlsx',
            $filters['shop_code']
        );

        return Excel::download(new ShopCreditNoteSummaryExport($report), $filename);
    }

    /**
     * Export Detailed Shop Credit Note CSV (register, adjustments, reconciliation, or ZIP bundle).
     */
    public function exportDetailedCsv(ShopCreditNoteReportRequest $request): StreamedResponse|BinaryFileResponse
    {
        $this->ensureAuthorized($request->user());

        $validated = $request->validated();
        $dataset = (string) ($validated['dataset'] ?? 'register');
        $filters = $this->reportService->resolveFilters($validated, $request->user());
        $report = $this->reportService->buildDetailedReport($filters);

        if ($dataset === 'bundle') {
            return $this->downloadCsvBundle(
                'Shop-Credit-Notes-Detailed-CSV',
                $filters,
                [
                    SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Register', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']) => $this->buildRegisterCsv($report['register_rows']),
                    SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Product-Adjustments', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']) => $this->buildAdjustmentsCsv($report['product_adjustment_rows']),
                    SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Invoice-Reconciliation', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']) => $this->buildReconciliationCsv($report['invoice_reconciliation_rows']),
                ]
            );
        }

        if ($dataset === 'adjustments') {
            $filename = SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Product-Adjustments', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']);

            return $this->streamCsv($this->buildAdjustmentsCsv($report['product_adjustment_rows']), $filename);
        }

        if ($dataset === 'reconciliation') {
            $filename = SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Invoice-Reconciliation', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']);

            return $this->streamCsv($this->buildReconciliationCsv($report['invoice_reconciliation_rows']), $filename);
        }

        $filename = SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Register', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']);

        return $this->streamCsv($this->buildRegisterCsv($report['register_rows']), $filename);
    }

    /**
     * Export Summary Shop Credit Note CSV (daily, shop, daily_shop, or ZIP bundle).
     */
    public function exportSummaryCsv(ShopCreditNoteReportRequest $request): StreamedResponse|BinaryFileResponse
    {
        $this->ensureAuthorized($request->user());

        $validated = $request->validated();
        $dataset = (string) ($validated['dataset'] ?? 'daily');
        $filters = $this->reportService->resolveFilters($validated, $request->user());
        $report = $this->reportService->buildSummaryReport($filters);

        if ($dataset === 'bundle') {
            return $this->downloadCsvBundle(
                'Shop-Credit-Notes-Summary-CSV',
                $filters,
                [
                    SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Daily-Summary', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']) => $this->buildDailySummaryCsv($report['daily_summary']),
                    SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Shop-Summary', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']) => $this->buildShopSummaryCsv($report['shop_summary']),
                    SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Daily-Shop-Summary', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']) => $this->buildDailyShopSummaryCsv($report['daily_shop_rows']),
                ]
            );
        }

        if ($dataset === 'shop') {
            $filename = SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Shop-Summary', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']);

            return $this->streamCsv($this->buildShopSummaryCsv($report['shop_summary']), $filename);
        }

        if ($dataset === 'daily_shop') {
            $filename = SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Daily-Shop-Summary', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']);

            return $this->streamCsv($this->buildDailyShopSummaryCsv($report['daily_shop_rows']), $filename);
        }

        $filename = SpreadsheetSanitizer::buildFilename('Shop-Credit-Notes-Daily-Summary', $filters['from_date'], $filters['to_date'], 'csv', $filters['shop_code']);

        return $this->streamCsv($this->buildDailySummaryCsv($report['daily_summary']), $filename);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<int, string|int>>
     */
    private function buildRegisterCsv(array $rows): array
    {
        $csv = [[
            'Reporting Date',
            'Shop Code',
            'Shop Name',
            'Invoice Number',
            'Invoice Date',
            'Credit Note Number',
            'Credit Note Date',
            'Applied Date',
            'Reason',
            'Notes',
            'Credit Amount',
            'Applied By',
            'Status',
        ]];

        foreach ($rows as $row) {
            $csv[] = [
                $row['reporting_date'],
                SpreadsheetSanitizer::sanitizeCell($row['shop_code']),
                SpreadsheetSanitizer::sanitizeCell($row['shop_name']),
                SpreadsheetSanitizer::sanitizeCell($row['invoice_number']),
                $row['invoice_date'],
                SpreadsheetSanitizer::sanitizeCell($row['credit_note_number']),
                $row['credit_note_date'],
                $row['applied_date'],
                SpreadsheetSanitizer::sanitizeCell($row['reason']),
                SpreadsheetSanitizer::sanitizeCell($row['notes']),
                number_format((float) $row['credit_amount'], 2, '.', ''),
                SpreadsheetSanitizer::sanitizeCell($row['applied_by']),
                SpreadsheetSanitizer::sanitizeCell($row['status']),
            ];
        }

        return $csv;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<int, string>>
     */
    private function buildAdjustmentsCsv(array $rows): array
    {
        $csv = [[
            'Reporting Date',
            'Shop Code',
            'Shop Name',
            'Credit Note Number',
            'Invoice Number',
            'Product Code',
            'Product / Adjustment',
            'Quantity',
            'Original Rate',
            'Corrected Rate',
            'Difference',
            'Credit Amount',
            'Reason',
        ]];

        foreach ($rows as $row) {
            $csv[] = [
                $row['reporting_date'],
                SpreadsheetSanitizer::sanitizeCell($row['shop_code']),
                SpreadsheetSanitizer::sanitizeCell($row['shop_name']),
                SpreadsheetSanitizer::sanitizeCell($row['credit_note_number']),
                SpreadsheetSanitizer::sanitizeCell((string) ($row['invoice_number'] ?? '')),
                SpreadsheetSanitizer::sanitizeCell($row['product_code']),
                SpreadsheetSanitizer::sanitizeCell($row['product_name']),
                $row['quantity'] !== null ? number_format((float) $row['quantity'], 3, '.', '') : '',
                $row['original_rate'] !== null ? number_format((float) $row['original_rate'], 2, '.', '') : '',
                $row['corrected_rate'] !== null ? number_format((float) $row['corrected_rate'], 2, '.', '') : '',
                $row['difference'] !== null ? number_format((float) $row['difference'], 2, '.', '') : '',
                number_format((float) $row['credit_amount'], 2, '.', ''),
                SpreadsheetSanitizer::sanitizeCell($row['reason']),
            ];
        }

        return $csv;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<int, string>>
     */
    private function buildReconciliationCsv(array $rows): array
    {
        $csv = [[
            'Invoice Number',
            'Invoice Date',
            'Shop Code',
            'Shop Name',
            'Original Invoice Amount',
            'Period Credit Amount',
            'Cumulative Credit Amount',
            'Cumulative Debit Amount',
            'Effective Net Bill',
        ]];

        foreach ($rows as $row) {
            $csv[] = [
                SpreadsheetSanitizer::sanitizeCell($row['invoice_number']),
                $row['invoice_date'],
                SpreadsheetSanitizer::sanitizeCell($row['shop_code']),
                SpreadsheetSanitizer::sanitizeCell($row['shop_name']),
                number_format((float) $row['original_invoice_amount'], 2, '.', ''),
                number_format((float) $row['period_credit_amount'], 2, '.', ''),
                number_format((float) $row['cumulative_credit_amount'], 2, '.', ''),
                number_format((float) $row['cumulative_debit_amount'], 2, '.', ''),
                number_format((float) $row['effective_net_bill'], 2, '.', ''),
            ];
        }

        return $csv;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<int, string|int>>
     */
    private function buildDailySummaryCsv(array $rows): array
    {
        $csv = [['Date', 'Shops', 'Bills', 'Credit Notes', 'Credit Amount']];
        foreach ($rows as $row) {
            $csv[] = [
                $row['date'],
                (int) $row['shops_count'],
                (int) $row['bills_count'],
                (int) $row['credit_notes_count'],
                number_format((float) $row['credit_amount'], 2, '.', ''),
            ];
        }

        return $csv;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<int, string|int>>
     */
    private function buildShopSummaryCsv(array $rows): array
    {
        $csv = [['Shop Code', 'Shop Name', 'Affected Bills', 'Credit Notes', 'Credit Amount']];
        foreach ($rows as $row) {
            $csv[] = [
                SpreadsheetSanitizer::sanitizeCell($row['shop_code']),
                SpreadsheetSanitizer::sanitizeCell($row['shop_name']),
                (int) $row['bills_count'],
                (int) $row['credit_notes_count'],
                number_format((float) $row['credit_amount'], 2, '.', ''),
            ];
        }

        return $csv;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<int, string|int>>
     */
    private function buildDailyShopSummaryCsv(array $rows): array
    {
        $csv = [['Date', 'Shop Code', 'Shop Name', 'Bills', 'Credit Notes', 'Credit Amount']];
        foreach ($rows as $row) {
            $csv[] = [
                $row['date'],
                SpreadsheetSanitizer::sanitizeCell($row['shop_code']),
                SpreadsheetSanitizer::sanitizeCell($row['shop_name']),
                (int) $row['bills_count'],
                (int) $row['credit_notes_count'],
                number_format((float) $row['credit_amount'], 2, '.', ''),
            ];
        }

        return $csv;
    }

    /**
     * @param  array<int, array<int, string|int>>  $rows
     */
    private function streamCsv(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($handle, $row, ',', '"', '\\');
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, array<int, array<int, string|int>>>  $datasets
     */
    private function downloadCsvBundle(string $prefix, array $filters, array $datasets): BinaryFileResponse
    {
        $zipFilename = SpreadsheetSanitizer::buildFilename(
            $prefix,
            $filters['from_date'],
            $filters['to_date'],
            'zip',
            $filters['shop_code']
        );

        $tempPath = tempnam(sys_get_temp_dir(), 'scn_csv_');
        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($datasets as $csvName => $rows) {
            $stream = fopen('php://temp', 'r+');
            if ($stream !== false) {
                fwrite($stream, "\xEF\xBB\xBF");
                foreach ($rows as $row) {
                    fputcsv($stream, $row, ',', '"', '\\');
                }
                rewind($stream);
                $content = stream_get_contents($stream);
                fclose($stream);
                if ($content !== false) {
                    $zip->addFromString($csvName, $content);
                }
            }
        }

        $zip->close();

        return response()->download($tempPath, $zipFilename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    private function ensureAuthorized(?User $user): void
    {
        abort_unless(
            $user instanceof User && (
                $user->isMainAdmin()
                || $user->hasRole('admin')
                || $user->hasRole('super_admin')
                || $user->can(CashbookAccess::ReportsView)
            ),
            403
        );
    }
}
