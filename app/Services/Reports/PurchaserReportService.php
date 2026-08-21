<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaserReportService
{
    private const INCLUDED_STATUSES = ['generated', 'delivery_review', 'finalized', 'payment_pending', 'paid'];

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int,category_ids?:?array<int, int>}  $filters
     * @return array<string, mixed>
     */
    public function salesSummary(array $filters): array
    {
        $invoices = $this->filteredInvoices($filters);
        $shops = $this->salesRowsQuery($invoices, $filters)
            ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);

        return [
            'totals' => $this->salesTotals($invoices, $filters),
            'shops' => collect($shops->items())->map(fn (object $row): array => $this->salesRow($row))->all(),
            'pagination' => $this->pagination($shops),
        ];
    }

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int,category_ids?:?array<int, int>}  $filters
     * @return array<string, mixed>
     */
    public function salesSummaryExport(array $filters): array
    {
        $invoices = $this->filteredInvoices($filters);

        return [
            'totals' => $this->salesTotals($invoices, $filters),
            'shops' => $this->salesRowsQuery($invoices, $filters)->get()->map(fn (object $row): array => $this->salesRow($row))->all(),
        ];
    }

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int,category_ids?:?array<int, int>}  $filters
     * @return array<string, mixed>
     */
    public function itemSummary(array $filters): array
    {
        $lines = $this->itemLinesQuery($filters);
        $aggregate = $this->itemRowsQuery($lines);

        $linesSummary = (clone $lines)
            ->selectRaw('COUNT(*) as invoice_lines')
            ->selectRaw('COUNT(DISTINCT shop_invoice_items.product_id) as distinct_products')
            ->first();

        $rows = $this->sortedItemRows($aggregate, (string) ($filters['sort'] ?? 'sku'))
            ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);
        $pageItems = collect($rows->items());

        return [
            'summary' => [
                'distinct_products' => (int) ($linesSummary?->distinct_products ?? 0),
                'product_unit_rows' => (int) $rows->total(),
                'invoice_lines' => (int) ($linesSummary?->invoice_lines ?? 0),
            ],
            'items' => $this->enrichedItemRows($pageItems, $filters),
            'pagination' => $this->pagination($rows),
        ];
    }

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int,category_ids?:?array<int, int>}  $filters
     * @return array<string, mixed>
     */
    public function itemSummaryExport(array $filters): array
    {
        $lines = $this->itemLinesQuery($filters);
        $aggregate = $this->itemRowsQuery($lines);

        $linesSummary = (clone $lines)
            ->selectRaw('COUNT(*) as invoice_lines')
            ->selectRaw('COUNT(DISTINCT shop_invoice_items.product_id) as distinct_products')
            ->first();

        $sortedRows = $this->sortedItemRows($aggregate, (string) ($filters['sort'] ?? 'sku'))->get();

        return [
            'summary' => [
                'distinct_products' => (int) ($linesSummary?->distinct_products ?? 0),
                'product_unit_rows' => $sortedRows->count(),
                'invoice_lines' => (int) ($linesSummary?->invoice_lines ?? 0),
            ],
            'items' => $sortedRows
                ->map(fn (object $row): array => $this->itemRow($row))
                ->all(),
        ];
    }

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int,category_ids?:?array<int, int>}  $filters
     */
    private function filteredInvoices(array $filters): Builder
    {
        $categoryIds = $this->categoryIds($filters);

        return DB::table('shop_invoices')
            ->join('shops', 'shops.id', '=', 'shop_invoices.shop_id')
            ->whereDate('shop_invoices.business_date', '>=', $filters['date_from'])
            ->whereDate('shop_invoices.business_date', '<=', $filters['date_to'])
            ->whereIn('shop_invoices.status', $this->statuses($filters['status']))
            ->when($categoryIds !== null, function (Builder $query) use ($categoryIds): void {
                $query->whereExists(function (Builder $exists) use ($categoryIds): void {
                    $exists->selectRaw('1')
                        ->from('shop_invoice_items')
                        ->join('products', 'products.id', '=', 'shop_invoice_items.product_id')
                        ->whereColumn('shop_invoice_items.shop_invoice_id', 'shop_invoices.id')
                        ->whereIn('products.category_id', $categoryIds);
                });
            })
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

    private function salesRowsQuery(Builder $invoices, array $filters): Builder
    {
        if ($this->categoryIds($filters) !== null) {
            return $this->categoryScopedSalesRowsQuery($invoices, $this->categoryIds($filters) ?? []);
        }

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
    private function salesTotals(Builder $invoices, array $filters): array
    {
        if ($this->categoryIds($filters) !== null) {
            $rows = $this->categoryScopedSalesRowsQuery($invoices, $this->categoryIds($filters) ?? []);
            $totals = DB::query()->fromSub($rows, 'scoped_sales')
                ->selectRaw('COUNT(*) as shop_count')
                ->selectRaw('COALESCE(SUM(invoice_count), 0) as invoice_count')
                ->selectRaw('COALESCE(SUM(total_sales), 0) as total_sales')
                ->selectRaw('COALESCE(SUM(paid_amount), 0) as paid_amount')
                ->selectRaw('COALESCE(SUM(outstanding_amount), 0) as outstanding_amount')
                ->first();

            return [
                'total_sales' => $this->money($totals?->total_sales),
                'total_shops' => (int) ($totals?->shop_count ?? 0),
                'total_invoices' => (int) ($totals?->invoice_count ?? 0),
                'paid_amount' => $this->money($totals?->paid_amount),
                'outstanding_amount' => $this->money($totals?->outstanding_amount),
            ];
        }

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
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int,category_ids?:?array<int, int>}  $filters
     */
    private function itemLinesQuery(array $filters): Builder
    {
        $quantityExpression = $this->itemQuantityExpression();
        $categoryIds = $this->categoryIds($filters);

        return DB::table('shop_invoice_items')
            ->join('shop_invoices', 'shop_invoices.id', '=', 'shop_invoice_items.shop_invoice_id')
            ->join('shops', 'shops.id', '=', 'shop_invoices.shop_id')
            ->leftJoin('products', 'products.id', '=', 'shop_invoice_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereDate('shop_invoices.business_date', '>=', $filters['date_from'])
            ->whereDate('shop_invoices.business_date', '<=', $filters['date_to'])
            ->whereIn('shop_invoices.status', $this->statuses($filters['status']))
            ->whereRaw("{$quantityExpression} > 0")
            ->when($categoryIds !== null, fn (Builder $query) => $query->whereIn('products.category_id', $categoryIds))
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
            ->selectRaw('MAX(products.image) as product_image')
            ->selectRaw("{$unitExpression} as unit")
            ->selectRaw("SUM({$quantityExpression}) as billed_quantity")
            ->selectRaw('SUM(shop_invoice_items.final_line_total) as line_sales_amount')
            ->selectRaw('COUNT(*) as invoice_line_count')
            ->selectRaw('COUNT(DISTINCT shop_invoices.id) as invoice_count')
            ->selectRaw('COUNT(DISTINCT shop_invoices.shop_id) as shop_count')
            ->groupBy('shop_invoice_items.product_id')
            ->groupByRaw($unitExpression);
    }

    private function sortedItemRows(Builder $query, string $sort): Builder
    {
        return $sort === 'balance'
            ? $query->orderByDesc('billed_quantity')->orderBy('product_sku')->orderBy('product_name')
            : $query->orderByRaw("CASE WHEN product_sku IS NULL OR product_sku = '' THEN 1 ELSE 0 END")
                ->orderBy('product_sku')
                ->orderBy('product_name')
                ->orderBy('unit');
    }

    /** @param array<int, int> $categoryIds */
    private function categoryScopedSalesRowsQuery(Builder $invoices, array $categoryIds): Builder
    {
        $scopedInvoices = (clone $invoices)
            ->join('shop_invoice_items as scoped_items', 'scoped_items.shop_invoice_id', '=', 'shop_invoices.id')
            ->join('products as scoped_products', 'scoped_products.id', '=', 'scoped_items.product_id')
            ->whereIn('scoped_products.category_id', $categoryIds)
            ->select([
                'shop_invoices.id as invoice_id',
                'shop_invoices.shop_id',
                'shops.name as shop_name',
                'shops.code as shop_code',
                'shop_invoices.paid_amount',
            ])
            ->selectRaw('SUM(scoped_items.final_line_total) as scoped_total')
            ->groupBy(
                'shop_invoices.id',
                'shop_invoices.shop_id',
                'shops.name',
                'shops.code',
                'shop_invoices.paid_amount',
            );

        return DB::query()->fromSub($scopedInvoices, 'category_invoices')
            ->select(['shop_id', 'shop_name', 'shop_code'])
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('SUM(scoped_total) as total_sales')
            ->selectRaw('SUM(CASE WHEN paid_amount > scoped_total THEN scoped_total ELSE paid_amount END) as paid_amount')
            ->selectRaw('SUM(scoped_total - CASE WHEN paid_amount > scoped_total THEN scoped_total ELSE paid_amount END) as outstanding_amount')
            ->groupBy('shop_id', 'shop_name', 'shop_code')
            ->orderByDesc('total_sales')
            ->orderBy('shop_name');
    }

    /** @return array<string, mixed> */
    private function itemRow(object $row): array
    {
        $imagePath = isset($row->product_image) && $row->product_image !== null && (string) $row->product_image !== '' ? (string) $row->product_image : null;
        $imageUrl = $imagePath ? asset('storage/'.$imagePath) : null;

        return [
            'image' => $imagePath,
            'image_url' => $imageUrl,
            'product_image' => $imagePath,
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

    /** @param Collection<int, object> $rows */
    private function enrichedItemRows(Collection $rows, array $filters): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $productIds = $rows->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $unitExpression = $this->itemUnitExpression();
        $quantityExpression = $this->itemQuantityExpression();

        $deliveries = $this->itemLinesQuery($filters)
            ->whereIn('shop_invoice_items.product_id', $productIds)
            ->select([
                'shop_invoice_items.product_id',
                'shop_invoices.shop_id',
                'shops.name as shop_name',
                'shops.code as shop_code',
            ])
            ->selectRaw("{$unitExpression} as unit")
            ->selectRaw("SUM({$quantityExpression}) as delivered_quantity")
            ->selectRaw('SUM(shop_invoice_items.final_line_total) as sales_amount')
            ->selectRaw('COUNT(DISTINCT shop_invoices.id) as invoice_count')
            ->groupBy('shop_invoice_items.product_id', 'shop_invoices.shop_id', 'shops.name', 'shops.code')
            ->groupByRaw($unitExpression)
            ->get()
            ->groupBy(fn (object $row): string => $row->product_id.'|'.strtoupper((string) $row->unit));

        $incoming = DB::table('goods_received_items')
            ->join('goods_received', 'goods_received.id', '=', 'goods_received_items.goods_received_id')
            ->whereNull('goods_received_items.deleted_at')
            ->whereNull('goods_received.deleted_at')
            ->whereIn('goods_received_items.product_id', $productIds)
            ->whereDate('goods_received.received_at', '>=', $filters['date_from'])
            ->whereDate('goods_received.received_at', '<=', $filters['date_to'])
            ->select('goods_received_items.product_id')
            ->selectRaw('SUM(goods_received_items.received_qty) as incoming_quantity')
            ->groupBy('goods_received_items.product_id')
            ->pluck('incoming_quantity', 'product_id');

        return $rows->map(function (object $row) use ($deliveries, $incoming): array {
            $item = $this->itemRow($row);
            $incomingQuantity = (float) ($incoming[$row->product_id] ?? 0);
            $outgoingQuantity = (float) $row->billed_quantity;
            $key = $row->product_id.'|'.strtoupper((string) $row->unit);

            $item['incoming_quantity'] = $this->quantity($incomingQuantity);
            $item['outgoing_quantity'] = $this->quantity($outgoingQuantity);
            $item['balance_quantity'] = $this->quantity($incomingQuantity - $outgoingQuantity);
            $item['delivered_shops'] = collect($deliveries->get($key, collect()))
                ->map(fn (object $shop): array => [
                    'shop_id' => (int) $shop->shop_id,
                    'shop_name' => (string) $shop->shop_name,
                    'shop_code' => (string) $shop->shop_code,
                    'delivered_quantity' => $this->quantity($shop->delivered_quantity),
                    'unit' => strtoupper((string) $shop->unit),
                    'sales_amount' => $this->money($shop->sales_amount),
                    'invoice_count' => (int) $shop->invoice_count,
                ])->values()->all();

            return $item;
        })->all();
    }

    private function itemQuantityExpression(): string
    {
        return "CASE WHEN shop_invoice_items.price_unit IS NOT NULL AND shop_invoice_items.price_unit <> '' THEN shop_invoice_items.delivered_price_quantity ELSE shop_invoice_items.delivered_qty END";
    }

    private function itemUnitExpression(): string
    {
        return "UPPER(COALESCE(NULLIF(shop_invoice_items.price_unit, ''), shop_invoice_items.unit))";
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, int>|null
     */
    private function categoryIds(array $filters): ?array
    {
        $categoryIds = $filters['category_ids'] ?? null;
        if (! is_array($categoryIds) || $categoryIds === []) {
            return null;
        }

        return array_values(array_filter(array_map('intval', $categoryIds)));
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
