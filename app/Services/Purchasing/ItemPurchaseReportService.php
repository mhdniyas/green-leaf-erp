<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Read-only procurement reporting for individual items (products).
 *
 * Provides product search without exposing raw numeric IDs in visible URLs,
 * date, grade & payment type filtered history, cash vs credit breakdowns,
 * attributable item vs distinct bill pending calculations, and drill-down
 * into vendor details and invoice settlements.
 */
final class ItemPurchaseReportService
{
    /**
     * Search products by name or SKU.
     *
     * @return Collection<int, Product>
     */
    public function searchProducts(?string $query = null, int $limit = 50): Collection
    {
        return Product::query()
            ->when(! empty($query), function ($builder) use ($query): void {
                $trimmed = trim($query);
                $builder->where(function ($sub) use ($trimmed): void {
                    $sub->where('name', 'like', '%'.$trimmed.'%')
                        ->orWhere('sku', 'like', '%'.$trimmed.'%');
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'public_uuid', 'name', 'unit', 'sku', 'category_id']);
    }

    /**
     * Get products that have recent purchase history.
     *
     * @return Collection<int, object>
     */
    public function getRecentPurchasedProducts(int $limit = 12): Collection
    {
        return DB::table('purchaser_cart_items')
            ->join('products', 'products.id', '=', 'purchaser_cart_items.product_id')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->select('products.id', 'products.public_uuid', 'products.name', 'products.unit', DB::raw('MAX(purchaser_carts.business_date) as last_purchased_date'), DB::raw('COUNT(purchaser_cart_items.id) as purchase_count'))
            ->groupBy('products.id', 'products.public_uuid', 'products.name', 'products.unit')
            ->orderByDesc('last_purchased_date')
            ->orderByDesc('purchase_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Filter dropdown options (purchasers, vendors, supported grades, purchase types).
     * Scoped to the actual available records for the selected product.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     purchasers: Collection<int, object>,
     *     vendors: Collection<int, object>,
     *     grades: Collection<int, object>,
     *     payment_types: array<string, string>
     * }
     */
    public function getOptions(?int $productId = null, array $filters = []): array
    {
        $startDate = ! empty($filters['start_date']) ? $filters['start_date'] : null;
        $endDate = ! empty($filters['end_date']) ? $filters['end_date'] : null;

        // 1. Purchasers (MUST have purchased this product at least once)
        $purchaserQuery = DB::table('users')
            ->join('purchaser_carts', 'purchaser_carts.user_id', '=', 'users.id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->join('purchaser_cart_items', 'purchaser_cart_items.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled');

        if ($productId !== null) {
            $purchaserQuery->where('purchaser_cart_items.product_id', $productId);
        }
        if ($startDate !== null) {
            $purchaserQuery->whereDate('purchaser_carts.business_date', '>=', $startDate);
        }
        if ($endDate !== null) {
            $purchaserQuery->whereDate('purchaser_carts.business_date', '<=', $endDate);
        }

        $purchasers = $purchaserQuery
            ->select('users.id', 'users.name', DB::raw('COUNT(purchaser_cart_items.id) as purchase_count'))
            ->groupBy('users.id', 'users.name')
            ->havingRaw('COUNT(purchaser_cart_items.id) > 0')
            ->orderBy('users.name')
            ->get();

        // If in this period there are no purchasers found for this product, fallback to all-time purchasers
        if ($purchasers->isEmpty() && $productId !== null) {
            $purchasers = DB::table('users')
                ->join('purchaser_carts', 'purchaser_carts.user_id', '=', 'users.id')
                ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
                ->join('purchaser_cart_items', 'purchaser_cart_items.purchaser_cart_id', '=', 'purchaser_carts.id')
                ->whereNull('purchase_invoices.deleted_at')
                ->where('purchase_invoices.status', '!=', 'cancelled')
                ->where('purchaser_cart_items.product_id', $productId)
                ->select('users.id', 'users.name', DB::raw('0 as purchase_count'))
                ->groupBy('users.id', 'users.name')
                ->orderBy('users.name')
                ->get();
        }

        // If user already requested a specific purchaser that isn't in the list, preserve it with count 0
        $selectedPurchaserId = isset($filters['purchaser_id']) && $filters['purchaser_id'] !== '' ? (int) $filters['purchaser_id'] : null;
        if ($selectedPurchaserId && ! $purchasers->contains('id', $selectedPurchaserId)) {
            $u = DB::table('users')->where('id', $selectedPurchaserId)->first(['id', 'name']);
            if ($u) {
                $purchasers->push((object) [
                    'id' => (int) $u->id,
                    'name' => (string) $u->name,
                    'purchase_count' => 0,
                ]);
            }
        }

        // 2. Vendors (MUST have supplied this product at least once)
        $vendorQuery = DB::table('suppliers')
            ->join('purchase_invoices', 'purchase_invoices.supplier_id', '=', 'suppliers.id')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->join('purchaser_cart_items', 'purchaser_cart_items.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled');

        if ($productId !== null) {
            $vendorQuery->where('purchaser_cart_items.product_id', $productId);
        }
        if ($startDate !== null) {
            $vendorQuery->whereDate('purchaser_carts.business_date', '>=', $startDate);
        }
        if ($endDate !== null) {
            $vendorQuery->whereDate('purchaser_carts.business_date', '<=', $endDate);
        }

        $vendors = $vendorQuery
            ->select('suppliers.id', 'suppliers.name', 'suppliers.public_uuid', DB::raw('COUNT(purchaser_cart_items.id) as purchase_count'))
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.public_uuid')
            ->havingRaw('COUNT(purchaser_cart_items.id) > 0')
            ->orderBy('suppliers.name')
            ->get();

        // If in this period there are no vendors found for this product, fallback to all-time suppliers of this product
        if ($vendors->isEmpty() && $productId !== null) {
            $vendors = DB::table('suppliers')
                ->join('purchase_invoices', 'purchase_invoices.supplier_id', '=', 'suppliers.id')
                ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
                ->join('purchaser_cart_items', 'purchaser_cart_items.purchaser_cart_id', '=', 'purchaser_carts.id')
                ->whereNull('purchase_invoices.deleted_at')
                ->where('purchase_invoices.status', '!=', 'cancelled')
                ->where('purchaser_cart_items.product_id', $productId)
                ->select('suppliers.id', 'suppliers.name', 'suppliers.public_uuid', DB::raw('0 as purchase_count'))
                ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.public_uuid')
                ->orderBy('suppliers.name')
                ->get();
        }

        // If user already requested a specific vendor that isn't in the list, preserve it with count 0
        $selectedVendorId = isset($filters['vendor_id']) && $filters['vendor_id'] !== '' ? (int) $filters['vendor_id'] : null;
        if ($selectedVendorId && ! $vendors->contains('id', $selectedVendorId)) {
            $v = DB::table('suppliers')->where('id', $selectedVendorId)->first(['id', 'name', 'public_uuid']);
            if ($v) {
                $vendors->push((object) [
                    'id' => (int) $v->id,
                    'name' => (string) $v->name,
                    'public_uuid' => (string) $v->public_uuid,
                    'purchase_count' => 0,
                ]);
            }
        }

        // 3. Dynamic Grades (only grades actually available for this product / purchasing data)
        $gradesQuery = DB::table('purchaser_cart_items')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled');

        if ($productId !== null) {
            $gradesQuery->where('purchaser_cart_items.product_id', $productId);
        }
        if ($startDate !== null) {
            $gradesQuery->whereDate('purchaser_carts.business_date', '>=', $startDate);
        }
        if ($endDate !== null) {
            $gradesQuery->whereDate('purchaser_carts.business_date', '<=', $endDate);
        }

        $gradeCounts = $gradesQuery
            ->selectRaw("
                CASE
                    WHEN purchaser_cart_items.grade IS NULL OR TRIM(purchaser_cart_items.grade) = '' THEN 'ungraded'
                    ELSE UPPER(TRIM(purchaser_cart_items.grade))
                END as grade_key,
                COUNT(*) as line_count
            ")
            ->groupBy('grade_key')
            ->get();

        // If in this period there are no grades found for this product, fallback to all-time grades
        if ($gradeCounts->isEmpty() && $productId !== null) {
            $gradeCounts = DB::table('purchaser_cart_items')
                ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
                ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
                ->whereNull('purchase_invoices.deleted_at')
                ->where('purchase_invoices.status', '!=', 'cancelled')
                ->where('purchaser_cart_items.product_id', $productId)
                ->selectRaw("
                    CASE
                        WHEN purchaser_cart_items.grade IS NULL OR TRIM(purchaser_cart_items.grade) = '' THEN 'ungraded'
                        ELSE UPPER(TRIM(purchaser_cart_items.grade))
                    END as grade_key,
                    0 as line_count
                ")
                ->groupBy('grade_key')
                ->get();
        }

        $grades = collect();
        foreach ($gradeCounts as $row) {
            $key = (string) $row->grade_key;
            $label = match ($key) {
                'ungraded' => 'Ungraded / Unspecified',
                'A' => 'Grade A',
                'B' => 'Grade B',
                'C' => 'Grade C',
                'D' => 'Grade D',
                default => str_starts_with($key, 'GRADE') ? $key : 'Grade '.$key,
            };

            $grades->push((object) [
                'key' => $key,
                'label' => $label,
                'count' => (int) $row->line_count,
            ]);
        }

        $grades = $grades->sortBy(function (object $g): int {
            return match (strtoupper($g->key)) {
                'A' => 1,
                'B' => 2,
                'C' => 3,
                'D' => 4,
                'UNGRADED' => 99,
                default => 50,
            };
        })->values();

        // If no records exist yet for this product, fallback to standard Grade A
        if ($grades->isEmpty()) {
            $grades->push((object) [
                'key' => 'A',
                'label' => 'Grade A',
                'count' => 0,
            ]);
        }

        // If user already requested a specific grade that isn't in the list, preserve it
        $selectedGrade = (string) ($filters['grade'] ?? 'all');
        if ($selectedGrade !== '' && $selectedGrade !== 'all' && ! $grades->contains(fn ($g) => strtolower($g->key) === strtolower($selectedGrade))) {
            $grades->push((object) [
                'key' => $selectedGrade,
                'label' => strtoupper($selectedGrade) === 'UNGRADED' ? 'Ungraded / Unspecified' : (str_starts_with(strtoupper($selectedGrade), 'GRADE') ? $selectedGrade : 'Grade '.strtoupper($selectedGrade)),
                'count' => 0,
            ]);
        }

        // 4. Payment Types (Cash / Credit)
        $paymentTypes = ['all' => 'All Purchases'];

        $paymentMethodsQuery = DB::table('purchase_invoices')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled');

        if ($productId !== null) {
            $paymentMethodsQuery->join('purchaser_cart_items', 'purchaser_cart_items.purchaser_cart_id', '=', 'purchaser_carts.id')
                ->where('purchaser_cart_items.product_id', $productId);
        }
        if ($startDate !== null) {
            $paymentMethodsQuery->whereDate('purchaser_carts.business_date', '>=', $startDate);
        }
        if ($endDate !== null) {
            $paymentMethodsQuery->whereDate('purchaser_carts.business_date', '<=', $endDate);
        }

        $paymentClasses = $paymentMethodsQuery
            ->selectRaw("DISTINCT CASE WHEN LOWER(COALESCE(purchase_invoices.payment_method, purchaser_carts.payment_method, '')) = 'credit' OR purchase_invoices.payment_paid_by = 'vendor_credit' OR purchase_invoices.payment_status = 'credit_pending_approval' THEN 'credit' ELSE 'cash' END as payment_class")
            ->pluck('payment_class')
            ->all();

        if (empty($paymentClasses)) {
            $paymentTypes['cash'] = 'Cash Only';
            $paymentTypes['credit'] = 'Credit Only';
        } else {
            if (in_array('cash', $paymentClasses, true)) {
                $paymentTypes['cash'] = 'Cash Only';
            }
            if (in_array('credit', $paymentClasses, true)) {
                $paymentTypes['credit'] = 'Credit Only';
            }
        }

        return [
            'purchasers' => $purchasers,
            'vendors' => $vendors,
            'grades' => $grades,
            'payment_types' => $paymentTypes,
        ];
    }

    /**
     * Build report data for selected product and filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     product: ?Product,
     *     searchResults: Collection<int, Product>,
     *     summary: object,
     *     gradeBreakdown: Collection<int, object>,
     *     items: LengthAwarePaginator|Collection<int, object>,
     *     options: array<string, mixed>,
     *     recentProducts: Collection<int, object>
     * }
     */
    public function getReportData(array $filters): array
    {
        $recentProducts = $this->getRecentPurchasedProducts();

        $productIdentifier = $filters['product'] ?? $filters['product_uuid'] ?? $filters['product_id'] ?? null;
        $searchProduct = trim((string) ($filters['search_product'] ?? ''));

        $product = null;
        $searchResults = collect();

        if ($productIdentifier) {
            $identifierStr = (string) $productIdentifier;
            if (Str::isUuid($identifierStr)) {
                $product = Product::query()->with('category')->where('public_uuid', $identifierStr)->first();
            } elseif (is_numeric($identifierStr)) {
                $product = Product::query()->with('category')->find((int) $identifierStr);
            } else {
                $product = Product::query()->with('category')
                    ->where('sku', $identifierStr)
                    ->orWhere('public_uuid', $identifierStr)
                    ->first();
            }
        } elseif ($searchProduct !== '') {
            $matches = $this->searchProducts($searchProduct, 15);
            if ($matches->count() === 1) {
                $product = $matches->first();
            } else {
                $searchResults = $matches;
            }
        }

        $options = $this->getOptions($product?->id, $filters);

        if (! $product) {
            return [
                'product' => null,
                'searchResults' => $searchResults,
                'summary' => (object) [
                    'total_quantity' => 0.0,
                    'total_amount' => 0.0,
                    'cash_purchase_amount' => 0.0,
                    'credit_purchase_amount' => 0.0,
                    'weighted_avg_rate' => 0.0,
                    'associated_credit_bill_pending' => 0.0,
                    'exact_attributable_pending' => 0.0,
                    'credit_invoice_count' => 0,
                    'multi_product_bill_count' => 0,
                    'has_multi_product_credit_bills' => false,
                    'unit' => '',
                    'invoice_count' => 0,
                    'purchaser_count' => 0,
                    'vendor_count' => 0,
                ],
                'gradeBreakdown' => collect(),
                'items' => collect(),
                'options' => $options,
                'recentProducts' => $recentProducts,
            ];
        }

        $query = $this->filteredItemQuery($product->id, $filters);

        // 1. Summary Metrics
        $summaryRaw = DB::query()->fromSub(
            (clone $query)->selectRaw('
                filtered_items.quantity,
                filtered_items.item_net,
                filtered_items.payment_class,
                filtered_items.invoice_id,
                filtered_items.purchaser_id,
                filtered_items.supplier_id
            '),
            'sub_items'
        )->selectRaw("
            COALESCE(SUM(quantity), 0) as total_quantity,
            COALESCE(SUM(item_net), 0) as total_amount,
            COALESCE(SUM(CASE WHEN payment_class = 'cash' THEN item_net ELSE 0 END), 0) as cash_purchase_amount,
            COALESCE(SUM(CASE WHEN payment_class = 'credit' THEN item_net ELSE 0 END), 0) as credit_purchase_amount,
            COUNT(DISTINCT invoice_id) as invoice_count,
            COUNT(DISTINCT purchaser_id) as purchaser_count,
            COUNT(DISTINCT supplier_id) as vendor_count
        ")->first();

        // Distinct credit invoices summary (never double-count repeated lines on the same bill)
        $creditSummary = DB::query()->fromSub(
            (clone $query)
                ->where('filtered_items.payment_class', 'credit')
                ->selectRaw('
                    filtered_items.invoice_id,
                    filtered_items.invoice_outstanding,
                    filtered_items.product_count
                ')
                ->groupBy('filtered_items.invoice_id', 'filtered_items.invoice_outstanding', 'filtered_items.product_count'),
            'distinct_credit_bills'
        )->selectRaw('
            COALESCE(SUM(invoice_outstanding), 0) as associated_credit_bill_pending,
            COALESCE(SUM(CASE WHEN product_count = 1 THEN invoice_outstanding ELSE 0 END), 0) as exact_attributable_pending,
            COUNT(invoice_id) as credit_invoice_count,
            COUNT(CASE WHEN invoice_outstanding > 0.01 THEN invoice_id END) as unpaid_credit_invoice_count,
            COUNT(CASE WHEN product_count > 1 THEN invoice_id END) as multi_product_bill_count
        ')->first();

        $totalQty = (float) ($summaryRaw->total_quantity ?? 0);
        $totalAmount = (float) ($summaryRaw->total_amount ?? 0);
        $cashAmount = (float) ($summaryRaw->cash_purchase_amount ?? 0);
        $creditAmount = (float) ($summaryRaw->credit_purchase_amount ?? 0);
        $weightedAvgRate = $totalQty > 0 ? ($totalAmount / $totalQty) : 0.0;
        $associatedPending = (float) ($creditSummary->associated_credit_bill_pending ?? 0);
        $exactAttributablePending = (float) ($creditSummary->exact_attributable_pending ?? 0);
        $creditInvoiceCount = (int) ($creditSummary->credit_invoice_count ?? 0);
        $multiProductBillCount = (int) ($creditSummary->multi_product_bill_count ?? 0);

        $summary = (object) [
            'total_quantity' => $totalQty,
            'total_amount' => $totalAmount,
            'cash_purchase_amount' => $cashAmount,
            'credit_purchase_amount' => $creditAmount,
            'weighted_avg_rate' => $weightedAvgRate,
            'associated_credit_bill_pending' => $associatedPending,
            'exact_attributable_pending' => $exactAttributablePending,
            'credit_invoice_count' => $creditInvoiceCount,
            'multi_product_bill_count' => $multiProductBillCount,
            'has_multi_product_credit_bills' => $multiProductBillCount > 0,
            'unit' => (string) ($product->unit ?: 'unit'),
            'invoice_count' => (int) ($summaryRaw->invoice_count ?? 0),
            'purchaser_count' => (int) ($summaryRaw->purchaser_count ?? 0),
            'vendor_count' => (int) ($summaryRaw->vendor_count ?? 0),
        ];

        // 2. Grade-wise Breakdown
        $gradeRows = DB::query()->fromSub(
            (clone $query)->selectRaw('filtered_items.grade, filtered_items.quantity, filtered_items.item_net'),
            'sub_grades'
        )->selectRaw('
            grade,
            COALESCE(SUM(quantity), 0) as total_quantity,
            COALESCE(SUM(item_net), 0) as total_amount
        ')->groupBy('grade')->get();

        $gradeBreakdown = $gradeRows->map(function (object $row): object {
            $qty = (float) $row->total_quantity;
            $amount = (float) $row->total_amount;
            $rawGrade = $row->grade;
            $label = ! empty($rawGrade) ? trim((string) $rawGrade) : 'Ungraded';
            $avgRate = $qty > 0 ? ($amount / $qty) : 0.0;

            return (object) [
                'grade' => $label,
                'raw_grade' => $rawGrade,
                'quantity' => $qty,
                'amount' => $amount,
                'avg_rate' => $avgRate,
            ];
        })->sortBy(function (object $row): int {
            return match (strtoupper($row->grade)) {
                'A' => 1,
                'B' => 2,
                'C' => 3,
                'D' => 4,
                default => 5,
            };
        })->values();

        // 3. Paginated Items
        $itemsQuery = clone $query;
        $sortBy = (string) ($filters['sort_by'] ?? '');
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortColumn = match ($sortBy) {
            'date' => 'filtered_items.business_date',
            'bill', 'invoice' => 'filtered_items.invoice_number',
            'grade' => 'filtered_items.grade',
            'qty', 'quantity' => 'filtered_items.quantity',
            'rate', 'unit_price' => 'filtered_items.unit_price',
            'total', 'item_net' => 'filtered_items.item_net',
            'type' => 'filtered_items.payment_class',
            'purchaser' => 'filtered_items.purchaser_name',
            'vendor', 'supplier' => 'filtered_items.supplier_name',
            'pending' => 'filtered_items.invoice_outstanding',
            default => null,
        };

        if ($sortColumn !== null) {
            $itemsQuery->orderBy($sortColumn, $sortDir);
        }

        $items = $itemsQuery
            ->orderByDesc('filtered_items.business_date')
            ->orderByDesc('filtered_items.invoice_id')
            ->orderByDesc('filtered_items.item_id')
            ->paginate(25)
            ->withQueryString();

        // Fetch settlement allocations for the invoices on the current page in 1 single query
        $invoiceIds = collect($items->items())->pluck('invoice_id')->unique()->filter()->all();
        $settlementAllocations = [];
        if (! empty($invoiceIds)) {
            $allocQuery = DB::table('vendor_settlement_allocations')
                ->join('vendor_settlements', 'vendor_settlements.id', '=', 'vendor_settlement_allocations.vendor_settlement_id')
                ->whereIn('vendor_settlement_allocations.purchase_invoice_id', $invoiceIds);

            if (Schema::hasColumn('vendor_settlement_allocations', 'is_reversed')) {
                $allocQuery->where('vendor_settlement_allocations.is_reversed', false);
            }

            $settlementAllocations = $allocQuery
                ->select([
                    'vendor_settlement_allocations.purchase_invoice_id',
                    'vendor_settlements.payment_date',
                    'vendor_settlement_allocations.cash_allocated',
                    'vendor_settlement_allocations.advance_allocated',
                    'vendor_settlement_allocations.discount_allocated',
                    'vendor_settlement_allocations.total_settled',
                ])
                ->orderByDesc('vendor_settlements.payment_date')
                ->get()
                ->groupBy('purchase_invoice_id');
        }

        foreach ($items->items() as $item) {
            $item->settlements = $settlementAllocations[$item->invoice_id] ?? collect();
            $item->is_single_product_bill = (int) $item->product_count === 1;
            $item->attributable_pending = $item->is_single_product_bill ? (float) $item->invoice_outstanding : null;
            $item->bill_pending = (float) $item->invoice_outstanding;

            if ($item->payment_class === 'cash') {
                $item->payment_status_badge = 'Cash';
                $item->payment_badge_color = 'bg-slate-100 text-slate-800 border-slate-200';
            } else {
                if ($item->invoice_outstanding <= 0.01) {
                    $item->payment_status_badge = 'Credit • Settled';
                    $item->payment_badge_color = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                } elseif ($item->invoice_paid > 0.01) {
                    $item->payment_status_badge = 'Credit • Partially Paid';
                    $item->payment_badge_color = 'bg-amber-100 text-amber-800 border-amber-200';
                } else {
                    $item->payment_status_badge = 'Credit • Unpaid';
                    $item->payment_badge_color = 'bg-rose-100 text-rose-800 border-rose-200';
                }
            }
        }

        $filterAssistance = null;
        if ($product && $items->isEmpty()) {
            $hasFilterParams = (! empty($filters['vendor_id']))
                || (! empty($filters['purchaser_id']))
                || (! empty($filters['grade']) && $filters['grade'] !== 'all')
                || (! empty($filters['payment']) && $filters['payment'] !== 'all');

            $vendorHistory = null;
            if (! empty($filters['vendor_id'])) {
                $vendorHistory = DB::table('purchaser_cart_items')
                    ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
                    ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
                    ->join('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
                    ->whereNull('purchase_invoices.deleted_at')
                    ->where('purchase_invoices.status', '!=', 'cancelled')
                    ->where('purchaser_cart_items.product_id', $product->id)
                    ->where('purchase_invoices.supplier_id', (int) $filters['vendor_id'])
                    ->selectRaw('COUNT(*) as total_count, MIN(purchaser_carts.business_date) as min_date, MAX(purchaser_carts.business_date) as max_date, GROUP_CONCAT(DISTINCT purchaser_cart_items.grade) as grades, MAX(suppliers.name) as vendor_name')
                    ->first();
            }

            $gradeHistory = null;
            if (! empty($filters['grade']) && $filters['grade'] !== 'all') {
                $gradeHistory = DB::table('purchaser_cart_items')
                    ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
                    ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
                    ->join('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
                    ->whereNull('purchase_invoices.deleted_at')
                    ->where('purchase_invoices.status', '!=', 'cancelled')
                    ->where('purchaser_cart_items.product_id', $product->id)
                    ->where('purchaser_cart_items.grade', $filters['grade'])
                    ->selectRaw('COUNT(*) as total_count, MIN(purchaser_carts.business_date) as min_date, MAX(purchaser_carts.business_date) as max_date, GROUP_CONCAT(DISTINCT suppliers.name) as vendor_names')
                    ->first();
            }

            $inPeriodCount = 0;
            if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
                $inPeriodCount = DB::table('purchaser_cart_items')
                    ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
                    ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
                    ->whereNull('purchase_invoices.deleted_at')
                    ->where('purchase_invoices.status', '!=', 'cancelled')
                    ->where('purchaser_cart_items.product_id', $product->id)
                    ->whereDate('purchaser_carts.business_date', '>=', $filters['start_date'])
                    ->whereDate('purchaser_carts.business_date', '<=', $filters['end_date'])
                    ->count();
            }

            $filterAssistance = [
                'has_filters_applied' => $hasFilterParams,
                'vendor_history' => $vendorHistory && (int) $vendorHistory->total_count > 0 ? $vendorHistory : null,
                'grade_history' => $gradeHistory && (int) $gradeHistory->total_count > 0 ? $gradeHistory : null,
                'period_total_count' => (int) $inPeriodCount,
            ];
        }

        return [
            'product' => $product,
            'searchResults' => $searchResults,
            'summary' => $summary,
            'gradeBreakdown' => $gradeBreakdown,
            'items' => $items,
            'options' => $options,
            'recentProducts' => $recentProducts,
            'filterAssistance' => $filterAssistance,
        ];
    }

    /**
     * Authoritative filtered query returning line-level purchase items for one product.
     *
     * @param  array<string, mixed>  $filters
     */
    public function filteredItemQuery(int $productId, array $filters): Builder
    {
        $cartTotals = DB::table('purchaser_cart_items')
            ->selectRaw('purchaser_cart_id, SUM(CASE WHEN line_total > 0 THEN line_total ELSE quantity * unit_price END) as gross_total')
            ->groupBy('purchaser_cart_id');

        $cartProductCounts = DB::table('purchaser_cart_items')
            ->selectRaw('purchaser_cart_id, COUNT(DISTINCT product_id) as product_count, COUNT(id) as total_lines')
            ->groupBy('purchaser_cart_id');

        $settlementsQuery = DB::table('vendor_settlement_allocations');
        if (Schema::hasColumn('vendor_settlement_allocations', 'is_reversed')) {
            $settlementsQuery->where('is_reversed', false);
        }
        $settlements = $settlementsQuery
            ->selectRaw('purchase_invoice_id, SUM(total_settled) as settled_amount, SUM(cash_allocated) as cash_allocated, SUM(advance_allocated) as advance_allocated, SUM(discount_allocated) as discount_allocated')
            ->groupBy('purchase_invoice_id');

        $grossExpression = 'CASE WHEN purchaser_cart_items.line_total > 0 THEN purchaser_cart_items.line_total ELSE purchaser_cart_items.quantity * purchaser_cart_items.unit_price END';
        $invoiceNetExpression = 'CASE WHEN purchase_invoices.amount - purchase_invoices.discount_amount > 0 THEN purchase_invoices.amount - purchase_invoices.discount_amount ELSE 0 END';
        $netExpression = "({$grossExpression} * CASE WHEN COALESCE(cart_totals.gross_total, 0) > 0 THEN ({$invoiceNetExpression}) / cart_totals.gross_total ELSE 1 END)";
        $paymentClass = "CASE WHEN LOWER(COALESCE(purchase_invoices.payment_method, purchaser_carts.payment_method, '')) = 'credit' OR purchase_invoices.payment_paid_by = 'vendor_credit' OR purchase_invoices.payment_status = 'credit_pending_approval' THEN 'credit' ELSE 'cash' END";

        $invoicePaidExpression = "CASE WHEN COALESCE(settlement_totals.settled_amount, purchase_invoices.paid_amount, 0) > {$invoiceNetExpression} THEN {$invoiceNetExpression} ELSE COALESCE(settlement_totals.settled_amount, purchase_invoices.paid_amount, 0) END";
        $invoiceOutstandingExpression = "CASE WHEN ({$invoiceNetExpression} - {$invoicePaidExpression}) > 0.01 THEN ({$invoiceNetExpression} - {$invoicePaidExpression}) ELSE 0 END";

        $base = DB::table('purchaser_cart_items')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->join('products', 'products.id', '=', 'purchaser_cart_items.product_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->leftJoin('users', 'users.id', '=', 'purchaser_carts.user_id')
            ->leftJoinSub($cartTotals, 'cart_totals', 'cart_totals.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->leftJoinSub($cartProductCounts, 'cart_product_counts', 'cart_product_counts.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->leftJoinSub($settlements, 'settlement_totals', 'settlement_totals.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where('purchaser_cart_items.product_id', $productId)
            ->selectRaw("
                purchaser_cart_items.id as item_id,
                purchaser_carts.business_date,
                products.name as product_name,
                products.unit as product_unit,
                products.public_uuid as product_public_uuid,
                purchaser_cart_items.grade,
                purchaser_cart_items.quantity,
                purchaser_cart_items.unit_price,
                {$netExpression} as item_net,
                users.id as purchaser_id,
                users.public_uuid as purchaser_public_uuid,
                users.name as purchaser_name,
                suppliers.id as supplier_id,
                suppliers.public_uuid as supplier_public_uuid,
                suppliers.name as supplier_name,
                suppliers.contact as supplier_contact,
                suppliers.mobile_number as supplier_mobile,
                suppliers.location as supplier_location,
                purchase_invoices.id as invoice_id,
                purchase_invoices.public_uuid as invoice_public_uuid,
                purchase_invoices.invoice_number,
                purchase_invoices.amount as invoice_amount,
                purchase_invoices.discount_amount as invoice_discount_amount,
                purchase_invoices.status as invoice_status,
                purchase_invoices.payment_status as invoice_payment_status,
                {$paymentClass} as payment_class,
                {$invoiceNetExpression} as invoice_net,
                {$invoicePaidExpression} as invoice_paid,
                {$invoiceOutstandingExpression} as invoice_outstanding,
                COALESCE(cart_product_counts.product_count, 1) as product_count,
                COALESCE(cart_product_counts.total_lines, 1) as total_lines
            ");

        if (! empty($filters['start_date'])) {
            $base->whereDate('purchaser_carts.business_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $base->whereDate('purchaser_carts.business_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['purchaser_id'])) {
            $base->where('purchaser_carts.user_id', (int) $filters['purchaser_id']);
        }
        if (! empty($filters['vendor_id'])) {
            $base->where('purchase_invoices.supplier_id', (int) $filters['vendor_id']);
        }
        if (! empty($filters['grade']) && $filters['grade'] !== 'all') {
            if (strtolower($filters['grade']) === 'ungraded') {
                $base->where(function (Builder $sub): void {
                    $sub->whereNull('purchaser_cart_items.grade')
                        ->orWhere('purchaser_cart_items.grade', '');
                });
            } else {
                $base->where('purchaser_cart_items.grade', $filters['grade']);
            }
        }
        if (! empty($filters['payment']) && $filters['payment'] !== 'all') {
            $payment = $filters['payment'] === 'credit' ? 'credit' : 'cash';
            $base->whereRaw("{$paymentClass} = ?", [$payment]);
        }

        return DB::query()->fromSub($base, 'filtered_items');
    }
}
