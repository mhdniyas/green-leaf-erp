<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SortSheetExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array<string, mixed>>  $matrix  [productId => [shopId => qty]]
     * @param  array<int, array<string, mixed>>  $productMeta  [productId => ['name','unit',...]]
     * @param  Collection  $shops  ordered shop collection
     */
    public function __construct(
        private readonly array $matrix,
        private readonly array $productMeta,
        private readonly Collection $shops,
        private readonly string $date,
    ) {}

    /** @return array<int, SortSheetMainSheet> */
    public function sheets(): array
    {
        return [
            new SortSheetMainSheet($this->matrix, $this->productMeta, $this->shops, $this->date),
        ];
    }
}

// ─── Main "Sheet1" with the full matrix ──────────────────────────────────────

class SortSheetMainSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(
        private readonly array $matrix,
        private readonly array $productMeta,
        private readonly Collection $shops,
        private readonly string $date,
    ) {}

    public function title(): string
    {
        return 'Sheet1';
    }

    /**
     * Build the raw rows array.
     * Each product takes TWO rows:
     *   Row A: SL | Item Name | qty per shop... | Total | Unit
     *   Row B: '' | ''        | tag per shop... | ''    | ''
     *
     * @return array<int, array<mixed>>
     */
    public function array(): array
    {
        return []; // We build everything in AfterSheet for full control
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $this->buildSheet($sheet);
            },
        ];
    }

    private function buildSheet(Worksheet $sheet): void
    {
        $shops = $this->shops;
        $shopCount = $shops->count();

        // ── Column index mapping ──────────────────────────────────────────────
        // Col A  = SL
        // Col B  = Item
        // Col C..C+shopCount-1 = shop quantities
        // Col C+shopCount = Total
        // Col C+shopCount+1 = Unit
        $slCol = 1;  // A
        $itemCol = 2;  // B
        $firstShopCol = 3;  // C
        $totalCol = $firstShopCol + $shopCount;       // after all shops
        $unitCol = $totalCol + 1;

        $totalColLetter = Coordinate::stringFromColumnIndex($totalCol);
        $unitColLetter = Coordinate::stringFromColumnIndex($unitCol);

        // ── Row 1: Title ──────────────────────────────────────────────────────
        $lastColLetter = Coordinate::stringFromColumnIndex($unitCol);
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->setCellValue('A1', "Sort Sheet — {$this->date}");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '155724']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'c3e6cb']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        // ── Row 2: Header ─────────────────────────────────────────────────────
        $headerRow = 2;
        $sheet->setCellValue('A2', 'Code');
        $sheet->setCellValue('B2', 'Item');

        foreach ($shops as $idx => $shop) {
            $col = $firstShopCol + $idx;
            $sheet->setCellValueByColumnAndRow($col, $headerRow, $shop->name);
        }
        $sheet->setCellValueByColumnAndRow($totalCol, $headerRow, 'Total');
        $sheet->setCellValueByColumnAndRow($unitCol, $headerRow, 'Unit');

        // Style header row
        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2d6a4f']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1a5235']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        // ── Data rows (2 rows per product) ───────────────────────────────────
        $currentRow = 3;
        $sl = 1;

        foreach ($this->matrix as $productId => $shopQtys) {
            $meta = $this->productMeta[$productId];
            $qtyRow = $currentRow;
            $tagRow = $currentRow + 1;

            // --- Quantity Row ---
            // Code (merged over 2 rows)
            $sheet->mergeCells("A{$qtyRow}:A{$tagRow}");
            $sheet->setCellValue("A{$qtyRow}", $meta['sku']);
            $sheet->getStyle("A{$qtyRow}:A{$tagRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Item (merged over 2 rows)
            $sheet->mergeCells("B{$qtyRow}:B{$tagRow}");
            $sheet->setCellValue("B{$qtyRow}", $meta['name']);
            $sheet->getStyle("B{$qtyRow}:B{$tagRow}")->applyFromArray([
                'font' => ['bold' => false, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Shop quantities (top row) + warehouse tags (bottom row)
            $rowTotal = 0.0;
            foreach ($shops as $idx => $shop) {
                $col = $firstShopCol + $idx;
                $qty = (float) ($shopQtys[$shop->id] ?? 0);
                $rowTotal += $qty;

                // Quantity cell
                $displayQty = ($qty > 0)
                    ? (($qty == (int) $qty) ? (int) $qty : $qty)
                    : 0;
                $sheet->setCellValueByColumnAndRow($col, $qtyRow, $displayQty);
                $sheet->getStyleByColumnAndRow($col, $qtyRow)->applyFromArray([
                    'font' => ['bold' => $qty > 0, 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Tag cell (warehouse letter)
                $tag = $shop->warehouse_tag ?? '';
                $sheet->setCellValueByColumnAndRow($col, $tagRow, $tag);
                $sheet->getStyleByColumnAndRow($col, $tagRow)->applyFromArray([
                    'font' => ['bold' => false, 'size' => 8, 'color' => ['rgb' => '666666']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f8f9fa']],
                ]);
            }

            // Total (qty row, merged over 2 rows)
            $sheet->mergeCells(
                Coordinate::stringFromColumnIndex($totalCol).$qtyRow.':'.
                Coordinate::stringFromColumnIndex($totalCol).$tagRow
            );
            $displayTotal = ($rowTotal == (int) $rowTotal) ? (int) $rowTotal : $rowTotal;
            $sheet->setCellValueByColumnAndRow($totalCol, $qtyRow, $displayTotal);
            $sheet->getStyleByColumnAndRow($totalCol, $qtyRow)->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '155724']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'd4edda']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Unit (merged over 2 rows)
            $sheet->mergeCells(
                Coordinate::stringFromColumnIndex($unitCol).$qtyRow.':'.
                Coordinate::stringFromColumnIndex($unitCol).$tagRow
            );
            $sheet->setCellValueByColumnAndRow($unitCol, $qtyRow, strtolower($meta['unit'] ?? ''));
            $sheet->getStyleByColumnAndRow($unitCol, $qtyRow)->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '666666']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Row heights
            $sheet->getRowDimension($qtyRow)->setRowHeight(20);
            $sheet->getRowDimension($tagRow)->setRowHeight(14);

            // Borders for both rows
            $rangeStr = "A{$qtyRow}:{$lastColLetter}{$tagRow}";
            $sheet->getStyle($rangeStr)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_HAIR,   'color' => ['rgb' => 'dddddd']],
                    'outerBorder' => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => 'aaaaaa']],
                ],
            ]);

            // Alternate row shade on qty row
            if ($sl % 2 === 0) {
                $sheet->getStyle("A{$qtyRow}:{$lastColLetter}{$qtyRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f5fbf6']],
                ]);
            }

            $currentRow += 2;
            $sl++;
        }

        // ── Grand totals row ─────────────────────────────────────────────────
        $grandRow = $currentRow;
        $sheet->mergeCells("A{$grandRow}:B{$grandRow}");
        $sheet->setCellValue("A{$grandRow}", 'Grand Total ('.count($this->matrix).' items)');

        $grandTotal = 0.0;
        foreach ($shops as $idx => $shop) {
            $col = $firstShopCol + $idx;
            $colTotal = (float) collect($this->matrix)->sum(fn ($shopQtys) => $shopQtys[$shop->id] ?? 0);
            $grandTotal += $colTotal;
            $displayColTotal = ($colTotal == (int) $colTotal) ? (int) $colTotal : $colTotal;
            $sheet->setCellValueByColumnAndRow($col, $grandRow, $displayColTotal > 0 ? $displayColTotal : '—');
        }

        $displayGrand = ($grandTotal == (int) $grandTotal) ? (int) $grandTotal : $grandTotal;
        $sheet->setCellValueByColumnAndRow($totalCol, $grandRow, $displayGrand);

        $sheet->getStyle("A{$grandRow}:{$lastColLetter}{$grandRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a6632']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '155724']]],
        ]);
        $sheet->getRowDimension($grandRow)->setRowHeight(22);

        // ── Column widths ─────────────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(6);   // SL
        $sheet->getColumnDimension('B')->setWidth(22);  // Item

        foreach ($shops as $idx => $shop) {
            $colLetter = Coordinate::stringFromColumnIndex($firstShopCol + $idx);
            $sheet->getColumnDimension($colLetter)->setWidth(8);
        }
        $sheet->getColumnDimension($totalColLetter)->setWidth(9);  // Total
        $sheet->getColumnDimension($unitColLetter)->setWidth(6);   // Unit

        // ── Freeze SL + Item columns, keep header visible ─────────────────────
        $sheet->freezePane('C3');

        // ── Print settings ───────────────────────────────────────────────────
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.4)->setRight(0.4);
    }
}
