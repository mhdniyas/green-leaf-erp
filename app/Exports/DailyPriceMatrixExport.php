<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DailyPriceMatrixExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly array $data,
        private readonly string $scope,
    ) {}

    public function title(): string
    {
        return 'Price Matrix';
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    public function array(): array
    {
        $rows = $this->data['exportRows'] ?? $this->matrixExportRows();
        $dateHeaders = $this->dateHeaders();
        $title = match ($this->scope) {
            'today' => 'Today Selling Price Matrix',
            'today_changed' => 'Today Changed Selling Prices',
            default => 'Weekly Selling Price Matrix',
        };

        $sheetRows = [
            [$title],
            ['Price '.strtoupper((string) $this->data['matrixCategory']), Carbon::parse((string) $this->data['targetBusinessDate'])->format('d M Y')],
            [],
            array_merge(['SL', 'Code', 'Item', 'Unit'], $dateHeaders),
        ];

        foreach ($rows as $row) {
            $sheetRow = [
                $row['sl_no'],
                $row['sku'],
                $row['name'],
                strtoupper((string) $row['unit']),
            ];

            foreach ($row['prices'] as $priceInfo) {
                $value = $priceInfo['price'] !== null ? number_format((float) $priceInfo['price'], 2, '.', '') : '';
                $sheetRow[] = $priceInfo['changed'] ? $value.' (changed)' : $value;
            }

            $sheetRows[] = $sheetRow;
        }

        if (count($rows) === 0) {
            $sheetRows[] = ['', '', 'No changed prices found.', ''];
        }

        return $sheetRows;
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->getStyle("A1:{$lastColumn}4")->getFont()->setBold(true);
                $sheet->getStyle("A1:{$lastColumn}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('CFFAFE');
                $sheet->getStyle("A4:{$lastColumn}4")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E2E8F0');
                $sheet->freezePane('A5');
            },
        ];
    }

    /**
     * @return array<int, string>
     */
    private function dateHeaders(): array
    {
        if ($this->scope === 'today' || $this->scope === 'today_changed') {
            return [Carbon::parse((string) $this->data['targetBusinessDate'])->format('d-M-Y')];
        }

        return collect((array) $this->data['matrixDates'])
            ->keys()
            ->map(fn (string $date): string => Carbon::parse($date)->format('d-M-Y'))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matrixExportRows(): array
    {
        $matrixCategory = (string) $this->data['matrixCategory'];
        $targetDate = (string) $this->data['targetBusinessDate'];
        $dates = $this->scope === 'week'
            ? (array) $this->data['matrixDates']
            : [$targetDate => $this->data['matrixDates'][$targetDate] ?? []];

        $rows = [];

        foreach ((array) $this->data['matrixProducts'] as $product) {
            $prices = [];
            $hasChanged = false;

            foreach ($dates as $dateStr => $dateInfo) {
                $cell = $product['prices'][$dateStr] ?? [];
                $price = match ($matrixCategory) {
                    'a' => $cell['price_a'] ?? null,
                    'b' => $cell['price_b'] ?? null,
                    default => $cell['price_c'] ?? null,
                };
                $changed = match ($matrixCategory) {
                    'a' => (bool) ($cell['changed_a'] ?? false),
                    'b' => (bool) ($cell['changed_b'] ?? false),
                    default => (bool) ($cell['changed_c'] ?? false),
                };

                $prices[$dateStr] = [
                    'price' => $price,
                    'changed' => $changed,
                ];
                $hasChanged = $hasChanged || $changed;
            }

            if ($this->scope === 'today_changed' && ! $hasChanged) {
                continue;
            }

            $rows[] = [
                'sl_no' => $product['sl_no'],
                'sku' => $product['sku'] ?: '',
                'name' => $product['name'],
                'unit' => $product['unit'] ?: 'kg',
                'prices' => $prices,
            ];
        }

        return $rows;
    }
}
