<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SellingPriceCategory;
use App\Models\Shop;
use App\Models\ShopProductSellingPrice;
use App\Services\Pricing\SellingPriceResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShopSellingPriceController extends Controller
{
    public function __construct(
        private readonly SellingPriceResolverService $resolverService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $shops = Shop::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'shop_price_group_id']);

        $selectedShopId = $request->filled('shop_id')
            ? $request->integer('shop_id')
            : ($shops->first()?->id ?? null);

        $selectedShop = $selectedShopId
            ? $shops->firstWhere('id', $selectedShopId) ?? Shop::query()->find($selectedShopId)
            : null;

        $search = trim((string) $request->input('search', ''));
        $categoryId = $request->input('category_id', 'all');
        $overrideStatus = (string) $request->input('override_status', 'all');

        $categories = SellingPriceCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'markup_percentage', 'is_active']);

        $query = Product::query()
            ->where('is_active', true)
            ->with([
                'category:id,name',
                'sellingPriceCategory:id,name,markup_percentage,is_active',
                'sellingPriceConfig',
            ]);

        if ($selectedShop) {
            $query->with([
                'shopSellingPrices' => fn ($q) => $q->where('shop_id', $selectedShop->id)->with('updater:id,name'),
            ]);
        }

        // 1. Search filter
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        // 2. Category filter
        if ($categoryId === 'unassigned') {
            $query->whereNull('selling_price_category_id');
        } elseif (is_numeric($categoryId) && (int) $categoryId > 0) {
            $query->where('selling_price_category_id', (int) $categoryId);
        }

        // 3. Override status filter
        if ($selectedShop && $overrideStatus === 'customized') {
            $query->whereHas('shopSellingPrices', function ($q) use ($selectedShop): void {
                $q->where('shop_id', $selectedShop->id);
            });
        } elseif ($selectedShop && $overrideStatus === 'inherited') {
            $query->whereDoesntHave('shopSellingPrices', function ($q) use ($selectedShop): void {
                $q->where('shop_id', $selectedShop->id);
            });
        }

        $query->orderBy('name');

        $products = $query->paginate(25)->withQueryString();

        // Batch resolve supply prices
        $supplyPrices = $selectedShop
            ? $this->resolverService->batchResolveSupplyPrices($products->getCollection(), $selectedShop)
            : [];

        // Map resolutions
        $resolvedRows = $products->getCollection()->map(function (Product $product) use ($selectedShop, $supplyPrices): array {
            $supplyPrice = $supplyPrices[$product->id] ?? null;
            $resolution = $this->resolverService->resolve($product, $supplyPrice, $selectedShop);

            return [
                'product' => $product,
                'resolution' => $resolution,
                'general_rule' => $resolution['general_rule'],
                'supply_price' => $supplyPrice,
            ];
        });

        // Summary metrics
        $totalProductsCount = Product::query()->where('is_active', true)->count();
        $shopCustomizedCount = $selectedShop
            ? ShopProductSellingPrice::query()->where('shop_id', $selectedShop->id)->count()
            : 0;
        $shopInheritedCount = max(0, $totalProductsCount - $shopCustomizedCount);

        return view('admin.selling-price.shop-pricing', [
            'activeTab' => 'shop-pricing',
            'shops' => $shops,
            'selectedShop' => $selectedShop,
            'selectedShopId' => $selectedShopId,
            'categories' => $categories,
            'products' => $products,
            'resolvedRows' => $resolvedRows,
            'search' => $search,
            'categoryId' => $categoryId,
            'overrideStatus' => $overrideStatus,
            'totalProductsCount' => $totalProductsCount,
            'shopCustomizedCount' => $shopCustomizedCount,
            'shopInheritedCount' => $shopInheritedCount,
            'globalPercentage' => $this->resolverService->globalDefaultPercentage(),
        ]);
    }

    public function updateOverride(Request $request, Shop $shop, Product $product): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'markup_percentage' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        $percentage = round((float) $validated['markup_percentage'], 2);
        $userId = $request->user()?->id;

        $override = ShopProductSellingPrice::query()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'product_id' => $product->id,
            ],
            [
                'markup_percentage' => $percentage,
                'updated_by' => $userId,
            ]
        );

        if (! $override->created_by && $userId) {
            $override->created_by = $userId;
            $override->save();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Selling price override for {$product->name} at {$shop->name} set to {$percentage}%.",
                'override' => $override,
            ]);
        }

        return redirect()->back()->with('success', "Selling price override for {$product->name} at {$shop->name} set to {$percentage}%.");
    }

    public function resetOverride(Request $request, Shop $shop, Product $product): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);

        ShopProductSellingPrice::query()
            ->where('shop_id', $shop->id)
            ->where('product_id', $product->id)
            ->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Selling price override for {$product->name} at {$shop->name} reset to general pricing.",
            ]);
        }

        return redirect()->back()->with('success', "Selling price override for {$product->name} at {$shop->name} reset to general pricing.");
    }

    public function bulkApply(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'shop_ids' => ['required', 'array', 'min:1'],
            'shop_ids.*' => ['integer', 'exists:shops,id'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'markup_percentage' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        $shopIds = array_map('intval', $validated['shop_ids']);
        $productIds = array_map('intval', $validated['product_ids']);
        $percentage = round((float) $validated['markup_percentage'], 2);
        $userId = $request->user()?->id;

        $totalCombinations = count($shopIds) * count($productIds);
        if ($totalCombinations > 1000) {
            $msg = 'Cannot process more than 1,000 combinations in a single bulk operation. Please narrow your shop or product selection.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->back()->withErrors(['bulk' => $msg]);
        }

        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($shopIds, $productIds, $percentage, $userId, &$createdCount, &$updatedCount): void {
            $existing = ShopProductSellingPrice::query()
                ->whereIn('shop_id', $shopIds)
                ->whereIn('product_id', $productIds)
                ->get()
                ->groupBy('shop_id')
                ->map(fn ($rows) => $rows->keyBy('product_id'));

            foreach ($shopIds as $shopId) {
                foreach ($productIds as $productId) {
                    $record = $existing->get($shopId)?->get($productId);
                    if ($record) {
                        $record->markup_percentage = $percentage;
                        $record->updated_by = $userId;
                        $record->save();
                        $updatedCount++;
                    } else {
                        ShopProductSellingPrice::create([
                            'shop_id' => $shopId,
                            'product_id' => $productId,
                            'markup_percentage' => $percentage,
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]);
                        $createdCount++;
                    }
                }
            }
        });

        $msg = sprintf(
            'Bulk pricing applied: %d overrides created and %d updated across %d shop(s) and %d product(s).',
            $createdCount,
            $updatedCount,
            count($shopIds),
            count($productIds)
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function bulkReset(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'shop_ids' => ['required', 'array', 'min:1'],
            'shop_ids.*' => ['integer', 'exists:shops,id'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $shopIds = array_map('intval', $validated['shop_ids']);
        $productIds = array_map('intval', $validated['product_ids']);

        $deletedCount = 0;
        DB::transaction(function () use ($shopIds, $productIds, &$deletedCount): void {
            $deletedCount = ShopProductSellingPrice::query()
                ->whereIn('shop_id', $shopIds)
                ->whereIn('product_id', $productIds)
                ->delete();
        });

        $msg = sprintf(
            '%d shop-specific pricing override(s) reset to general pricing across %d shop(s) and %d product(s).',
            $deletedCount,
            count($shopIds),
            count($productIds)
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'deleted_count' => $deletedCount,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
