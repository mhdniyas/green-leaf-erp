<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryBusinessDayExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    use Exportable;

    /**
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly Collection|array $rows,
        private readonly string $dateFormatted,
        private readonly string $warehouseName = 'All Warehouses',
        private readonly string $sheetTitle = 'Business Day'
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $sheetRows = [
            ['Business Day'],
            ['Date: '.$this->dateFormatted.' | Warehouse: '.$this->warehouseName],
            [],
            ['#', 'CODE', 'PRODUCT', 'ADVANCE'],
        ];

        $list = is_array($this->rows) ? $this->rows : $this->rows->values()->all();

        foreach ($list as $index => $row) {
            $sheetRows[] = [
                $index + 1,
                (string) ($row['product_code'] ?: ($row['sku'] ?? '—')),
                (string) ($row['product_name'] ?? ''),
                (string) ($row['formatted_advance'] ?? ($row['advance'] ?? '')),
            ];
        }

        return $sheetRows;
    }

    public function styles(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestRow();

        if ($highestRow >= 4) {
            $sheet->getStyle('A4:A'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B4:B'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C4:C'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('D4:D'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle('A4:D'.$highestRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
        }

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '059669']],
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '475569']],
            ],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
