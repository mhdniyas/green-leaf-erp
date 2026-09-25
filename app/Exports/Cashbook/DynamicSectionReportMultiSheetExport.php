<?php

declare(strict_types=1);

namespace App\Exports\Cashbook;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class DynamicSectionReportMultiSheetExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $reportData
     */
    public function __construct(
        private readonly array $reportData,
        private readonly ?string $selectedSectionKey = null
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function sheets(): array
    {
        $sheets = [];
        $sections = $this->reportData['sections'] ?? [];
        $overallSummary = $this->reportData['overall_summary'] ?? [];
        $period = $this->reportData['period'] ?? [];

        // If a single section is requested
        if ($this->selectedSectionKey && isset($sections[$this->selectedSectionKey])) {
            $sec = $sections[$this->selectedSectionKey];
            $sheetTitle = $this->sanitizeSheetTitle($sec['name']);
            $sheets[] = $this->buildSectionSheet($sec, $sheetTitle, $period);

            return $sheets;
        }

        // Full report: 1. Summary Sheet
        $summaryHeadings = ['Section', 'Type', 'Sales (₹)', 'Purchases (₹)', 'Balance (₹)'];
        $summaryRows = [];

        foreach ($sections as $sec) {
            $isTrading = ($sec['type'] === 'trading');
            $summaryRows[] = [
                $sec['name'],
                $isTrading ? 'Trading / Product' : 'Operating Overhead',
                $isTrading ? number_format($sec['summary']['sales'], 2, '.', '') : '—',
                $isTrading ? number_format($sec['summary']['purchases'], 2, '.', '') : '—',
                $isTrading ? number_format($sec['summary']['balance'], 2, '.', '') : '—',
            ];
        }

        $summaryRows[] = [
            'TOTAL',
            'All Sections',
            number_format($overallSummary['total_sales'] ?? 0, 2, '.', ''),
            number_format($overallSummary['total_purchases'] ?? 0, 2, '.', ''),
            number_format($overallSummary['balance'] ?? 0, 2, '.', ''),
        ];

        $sheets[] = new MonthlyReportExport($summaryHeadings, $summaryRows, 'Summary');

        // 2. Individual Section Sheets
        $usedTitles = ['Summary' => true];
        foreach ($sections as $sec) {
            $rawTitle = $sec['name'];
            $sheetTitle = $this->sanitizeSheetTitle($rawTitle, $usedTitles);
            $usedTitles[$sheetTitle] = true;
            $sheets[] = $this->buildSectionSheet($sec, $sheetTitle, $period);
        }

        return $sheets;
    }

    private function buildSectionSheet(array $sec, string $sheetTitle, array $period): MonthlyReportExport
    {
        $isTrading = ($sec['type'] === 'trading');
        $rows = [];

        if ($isTrading) {
            $headings = ['Date', 'Sale (₹)', 'Purchase (₹)', 'Balance (₹)'];
            foreach ($sec['daily_rows'] as $d) {
                $rows[] = [
                    $d['formatted_date'],
                    number_format($d['sale'], 2, '.', ''),
                    number_format($d['purchase'], 2, '.', ''),
                    number_format($d['balance'], 2, '.', ''),
                ];
            }
            $rows[] = [
                'MONTHLY TOTAL',
                number_format($sec['summary']['sales'], 2, '.', ''),
                number_format($sec['summary']['purchases'], 2, '.', ''),
                number_format($sec['summary']['balance'], 2, '.', ''),
            ];
        } else {
            $headings = ['Date', 'Expense (₹)'];
            foreach ($sec['daily_rows'] as $d) {
                $rows[] = [
                    $d['formatted_date'],
                    number_format($d['expense'], 2, '.', ''),
                ];
            }
            $rows[] = [
                'MONTHLY TOTAL',
                number_format($sec['summary']['total_expenses'], 2, '.', ''),
            ];
        }

        return new MonthlyReportExport($headings, $rows, $sheetTitle);
    }

    /**
     * Sanitize worksheet titles: max 31 chars, remove invalid characters, ensure uniqueness.
     *
     * @param  array<string, bool>  $usedTitles
     */
    private function sanitizeSheetTitle(string $rawTitle, array $usedTitles = []): string
    {
        // Remove invalid characters: \ / ? * : [ ]
        $cleaned = preg_replace('/[\\\\\\/\?\*\:\[\]]/', '_', trim($rawTitle)) ?: 'Section';
        $cleaned = mb_substr($cleaned, 0, 31);

        $candidate = $cleaned;
        $counter = 1;
        while (isset($usedTitles[$candidate])) {
            $suffix = '_'.$counter;
            $base = mb_substr($cleaned, 0, 31 - mb_strlen($suffix));
            $candidate = $base.$suffix;
            $counter++;
        }

        return $candidate;
    }
}
