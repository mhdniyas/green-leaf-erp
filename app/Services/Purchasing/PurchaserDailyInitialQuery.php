<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaserCartItem;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PurchaserDailyInitialQuery
{
    /**
     * Fetch pending demand items for the Daily page using lightweight grouped queries.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getPendingDemand(
        Carbon $date,
        string $purchaseGrade = 'A',
        ?User $user = null,
        ?string $selectedChip = 'All',
        ?string $search = null
    ): Collection {
        $dateString = $date->toDateString();
        $hasCategoryFilter = $user && $user->hasAssignedCategoryFilter();
        $assignedCatIds = $hasCategoryFilter ? $user->assignedCategoryIds() : [];

        if ($purchaseGrade === 'B') {
            return $this->getGradeBPendingCatalog($dateString, $user, $assignedCatIds, $hasCategoryFilter, $selectedChip, $search);
        }

        // 1. Grouped approved demand by product_id
        $approvedQuery = ShopOrderItem::query()
            ->where('product_grade', 'A')
            ->whereNull('deleted_at')
            ->whereHas('order', function ($query) use ($dateString): void {
                $query->whereDate('business_date', $dateString)->where('state', 'approved');
            })
            ->with('order:id,order_source,business_date,created_at,submitted_at');

        if ($hasCategoryFilter && ! empty($assignedCatIds)) {
            $approvedQuery->whereIn('product_id', function ($query) use ($assignedCatIds): void {
                $query->select('id')
                    ->from('products')
                    ->whereIn('category_id', $assignedCatIds)
                    ->whereNull('deleted_at');
            });
        }

        /** @var Collection<int, Collection<int, ShopOrderItem>> $approvedItemsByProduct */
        $approvedItemsByProduct = $approvedQuery->get()->groupBy('product_id');

        if ($approvedItemsByProduct->isEmpty()) {
            return collect();
        }

        $productIds = $approvedItemsByProduct->keys()->map(fn ($id): int => (int) $id)->all();

        // 2. Grouped draft quantities by product_id
        /** @var Collection<int, float> $draftQuantities */
        $draftQuantities = PurchaserCartItem::query()
            ->where('grade', 'A')
            ->whereIn('product_id', $productIds)
            ->whereHas('cart', function ($query) use ($dateString): void {
                $query->whereDate('business_date', $dateString)
                    ->where('status', 'draft')
                    ->where('purchase_grade', 'A');
            })
            ->selectRaw('product_id, SUM(quantity) as total_draft_qty')
            ->groupBy('product_id')
            ->pluck('total_draft_qty', 'product_id');

        // 3. Grouped submitted (bought) quantities by product_id with cart timestamps
        /** @var Collection<int, Collection<int, PurchaserCartItem>> $submittedCartsByProduct */
        $submittedCartsByProduct = PurchaserCartItem::query()
            ->where('grade', 'A')
            ->whereIn('product_id', $productIds)
            ->whereHas('cart', function ($query) use ($dateString): void {
                $query->whereDate('business_date', $dateString)
                    ->where('status', 'submitted')
                    ->where('purchase_grade', 'A');
            })
            ->with('cart:id,business_date,status,submitted_at,created_at')
            ->get()
            ->groupBy('product_id');

        // 4. Retrieve Product and Category details for demand products
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with('category:id,name')
            ->get(['id', 'category_id', 'name', 'sku', 'unit'])
            ->keyBy('id');

        // 5. Combine and calculate metrics
        $items = collect();
        foreach ($approvedItemsByProduct as $productId => $approvedItems) {
            /** @var Product|null $product */
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }

            $productCarts = $submittedCartsByProduct->get($productId, collect());
            $normalItems = $approvedItems->filter(fn (ShopOrderItem $i): bool => ! $i->order?->isAdminDirectPurchase());
            $addonItems = $approvedItems->filter(fn (ShopOrderItem $i): bool => (bool) $i->order?->isAdminDirectPurchase());

            $normalApproved = (float) $normalItems->sum('approved_qty');
            $normalBought = (float) $productCarts->sum('quantity');
            $normalRemaining = max(0, $normalApproved - $normalBought);

            $addonRemaining = 0.0;
            foreach ($addonItems->groupBy('shop_order_id') as $orderItems) {
                $first = $orderItems->first();
                $orderTime = $first->order->created_at ?? $first->order->submitted_at;
                $addonApproved = (float) $orderItems->sum('approved_qty');
                $addonCarts = $productCarts->filter(function (PurchaserCartItem $c) use ($orderTime): bool {
                    $cartTime = $c->cart->submitted_at ?? $c->cart->created_at;

                    return $cartTime && $orderTime && $cartTime->gte($orderTime);
                });
                $addonBought = (float) $addonCarts->sum('quantity');
                $addonRemaining += max(0, $addonApproved - $addonBought);
            }

            $approved = (float) $approvedItems->sum('approved_qty');
            $bought = (float) $productCarts->sum('quantity');
            $draft = (float) ($draftQuantities->get($productId) ?? 0);
            $remaining = $normalRemaining + $addonRemaining;

            // Filter ONLY pending products where purchasing work remains
            if ($remaining <= 0) {
                continue;
            }

            $categoryName = (string) ($product->category?->name ?? 'Other');

            $items->push([
                'product_id' => (int) $productId,
                'product_name' => (string) $product->name,
                'sku' => (string) $product->sku,
                'unit' => (string) $product->unit,
                'category_id' => (int) $product->category_id,
                'category_name' => $categoryName,
                'total_approved_qty' => $approved,
                'bought_qty' => $bought,
                'draft_qty' => $draft,
                'remaining_qty' => $remaining,
                'order_date' => $dateString,
                'search_index' => strtolower(implode(' ', [
                    (string) $product->name,
                    (string) $product->sku,
                    $categoryName,
                ])),
            ]);
        }

        // Sort items by sortable SKU
        $sorted = $items->sortBy(fn (array $item): string => Product::sortableSku((string) $item['sku']).'_'.$item['order_date'])->values();

        // Apply category filter and search if requested
        return $this->applyFilters($sorted, $selectedChip, $search);
    }

    /**
     * Calculate top metric summary strip.
     *
     * @return array{products: int, approved_qty: float, bought_qty: float, remaining_qty: float, draft_carts: int}
     */
    public function getFulfillmentMetrics(
        Carbon $date,
        string $purchaseGrade = 'A',
        ?User $user = null
    ): array {
        $dateString = $date->toDateString();
        $hasCategoryFilter = $user && $user->hasAssignedCategoryFilter();
        $assignedCatIds = $hasCategoryFilter ? $user->assignedCategoryIds() : [];

        $approvedQuery = ShopOrderItem::query()
            ->where('product_grade', $purchaseGrade)
            ->whereNull('deleted_at')
            ->whereHas('order', function ($query) use ($dateString): void {
                $query->whereDate('business_date', $dateString)->where('state', 'approved');
            })
            ->with('order:id,order_source,business_date,created_at,submitted_at');

        if ($hasCategoryFilter && ! empty($assignedCatIds)) {
            $approvedQuery->whereIn('product_id', function ($query) use ($assignedCatIds): void {
                $query->select('id')
                    ->from('products')
                    ->whereIn('category_id', $assignedCatIds)
                    ->whereNull('deleted_at');
            });
        }

        /** @var Collection<int, Collection<int, ShopOrderItem>> $approvedItemsByProduct */
        $approvedItemsByProduct = $approvedQuery->get()->groupBy('product_id');

        if ($approvedItemsByProduct->isEmpty()) {
            return [
                'products' => 0,
                'approved_qty' => 0.0,
                'bought_qty' => 0.0,
                'remaining_qty' => 0.0,
                'draft_carts' => 0,
            ];
        }

        $productIds = $approvedItemsByProduct->keys()->map(fn ($id): int => (int) $id)->all();

        $submittedCartsByProduct = PurchaserCartItem::query()
            ->where('grade', $purchaseGrade)
            ->whereIn('product_id', $productIds)
            ->whereHas('cart', function ($query) use ($dateString, $purchaseGrade): void {
                $query->whereDate('business_date', $dateString)
                    ->where('status', 'submitted')
                    ->where('purchase_grade', $purchaseGrade);
            })
            ->with('cart:id,business_date,status,submitted_at,created_at')
            ->get()
            ->groupBy('product_id');

        $totalApproved = 0.0;
        $totalBought = 0.0;
        $totalRemaining = 0.0;

        foreach ($approvedItemsByProduct as $productId => $approvedItems) {
            $productCarts = $submittedCartsByProduct->get($productId, collect());
            $normalItems = $approvedItems->filter(fn (ShopOrderItem $i): bool => ! $i->order?->isAdminDirectPurchase());
            $addonItems = $approvedItems->filter(fn (ShopOrderItem $i): bool => (bool) $i->order?->isAdminDirectPurchase());

            $normalApproved = (float) $normalItems->sum('approved_qty');
            $normalBought = (float) $productCarts->sum('quantity');
            $normalRemaining = max(0, $normalApproved - $normalBought);

            $addonRemaining = 0.0;
            foreach ($addonItems->groupBy('shop_order_id') as $orderItems) {
                $first = $orderItems->first();
                $orderTime = $first->order->created_at ?? $first->order->submitted_at;
                $addonApproved = (float) $orderItems->sum('approved_qty');
                $addonCarts = $productCarts->filter(function (PurchaserCartItem $c) use ($orderTime): bool {
                    $cartTime = $c->cart->submitted_at ?? $c->cart->created_at;

                    return $cartTime && $orderTime && $cartTime->gte($orderTime);
                });
                $addonBought = (float) $addonCarts->sum('quantity');
                $addonRemaining += max(0, $addonApproved - $addonBought);
            }

            $totalApproved += (float) $approvedItems->sum('approved_qty');
            $totalBought += (float) $productCarts->sum('quantity');
            $totalRemaining += ($normalRemaining + $addonRemaining);
        }

        return [
            'products' => $approvedItemsByProduct->count(),
            'approved_qty' => $totalApproved,
            'bought_qty' => $totalBought,
            'remaining_qty' => $totalRemaining,
            'draft_carts' => 0,
        ];
    }

    /**
     * Get authorized category filter names for the purchaser (without 'Frequent').
     *
     * @return array<int, string>
     */
    public function getQuickFilters(?User $user): array
    {
        if (! $user || ! $user->hasAssignedCategoryFilter()) {
            $categories = Category::query()
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->all();

            return array_values(array_unique(array_merge(['All'], $categories)));
        }

        $assignedCatNames = Category::query()
            ->whereIn('id', $user->assignedCategoryIds())
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return array_values(array_unique(array_merge(['All'], $assignedCatNames)));
    }

    /**
     * Handle Grade B purchase catalog.
     *
     * @param  array<int, int>  $assignedCatIds
     * @return Collection<int, array<string, mixed>>
     */
    private function getGradeBPendingCatalog(
        string $dateString,
        ?User $user,
        array $assignedCatIds,
        bool $hasCategoryFilter,
        ?string $selectedChip,
        ?string $search
    ): Collection {
        $products = Product::query()
            ->active()
            ->where('show_in_purchaser_order', true)
            ->with('category:id,name')
            ->when($hasCategoryFilter && ! empty($assignedCatIds), fn ($query) => $query->whereIn('category_id', $assignedCatIds))
            ->ordered()
            ->get(['id', 'name', 'sku', 'unit', 'category_id']);

        $approvedGradeBQuantities = ShopOrderItem::query()
            ->where('product_grade', 'B')
            ->whereNull('deleted_at')
            ->whereHas('order', fn ($query) => $query
                ->whereDate('business_date', $dateString)
                ->where('state', 'approved'))
            ->when($hasCategoryFilter && ! empty($assignedCatIds), fn ($query) => $query->whereIn('product_id', $products->pluck('id')->all()))
            ->selectRaw('product_id, SUM(approved_qty) as approved_quantity')
            ->groupBy('product_id')
            ->pluck('approved_quantity', 'product_id');

        $cartItems = PurchaserCartItem::query()
            ->where('grade', 'B')
            ->whereHas('cart', fn ($query) => $query
                ->whereDate('business_date', $dateString)
                ->where('purchase_grade', 'B')
                ->whereIn('status', ['draft', 'submitted']))
            ->with('cart:id,user_id,business_date,status')
            ->get()
            ->groupBy('product_id');

        $userId = $user ? (int) $user->id : 0;

        $items = $products->map(function (Product $product) use ($approvedGradeBQuantities, $cartItems, $userId, $dateString): array {
            $productCartItems = $cartItems->get($product->id, collect());
            $draftQuantity = (float) $productCartItems->filter(fn (PurchaserCartItem $item): bool => $item->cart?->status === 'draft' && (int) $item->cart->user_id === $userId)->sum('quantity');
            $submittedQuantity = (float) $productCartItems->filter(fn (PurchaserCartItem $item): bool => $item->cart?->status === 'submitted')->sum('quantity');
            $approvedQuantity = (float) ($approvedGradeBQuantities->get($product->id) ?? 0);
            $hasGradeBOrder = $approvedQuantity > 0;

            return [
                'product_id' => (int) $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'category_name' => $product->category?->name ?? 'Other',
                'is_direct_catalog' => ! $hasGradeBOrder,
                'is_grade_b_catalog' => true,
                'has_grade_b_order' => $hasGradeBOrder,
                'total_approved_qty' => $approvedQuantity,
                'bought_qty' => $submittedQuantity,
                'draft_qty' => $draftQuantity,
                'remaining_qty' => max(0, $approvedQuantity - $submittedQuantity),
                'order_date' => $dateString,
                'search_index' => strtolower(implode(' ', [(string) $product->name, (string) $product->sku, (string) ($product->category?->name ?? '')])),
            ];
        });

        return $this->applyFilters($items, $selectedChip, $search);
    }

    /**
     * Fetch summary for specific selected products using lightweight grouped SQL queries.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, array<string, mixed>>
     */
    public function getSelectedProductsSummary(
        Carbon $date,
        string $purchaseGrade,
        array $productIds
    ): Collection {
        $dateString = $date->toDateString();
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (empty($productIds)) {
            return collect();
        }

        // 1. Grouped approved demand for selected product_ids
        $approvedItems = ShopOrderItem::query()
            ->where('product_grade', $purchaseGrade)
            ->whereNull('deleted_at')
            ->whereIn('product_id', $productIds)
            ->whereHas('order', function ($query) use ($dateString): void {
                $query->whereDate('business_date', $dateString)->where('state', 'approved');
            })
            ->with('order:id,order_source,business_date,created_at,submitted_at')
            ->get()
            ->groupBy('product_id');

        // 2. Grouped draft quantities for selected product_ids
        $draftQuantities = PurchaserCartItem::query()
            ->where('grade', $purchaseGrade)
            ->whereIn('product_id', $productIds)
            ->whereHas('cart', function ($query) use ($dateString, $purchaseGrade): void {
                $query->whereDate('business_date', $dateString)
                    ->where('status', 'draft')
                    ->where('purchase_grade', $purchaseGrade);
            })
            ->selectRaw('product_id, SUM(quantity) as total_draft_qty')
            ->groupBy('product_id')
            ->pluck('total_draft_qty', 'product_id');

        // 3. Grouped submitted (bought) quantities for selected product_ids
        $submittedCarts = PurchaserCartItem::query()
            ->where('grade', $purchaseGrade)
            ->whereIn('product_id', $productIds)
            ->whereHas('cart', function ($query) use ($dateString, $purchaseGrade): void {
                $query->whereDate('business_date', $dateString)
                    ->where('status', 'submitted')
                    ->where('purchase_grade', $purchaseGrade);
            })
            ->with('cart:id,business_date,status,submitted_at,created_at')
            ->get()
            ->groupBy('product_id');

        // 4. Retrieve Product, Category, and orderUnits
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with([
                'category:id,name',
                'orderUnits' => fn ($query) => $query->where('is_orderable', true)->orderBy('sort_order')->orderBy('id'),
            ])
            ->get(['id', 'category_id', 'name', 'sku', 'unit'])
            ->keyBy('id');

        $selectedSummary = collect();
        foreach ($productIds as $productId) {
            /** @var Product|null $product */
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }

            $items = $approvedItems->get($productId, collect());
            $carts = $submittedCarts->get($productId, collect());

            $normalItems = $items->filter(fn (ShopOrderItem $i): bool => ! $i->order?->isAdminDirectPurchase());
            $addonItems = $items->filter(fn (ShopOrderItem $i): bool => (bool) $i->order?->isAdminDirectPurchase());

            $normalApproved = (float) $normalItems->sum('approved_qty');
            $normalBought = (float) $carts->sum('quantity');
            $normalRemaining = max(0, $normalApproved - $normalBought);

            $addonRemaining = 0.0;
            foreach ($addonItems->groupBy('shop_order_id') as $orderItems) {
                $first = $orderItems->first();
                $orderTime = $first->order->created_at ?? $first->order->submitted_at;
                $addonApproved = (float) $orderItems->sum('approved_qty');
                $addonCarts = $carts->filter(function (PurchaserCartItem $c) use ($orderTime): bool {
                    $cartTime = $c->cart->submitted_at ?? $c->cart->created_at;

                    return $cartTime && $orderTime && $cartTime->gte($orderTime);
                });
                $addonBought = (float) $addonCarts->sum('quantity');
                $addonRemaining += max(0, $addonApproved - $addonBought);
            }

            $approved = (float) $items->sum('approved_qty');
            $bought = (float) $carts->sum('quantity');
            $draft = (float) ($draftQuantities->get($productId) ?? 0.0);
            $remaining = $normalRemaining + $addonRemaining;

            $selectedSummary->push([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'category_name' => (string) ($product->category?->name ?? 'Other'),
                'sku' => (string) ($product->sku ?? ''),
                'unit' => (string) ($product->unit ?? 'kg'),
                'orderable_units' => $this->formatOrderableUnits($product),
                'total_approved_qty' => $approved,
                'bought_qty' => $bought,
                'draft_qty' => $draft,
                'remaining_qty' => $remaining,
                'measure_breakdown' => [],
                'is_frequent' => false,
            ]);
        }

        return $selectedSummary;
    }

    /**
     * @return array<int, array{unit:string,label:string,conversion_to_base:float,is_base:bool}>
     */
    private function formatOrderableUnits(Product $product): array
    {
        $units = $product->relationLoaded('orderUnits')
            ? $product->orderUnits
            : $product->orderUnits()->where('is_orderable', true)->orderBy('sort_order')->orderBy('id')->get();

        $measurementUnits = $units
            ->filter(fn ($unit): bool => (float) $unit->conversion_to_base > 0)
            ->values();

        if ($measurementUnits->isEmpty()) {
            return [[
                'unit' => $product->unit,
                'label' => strtoupper((string) $product->unit),
                'conversion_to_base' => 1.0,
                'is_base' => true,
            ]];
        }

        return $measurementUnits
            ->map(fn ($unit): array => [
                'unit' => (string) $unit->unit,
                'label' => (string) ($unit->label ?: strtoupper((string) $unit->unit)),
                'conversion_to_base' => (float) $unit->conversion_to_base,
                'is_base' => (bool) $unit->is_base,
            ])
            ->all();
    }

    /**
     * Filter items by chip (category) and search query.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $items, ?string $chip, ?string $search): Collection
    {
        $filtered = $items;

        if ($chip && $chip !== 'All') {
            $chipLower = strtolower($chip);
            $filtered = $filtered->filter(fn (array $item): bool => strtolower((string) ($item['category_name'] ?? '')) === $chipLower);
        }

        if ($search && trim($search) !== '') {
            $searchLower = strtolower(trim($search));
            $filtered = $filtered->filter(fn (array $item): bool => str_contains((string) ($item['search_index'] ?? ''), $searchLower));
        }

        return $filtered->values();
    }
}
