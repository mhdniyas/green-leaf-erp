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

class ShopSummarySheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    public function title(): string
    {
        return 'Shop-wise Summary';
    }

    public function headings(): array
    {
        return ['Shop Code', 'Shop Name', 'Affected Bills', 'Credit Notes', 'Credit Amount'];
    }

    public function array(): array
    {
        $data = [];
        foreach ($this->rows as $row) {
            $data[] = [
                SpreadsheetSanitizer::sanitizeCell($row['shop_code']),
                SpreadsheetSanitizer::sanitizeCell($row['shop_name']),
                (int) $row['bills_count'],
                (int) $row['credit_notes_count'],
                (float) $row['credit_amount'],
            ];
        }

        $lastDataRow = count($data) + 1;
        $data[] = [
            'Grand Total',
            '',
            $lastDataRow >= 2 ? "=SUM(C2:C{$lastDataRow})" : 0,
            $lastDataRow >= 2 ? "=SUM(D2:D{$lastDataRow})" : 0,
            $lastDataRow >= 2 ? "=SUM(E2:E{$lastDataRow})" : 0.00,
        ];

        return $data;
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'E' => '₹#,##0.00',
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
                $sheet->setAutoFilter("A1:E{$lastDataRow}");
            },
        ];
    }
}
