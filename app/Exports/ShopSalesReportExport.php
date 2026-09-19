<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShopSalesReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(
        private readonly array $report,
        private readonly string $shopName
    ) {}

    public function headings(): array
    {
        return [
            ['Sales Report — '.$this->shopName],
            ['Period: '.$this->report['period']['label']],
            [],
            [
                'Date',
                'Day',
                'Sales (₹)',
                'Rent (₹)',
                'Cash Purchase (₹)',
                'Other Expense (₹)',
                'Total Expenses (₹)',
                'Net Balance (₹)',
            ],
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->report['daily_rows'] as $day) {
            $rows[] = [
                $day['formatted_date'],
                $day['day_name'],
                number_format((float) $day['sales'], 2, '.', ''),
                number_format((float) $day['rent'], 2, '.', ''),
                number_format((float) $day['purchase'], 2, '.', ''),
                number_format((float) $day['other_expense'], 2, '.', ''),
                number_format((float) $day['total_expenses'], 2, '.', ''),
                number_format((float) $day['net_balance'], 2, '.', ''),
            ];
        }

        // Summary row
        $summary = $this->report['summary'];
        $rows[] = [];
        $rows[] = [
            'TOTALS',
            '',
            number_format((float) $summary['total_sales'], 2, '.', ''),
            number_format((float) $summary['total_rent'], 2, '.', ''),
            number_format((float) $summary['total_purchase'], 2, '.', ''),
            number_format((float) $summary['total_other_expense'], 2, '.', ''),
            number_format((float) $summary['total_expenses'], 2, '.', ''),
            number_format((float) $summary['net_total'], 2, '.', ''),
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true, 'size' => 11]],
            4 => ['font' => ['bold' => true], 'background' => ['color' => ['rgb' => 'E2E8F0']]],
        ];
    }
}
