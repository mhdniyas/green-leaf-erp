<?php

declare(strict_types=1);

namespace App\Exports\ShopCreditNotes;

use App\Exports\ShopCreditNotes\Sheets\DailyShopSummarySheet;
use App\Exports\ShopCreditNotes\Sheets\DailySummarySheet;
use App\Exports\ShopCreditNotes\Sheets\OverviewSheet;
use App\Exports\ShopCreditNotes\Sheets\ShopSummarySheet;
use App\Support\SpreadsheetSanitizer;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShopCreditNoteDetailedExport implements WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(
        private readonly array $report,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new OverviewSheet($this->report),
            new CreditNoteRegisterSheet($this->report['register_rows']),
            new InvoiceReconciliationSheet($this->report['invoice_reconciliation_rows']),
            new ProductAdjustmentsSheet($this->report['product_adjustment_rows']),
            new DailySummarySheet($this->report['daily_summary']),
            new ShopSummarySheet($this->report['shop_summary']),
            new DailyShopSummarySheet($this->report['daily_shop_rows'], 'Daily Shop-wise'),
        ];
    }
}

class CreditNoteRegisterSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    public function title(): string
    {
        return 'Credit Note Register';
    }

    public function headings(): array
    {
        return [
            'Reporting Date',
            'Shop Code',
            'Shop Name',
            'Invoice Number',
            'Credit Note Number',
            'Reason',
            'Notes',
            'Applied Date',
            'Applied By',
            'Credit Amount',
            'Status',
        ];
    }

    public function array(): array
    {
        $data = [];
        foreach ($this->rows as $row) {
            $reportDate = $row['reporting_date'] !== '' ? ExcelDate::PHPToExcel(Carbon::parse($row['reporting_date'])) : '';
            $appliedDate = $row['applied_date'] !== '' ? ExcelDate::PHPToExcel(Carbon::parse($row['applied_date'])) : 'Unapplied';

            $data[] = [
                $reportDate,
                SpreadsheetSanitizer::sanitizeCell($row['shop_code']),
                SpreadsheetSanitizer::sanitizeCell($row['shop_name']),
                SpreadsheetSanitizer::sanitizeCell($row['invoice_number']),
                SpreadsheetSanitizer::sanitizeCell($row['credit_note_number']),
                SpreadsheetSanitizer::sanitizeCell($row['reason']),
                SpreadsheetSanitizer::sanitizeCell($row['notes']),
                $appliedDate,
                SpreadsheetSanitizer::sanitizeCell($row['applied_by']),
                (float) $row['credit_amount'],
                SpreadsheetSanitizer::sanitizeCell($row['status']),
            ];
        }

        $lastDataRow = count($data) + 1;
        $data[] = [
            'Grand Total',
            '',
            '',
            '',
            count($this->rows).' Notes',
            '',
            '',
            '',
            '',
            $lastDataRow >= 2 ? "=SUM(J2:J{$lastDataRow})" : 0.00,
            '',
        ];

        return $data;
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'B' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'J' => '₹#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $totalRow = count($this->rows) + 2;

        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']]],
            $totalRow => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $lastDataRow = max(1, count($this->rows) + 1);
                $sheet->setAutoFilter("A1:K{$lastDataRow}");
            },
        ];
    }
}

class InvoiceReconciliationSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    public function title(): string
    {
        return 'Invoice Reconciliation';
    }

    public function headings(): array
    {
        return [
            'Invoice Number',
            'Invoice Date',
            'Shop Code',
            'Shop Name',
            'Original Invoice Amount',
            'Period Credit Amount',
            'Cumulative Credit Amount',
            'Cumulative Debit Amount',
            'Effective Net Bill',
        ];
    }

    public function array(): array
    {
        $data = [];
        foreach ($this->rows as $row) {
            $invDate = $row['invoice_date'] !== '' ? ExcelDate::PHPToExcel(Carbon::parse($row['invoice_date'])) : '';
            $data[] = [
                SpreadsheetSanitizer::sanitizeCell($row['invoice_number']),
                $invDate,
                SpreadsheetSanitizer::sanitizeCell($row['shop_code']),
                SpreadsheetSanitizer::sanitizeCell($row['shop_name']),
                (float) $row['original_invoice_amount'],
                (float) $row['period_credit_amount'],
                (float) $row['cumulative_credit_amount'],
                (float) $row['cumulative_debit_amount'],
                (float) $row['effective_net_bill'],
            ];
        }

        $lastDataRow = count($data) + 1;
        $data[] = [
            'Grand Total',
            '',
            '',
            count($this->rows).' Invoices',
            $lastDataRow >= 2 ? "=SUM(E2:E{$lastDataRow})" : 0.00,
            $lastDataRow >= 2 ? "=SUM(F2:F{$lastDataRow})" : 0.00,
            $lastDataRow >= 2 ? "=SUM(G2:G{$lastDataRow})" : 0.00,
            $lastDataRow >= 2 ? "=SUM(H2:H{$lastDataRow})" : 0.00,
            $lastDataRow >= 2 ? "=SUM(I2:I{$lastDataRow})" : 0.00,
        ];

        return $data;
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'C' => NumberFormat::FORMAT_TEXT,
            'E' => '₹#,##0.00',
            'F' => '₹#,##0.00',
            'G' => '₹#,##0.00',
            'H' => '₹#,##0.00',
            'I' => '₹#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $totalRow = count($this->rows) + 2;

        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']]],
            $totalRow => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $lastDataRow = max(1, count($this->rows) + 1);
                $sheet->setAutoFilter("A1:I{$lastDataRow}");
            },
        ];
    }
}

class ProductAdjustmentsSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    public function title(): string
    {
        return 'Product Adjustments';
    }

    public function headings(): array
    {
        return [
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
        ];
    }

    public function array(): array
    {
        $data = [];
        foreach ($this->rows as $row) {
            $reportDate = $row['reporting_date'] !== '' ? ExcelDate::PHPToExcel(Carbon::parse($row['reporting_date'])) : '';
            $data[] = [
                $reportDate,
                SpreadsheetSanitizer::sanitizeCell($row['shop_code']),
                SpreadsheetSanitizer::sanitizeCell($row['shop_name']),
                SpreadsheetSanitizer::sanitizeCell($row['credit_note_number']),
                SpreadsheetSanitizer::sanitizeCell((string) ($row['invoice_number'] ?? '')),
                SpreadsheetSanitizer::sanitizeCell($row['product_code']),
                SpreadsheetSanitizer::sanitizeCell($row['product_name']),
                $row['quantity'] !== null ? (float) $row['quantity'] : '',
                $row['original_rate'] !== null ? (float) $row['original_rate'] : '',
                $row['corrected_rate'] !== null ? (float) $row['corrected_rate'] : '',
                $row['difference'] !== null ? (float) $row['difference'] : '',
                (float) $row['credit_amount'],
                SpreadsheetSanitizer::sanitizeCell($row['reason']),
            ];
        }

        $lastDataRow = count($data) + 1;
        $data[] = [
            'Grand Total',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $lastDataRow >= 2 ? "=SUM(L2:L{$lastDataRow})" : 0.00,
            '',
        ];

        return $data;
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'B' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'I' => '₹#,##0.00',
            'J' => '₹#,##0.00',
            'K' => '₹#,##0.00',
            'L' => '₹#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $totalRow = count($this->rows) + 2;

        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']]],
            $totalRow => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $lastDataRow = max(1, count($this->rows) + 1);
                $sheet->setAutoFilter("A1:M{$lastDataRow}");
            },
        ];
    }
}
