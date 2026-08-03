<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\DailyPriceApproval;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class DailyPriceMatrixExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        protected Collection $products,
        protected array $dates,
        protected string $matrixCategory
    ) {
    }

    public function collection(): Collection
    {
        $productIds = $this->products->pluck('id');

        // Get all approvals for the date range
        $approvals = DailyPriceApproval::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('business_date', $this->dates)
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $items) => $items->keyBy(fn (DailyPriceApproval $app) => $app->business_date->toDateString()));

        $rows = [];
        
        foreach ($this->products as $product) {
            $productApprovals = $approvals->get($product->id) ?? collect();
            
            $row = [
                'product' => $product,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'prices' => [],
            ];
            
            foreach ($this->dates as $dateStr) {
                $approval = $productApprovals->get($dateStr);
                
                if ($this->matrixCategory === 'a') {
                    $row['prices'][$dateStr] = $approval?->price_a;
                } elseif ($this->matrixCategory === 'b') {
                    $row['prices'][$dateStr] = $approval?->price_b;
                } else {
                    $row['prices'][$dateStr] = $approval?->price_c;
                }
            }
            
            $rows[] = $row;
        }

        return collect($rows);
    }

    public function headings(): array
    {
        $headers = ['Product Name', 'Product Code', 'Unit'];
        
        foreach ($this->dates as $dateStr) {
            $date = Carbon::parse($dateStr);
            $headers[] = $date->format('d-M-Y');
        }
        
        return $headers;
    }

    public function map($row): array
    {
        $mapped = [
            $row['product']->name,
            $row['sku'] ?: '',
            strtoupper($row['unit'] ?: 'KG'),
        ];
        
        foreach ($this->dates as $dateStr) {
            $price = $row['prices'][$dateStr] ?? null;
            $mapped[] = $price !== null ? number_format((float) $price, 2, '.', '') : '';
        }
        
        return $mapped;
    }

    public function title(): string
    {
        return 'Price Matrix ' . strtoupper($this->matrixCategory);
    }
}
