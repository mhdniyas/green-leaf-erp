<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ShopVendorPurchaseReportService
{
    /**
     * Compact summary for main shop cashbook.
     *
     * @return array{
     *     total_purchase: float,
     *     cash_purchase: float,
     *     credit_purchase: float,
     *     invoice_count: int
     * }
     */
    public function summaryForShopPeriod(int $shopId, string $startDate, string $endDate): array
    {
        $query = DB::table('purchase_invoices')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where('purchase_invoices.shop_id', $shopId)
            ->where(function (Builder $q): void {
                $q->where('purchase_invoices.purchase_source', 'shop')
                    ->orWhere('purchaser_carts.purchase_source', 'shop');
            })
            ->whereDate('purchaser_carts.business_date', '>=', $startDate)
            ->whereDate('purchaser_carts.business_date', '<=', $endDate);

        $result = $query->selectRaw("
            COALESCE(SUM(purchase_invoices.amount - purchase_invoices.discount_amount), 0) as total_purchase,
            COALESCE(SUM(CASE WHEN LOWER(purchase_invoices.payment_method) = 'cash' THEN purchase_invoices.amount - purchase_invoices.discount_amount ELSE 0 END), 0) as cash_purchase,
            COALESCE(SUM(CASE WHEN LOWER(purchase_invoices.payment_method) = 'credit' THEN purchase_invoices.amount - purchase_invoices.discount_amount ELSE 0 END), 0) as credit_purchase,
            COUNT(DISTINCT purchase_invoices.id) as invoice_count
        ")->first();

        return [
            'total_purchase' => round((float) ($result->total_purchase ?? 0), 2),
            'cash_purchase' => round((float) ($result->cash_purchase ?? 0), 2),
            'credit_purchase' => round((float) ($result->credit_purchase ?? 0), 2),
            'invoice_count' => (int) ($result->invoice_count ?? 0),
        ];
    }

    /**
     * Detailed read-only Vendor Purchases report for a shop.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(int $shopId, array $filters): array
    {
        $summary = $this->calculateSummary($shopId, $filters);
        $vendorSummary = $this->calculateVendorSummary($shopId, $filters);
        $productSummary = $this->calculateProductSummary($shopId, $filters);
        $dailyDetails = $this->buildDailyDetails($shopId, $filters);
        $options = $this->getFilterOptions($shopId);

        return [
            'summary' => $summary,
            'vendor_summary' => $vendorSummary,
            'product_summary' => $productSummary,
            'daily_details' => $dailyDetails,
            'options' => $options,
            'filters' => $filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     total_purchase: float,
     *     cash_purchase: float,
     *     credit_purchase: float,
     *     invoice_count: int,
     *     vendor_count: int,
     *     product_count: int
     * }
     */
    private function calculateSummary(int $shopId, array $filters): array
    {
        $itemsQuery = $this->filteredItemsQuery($shopId, $filters);

        $result = (clone $itemsQuery)->selectRaw('
            COALESCE(SUM(purchaser_cart_items.line_total), 0) as total_purchase,
            COALESCE(SUM(CASE WHEN LOWER(purchase_invoices.payment_method) = "cash" THEN purchaser_cart_items.line_total ELSE 0 END), 0) as cash_purchase,
            COALESCE(SUM(CASE WHEN LOWER(purchase_invoices.payment_method) = "credit" THEN purchaser_cart_items.line_total ELSE 0 END), 0) as credit_purchase,
            COUNT(DISTINCT purchase_invoices.id) as invoice_count,
            COUNT(DISTINCT purchase_invoices.supplier_id) as vendor_count,
            COUNT(DISTINCT purchaser_cart_items.product_id) as product_count
        ')->first();

        return [
            'total_purchase' => round((float) ($result->total_purchase ?? 0), 2),
            'cash_purchase' => round((float) ($result->cash_purchase ?? 0), 2),
            'credit_purchase' => round((float) ($result->credit_purchase ?? 0), 2),
            'invoice_count' => (int) ($result->invoice_count ?? 0),
            'vendor_count' => (int) ($result->vendor_count ?? 0),
            'product_count' => (int) ($result->product_count ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function calculateVendorSummary(int $shopId, array $filters): Collection
    {
        $itemsQuery = $this->filteredItemsQuery($shopId, $filters);

        $unitRows = (clone $itemsQuery)
            ->selectRaw("
                purchase_invoices.supplier_id,
                COALESCE(suppliers.name, 'Unknown Vendor') as supplier_name,
                COALESCE(products.unit, 'kg') as item_unit,
                SUM(purchaser_cart_items.quantity) as total_qty,
                SUM(purchaser_cart_items.line_total) as unit_line_total
            ")
            ->groupBy('purchase_invoices.supplier_id', 'suppliers.name', 'products.unit')
            ->get();

        $invoiceAggregates = (clone $itemsQuery)
            ->selectRaw("
                purchase_invoices.supplier_id,
                COALESCE(suppliers.name, 'Unknown Vendor') as supplier_name,
                COUNT(DISTINCT purchase_invoices.id) as purchase_count,
                SUM(purchaser_cart_items.line_total) as total_purchase,
                SUM(CASE WHEN LOWER(purchase_invoices.payment_method) = 'cash' THEN purchaser_cart_items.line_total ELSE 0 END) as cash_purchase,
                SUM(CASE WHEN LOWER(purchase_invoices.payment_method) = 'credit' THEN purchaser_cart_items.line_total ELSE 0 END) as credit_purchase
            ")
            ->groupBy('purchase_invoices.supplier_id', 'suppliers.name')
            ->orderByDesc('total_purchase')
            ->get();

        return $invoiceAggregates->map(function ($agg) use ($unitRows) {
            $vendorUnits = $unitRows->where('supplier_id', $agg->supplier_id);
            $qtyParts = [];
            foreach ($vendorUnits as $u) {
                $qtyVal = (float) $u->total_qty;
                $formattedQty = $qtyVal == (int) $qtyVal
                    ? (string) (int) $qtyVal
                    : rtrim(rtrim(number_format($qtyVal, 3), '0'), '.');
                $qtyParts[] = "{$formattedQty} {$u->item_unit}";
            }
            $qtyString = ! empty($qtyParts) ? implode(', ', $qtyParts) : '0';

            return (object) [
                'supplier_id' => $agg->supplier_id,
                'supplier_name' => $agg->supplier_name,
                'purchase_count' => (int) $agg->purchase_count,
                'qty_formatted' => $qtyString,
                'total_purchase' => round((float) $agg->total_purchase, 2),
                'cash_purchase' => round((float) $agg->cash_purchase, 2),
                'credit_purchase' => round((float) $agg->credit_purchase, 2),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function calculateProductSummary(int $shopId, array $filters): Collection
    {
        $itemsQuery = $this->filteredItemsQuery($shopId, $filters);

        $rows = (clone $itemsQuery)
            ->selectRaw("
                products.id as product_id,
                products.name as product_name,
                COALESCE(products.unit, 'kg') as item_unit,
                SUM(purchaser_cart_items.quantity) as total_qty,
                SUM(purchaser_cart_items.line_total) as total_purchase,
                COUNT(DISTINCT purchase_invoices.id) as purchase_count
            ")
            ->groupBy('products.id', 'products.name', 'products.unit')
            ->orderByDesc('total_purchase')
            ->get();

        return $rows->map(function ($row) {
            $totalQty = (float) $row->total_qty;
            $totalPurchase = round((float) $row->total_purchase, 2);
            $avgBuy = $totalQty > 0 ? round($totalPurchase / $totalQty, 2) : 0.0;

            $formattedQty = $totalQty == (int) $totalQty
                ? (string) (int) $totalQty
                : rtrim(rtrim(number_format($totalQty, 3), '0'), '.');

            return (object) [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'unit' => $row->item_unit,
                'total_qty' => $totalQty,
                'qty_formatted' => "{$formattedQty} {$row->item_unit}",
                'total_purchase' => $totalPurchase,
                'avg_buy' => $avgBuy,
                'avg_buy_formatted' => '₹'.number_format($avgBuy, 2).'/'.$row->item_unit,
                'purchase_count' => (int) $row->purchase_count,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<string, object>
     */
    private function buildDailyDetails(int $shopId, array $filters): Collection
    {
        $invoicesQuery = PurchaseInvoice::query()
            ->with([
                'supplier',
                'shopLedgerEntrySetting.entryType',
                'shopLedgerEntrySetting.headerGroup',
                'shopLedgerEntrySetting.vendorSettlementRelation',
                'purchaserCart.items.product',
            ])
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where('purchase_invoices.shop_id', $shopId)
            ->where(function ($q): void {
                $q->where('purchase_invoices.purchase_source', 'shop')
                    ->orWhereHas('purchaserCart', fn ($cq) => $cq->where('purchase_source', 'shop'));
            });

        if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
            $invoicesQuery->whereHas('purchaserCart', function ($q) use ($filters): void {
                if (! empty($filters['start_date'])) {
                    $q->whereDate('business_date', '>=', $filters['start_date']);
                }
                if (! empty($filters['end_date'])) {
                    $q->whereDate('business_date', '<=', $filters['end_date']);
                }
            });
        }

        if (! empty($filters['vendor_id'])) {
            $invoicesQuery->where('purchase_invoices.supplier_id', (int) $filters['vendor_id']);
        }

        if (! empty($filters['category_id'])) {
            $invoicesQuery->where('purchase_invoices.shop_ledger_entry_setting_id', (int) $filters['category_id']);
        }

        if (! empty($filters['payment']) && $filters['payment'] !== 'all') {
            $payment = strtolower((string) $filters['payment']);
            $invoicesQuery->whereRaw('LOWER(purchase_invoices.payment_method) = ?', [$payment]);
        }

        if (! empty($filters['search'])) {
            $search = '%'.trim((string) $filters['search']).'%';
            $invoicesQuery->where(function ($q) use ($search): void {
                $q->where('purchase_invoices.invoice_number', 'like', $search)
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $search))
                    ->orWhereHas('purchaserCart.items.product', fn ($pq) => $pq->where('name', 'like', $search));
            });
        }

        $invoices = $invoicesQuery
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->select('purchase_invoices.*', 'purchaser_carts.business_date as cart_business_date')
            ->orderByDesc('purchaser_carts.business_date')
            ->orderByDesc('purchase_invoices.id')
            ->get();

        return $invoices->groupBy(function (PurchaseInvoice $invoice) {
            $date = $invoice->purchaserCart?->business_date;

            return $date instanceof Carbon ? $date->toDateString() : (string) ($invoice->cart_business_date ?? $invoice->created_at->toDateString());
        })->map(function (Collection $dayInvoices, string $dateString) {
            $carbonDate = Carbon::parse($dateString);
            $dayTotal = 0.0;
            $dayCash = 0.0;
            $dayCredit = 0.0;

            $formattedInvoices = $dayInvoices->map(function (PurchaseInvoice $invoice) use (&$dayTotal, &$dayCash, &$dayCredit) {
                $isCredit = strcasecmp((string) $invoice->payment_method, 'Credit') === 0;
                $netAmount = max(0.0, round((float) $invoice->amount - (float) $invoice->discount_amount, 2));

                $dayTotal += $netAmount;
                if ($isCredit) {
                    $dayCredit += $netAmount;
                } else {
                    $dayCash += $netAmount;
                }

                $setting = $invoice->shopLedgerEntrySetting;
                $categoryName = $setting?->display_name ?: ($setting?->entryType?->label ?? $setting?->entryType?->name);
                $headerName = $setting?->headerGroup?->name;
                $settlementName = $setting?->vendorSettlementRelation?->name;

                $items = collect($invoice->purchaserCart?->items ?? [])->map(function ($item) {
                    $qty = (float) $item->quantity;
                    $lineTotal = round((float) $item->line_total, 2);
                    $effectiveRate = $qty > 0 ? round($lineTotal / $qty, 2) : (float) $item->unit_price;

                    $unit = $item->product?->unit ?? 'kg';
                    $formattedQty = $qty == (int) $qty ? (string) (int) $qty : rtrim(rtrim(number_format($qty, 3), '0'), '.');

                    return (object) [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->name ?? "Product #{$item->product_id}",
                        'grade' => $item->grade ?? 'A',
                        'unit' => $unit,
                        'quantity' => $qty,
                        'qty_formatted' => "{$formattedQty} {$unit}",
                        'unit_price' => (float) $item->unit_price,
                        'line_total' => $lineTotal,
                        'effective_rate' => $effectiveRate,
                        'effective_rate_formatted' => '₹'.number_format($effectiveRate, 2).'/'.$unit,
                    ];
                });

                return (object) [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'bill_number' => $invoice->purchaserCart?->bill_number,
                    'supplier_id' => $invoice->supplier_id,
                    'supplier_name' => $invoice->supplier?->name ?? 'Unknown Vendor',
                    'payment_method' => $isCredit ? 'Credit' : 'Cash',
                    'amount' => (float) $invoice->amount,
                    'discount_amount' => (float) $invoice->discount_amount,
                    'net_amount' => $netAmount,
                    'has_category' => $setting !== null,
                    'category_name' => $categoryName ?: 'Legacy Vendor Purchase',
                    'header_name' => $headerName,
                    'settlement_name' => $settlementName,
                    'created_at' => $invoice->created_at,
                    'items' => $items,
                ];
            });

            return (object) [
                'date' => $dateString,
                'formatted_date' => $carbonDate->format('d M Y'),
                'day_of_week' => $carbonDate->format('l'),
                'day_total' => round($dayTotal, 2),
                'day_cash' => round($dayCash, 2),
                'day_credit' => round($dayCredit, 2),
                'invoices' => $formattedInvoices,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredItemsQuery(int $shopId, array $filters): Builder
    {
        $query = DB::table('purchaser_cart_items')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->join('products', 'products.id', '=', 'purchaser_cart_items.product_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where('purchase_invoices.shop_id', $shopId)
            ->where(function (Builder $q): void {
                $q->where('purchase_invoices.purchase_source', 'shop')
                    ->orWhere('purchaser_carts.purchase_source', 'shop');
            });

        if (! empty($filters['start_date'])) {
            $query->whereDate('purchaser_carts.business_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('purchaser_carts.business_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['vendor_id'])) {
            $query->where('purchase_invoices.supplier_id', (int) $filters['vendor_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('purchase_invoices.shop_ledger_entry_setting_id', (int) $filters['category_id']);
        }
        if (! empty($filters['payment']) && $filters['payment'] !== 'all') {
            $payment = strtolower((string) $filters['payment']);
            $query->whereRaw('LOWER(purchase_invoices.payment_method) = ?', [$payment]);
        }
        if (! empty($filters['search'])) {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $sq) use ($search): void {
                $sq->where('suppliers.name', 'like', $search)
                    ->orWhere('products.name', 'like', $search)
                    ->orWhere('purchase_invoices.invoice_number', 'like', $search)
                    ->orWhere('purchaser_carts.bill_number', 'like', $search);
            });
        }

        return $query;
    }

    /**
     * @return array{
     *     vendors: Collection<int, object>,
     *     categories: Collection<int, object>
     * }
     */
    private function getFilterOptions(int $shopId): array
    {
        $vendors = DB::table('suppliers')
            ->whereIn('id', function ($q) use ($shopId) {
                $q->select('supplier_id')
                    ->from('shop_suppliers')
                    ->where('shop_id', $shopId)
                    ->union(
                        DB::table('purchase_invoices')
                            ->select('supplier_id')
                            ->where('shop_id', $shopId)
                            ->where('purchase_source', 'shop')
                            ->whereNull('deleted_at')
                    );
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($v) => (object) ['id' => (int) $v->id, 'name' => (string) $v->name])
            ->values();

        $categories = ShopLedgerEntrySetting::query()
            ->with(['entryType', 'headerGroup', 'vendorSettlementRelation'])
            ->where('shop_id', $shopId)
            ->where('is_vendor_purchase', true)
            ->get()
            ->map(fn ($s) => (object) [
                'id' => (int) $s->id,
                'name' => $s->display_name ?: ($s->entryType?->label ?? $s->entryType?->name ?? "Category #{$s->id}"),
                'header_name' => $s->headerGroup?->name,
                'settlement_name' => $s->vendorSettlementRelation?->name,
            ])
            ->values();

        return compact('vendors', 'categories');
    }
}
