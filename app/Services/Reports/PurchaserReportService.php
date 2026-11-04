<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\Inventory\ProductGrade;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaserReportService
{
    private const INCLUDED_STATUSES = ['open', 'approved', 'generated', 'delivery_review', 'finalized', 'payment_pending', 'paid'];

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

        $rows = $this->sortedItemRows(
            $aggregate,
            (string) ($filters['sort'] ?? 'sku'),
            (string) ($filters['direction'] ?? 'asc')
        )->paginate($filters['per_page'], ['*'], 'page', $filters['page']);
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

        $sortedRows = $this->sortedItemRows(
            $aggregate,
            (string) ($filters['sort'] ?? 'sku'),
            (string) ($filters['direction'] ?? 'asc')
        )->get();

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
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int,sort:string,direction:string,operational_date:string,category_ids?:?array<int, int>}  $filters
     * @return array<string, mixed>
     */
    public function specialPricesReport(array $filters): array
    {
        $query = $this->specialPricesBaseQuery($filters);
        $summary = $this->specialPricesSummary($filters);

        $paginator = $query->paginate($filters['per_page'], ['shop_daily_product_prices.*'], 'page', $filters['page']);
        $rows = $this->transformSpecialPriceRows(collect($paginator->items()), $filters['operational_date']);

        return [
            'summary' => $summary,
            'items' => $rows,
            'paginator' => $paginator,
            'pagination' => $this->pagination($paginator),
        ];
    }

    /**
     * @param  array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,sort:string,direction:string,operational_date:string,category_ids?:?array<int, int>}  $filters
     * @return array<string, mixed>
     */
    public function specialPricesExport(array $filters): array
    {
        $query = $this->specialPricesBaseQuery($filters);
        $summary = $this->specialPricesSummary($filters);
        $rows = $this->transformSpecialPriceRows($query->get(), $filters['operational_date']);

        return [
            'summary' => $summary,
            'items' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function specialPricesBaseQuery(array $filters): EloquentBuilder
    {
        $appliedSubquery = function ($exists): void {
            $exists->selectRaw('1')
                ->from('shop_invoices as si')
                ->join('shop_invoice_items as sii', 'sii.shop_invoice_id', '=', 'si.id')
                ->whereColumn('si.shop_id', 'shop_daily_product_prices.shop_id')
                ->whereColumn('si.business_date', 'shop_daily_product_prices.business_date')
                ->whereColumn('sii.product_id', 'shop_daily_product_prices.product_id')
                ->whereRaw("UPPER(COALESCE(sii.product_grade, 'A')) = UPPER(COALESCE(shop_daily_product_prices.product_grade, 'A'))")
                ->whereRaw('ABS(sii.unit_price - shop_daily_product_prices.selling_price) < 0.005');
        };

        $query = ShopDailyProductPrice::query()
            ->with([
                'product.category',
                'shop.priceGroup',
                'createdBy',
                'approvedBy',
            ])
            ->whereDate('shop_daily_product_prices.business_date', '>=', $filters['date_from'])
            ->whereDate('shop_daily_product_prices.business_date', '<=', $filters['date_to'])
            ->when($filters['shop_id'] !== null, fn (EloquentBuilder $q) => $q->where('shop_daily_product_prices.shop_id', $filters['shop_id']))
            ->when(! empty($filters['grade']) && in_array(strtoupper($filters['grade']), ['A', 'B', 'C'], true), function (EloquentBuilder $q) use ($filters): void {
                $q->where('shop_daily_product_prices.product_grade', strtoupper($filters['grade']));
            })
            ->when(! empty($filters['category_ids']), function (EloquentBuilder $q) use ($filters): void {
                $q->whereHas('product', fn (EloquentBuilder $pq) => $pq->whereIn('category_id', $filters['category_ids']));
            })
            ->when($filters['search'] !== '', function (EloquentBuilder $q) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $q->where(function (EloquentBuilder $sub) use ($search): void {
                    $sub->whereHas('product', fn (EloquentBuilder $pq) => $pq->where('name', 'like', $search)->orWhere('sku', 'like', $search))
                        ->orWhereHas('shop', fn (EloquentBuilder $sq) => $sq->where('name', 'like', $search)->orWhere('code', 'like', $search));
                });
            });

        $operationalDate = $filters['operational_date'] ?? today()->toDateString();
        $status = $filters['status'] ?? 'all';

        match ($status) {
            'draft' => $query->where('shop_daily_product_prices.status', 'draft'),
            'approved' => $query->where('shop_daily_product_prices.status', 'approved'),
            'applied' => $query->where('shop_daily_product_prices.status', 'approved')->whereExists($appliedSubquery),
            'not_applied' => $query->where('shop_daily_product_prices.status', 'approved')->whereNotExists($appliedSubquery),
            'expired' => $query->where('shop_daily_product_prices.status', 'approved')
                ->where('shop_daily_product_prices.business_date', '<', $operationalDate)
                ->whereNotExists($appliedSubquery),
            default => null,
        };

        $sort = $filters['sort'] ?? 'product';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $this->sortSpecialPricesQuery($query, $sort, $direction);
    }

    private function sortSpecialPricesQuery(EloquentBuilder $query, string $sort, string $direction): EloquentBuilder
    {
        return match ($sort) {
            'product', 'name' => $query
                ->join('products as sort_p', 'sort_p.id', '=', 'shop_daily_product_prices.product_id')
                ->join('shops as sort_s', 'sort_s.id', '=', 'shop_daily_product_prices.shop_id')
                ->orderBy('sort_p.name', $direction)
                ->orderBy('sort_s.name', 'asc')
                ->orderByDesc('shop_daily_product_prices.business_date')
                ->select('shop_daily_product_prices.*'),
            'sku' => $query
                ->join('products as sort_p', 'sort_p.id', '=', 'shop_daily_product_prices.product_id')
                ->orderBy('sort_p.sku', $direction)
                ->orderByDesc('shop_daily_product_prices.business_date')
                ->select('shop_daily_product_prices.*'),
            'shop' => $query
                ->join('shops as sort_s', 'sort_s.id', '=', 'shop_daily_product_prices.shop_id')
                ->join('products as sort_p', 'sort_p.id', '=', 'shop_daily_product_prices.product_id')
                ->orderBy('sort_s.name', $direction)
                ->orderBy('sort_p.name', 'asc')
                ->orderByDesc('shop_daily_product_prices.business_date')
                ->select('shop_daily_product_prices.*'),
            'date' => $query
                ->orderBy('shop_daily_product_prices.business_date', $direction)
                ->orderBy('shop_daily_product_prices.product_id', 'asc'),
            'price', 'special_price' => $query
                ->orderBy('shop_daily_product_prices.selling_price', $direction),
            'grade' => $query
                ->orderBy('shop_daily_product_prices.product_grade', $direction)
                ->orderBy('shop_daily_product_prices.product_id', 'asc'),
            'status' => $query
                ->orderBy('shop_daily_product_prices.status', $direction)
                ->orderByDesc('shop_daily_product_prices.business_date'),
            'updated_at' => $query
                ->orderBy('shop_daily_product_prices.updated_at', $direction),
            default => $query
                ->join('products as sort_p', 'sort_p.id', '=', 'shop_daily_product_prices.product_id')
                ->join('shops as sort_s', 'sort_s.id', '=', 'shop_daily_product_prices.shop_id')
                ->orderByDesc('shop_daily_product_prices.business_date')
                ->orderBy('sort_p.name', 'asc')
                ->orderBy('sort_s.name', 'asc')
                ->select('shop_daily_product_prices.*'),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function specialPricesSummary(array $filters): array
    {
        $base = ShopDailyProductPrice::query()
            ->whereDate('business_date', '>=', $filters['date_from'])
            ->whereDate('business_date', '<=', $filters['date_to'])
            ->when($filters['shop_id'] !== null, fn (EloquentBuilder $q) => $q->where('shop_id', $filters['shop_id']))
            ->when(! empty($filters['grade']) && in_array(strtoupper($filters['grade']), ['A', 'B', 'C'], true), function (EloquentBuilder $q) use ($filters): void {
                $q->where('product_grade', strtoupper($filters['grade']));
            })
            ->when(! empty($filters['category_ids']), function (EloquentBuilder $q) use ($filters): void {
                $q->whereHas('product', fn (EloquentBuilder $pq) => $pq->whereIn('category_id', $filters['category_ids']));
            })
            ->when($filters['search'] !== '', function (EloquentBuilder $q) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $q->where(function (EloquentBuilder $sub) use ($search): void {
                    $sub->whereHas('product', fn (EloquentBuilder $pq) => $pq->where('name', 'like', $search)->orWhere('sku', 'like', $search))
                        ->orWhereHas('shop', fn (EloquentBuilder $sq) => $sq->where('name', 'like', $search)->orWhere('code', 'like', $search));
                });
            });

        $appliedSubquery = function ($exists): void {
            $exists->selectRaw('1')
                ->from('shop_invoices as si')
                ->join('shop_invoice_items as sii', 'sii.shop_invoice_id', '=', 'si.id')
                ->whereColumn('si.shop_id', 'shop_daily_product_prices.shop_id')
                ->whereColumn('si.business_date', 'shop_daily_product_prices.business_date')
                ->whereColumn('sii.product_id', 'shop_daily_product_prices.product_id')
                ->whereRaw("UPPER(COALESCE(sii.product_grade, 'A')) = UPPER(COALESCE(shop_daily_product_prices.product_grade, 'A'))")
                ->whereRaw('ABS(sii.unit_price - shop_daily_product_prices.selling_price) < 0.005');
        };

        $totalRecords = (clone $base)->count();
        $distinctShops = (clone $base)->distinct('shop_id')->count('shop_id');
        $distinctProducts = (clone $base)->distinct('product_id')->count('product_id');
        $appliedCount = (clone $base)->where('status', 'approved')->whereExists($appliedSubquery)->count();
        $draftCount = (clone $base)->where('status', 'draft')->count();
        $approvedCount = (clone $base)->where('status', 'approved')->count();
        $notAppliedCount = $approvedCount - $appliedCount;

        return [
            'total_records' => $totalRecords,
            'distinct_shops' => $distinctShops,
            'distinct_products' => $distinctProducts,
            'applied_count' => $appliedCount,
            'not_applied_count' => $notAppliedCount,
            'draft_count' => $draftCount,
            'approved_count' => $approvedCount,
        ];
    }

    /**
     * @param  Collection<int, ShopDailyProductPrice>  $items
     * @return array<int, array<string, mixed>>
     */
    private function transformSpecialPriceRows(Collection $items, string $operationalDate): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $productIds = $items->pluck('product_id')->unique()->values();
        $dates = $items->pluck('business_date')->map(fn ($d) => $d->format('Y-m-d'))->unique()->values();
        $shopIds = $items->pluck('shop_id')->unique()->values();

        $minDate = $dates->min();
        $maxDate = $dates->max();

        $approvals = DailyPriceApproval::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('business_date', '>=', $minDate)
            ->whereDate('business_date', '<=', $maxDate)
            ->get()
            ->keyBy(function (DailyPriceApproval $a): string {
                $grade = $a->grade instanceof ProductGrade
                    ? $a->grade->value
                    : (string) $a->grade;

                return $a->product_id.'|'.$a->business_date->format('Y-m-d').'|'.strtoupper($grade);
            });

        $invoices = ShopInvoice::query()
            ->with(['items' => function ($iq) use ($productIds): void {
                $iq->whereIn('product_id', $productIds);
            }])
            ->whereIn('shop_id', $shopIds)
            ->whereDate('business_date', '>=', $minDate)
            ->whereDate('business_date', '<=', $maxDate)
            ->get()
            ->groupBy(fn (ShopInvoice $inv): string => $inv->shop_id.'|'.$inv->business_date->format('Y-m-d'));

        return $items->map(function (ShopDailyProductPrice $row) use ($approvals, $invoices, $operationalDate): array {
            $dateString = $row->business_date->toDateString();
            $rowGrade = strtoupper((string) ($row->product_grade ?: 'A'));
            $approval = $approvals->get($row->product_id.'|'.$dateString.'|'.$rowGrade);

            $group = $row->shop?->priceGroup;
            $groupName = strtoupper((string) ($group?->name ?? ''));
            $priceColumn = match ($groupName) {
                'A' => 'price_a',
                'B' => 'price_b',
                'C' => 'price_c',
                default => null,
            };

            $normalPrice = null;
            if ($approval && $priceColumn && $approval->status === 'approved' && (float) $approval->{$priceColumn} > 0) {
                $normalPrice = round((float) $approval->{$priceColumn}, 2);
            }

            $specialPrice = round((float) $row->selling_price, 2);
            $difference = $normalPrice !== null ? round($specialPrice - $normalPrice, 2) : null;

            $shopInvoices = $invoices->get($row->shop_id.'|'.$dateString);
            $invoice = null;
            $invoiceItem = null;

            if ($shopInvoices instanceof Collection) {
                foreach ($shopInvoices as $inv) {
                    $item = $inv->items->first(function (ShopInvoiceItem $sii) use ($row, $rowGrade): bool {
                        return (int) $sii->product_id === (int) $row->product_id
                            && strtoupper((string) ($sii->product_grade ?: 'A')) === $rowGrade;
                    });
                    if ($item) {
                        $invoice = $inv;
                        $invoiceItem = $item;
                        break;
                    }
                }
                $invoice ??= $shopInvoices->first();
            }

            $isApplied = false;
            $isMismatch = false;
            if ($row->status === 'approved' && $invoiceItem) {
                if (abs((float) $invoiceItem->unit_price - $specialPrice) < 0.005) {
                    $isApplied = true;
                } else {
                    $isMismatch = true;
                }
            }

            if ($row->status === 'draft') {
                $statusKey = 'draft';
                $statusLabel = 'Draft';
            } elseif ($isApplied) {
                $statusKey = 'applied';
                $statusLabel = 'Applied to Invoice';
            } elseif ($isMismatch) {
                $statusKey = 'not_applied';
                $statusLabel = 'Not Applied (Mismatch)';
            } elseif ($dateString < $operationalDate) {
                $statusKey = 'expired';
                $statusLabel = 'Expired (Unused)';
            } else {
                $statusKey = 'configured';
                $statusLabel = 'Configured (Pending Bill)';
            }

            $updater = $row->approvedBy ?? $row->createdBy;
            $updaterName = $updater?->name;
            $updatedAt = $row->updated_at ?? $row->approved_at ?? $row->created_at;

            return [
                'id' => $row->id,
                'business_date' => $dateString,
                'business_date_formatted' => $row->business_date->format('d M Y'),
                'product_id' => $row->product_id,
                'product_name' => $row->product?->name ?? 'Unknown Product',
                'product_sku' => $row->product?->sku ?? '—',
                'category_name' => $row->product?->category?->name ?? 'Uncategorized',
                'price_unit' => (string) ($row->price_unit ?: $row->product?->unit ?: 'kg'),
                'shop_id' => $row->shop_id,
                'shop_name' => $row->shop?->name ?? 'Unknown Shop',
                'shop_code' => $row->shop?->code ?? '—',
                'price_group' => $groupName !== '' ? $groupName : 'N/A',
                'grade' => $rowGrade,
                'grade_label' => 'Grade '.$rowGrade,
                'normal_price' => $normalPrice,
                'normal_price_formatted' => $normalPrice !== null ? '₹'.number_format($normalPrice, 2) : '—',
                'special_price' => $specialPrice,
                'special_price_formatted' => '₹'.number_format($specialPrice, 2),
                'difference' => $difference,
                'difference_formatted' => $difference !== null
                    ? ($difference < 0 ? '-₹'.number_format(abs($difference), 2) : '+₹'.number_format($difference, 2))
                    : '—',
                'raw_status' => $row->status,
                'status_key' => $statusKey,
                'status_label' => $statusLabel,
                'is_applied' => $isApplied,
                'invoice_id' => $invoice?->id,
                'invoice_number' => $invoice?->invoice_number,
                'invoice_item_price' => $invoiceItem ? round((float) $invoiceItem->unit_price, 2) : null,
                'updater_name' => $updaterName,
                'updated_at' => $updatedAt,
                'updated_at_formatted' => $updatedAt?->format('d M Y, h:i A'),
                'approved_by_name' => $row->approvedBy?->name,
                'approved_at' => $row->approved_at,
                'approved_at_formatted' => $row->approved_at?->format('d M Y, h:i A'),
                'reason' => $row->reason,
            ];
        })->all();
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

    private function sortedItemRows(Builder $query, string $sort, string $direction = 'asc'): Builder
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return match ($sort) {
            'product', 'name' => $query->orderBy('product_name', $dir)
                ->orderByRaw("CASE WHEN product_sku IS NULL OR product_sku = '' THEN 1 ELSE 0 END ASC")
                ->orderBy('product_sku')
                ->orderBy('unit'),
            'category' => $query->orderByRaw("CASE WHEN category_name IS NULL OR category_name = '' THEN 1 ELSE 0 END ASC")
                ->orderBy('category_name', $dir)
                ->orderBy('product_name')
                ->orderBy('unit'),
            'unit' => $query->orderBy('unit', $dir)
                ->orderBy('product_name')
                ->orderBy('product_sku'),
            'quantity', 'billed_quantity' => $query->orderBy('billed_quantity', $dir)
                ->orderBy('product_name')
                ->orderBy('unit'),
            'sales', 'line_sales_amount' => $query->orderBy('line_sales_amount', $dir)
                ->orderBy('product_name')
                ->orderBy('unit'),
            'shops', 'shop_count' => $query->orderBy('shop_count', $dir)
                ->orderBy('product_name')
                ->orderBy('unit'),
            'invoices', 'invoice_count' => $query->orderBy('invoice_count', $dir)
                ->orderBy('product_name')
                ->orderBy('unit'),
            'balance' => $query->orderBy('billed_quantity', $dir === 'asc' ? 'asc' : 'desc')
                ->orderBy('product_sku')
                ->orderBy('product_name'),
            default => $this->applySkuOrder($query, $dir),
        };
    }

    private function applySkuOrder(Builder $query, string $direction = 'asc'): Builder
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $driver = $query->getConnection()->getDriverName();
        $priority = Product::numericSkuPriorityExpression('product_sku', $driver);
        $value = Product::numericSkuValueExpression('product_sku', $driver);

        return $query
            ->orderByRaw("CASE WHEN product_sku IS NULL OR product_sku = '' THEN 1 ELSE 0 END ASC")
            ->orderByRaw("{$priority} ASC")
            ->orderByRaw("{$value} {$dir}")
            ->orderBy('product_sku', $dir)
            ->orderBy('product_name', 'asc')
            ->orderBy('unit', 'asc');
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
            ->where('goods_received.status', 'approved')
            ->whereIn('goods_received_items.product_id', $productIds)
            ->whereDate('goods_received.received_at', '>=', $filters['date_from'])
            ->whereDate('goods_received.received_at', '<=', $filters['date_to'])
            ->select('goods_received_items.product_id')
            ->selectRaw('SUM(goods_received_items.received_qty) as incoming_quantity')
            ->groupBy('goods_received_items.product_id')
            ->pluck('incoming_quantity', 'product_id');

        $matchedBillIncoming = DB::table('advance_receive_matches')
            ->join('goods_received as bill_gr', 'bill_gr.id', '=', 'advance_receive_matches.bill_goods_received_id')
            ->join('goods_received as adv_gr', 'adv_gr.id', '=', 'advance_receive_matches.advance_goods_received_id')
            ->whereNull('bill_gr.deleted_at')
            ->whereNull('adv_gr.deleted_at')
            ->where('bill_gr.status', 'approved')
            ->where('adv_gr.status', 'approved')
            ->whereIn('advance_receive_matches.product_id', $productIds)
            ->whereDate('bill_gr.received_at', '>=', $filters['date_from'])
            ->whereDate('bill_gr.received_at', '<=', $filters['date_to'])
            ->select('advance_receive_matches.product_id')
            ->selectRaw('SUM(advance_receive_matches.base_qty) as matched_quantity')
            ->groupBy('advance_receive_matches.product_id')
            ->pluck('matched_quantity', 'product_id');

        return $rows->map(function (object $row) use ($deliveries, $incoming, $matchedBillIncoming): array {
            $item = $this->itemRow($row);
            $rawIncomingQuantity = (float) ($incoming[$row->product_id] ?? 0);
            $reconciledDeduction = (float) ($matchedBillIncoming[$row->product_id] ?? 0);
            $incomingQuantity = max(0.0, $rawIncomingQuantity - $reconciledDeduction);
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
