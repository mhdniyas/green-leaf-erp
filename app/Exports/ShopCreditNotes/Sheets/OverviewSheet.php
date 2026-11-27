<?php

declare(strict_types=1);

namespace App\Exports\ShopCreditNotes\Sheets;

use App\Support\SpreadsheetSanitizer;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OverviewSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /** @param array<string, mixed> $report */
    public function __construct(private readonly array $report) {}

    public function title(): string
    {
        return 'Overview';
    }

    public function headings(): array
    {
        return ['Metric / Filter', 'Value'];
    }

    public function array(): array
    {
        $o = $this->report['overview'];

        return [
            ['Report', 'Shop Credit Note Report'],
            ['Reporting Period', $o['period_label']],
            ['From Date', $o['from_date']],
            ['To Date', $o['to_date']],
            ['Shop Filter', SpreadsheetSanitizer::sanitizeCell($o['shop_name'])],
            ['Total Affected Shops', (int) $o['total_affected_shops']],
            ['Total Affected Invoices', (int) $o['total_affected_invoices']],
            ['Total Credit Notes', (int) $o['total_credit_notes']],
            ['Total Credit Amount (INR)', (float) $o['total_credit_amount']],
            ['Generated At', now()->format('Y-m-d H:i:s')],
        ];
    }

    public function columnFormats(): array
    {
        return ['A' => NumberFormat::FORMAT_TEXT];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']]],
            10 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->getStyle('B10')->getNumberFormat()->setFormatCode('₹#,##0.00');
            },
        ];
    }
}
