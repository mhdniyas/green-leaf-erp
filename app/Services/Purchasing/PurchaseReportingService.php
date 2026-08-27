<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\User;
use App\Services\Finance\PurchaserFinanceService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only procurement reporting over the existing invoice and purchaser-cart flow.
 *
 * The report grain is one purchaser-cart item. This keeps category and product filters
 * financially correct when an invoice contains more than one category.
 */
final class PurchaseReportingService
{
    public function __construct(private readonly PurchaserFinanceService $purchaserFinanceService) {}

    /** @param array<string, mixed> $filters */
    public function dashboard(array $filters): array
    {
        $items = $this->filteredItems($filters);
        $summary = $this->summary($items);

        return [
            'summary' => $summary,
            'recentPurchases' => $this->invoiceRows($items)
                ->orderByDesc('business_date')
                ->orderByDesc('invoice_id')
                ->limit(10)
                ->get(),
            'vendors' => $this->vendorRows($items)->orderByDesc('total_purchase')->limit(5)->get(),
            'purchasers' => $this->purchaserRows($items, $filters)->orderByDesc('total_purchase')->limit(5)->get(),
            'categories' => $this->categoryRows($items)->orderByDesc('total_purchase')->limit(8)->get(),
            'unsupportedLegacyCount' => $this->unsupportedLegacyCount($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function report(array $filters): array
    {
        $items = $this->filteredItems($filters);

        return [
            'summary' => $this->summary($items),
            'vendors' => $this->vendorRows($items)->orderByDesc('total_purchase')->paginate(25, ['*'], 'vendors_page')->withQueryString(),
            'purchasers' => $this->purchaserRows($items, $filters)->orderByDesc('total_purchase')->paginate(25, ['*'], 'purchasers_page')->withQueryString(),
            'categories' => $this->categoryRows($items)->orderByDesc('total_purchase')->paginate(25, ['*'], 'categories_page')->withQueryString(),
            'invoices' => $this->invoiceRows($items)->orderByDesc('business_date')->paginate(25, ['*'], 'invoices_page')->withQueryString(),
            'unsupportedLegacyCount' => $this->unsupportedLegacyCount($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function section(string $section, array $filters): array
    {
        $items = $this->filteredItems($filters);
        $rows = match ($section) {
            'purchasers' => $this->purchaserRows($items, $filters + ['include_zero_activity' => true]),
            'vendors' => $this->vendorRows($items),
            'categories' => $this->categoryRows($items),
            'invoices' => $this->invoiceRows($items),
        };
        $rowSummary = $section === 'purchasers'
            ? DB::query()->fromSub(clone $rows, 'purchase_purchasers')->selectRaw('COALESCE(SUM(funding), 0) as funding, COALESCE(SUM(funding_used), 0) as funding_used, COALESCE(SUM(balance), 0) as balance')->first()
            : null;

        return [
            'summary' => $this->summary($items),
            'rowSummary' => $rowSummary,
            'rows' => $rows->orderByDesc($section === 'invoices' ? 'business_date' : 'total_purchase')
                ->paginate(20)
                ->withQueryString(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function purchaserDetail(int $purchaserId, array $filters, string $tab = 'overview'): array
    {
        $filters['purchaser_id'] = $purchaserId;
        $items = $this->filteredItems($filters);

        $detail = [
            'summary' => $this->summary($items),
        ];

        if ($tab === 'purchases') {
            $detail['invoices'] = $this->invoiceRows($items)->orderByDesc('business_date')->paginate(15, ['*'], 'invoices_page')->withQueryString();
        } elseif ($tab === 'vendors') {
            $detail['vendors'] = $this->vendorRows($items)->orderByDesc('total_purchase')->limit(20)->get();
        } elseif ($tab === 'categories') {
            $detail['categories'] = $this->categoryRows($items)->orderByDesc('total_purchase')->limit(20)->get();
        }

        return $detail;
    }

    /** @param array<string, mixed> $filters */
    public function vendorDetail(int $vendorId, array $filters): array
    {
        $filters['vendor_id'] = $vendorId;
        $items = $this->filteredItems($filters);

        return [
            'summary' => $this->summary($items),
            'invoices' => $this->invoiceRows($items)->orderByDesc('business_date')->paginate(15, ['*'], 'invoices_page')->withQueryString(),
            'purchasers' => $this->purchaserRows($items, $filters)->orderByDesc('total_purchase')->limit(20)->get(),
            'categories' => $this->categoryRows($items)->orderByDesc('total_purchase')->limit(20)->get(),
            'payments' => DB::table('vendor_settlements')->where('supplier_id', $vendorId)
                ->whereBetween('payment_date', [$filters['start_date'], $filters['end_date']])
                ->orderByDesc('payment_date')->limit(20)->get(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function categoryDetail(int $categoryId, array $filters): array
    {
        $filters['category_ids'] = [$categoryId];
        $items = $this->filteredItems($filters);

        return [
            'summary' => $this->summary($items),
            'vendors' => $this->vendorRows($items)->orderByDesc('total_purchase')->limit(20)->get(),
            'purchasers' => $this->purchaserRows($items, $filters)->orderByDesc('total_purchase')->limit(20)->get(),
            'invoices' => $this->invoiceRows($items)->orderByDesc('business_date')->paginate(15, ['*'], 'invoices_page')->withQueryString(),
            'products' => DB::query()->fromSub($items, 'purchase_items')->selectRaw('product_id, product_name, SUM(item_net) as total_purchase, COUNT(DISTINCT invoice_id) as invoice_count')->groupBy('product_id', 'product_name')->orderByDesc('total_purchase')->limit(25)->get(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function options(array $filters): array
    {
        $categories = DB::table('categories')
            ->join('products', 'products.category_id', '=', 'categories.id')
            ->join('purchaser_cart_items', 'purchaser_cart_items.product_id', '=', 'products.id')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->select('categories.id', 'categories.name')
            ->distinct()
            ->orderBy('categories.name')
            ->get()
            ->map(static fn (object $category): object => (object) ['id' => (int) $category->id, 'label' => (string) $category->name])
            ->values();

        $purchasers = DB::table('users')
            ->join('purchaser_carts', 'purchaser_carts.user_id', '=', 'users.id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->select('users.id', 'users.name')
            ->distinct()
            ->orderBy('users.name')
            ->get()
            ->map(static fn (object $purchaser): object => (object) ['id' => (int) $purchaser->id, 'label' => (string) $purchaser->name])
            ->values();

        $vendors = DB::table('suppliers')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (object $vendor): object => (object) ['id' => (int) $vendor->id, 'label' => (string) $vendor->name])
            ->values();

        return compact('categories', 'purchasers', 'vendors');
    }

    /** @param array<string, mixed> $filters */
    private function filteredItems(array $filters): Builder
    {
        $cartTotals = DB::table('purchaser_cart_items')
            ->selectRaw('purchaser_cart_id, SUM(CASE WHEN line_total > 0 THEN line_total ELSE quantity * unit_price END) as gross_total')
            ->groupBy('purchaser_cart_id');
        $settlements = DB::table('vendor_settlement_allocations')
            ->selectRaw('purchase_invoice_id, SUM(total_settled) as settled_amount')
            ->groupBy('purchase_invoice_id');

        $grossExpression = 'CASE WHEN purchaser_cart_items.line_total > 0 THEN purchaser_cart_items.line_total ELSE purchaser_cart_items.quantity * purchaser_cart_items.unit_price END';
        $invoiceNetExpression = 'CASE WHEN purchase_invoices.amount - purchase_invoices.discount_amount > 0 THEN purchase_invoices.amount - purchase_invoices.discount_amount ELSE 0 END';
        $netExpression = "({$grossExpression} * CASE WHEN COALESCE(cart_totals.gross_total, 0) > 0 THEN ({$invoiceNetExpression}) / cart_totals.gross_total ELSE 1 END)";
        $paidExpression = "CASE WHEN COALESCE(settlement_totals.settled_amount, purchase_invoices.paid_amount, 0) > {$invoiceNetExpression} THEN {$invoiceNetExpression} ELSE COALESCE(settlement_totals.settled_amount, purchase_invoices.paid_amount, 0) END";
        $paymentClass = "CASE WHEN LOWER(COALESCE(purchase_invoices.payment_method, purchaser_carts.payment_method, '')) = 'credit' OR purchase_invoices.payment_paid_by = 'vendor_credit' OR purchase_invoices.payment_status = 'credit_pending_approval' THEN 'credit' ELSE 'cash' END";

        $query = DB::table('purchase_invoices')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->join('purchaser_cart_items', 'purchaser_cart_items.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->join('products', 'products.id', '=', 'purchaser_cart_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'products.default_warehouse_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->leftJoin('users', 'users.id', '=', 'purchaser_carts.user_id')
            ->leftJoinSub($cartTotals, 'cart_totals', 'cart_totals.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->leftJoinSub($settlements, 'settlement_totals', 'settlement_totals.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->selectRaw("purchase_invoices.id as invoice_id, purchase_invoices.public_uuid as invoice_public_uuid, purchase_invoices.invoice_number, purchaser_carts.id as purchaser_cart_id, purchaser_carts.business_date, purchase_invoices.supplier_id, suppliers.public_uuid as supplier_public_uuid, suppliers.name as supplier_name, purchaser_carts.user_id as purchaser_id, users.public_uuid as purchaser_public_uuid, users.name as purchaser_name, products.id as product_id, products.name as product_name, products.default_warehouse_id, warehouses.code as warehouse_code, CASE WHEN warehouses.code = 'VEG-WH' THEN 'Vegetables' WHEN warehouses.code = 'FRT-WH' THEN 'Fruits' ELSE 'Other' END as produce_type, categories.id as category_id, categories.name as category_name, purchaser_cart_items.grade, purchaser_cart_items.quantity, purchaser_cart_items.unit_price, {$paymentClass} as payment_class, {$netExpression} as item_net, ({$netExpression} * {$paidExpression} / NULLIF({$invoiceNetExpression}, 0)) as item_paid");

        $this->applyFilters($query, $filters);

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        if ($startDate) {
            $query->whereDate('purchaser_carts.business_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('purchaser_carts.business_date', '<=', $endDate);
        }
        if (! empty($filters['purchaser_id'])) {
            $query->where('purchaser_carts.user_id', (int) $filters['purchaser_id']);
        }
        if (! empty($filters['vendor_id'])) {
            $query->where('purchase_invoices.supplier_id', (int) $filters['vendor_id']);
        }
        if (($filters['payment'] ?? 'all') !== 'all') {
            $payment = $filters['payment'] === 'credit' ? 'credit' : 'cash';
            $query->whereRaw("CASE WHEN LOWER(COALESCE(purchase_invoices.payment_method, purchaser_carts.payment_method, '')) = 'credit' OR purchase_invoices.payment_paid_by = 'vendor_credit' OR purchase_invoices.payment_status = 'credit_pending_approval' THEN 'credit' ELSE 'cash' END = ?", [$payment]);
        }
        if (! empty($filters['category_ids'])) {
            $query->whereIn('products.category_id', $filters['category_ids']);
        }
        if (! empty($filters['grade'])) {
            $query->where('purchaser_cart_items.grade', $filters['grade']);
        }
        if (! empty($filters['purchase_product_filter_id'])) {
            $filterId = (int) $filters['purchase_product_filter_id'];
            $query->whereExists(function (Builder $sub) use ($filterId): void {
                $sub->selectRaw('1')
                    ->from('purchase_product_filter_items')
                    ->whereColumn('purchase_product_filter_items.product_id', 'purchaser_cart_items.product_id')
                    ->where('purchase_product_filter_items.filter_id', $filterId);
            });
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $scope = $filters['search_scope'] ?? 'invoices';
            $query->where(function (Builder $searchQuery) use ($scope, $search): void {
                match ($scope) {
                    'purchasers' => $searchQuery->where('users.name', 'like', $search),
                    'vendors' => $searchQuery->where('suppliers.name', 'like', $search),
                    'categories' => $searchQuery->where('categories.name', 'like', $search)->orWhere('products.name', 'like', $search),
                    default => $searchQuery->where('purchase_invoices.invoice_number', 'like', $search)->orWhere('suppliers.name', 'like', $search)->orWhere('users.name', 'like', $search)->orWhere('products.name', 'like', $search),
                };
            });
        }
    }

    private function summary(Builder $items): object
    {
        return DB::query()->fromSub($items, 'purchase_items')->selectRaw("COALESCE(SUM(item_net), 0) as total_purchase, COALESCE(SUM(CASE WHEN payment_class = 'cash' THEN item_net ELSE 0 END), 0) as cash_purchase, COALESCE(SUM(CASE WHEN payment_class = 'credit' THEN item_net ELSE 0 END), 0) as credit_purchase, COALESCE(SUM(item_paid), 0) as credit_paid, COALESCE(SUM(CASE WHEN payment_class = 'credit' AND item_net > item_paid THEN item_net - item_paid ELSE 0 END), 0) as credit_outstanding, COUNT(DISTINCT supplier_id) as vendor_count, COUNT(DISTINCT purchaser_id) as purchaser_count, COUNT(DISTINCT invoice_id) as invoice_count, COUNT(DISTINCT category_id) as category_count")->first();
    }

    private function vendorRows(Builder $items): Builder
    {
        $tagExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "category_id || '|' || category_name"
            : "CONCAT(category_id, '|', category_name)";

        return DB::query()->fromSub($items, 'purchase_items')->selectRaw("supplier_id, supplier_public_uuid, supplier_name, COUNT(DISTINCT invoice_id) as invoice_count, SUM(CASE WHEN payment_class = 'cash' THEN item_net ELSE 0 END) as cash_purchase, SUM(CASE WHEN payment_class = 'credit' THEN item_net ELSE 0 END) as credit_purchase, SUM(item_paid) as credit_paid, SUM(CASE WHEN payment_class = 'credit' AND item_net > item_paid THEN item_net - item_paid ELSE 0 END) as outstanding, SUM(item_net) as total_purchase, GROUP_CONCAT(DISTINCT {$tagExpression}) as category_tags")->groupBy('supplier_id', 'supplier_public_uuid', 'supplier_name');
    }

    /** @param array<string, mixed> $filters */
    private function purchaserRows(Builder $items, array $filters): Builder
    {
        $purchaserUsers = User::query()
            ->where(function ($query): void {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', 'purchaser'))
                    ->orWhereIn('id', DB::table('purchaser_carts')->select('user_id'));
            })
            ->when(! empty($filters['search']) && ($filters['search_scope'] ?? 'invoices') === 'purchasers', function ($query) use ($filters): void {
                $query->where('name', 'like', '%'.$filters['search'].'%');
            })
            ->select('id as purchaser_id', 'public_uuid as purchaser_public_uuid', 'name as purchaser_name');

        $purchases = DB::query()->fromSub($items, 'purchase_items')
            ->selectRaw('purchaser_id, COUNT(DISTINCT invoice_id) as invoice_count, SUM(CASE WHEN payment_class = "cash" THEN item_net ELSE 0 END) as cash_purchase, SUM(CASE WHEN payment_class = "credit" THEN item_net ELSE 0 END) as credit_purchase, SUM(item_net) as total_purchase')
            ->groupBy('purchaser_id');

        return DB::query()->fromSub($purchaserUsers, 'base_purchasers')
            ->leftJoinSub($purchases, 'purchaser_purchases', 'purchaser_purchases.purchaser_id', '=', 'base_purchasers.purchaser_id')
            ->when(empty($filters['include_zero_activity']), fn (Builder $query) => $query->whereNotNull('purchaser_purchases.purchaser_id'))
            ->leftJoinSub($this->purchaserFinanceService->balanceRows($filters['start_date'] ?? '', $filters['end_date'] ?? ''), 'period_funding', 'period_funding.purchaser_id', '=', 'base_purchasers.purchaser_id')
            ->leftJoinSub($this->purchaserFinanceService->balanceRows(), 'purchaser_funding', 'purchaser_funding.purchaser_id', '=', 'base_purchasers.purchaser_id')
            ->selectRaw('base_purchasers.purchaser_id, base_purchasers.purchaser_public_uuid, base_purchasers.purchaser_name, COALESCE(purchaser_purchases.invoice_count, 0) as invoice_count, COALESCE(purchaser_purchases.cash_purchase, 0) as cash_purchase, COALESCE(purchaser_purchases.credit_purchase, 0) as credit_purchase, COALESCE(purchaser_purchases.total_purchase, 0) as total_purchase, COALESCE(period_funding.transaction_count, 0) as transaction_count, COALESCE(period_funding.cash_given, 0) as funding, COALESCE(period_funding.cash_used, 0) as funding_used, COALESCE(purchaser_funding.remaining_advance, 0) as balance');
    }

    private function categoryRows(Builder $items): Builder
    {
        return DB::query()->fromSub($items, 'purchase_items')->selectRaw("category_id, category_name, COUNT(DISTINCT supplier_id) as vendor_count, COUNT(DISTINCT purchaser_id) as purchaser_count, COUNT(DISTINCT invoice_id) as invoice_count, SUM(CASE WHEN payment_class = 'cash' THEN item_net ELSE 0 END) as cash_purchase, SUM(CASE WHEN payment_class = 'credit' THEN item_net ELSE 0 END) as credit_purchase, SUM(item_net) as total_purchase")->groupBy('category_id', 'category_name');
    }

    private function invoiceRows(Builder $items): Builder
    {
        $produceExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'GROUP_CONCAT(DISTINCT produce_type)'
            : 'GROUP_CONCAT(DISTINCT produce_type ORDER BY produce_type SEPARATOR ", ")';
        $categoryExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'GROUP_CONCAT(DISTINCT category_name)'
            : 'GROUP_CONCAT(DISTINCT category_name ORDER BY category_name SEPARATOR ", ")';

        return DB::query()->fromSub($items, 'purchase_items')->selectRaw('invoice_id, invoice_public_uuid, invoice_number, business_date, supplier_id, supplier_public_uuid, supplier_name, purchaser_id, purchaser_public_uuid, purchaser_name, payment_class, '.$produceExpression.' as produce_types, '.$categoryExpression.' as categories, SUM(item_net) as total_purchase, SUM(item_paid) as paid_amount, SUM(CASE WHEN payment_class = "credit" AND item_net > item_paid THEN item_net - item_paid ELSE 0 END) as outstanding')->groupBy('invoice_id', 'invoice_public_uuid', 'invoice_number', 'business_date', 'supplier_id', 'supplier_public_uuid', 'supplier_name', 'purchaser_id', 'purchaser_public_uuid', 'purchaser_name', 'payment_class');
    }

    /** @param array<string, mixed> $filters */
    private function unsupportedLegacyCount(array $filters): int
    {
        $query = DB::table('purchase_invoices')->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')->whereNotExists(function (Builder $items): void {
                $items->selectRaw('1')->from('purchaser_cart_items')->whereColumn('purchaser_cart_items.purchaser_cart_id', 'purchase_invoices.purchaser_cart_id');
            });
        if (! empty($filters['start_date'])) {
            $query->whereDate('purchaser_carts.business_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('purchaser_carts.business_date', '<=', $filters['end_date']);
        }

        return $query->count();
    }
}
