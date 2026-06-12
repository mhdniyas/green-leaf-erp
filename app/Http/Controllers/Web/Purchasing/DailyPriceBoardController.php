<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\UpdateDailySellingPricesRequest;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DailyPriceBoardController extends Controller
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeBoardAccess();

        $this->priceBoardService->ensureDefaultPriceGroups();
        $selectedGroups = ShopPriceGroup::query()
            ->active()
            ->orderBy('name')
            ->get();
        $search = trim((string) $request->input('search', ''));

        foreach ($selectedGroups as $group) {
            $this->priceBoardService->ensureSellingPricesForGroup($group, $request->user()?->id);
        }

        $products = Product::query()
            ->with('category')
            ->active()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($productQuery) use ($search): void {
                    $productQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($search): void {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->get();

        $targetBusinessDate = Carbon::tomorrow()->toDateString();
        $todayOrderQuantities = ShopOrder::query()
            ->whereDate('business_date', $targetBusinessDate)
            ->whereIn('state', ['submitted', 'approved', 'update_requested'])
            ->with('items')
            ->get()
            ->flatMap->items
            ->groupBy('product_id')
            ->map(fn ($items): float => (float) $items->sum(fn ($item): float => (float) ($item->approved_qty ?? $item->requested_qty ?? 0)));

        $products = $products
            ->each(function (Product $product) use ($todayOrderQuantities): void {
                $product->setAttribute('today_order_qty', (float) ($todayOrderQuantities[$product->id] ?? 0));
            })
            ->sortBy([
                ['today_order_qty', 'desc'],
                ['name', 'asc'],
            ])
            ->values();

        $sellingPrices = DailyProductPrice::query()
            ->whereIn('shop_price_group_id', $selectedGroups->pluck('id'))
            ->whereIn('product_id', $products->pluck('id'))
            ->where('grade', 'A')
            ->get()
            ->groupBy('product_id');

        return view('purchase-manager.prices.index', [
            'selectedGroups' => $selectedGroups,
            'products' => $products,
            'sellingPrices' => $sellingPrices,
            'search' => $search,
            'targetBusinessDate' => $targetBusinessDate,
        ]);
    }

    public function update(UpdateDailySellingPricesRequest $request): RedirectResponse
    {
        $this->priceBoardService->updateShopCategoryPrices(
            $request->validated('simple_prices'),
            (int) $request->user()->id,
            $request->validated('reason')
        );

        return redirect()
            ->route('purchasing.prices.index', [
                'search' => $request->validated('search'),
            ])
            ->with('success', 'Daily product prices updated.');
    }

    private function authorizeBoardAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
    }
}
