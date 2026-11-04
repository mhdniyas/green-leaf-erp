<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSellingPrice;
use App\Models\SellingPriceCategory;
use App\Models\Shop;
use App\Services\Pricing\SellingPriceResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductSellingPriceMatrixController extends Controller
{
    public function __construct(
        private readonly SellingPriceResolverService $resolverService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->input('search', ''));
        $categoryId = $request->input('category_id', 'all');
        $pricingMode = (string) $request->input('pricing_mode', 'all');
        $shopId = $request->filled('shop_id') ? $request->integer('shop_id') : null;

        $shop = $shopId ? Shop::query()->find($shopId) : null;

        $query = Product::query()
            ->where('is_active', true)
            ->with([
                'category:id,name',
                'sellingPriceCategory:id,name,markup_percentage,is_active',
                'sellingPriceConfig',
            ]);

        // 1. Search by name, SKU, or ID
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        // 2. Filter by Category
        if ($categoryId === 'unassigned') {
            $query->whereNull('selling_price_category_id');
        } elseif (is_numeric($categoryId) && (int) $categoryId > 0) {
            $query->where('selling_price_category_id', (int) $categoryId);
        }

        // 3. Filter by Pricing Mode
        if (in_array($pricingMode, [ProductSellingPrice::MODE_CUSTOM_PERCENTAGE, ProductSellingPrice::MODE_FIXED_PRICE], true)) {
            $query->whereHas('sellingPriceConfig', function ($q) use ($pricingMode): void {
                $q->where('pricing_mode', $pricingMode);
            });
        } elseif ($pricingMode === ProductSellingPrice::MODE_INHERITED) {
            $query->where(function ($q): void {
                $q->whereDoesntHave('sellingPriceConfig')
                    ->orWhereHas('sellingPriceConfig', function ($sub): void {
                        $sub->where('pricing_mode', ProductSellingPrice::MODE_INHERITED);
                    });
            });
        }

        $query->orderBy('name');

        $products = $query->paginate(25)->withQueryString();

        // Resolve pricing rules & previews for current page products using batch resolution
        $supplyPrices = $shop
            ? $this->resolverService->batchResolveSupplyPrices($products->getCollection(), $shop)
            : [];

        $resolvedRows = $products->getCollection()->map(function (Product $product) use ($shop, $supplyPrices): array {
            $supplyPrice = $supplyPrices[$product->id] ?? null;
            $resolution = $this->resolverService->resolve($product, $supplyPrice, $shop);

            return [
                'product' => $product,
                'resolution' => $resolution,
            ];
        });

        $categories = SellingPriceCategory::query()->orderBy('name')->get(['id', 'name', 'markup_percentage', 'is_active']);
        $shops = Shop::query()->active()->orderBy('name')->get(['id', 'name', 'code']);
        $globalPercentage = $this->resolverService->globalDefaultPercentage();

        return view('admin.selling-price.matrix', [
            'products' => $products,
            'resolvedRows' => $resolvedRows,
            'categories' => $categories,
            'shops' => $shops,
            'selectedShop' => $shop,
            'globalPercentage' => $globalPercentage,
            'search' => $search,
            'categoryId' => $categoryId,
            'pricingMode' => $pricingMode,
            'shopId' => $shopId,
            'activeTab' => 'matrix',
        ]);
    }

    public function updateProductRule(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'pricing_mode' => ['required', 'string', 'in:inherited,custom_percentage,fixed_price'],
            'markup_percentage' => ['nullable', 'numeric', 'min:0', 'max:500', 'required_if:pricing_mode,custom_percentage'],
            'fixed_price' => ['nullable', 'numeric', 'min:0', 'required_if:pricing_mode,fixed_price'],
            'selling_price_category_id' => ['nullable', 'integer', 'exists:selling_price_categories,id'],
            'clear_category' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $product, $validated): void {
            // 1. Category Assignment update if submitted
            if ($request->boolean('clear_category')) {
                $product->update(['selling_price_category_id' => null]);
            } elseif ($request->filled('selling_price_category_id')) {
                $product->update(['selling_price_category_id' => (int) $validated['selling_price_category_id']]);
            }

            // 2. Pricing Mode rule update
            $mode = $validated['pricing_mode'];
            $markup = ($mode === ProductSellingPrice::MODE_CUSTOM_PERCENTAGE && filled($validated['markup_percentage'] ?? null))
                ? (float) $validated['markup_percentage']
                : null;
            $fixed = ($mode === ProductSellingPrice::MODE_FIXED_PRICE && filled($validated['fixed_price'] ?? null))
                ? round((float) $validated['fixed_price'], 2)
                : null;

            ProductSellingPrice::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'pricing_mode' => $mode,
                    'markup_percentage' => $markup,
                    'fixed_price' => $fixed,
                    'updated_by' => $request->user()->id,
                    'created_by' => $product->sellingPriceConfig?->created_by ?? $request->user()->id,
                ]
            );
        });

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Pricing rule for {$product->name} updated successfully.",
            ]);
        }

        return redirect()
            ->back()
            ->with('success', "Pricing rule for \"{$product->name}\" updated successfully.");
    }

    public function resetProductRule(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);

        DB::transaction(function () use ($request, $product): void {
            ProductSellingPrice::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'pricing_mode' => ProductSellingPrice::MODE_INHERITED,
                    'markup_percentage' => null,
                    'fixed_price' => null,
                    'updated_by' => $request->user()->id,
                    'created_by' => $product->sellingPriceConfig?->created_by ?? $request->user()->id,
                ]
            );
        });

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Pricing rule for {$product->name} reset to inherited.",
            ]);
        }

        return redirect()
            ->back()
            ->with('success', "Pricing rule for \"{$product->name}\" reset to inherited.");
    }

    public function bulkAssignCategory(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'selling_price_category_id' => ['required', 'integer', 'exists:selling_price_categories,id'],
        ]);

        $category = SellingPriceCategory::query()->findOrFail((int) $validated['selling_price_category_id']);
        $productIds = array_values(array_unique(array_map('intval', $validated['product_ids'])));

        DB::transaction(function () use ($productIds, $category): void {
            Product::query()
                ->whereIn('id', $productIds)
                ->update(['selling_price_category_id' => $category->id]);
        });

        $count = count($productIds);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Assigned {$count} products to category {$category->name}.",
                'count' => $count,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', "Assigned {$count} product(s) to category \"{$category->name}\".");
    }

    public function bulkSetPercentage(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'markup_percentage' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        $percentage = (float) $validated['markup_percentage'];
        $productIds = array_values(array_unique(array_map('intval', $validated['product_ids'])));
        $userId = $request->user()->id;

        DB::transaction(function () use ($productIds, $percentage, $userId): void {
            foreach ($productIds as $pId) {
                ProductSellingPrice::query()->updateOrCreate(
                    ['product_id' => $pId],
                    [
                        'pricing_mode' => ProductSellingPrice::MODE_CUSTOM_PERCENTAGE,
                        'markup_percentage' => $percentage,
                        'fixed_price' => null,
                        'updated_by' => $userId,
                        'created_by' => $userId,
                    ]
                );
            }
        });

        $count = count($productIds);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Set custom markup of {$percentage}% for {$count} products.",
                'count' => $count,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', "Set custom markup of {$percentage}% for {$count} product(s).");
    }

    public function bulkReset(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $productIds = array_values(array_unique(array_map('intval', $validated['product_ids'])));
        $userId = $request->user()->id;

        DB::transaction(function () use ($productIds, $userId): void {
            foreach ($productIds as $pId) {
                ProductSellingPrice::query()->updateOrCreate(
                    ['product_id' => $pId],
                    [
                        'pricing_mode' => ProductSellingPrice::MODE_INHERITED,
                        'markup_percentage' => null,
                        'fixed_price' => null,
                        'updated_by' => $userId,
                        'created_by' => $userId,
                    ]
                );
            }
        });

        $count = count($productIds);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Reset {$count} products to inherited pricing.",
                'count' => $count,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', "Reset {$count} product(s) to inherited pricing.");
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
