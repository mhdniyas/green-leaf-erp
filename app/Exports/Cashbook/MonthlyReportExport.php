<?php

declare(strict_types=1);

namespace App\Exports\Cashbook;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonthlyReportExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    use Exportable;

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
        private readonly string $sheetTitle = 'Report'
    ) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        return array_map(function (array $row): array {
            return array_map(function ($val) {
                if (is_string($val)) {
                    $trimmed = trim($val);
                    if (str_starts_with($trimmed, '=') || str_starts_with($trimmed, '+') || str_starts_with($trimmed, '-') || str_starts_with($trimmed, '@')) {
                        return "'".$val;
                    }
                }

                return $val;
            }, $row);
        }, $this->rows);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
