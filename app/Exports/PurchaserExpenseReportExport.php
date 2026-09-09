<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class PurchaserExpenseReportExport implements FromArray, ShouldAutoSize, WithTitle
{
    /**
     * @param  array<string, mixed>  $reportData
     */
    public function __construct(
        private readonly array $reportData,
    ) {}

    public function title(): string
    {
        return 'Purchaser Report';
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $summary = $this->reportData['summary'] ?? [];
        $rows = $this->reportData['rows'] ?? $this->reportData['all_rows'] ?? [];

        $sheetRows = [
            ['Green Leaf ERP - Purchaser Purchase & Expense Report'],
            ['Period:', ($summary['date_from_formatted'] ?? '').' to '.($summary['date_to_formatted'] ?? '')],
            ['Total Purchase:', (float) ($summary['total_purchase'] ?? 0.0)],
            ['Total Expenses:', (float) ($summary['total_expenses'] ?? 0.0)],
            ['Combined Total:', (float) ($summary['combined_total'] ?? 0.0)],
            ['Total Entries:', (int) ($summary['total_entries'] ?? 0)],
            [],
            [
                'Date',
                'Type',
                'Purchaser',
                'Supplier',
                'Reference',
                'Details / Expense Type',
                'Purchase Amount',
                'Expense Amount',
                'Total',
            ],
        ];

        foreach ($rows as $row) {
            $sheetRows[] = [
                $row['date'],
                $row['type_label'],
                $row['purchaser_name'],
                $row['supplier_name'],
                $row['reference'],
                $row['type'] === 'expense' ? $row['expense_type'].' - '.$row['details'] : $row['details'],
                (float) $row['purchase_amount'],
                (float) $row['expense_amount'],
                (float) $row['total'],
            ];
        }

        $sheetRows[] = [];
        $sheetRows[] = [
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            (float) ($summary['total_purchase'] ?? 0.0),
            (float) ($summary['total_expenses'] ?? 0.0),
            (float) ($summary['combined_total'] ?? 0.0),
        ];

        return $sheetRows;
    }
}
