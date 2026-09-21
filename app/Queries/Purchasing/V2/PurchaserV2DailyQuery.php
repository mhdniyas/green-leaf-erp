<?php

declare(strict_types=1);

namespace App\Queries\Purchasing\V2;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaserV2DailyQuery
{
    /**
     * Get paginated intended daily products with bounded SQL queries (exactly 2 queries).
     *
     * @return array{
     *     items: array<int, array{
     *         product_id: int,
     *         name: string,
     *         sku: string,
     *         unit: string,
     *         category_name: string,
     *         grade: string,
     *         intended_qty: float,
     *         purchased_qty: float,
     *         draft_qty: float,
     *         remaining_qty: float,
     *         is_fulfilled: bool,
     *         buy_url: string
     *     }>,
     *     pagination: array{
     *         current_page: int,
     *         per_page: int,
     *         total_count: int,
     *         has_more: bool
     *     },
     *     metrics: array{
     *         total_intended_products: int,
     *         total_approved_qty: float,
     *         total_purchased_qty: float,
     *         total_remaining_qty: float
     *     }
     * }
     */
    public function getProducts(
        Carbon $date,
        string $grade,
        User $user,
        ?string $search = null,
        string $statusFilter = 'all',
        int $perPage = 20,
        int $page = 1,
    ): array {
        $dateStr = $date->toDateString();
        $assignedCategoryIds = $user->hasAssignedCategoryFilter() ? $user->assignedCategoryIds() : [];
        $isCategoryFiltered = count($assignedCategoryIds) > 0;
        $search = trim((string) $search);

        // Base query for approved intended products on the date & grade
        $baseQuery = DB::table('shop_order_items as soi')
            ->join('shop_orders as so', 'so.id', '=', 'soi.shop_order_id')
            ->join('products as p', 'p.id', '=', 'soi.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where(function ($q) use ($dateStr): void {
                $q->where('so.business_date', $dateStr)
                    ->orWhere('so.business_date', 'like', "{$dateStr}%");
            })
            ->where('so.state', 'approved')
            ->where('soi.product_grade', $grade)
            ->where('soi.approved_qty', '>', 0)
            ->where('p.is_active', true);

