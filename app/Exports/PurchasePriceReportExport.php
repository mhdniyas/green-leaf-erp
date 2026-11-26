<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class PurchasePriceReportExport implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  Collection<int, object>  $rows
     */
    public function __construct(
        private readonly Collection $rows,
    ) {}

    public function title(): string
    {
        return 'Purchase Price Report';
    }

    /**
     * @return Collection<int, object>
     */
    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return array<string, int|float>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 28,
            'C' => 18,
            'D' => 10,
            'E' => 8,
            'F' => 14,
            'G' => 15,
            'H' => 18,
            'I' => 18,
            'J' => 18,
            'K' => 15,
            'L' => 14,
            'M' => 12,
            'N' => 18,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Code',
            'Product',
            'Category',
            'Grade',
            'Unit',
            'Purchase Qty',
            'Current Price',
            'Last Price',
            'Last Purchase Date',
            'Approved Purchase',
            'Selling Price',
            'Difference',
            'Margin %',
            'Price Status',
        ];
    }

    /**
     * @param  object  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $approvedPrice = $row->approved_purchase_price !== null ? (float) $row->approved_purchase_price : null;
        $currentPrice = $row->actual_purchase_price !== null ? (float) $row->actual_purchase_price : null;
        $lastPrice = $row->last_purchase_price !== null ? (float) $row->last_purchase_price : null;
        $sellingPrice = $row->selling_price !== null ? (float) $row->selling_price : null;
        $diff = ($currentPrice !== null && $sellingPrice !== null)
            ? ($sellingPrice - $currentPrice)
            : (($approvedPrice !== null && $sellingPrice !== null) ? ($sellingPrice - $approvedPrice) : null);
        $baseCost = $currentPrice ?? $approvedPrice;
        $margin = ($diff !== null && $baseCost > 0) ? ($diff / $baseCost * 100) : null;

        return [
            self::sanitizeCell($row->product_sku ?: ''),
            self::sanitizeCell($row->product_name),
            self::sanitizeCell($row->category_name ?: ''),
            self::sanitizeCell((string) ($row->grade ?? 'A')),
            self::sanitizeCell(strtoupper((string) ($row->price_unit ?: $row->product_unit))),
            $row->purchase_qty !== null ? round((float) $row->purchase_qty, 3) : null,
            $currentPrice !== null ? round($currentPrice, 2) : null,
            $lastPrice !== null ? round($lastPrice, 2) : ($currentPrice === null ? 'No Price Available' : null),
            self::sanitizeCell($row->last_purchase_date ?: ''),
            $approvedPrice !== null ? round($approvedPrice, 2) : null,
            $sellingPrice !== null ? round($sellingPrice, 2) : null,
            $diff !== null ? round($diff, 2) : null,
            $margin !== null ? round($margin, 2) : null,
            self::sanitizeCell((string) ($row->price_status_label ?? 'Not Available')),
        ];
    }

    /**
     * Neutralize spreadsheet formula injection (=, +, -, @, tab, CR) on string cells.
     */
    public static function sanitizeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
