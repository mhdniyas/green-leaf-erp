<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PurchaserReportService
{
    private const INCLUDED_STATUSES = ['generated', 'delivery_review', 'finalized', 'payment_pending', 'paid'];

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int}  $filters
     * @return array<string, mixed>
     */
    public function salesSummary(array $filters): array
    {
        $invoices = $this->filteredInvoices($filters);
        $shops = $this->salesRowsQuery($invoices)
            ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);

        return [
            'totals' => $this->salesTotals($invoices),
            'shops' => collect($shops->items())->map(fn (object $row): array => $this->salesRow($row))->all(),
            'pagination' => $this->pagination($shops),
        ];
    }

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int}  $filters
     * @return array<string, mixed>
     */
    public function salesSummaryExport(array $filters): array
    {
        $invoices = $this->filteredInvoices($filters);

        return [
            'totals' => $this->salesTotals($invoices),
            'shops' => $this->salesRowsQuery($invoices)->get()->map(fn (object $row): array => $this->salesRow($row))->all(),
        ];
    }

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int}  $filters
     * @return array<string, mixed>
     */
    public function itemSummary(array $filters): array
    {
        $lines = $this->itemLinesQuery($filters);
        $aggregate = $this->itemRowsQuery($lines);
        $distinctRows = DB::query()->fromSub(clone $aggregate, 'item_rows')->count();
        $distinctProducts = (clone $lines)->distinct()->count('shop_invoice_items.product_id');
        $invoiceLines = (clone $lines)->count();

        $rows = $aggregate
            ->orderBy('unit')
            ->orderByDesc('billed_quantity')
            ->orderBy('product_name')
            ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);

        return [
            'summary' => [
                'distinct_products' => (int) $distinctProducts,
                'product_unit_rows' => (int) $distinctRows,
                'invoice_lines' => (int) $invoiceLines,
            ],
            'items' => collect($rows->items())->map(fn (object $row): array => $this->itemRow($row))->all(),
            'pagination' => $this->pagination($rows),
        ];
    }

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int}  $filters
     * @return array<string, mixed>
     */
    public function itemSummaryExport(array $filters): array
    {
        $lines = $this->itemLinesQuery($filters);
        $aggregate = $this->itemRowsQuery($lines);

        return [
            'summary' => [
                'distinct_products' => (int) (clone $lines)->distinct()->count('shop_invoice_items.product_id'),
                'product_unit_rows' => (int) DB::query()->fromSub(clone $aggregate, 'item_rows')->count(),
                'invoice_lines' => (int) (clone $lines)->count(),
            ],
            'items' => $aggregate
                ->orderBy('unit')
                ->orderByDesc('billed_quantity')
                ->orderBy('product_name')
                ->get()
                ->map(fn (object $row): array => $this->itemRow($row))
                ->all(),
        ];
    }

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int}  $filters
     */
    private function filteredInvoices(array $filters): Builder
    {
        return DB::table('shop_invoices')
            ->join('shops', 'shops.id', '=', 'shop_invoices.shop_id')
            ->whereDate('shop_invoices.business_date', '>=', $filters['date_from'])
            ->whereDate('shop_invoices.business_date', '<=', $filters['date_to'])
            ->whereIn('shop_invoices.status', $this->statuses($filters['status']))
            ->when($filters['shop_id'] !== null, fn (Builder $query) => $query->where('shop_invoices.shop_id', $filters['shop_id']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('shops.name', 'like', $search)
                        ->orWhere('shops.code', 'like', $search)
                        ->orWhere('shop_invoices.invoice_number', 'like', $search);
                });
            });
    }

    private function salesRowsQuery(Builder $invoices): Builder
    {
        return (clone $invoices)
            ->select([
                'shop_invoices.shop_id',
                'shops.name as shop_name',
                'shops.code as shop_code',
            ])
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('SUM(shop_invoices.final_total) as total_sales')
            ->selectRaw('SUM(shop_invoices.paid_amount) as paid_amount')
            ->selectRaw('SUM(shop_invoices.balance_amount) as outstanding_amount')
            ->groupBy('shop_invoices.shop_id', 'shops.name', 'shops.code')
            ->orderByDesc('total_sales')
            ->orderBy('shops.name');
    }

    /** @return array<string, mixed> */
    private function salesTotals(Builder $invoices): array
    {
        $totals = (clone $invoices)
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COUNT(DISTINCT shop_invoices.shop_id) as shop_count')
            ->selectRaw('COALESCE(SUM(shop_invoices.final_total), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(shop_invoices.paid_amount), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(shop_invoices.balance_amount), 0) as outstanding_amount')
            ->first();

        return [
            'total_sales' => $this->money($totals?->total_sales),
            'total_shops' => (int) ($totals?->shop_count ?? 0),
            'total_invoices' => (int) ($totals?->invoice_count ?? 0),
            'paid_amount' => $this->money($totals?->paid_amount),
            'outstanding_amount' => $this->money($totals?->outstanding_amount),
        ];
    }

    /** @return array<string, mixed> */
    private function salesRow(object $row): array
    {
        return [
            'shop_id' => (int) $row->shop_id,
            'shop_name' => (string) $row->shop_name,
            'shop_code' => (string) $row->shop_code,
            'invoice_count' => (int) $row->invoice_count,
            'total_sales' => $this->money($row->total_sales),
            'paid_amount' => $this->money($row->paid_amount),
            'outstanding_amount' => $this->money($row->outstanding_amount),
        ];
    }

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int}  $filters
     */
    private function itemLinesQuery(array $filters): Builder
    {
        $quantityExpression = $this->itemQuantityExpression();

        return DB::table('shop_invoice_items')
            ->join('shop_invoices', 'shop_invoices.id', '=', 'shop_invoice_items.shop_invoice_id')
            ->join('shops', 'shops.id', '=', 'shop_invoices.shop_id')
            ->leftJoin('products', 'products.id', '=', 'shop_invoice_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereDate('shop_invoices.business_date', '>=', $filters['date_from'])
            ->whereDate('shop_invoices.business_date', '<=', $filters['date_to'])
            ->whereIn('shop_invoices.status', $this->statuses($filters['status']))
            ->whereRaw("{$quantityExpression} > 0")
            ->when($filters['shop_id'] !== null, fn (Builder $query) => $query->where('shop_invoices.shop_id', $filters['shop_id']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('shop_invoice_items.product_name', 'like', $search)
                        ->orWhere('products.name', 'like', $search)
                        ->orWhere('products.sku', 'like', $search)
                        ->orWhere('categories.name', 'like', $search)
                        ->orWhere('shop_invoice_items.price_unit', 'like', $search)
                        ->orWhere('shop_invoice_items.unit', 'like', $search);
                });
            });
    }

    private function itemRowsQuery(Builder $lines): Builder
    {
        $quantityExpression = $this->itemQuantityExpression();
        $unitExpression = $this->itemUnitExpression();

        return (clone $lines)
            ->select('shop_invoice_items.product_id')
            ->selectRaw('MAX(shop_invoice_items.product_name) as product_name')
            ->selectRaw('MAX(products.sku) as product_sku')
            ->selectRaw('MAX(categories.name) as category_name')
            ->selectRaw("{$unitExpression} as unit")
            ->selectRaw("SUM({$quantityExpression}) as billed_quantity")
            ->selectRaw('SUM(shop_invoice_items.final_line_total) as line_sales_amount')
            ->selectRaw('COUNT(*) as invoice_line_count')
            ->selectRaw('COUNT(DISTINCT shop_invoices.id) as invoice_count')
            ->selectRaw('COUNT(DISTINCT shop_invoices.shop_id) as shop_count')
            ->groupBy('shop_invoice_items.product_id')
            ->groupByRaw($unitExpression);
    }

    /** @return array<string, mixed> */
    private function itemRow(object $row): array
    {
        return [
            'product_id' => (int) $row->product_id,
            'product_name' => (string) $row->product_name,
            'product_sku' => $row->product_sku !== null ? (string) $row->product_sku : null,
            'category_name' => $row->category_name !== null ? (string) $row->category_name : null,
            'unit' => (string) $row->unit,
            'billed_quantity' => $this->quantity($row->billed_quantity),
            'line_sales_amount' => $this->money($row->line_sales_amount),
            'invoice_line_count' => (int) $row->invoice_line_count,
            'invoice_count' => (int) $row->invoice_count,
            'shop_count' => (int) $row->shop_count,
        ];
    }

    private function itemQuantityExpression(): string
    {
        return "CASE WHEN shop_invoice_items.price_unit IS NOT NULL AND shop_invoice_items.price_unit <> '' THEN shop_invoice_items.delivered_price_quantity ELSE shop_invoice_items.delivered_qty END";
    }

    private function itemUnitExpression(): string
    {
        return "UPPER(COALESCE(NULLIF(shop_invoice_items.price_unit, ''), shop_invoice_items.unit))";
    }

    /** @return array<int, string> */
    private function statuses(?string $status): array
    {
        return $status !== null && $status !== '' && $status !== 'all'
            ? [$status]
            : self::INCLUDED_STATUSES;
    }

    /** @return array<string, int> */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function money(mixed $value): string
    {
        return number_format(round((float) ($value ?? 0), 2), 2, '.', '');
    }

    private function quantity(mixed $value): string
    {
        return number_format(round((float) ($value ?? 0), 4), 4, '.', '');
    }
}