        if ($isCategoryFiltered) {
            $baseQuery->whereIn('p.category_id', $assignedCategoryIds);
        }

        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search): void {
                $q->where('p.name', 'like', "%{$search}%")
                    ->orWhere('p.sku', 'like', "{$search}%");
            });
        }

        // Subquery for product level aggregates
        $aggregatedQuery = (clone $baseQuery)
            ->select([
                'p.id as product_id',
                'p.name as product_name',
                'p.sku',
                'p.unit',
                DB::raw('COALESCE(c.name, "") as category_name'),
                DB::raw('SUM(soi.approved_qty) as intended_qty'),
            ])
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.unit', 'c.name')
            ->orderBy('p.name');

        // Fast total count using subquery wrap
        $totalCount = DB::table(DB::raw("({$aggregatedQuery->toSql()}) as sub"))
            ->mergeBindings($aggregatedQuery)
            ->count();

        // Paginate first query (max $perPage items)
        $offset = max(0, ($page - 1) * $perPage);
        $products = $aggregatedQuery
            ->offset($offset)
            ->limit($perPage)
            ->get();

        if ($products->isEmpty()) {
            return [
                'items' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_count' => $totalCount,
                    'has_more' => false,
                ],
                'metrics' => [
                    'total_intended_products' => $totalCount,
                    'total_approved_qty' => 0.0,
                    'total_purchased_qty' => 0.0,
                    'total_remaining_qty' => 0.0,
                ],
            ];
        }

        $productIds = $products->pluck('product_id')->map(fn ($id) => (int) $id)->all();

        // Query 2: Fetch submitted & draft cart quantities ONLY for the bounded product IDs
        $cartItems = DB::table('purchaser_cart_items as pci')
            ->join('purchaser_carts as pc', 'pc.id', '=', 'pci.purchaser_cart_id')
            ->where(function ($q) use ($dateStr): void {
                $q->where('pc.business_date', $dateStr)
                    ->orWhere('pc.business_date', 'like', "{$dateStr}%");
            })
            ->where('pc.purchase_grade', $grade)
            ->whereIn('pc.status', ['draft', 'submitted'])
            ->whereIn('pci.product_id', $productIds)
            ->select([
                'pci.product_id',
                'pc.status',
                DB::raw('SUM(pci.quantity) as total_qty'),
            ])
            ->groupBy('pci.product_id', 'pc.status')
            ->get();

        $submittedByProduct = [];
        $draftByProduct = [];
        foreach ($cartItems as $row) {
            $pId = (int) $row->product_id;
            $qty = (float) $row->total_qty;
            if ($row->status === 'submitted') {
                $submittedByProduct[$pId] = ($submittedByProduct[$pId] ?? 0.0) + $qty;
            } elseif ($row->status === 'draft') {
                $draftByProduct[$pId] = ($draftByProduct[$pId] ?? 0.0) + $qty;
            }
        }

        $items = [];
        $pageApprovedQty = 0.0;
        $pagePurchasedQty = 0.0;
        $pageRemainingQty = 0.0;

        foreach ($products as $p) {
            $productId = (int) $p->product_id;
            $intendedQty = round((float) $p->intended_qty, 2);
            $purchasedQty = round((float) ($submittedByProduct[$productId] ?? 0.0), 2);
            $draftQty = round((float) ($draftByProduct[$productId] ?? 0.0), 2);
            $remainingQty = max(0.0, round($intendedQty - $purchasedQty, 2));
            $isFulfilled = $remainingQty <= 0.001;

            // Apply statusFilter if needed
            if ($statusFilter === 'pending' && $isFulfilled) {
                continue;
            }
            if ($statusFilter === 'fulfilled' && ! $isFulfilled) {
                continue;
            }

            $pageApprovedQty += $intendedQty;
            $pagePurchasedQty += $purchasedQty;
            $pageRemainingQty += $remainingQty;

            $items[] = [
                'product_id' => $productId,
                'name' => (string) $p->product_name,
                'sku' => (string) $p->sku,
                'unit' => (string) $p->unit,
                'category_name' => (string) $p->category_name,
                'grade' => $grade,
                'intended_qty' => $intendedQty,
                'purchased_qty' => $purchasedQty,
                'draft_qty' => $draftQty,
                'remaining_qty' => $remainingQty,
                'is_fulfilled' => $isFulfilled,
                'buy_url' => url("/purchaser-v2/buy?product_id={$productId}&date={$dateStr}&grade={$grade}"),
            ];
        }

        $hasMore = ($offset + $perPage) < $totalCount;

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_count' => $totalCount,
                'has_more' => $hasMore,
            ],
            'metrics' => [
                'total_intended_products' => $totalCount,
                'total_approved_qty' => round($pageApprovedQty, 2),
                'total_purchased_qty' => round($pagePurchasedQty, 2),
                'total_remaining_qty' => round($pageRemainingQty, 2),
            ],
        ];
    }

    /**
     * Get demand breakdown for a single product on click (shop breakdown + carts).
     *
     * @return array{
     *     product: array{id: int, name: string, sku: string, unit: string, category: string},
     *     summary: array{intended_qty: float, purchased_qty: float, remaining_qty: float, grade: string, date: string},
     *     shops: array<int, array{shop_id: int, shop_name: string, order_number: string, requested_qty: float, approved_qty: float, unit: string}>,
     *     carts: array<int, array{cart_id: int, cart_number: string, cart_status: string, supplier_name: string, purchaser_name: string, quantity: float, unit_price: float, line_total: float}>
     * }
     */
    public function getProductDemandDetail(Product $product, Carbon $date, string $grade, User $user): array
    {
        $dateStr = $date->toDateString();

        // 1. Check category permission
        if ($user->hasAssignedCategoryFilter() && ! in_array((int) $product->category_id, $user->assignedCategoryIds(), true)) {
            abort(403, 'You do not have access to this product category.');
        }

        // Query 1: Individual shop orders requesting this product
        $shopDemands = DB::table('shop_order_items as soi')
            ->join('shop_orders as so', 'so.id', '=', 'soi.shop_order_id')
            ->leftJoin('shops as s', 's.id', '=', 'so.shop_id')
            ->where(function ($q) use ($dateStr): void {
                $q->where('so.business_date', $dateStr)
                    ->orWhere('so.business_date', 'like', "{$dateStr}%");
            })
            ->where('so.state', 'approved')
            ->where('soi.product_id', $product->id)
            ->where('soi.product_grade', $grade)
            ->where('soi.approved_qty', '>', 0)
            ->select([
                's.id as shop_id',
                's.name as shop_name',
                'so.order_number',
                'soi.requested_qty',
                'soi.approved_qty',
                'soi.unit',
            ])
            ->orderBy('s.name')
            ->get();

        $intendedQty = 0.0;
        $shopsList = [];
        foreach ($shopDemands as $row) {
            $approved = (float) $row->approved_qty;
            $intendedQty += $approved;
            $shopsList[] = [
                'shop_id' => (int) ($row->shop_id ?? 0),
                'shop_name' => (string) ($row->shop_name ?? 'Unknown Shop'),
                'order_number' => (string) $row->order_number,
                'requested_qty' => round((float) $row->requested_qty, 2),
                'approved_qty' => round($approved, 2),
                'unit' => (string) $row->unit,
            ];
        }

        // Query 2: Existing purchaser carts for this product
        $cartRows = DB::table('purchaser_cart_items as pci')
            ->join('purchaser_carts as pc', 'pc.id', '=', 'pci.purchaser_cart_id')
            ->leftJoin('suppliers as sup', 'sup.id', '=', 'pc.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'pc.user_id')
            ->where(function ($q) use ($dateStr): void {
                $q->where('pc.business_date', $dateStr)
                    ->orWhere('pc.business_date', 'like', "{$dateStr}%");
            })
            ->where('pc.purchase_grade', $grade)
            ->where('pci.product_id', $product->id)
            ->select([
                'pc.id as cart_id',
                'pc.cart_number',
                'pc.status as cart_status',
                'sup.name as supplier_name',
                'u.name as purchaser_name',
                'pci.quantity',
                'pci.unit_price',
                'pci.line_total',
            ])
            ->get();

        $purchasedQty = 0.0;
        $cartsList = [];
        foreach ($cartRows as $c) {
            $qty = (float) $c->quantity;
            if ($c->cart_status === 'submitted') {
                $purchasedQty += $qty;
            }
            $cartsList[] = [
                'cart_id' => (int) $c->cart_id,
                'cart_number' => (string) $c->cart_number,
                'cart_status' => (string) $c->cart_status,
                'supplier_name' => (string) ($c->supplier_name ?? 'Unassigned'),
                'purchaser_name' => (string) ($c->purchaser_name ?? 'Unknown'),
                'quantity' => round($qty, 2),
                'unit_price' => round((float) $c->unit_price, 4),
                'line_total' => round((float) $c->line_total, 2),
            ];
        }

        $remainingQty = max(0.0, round($intendedQty - $purchasedQty, 2));

        return [
            'product' => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'sku' => (string) $product->sku,
                'unit' => (string) $product->unit,
                'category' => (string) ($product->category?->name ?? 'General'),
            ],
            'summary' => [
                'intended_qty' => round($intendedQty, 2),
                'purchased_qty' => round($purchasedQty, 2),
                'remaining_qty' => $remainingQty,
                'grade' => $grade,
                'date' => $dateStr,
            ],
            'shops' => $shopsList,
            'carts' => $cartsList,
        ];
    }
}
