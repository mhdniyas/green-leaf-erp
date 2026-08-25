<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Product;
use App\Models\ShopPriceGroup;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PurchasePriceReportingService
{
    /** @return Collection<int, ShopPriceGroup> */
    public function activePriceGroups(): Collection
    {
        return ShopPriceGroup::query()
            ->active()
            ->whereIn('name', ['A', 'B', 'C'])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @param array<string, mixed> $filters */
    public function priceReport(array $filters): LengthAwarePaginator
    {
        $actualPrices = $this->actualPurchasePrices($filters);
        $specialPrices = DB::table('shop_daily_product_prices')
            ->whereDate('business_date', $filters['date'])
            ->where('status', 'approved')
            ->selectRaw('product_id, COUNT(*) as special_price_count, MIN(selling_price) as special_price_min, MAX(selling_price) as special_price_max')
            ->groupBy('product_id');

        $query = $this->approvalQuery('daily_price_approvals')
            ->leftJoinSub($actualPrices, 'actual_prices', 'actual_prices.product_id', '=', 'products.id')
            ->leftJoinSub($specialPrices, 'special_prices', 'special_prices.product_id', '=', 'products.id')
            ->whereDate('daily_price_approvals.business_date', $filters['date'])
            ->where('daily_price_approvals.status', 'approved')
            ->selectRaw('daily_price_approvals.purchase_price as approved_purchase_price, daily_price_approvals.price_a, daily_price_approvals.price_b, daily_price_approvals.price_c, daily_price_approvals.price_unit, actual_prices.actual_purchase_price, actual_prices.purchase_count, COALESCE(special_prices.special_price_count, 0) as special_price_count, special_prices.special_price_min, special_prices.special_price_max');

        $this->applyProductFilters($query, $filters);

        return $query->orderBy('products.name')->paginate(30)->withQueryString();
    }

    /** @param array<string, mixed> $filters */
    public function changedItems(array $filters, bool $paginate = true): LengthAwarePaginator|Collection
    {
        $priceColumn = $this->priceColumn((string) ($filters['price_group'] ?? 'A'));
        $query = $this->comparisonQuery($filters)
            ->selectRaw("previous.purchase_price as previous_purchase_price, current.purchase_price as current_purchase_price, previous.{$priceColumn} as previous_price, current.{$priceColumn} as current_price, current.price_unit")
            ->whereRaw("ABS(current.{$priceColumn} - previous.{$priceColumn}) > 0.001")
            ->orderBy('products.name');

        return $paginate
            ? $query->paginate(30)->withQueryString()
            : $query->limit(500)->get();
    }

    /** @param array<string, mixed> $filters */
    public function purchaserPriceComparison(array $filters): LengthAwarePaginator
    {
        return $this->comparisonQuery($filters)
            ->selectRaw('previous.purchase_price as previous_price, current.purchase_price as current_price, current.price_unit')
            ->orderBy('products.name')
            ->paginate(30)
            ->withQueryString();
    }

    /** @param array<string, mixed> $filters */
    public function changedPurchaserPrices(array $filters): Collection
    {
        return $this->comparisonQuery($filters)
            ->selectRaw('previous.purchase_price as previous_price, current.purchase_price as current_price, current.price_unit')
            ->whereRaw('ABS(current.purchase_price - previous.purchase_price) > 0.001')
            ->orderBy('products.name')
            ->limit(500)
            ->get();
    }

    /** @param array<string, mixed> $filters */
    public function productDetail(Product $product, array $filters): array
    {
        $approvals = DB::table('daily_price_approvals')
            ->where('product_id', $product->id)
            ->whereDate('business_date', $filters['date'])
            ->where('status', 'approved')
            ->orderByDesc('business_date')
            ->limit(31)
            ->get();

        $history = DB::table('purchaser_cart_items')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->leftJoin('users', 'users.id', '=', 'purchaser_carts.user_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchaser_cart_items.product_id', $product->id)
            ->whereDate('purchaser_carts.business_date', $filters['date'])
            ->select('purchaser_carts.business_date', 'purchaser_cart_items.grade', 'purchaser_cart_items.quantity', 'purchaser_cart_items.unit_price', 'suppliers.name as vendor_name', 'users.name as purchaser_name', 'purchase_invoices.public_uuid as invoice_public_uuid', 'purchase_invoices.invoice_number')
            ->orderByDesc('purchaser_carts.business_date')
            ->limit(50)
            ->get();

        $specialPrices = DB::table('shop_daily_product_prices')
            ->join('shops', 'shops.id', '=', 'shop_daily_product_prices.shop_id')
            ->where('shop_daily_product_prices.product_id', $product->id)
            ->whereDate('shop_daily_product_prices.business_date', $filters['date'])
            ->where('shop_daily_product_prices.status', 'approved')
            ->select('shop_daily_product_prices.business_date', 'shops.name as shop_name', 'shop_daily_product_prices.selling_price', 'shop_daily_product_prices.price_unit')
            ->orderByDesc('shop_daily_product_prices.business_date')
            ->limit(30)
            ->get();

        return compact('approvals', 'history', 'specialPrices');
    }

    /** @return array<string, Collection<int, object>> */
    public function options(): array
    {
        return [
            'categories' => DB::table('categories')->orderBy('name')->get(['id', 'name']),
            'products' => DB::table('products')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'purchasers' => DB::table('users')->join('purchaser_carts', 'purchaser_carts.user_id', '=', 'users.id')->select('users.id', 'users.name')->distinct()->orderBy('users.name')->get(),
            'vendors' => DB::table('suppliers')->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function actualPurchasePrices(array $filters): Builder
    {
        $query = DB::table('purchaser_cart_items')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->whereDate('purchaser_carts.business_date', $filters['date'])
            ->selectRaw('purchaser_cart_items.product_id, SUM(purchaser_cart_items.quantity * purchaser_cart_items.unit_price) / NULLIF(SUM(purchaser_cart_items.quantity), 0) as actual_purchase_price, COUNT(DISTINCT purchase_invoices.id) as purchase_count')
            ->groupBy('purchaser_cart_items.product_id');

        $this->applyPurchaseFilters($query, $filters);

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function comparisonQuery(array $filters): Builder
    {
        $query = DB::table('daily_price_approvals as current')
            ->join('daily_price_approvals as previous', function ($join) use ($filters): void {
                $join->on('previous.product_id', '=', 'current.product_id')
                    ->whereDate('previous.business_date', $filters['date_a'])
                    ->where('previous.status', 'approved');
            })
            ->join('products', 'products.id', '=', 'current.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'products.default_warehouse_id')
            ->whereDate('current.business_date', $filters['date_b'])
            ->where('current.status', 'approved')
            ->selectRaw('products.public_uuid as product_id, products.name as product_name, products.unit as product_unit, categories.name as category_name, warehouses.code as warehouse_code');

        $this->applyProductFilters($query, $filters);

        if ($this->hasPurchaseFilters($filters)) {
            $query->whereExists(function (Builder $purchaseQuery) use ($filters): void {
                $purchaseQuery->selectRaw('1')
                    ->from('purchaser_cart_items')
                    ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
                    ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
                    ->whereColumn('purchaser_cart_items.product_id', 'products.id')
                    ->whereNull('purchase_invoices.deleted_at')
                    ->whereDate('purchaser_carts.business_date', '>=', min($filters['date_a'], $filters['date_b']))
                    ->whereDate('purchaser_carts.business_date', '<=', max($filters['date_a'], $filters['date_b']));
                $this->applyPurchaseFilters($purchaseQuery, $filters);
            });
        }

        return $query;
    }

    private function approvalQuery(string $table): Builder
    {
        return DB::table($table)
            ->join('products', 'products.id', '=', $table.'.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'products.default_warehouse_id')
            ->selectRaw('products.public_uuid as product_id, products.name as product_name, products.unit as product_unit, categories.name as category_name, warehouses.code as warehouse_code');
    }

    /** @param array<string, mixed> $filters */
    private function applyProductFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['warehouse_code'])) {
            $query->where('warehouses.code', $filters['warehouse_code']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', (int) $filters['category_id']);
        }
        if (! empty($filters['product_id'])) {
            $query->where('products.id', (int) $filters['product_id']);
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyPurchaseFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['purchaser_id'])) {
            $query->where('purchaser_carts.user_id', (int) $filters['purchaser_id']);
        }
        if (! empty($filters['vendor_id'])) {
            $query->where('purchase_invoices.supplier_id', (int) $filters['vendor_id']);
        }
        if (! empty($filters['grade'])) {
            $query->where('purchaser_cart_items.grade', $filters['grade']);
        }
    }

    /** @param array<string, mixed> $filters */
    private function hasPurchaseFilters(array $filters): bool
    {
        return ! empty($filters['purchaser_id']) || ! empty($filters['vendor_id']) || ! empty($filters['grade']);
    }

    private function priceColumn(string $group): string
    {
        return match (strtoupper($group)) {
            'B' => 'price_b',
            'C' => 'price_c',
            default => 'price_a',
        };
    }
}
