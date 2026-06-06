<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ShopOrder;
use App\Models\ShopPreset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ShopOwnerController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $this->shopUser($request);

        return view('shop-owner.dashboard.index', $this->buildDashboardData($user));
    }

    public function ordersIndex(Request $request): View
    {
        $user = $this->shopUser($request);

        return view('shop-owner.orders.index', [
            'orders' => $this->shopOrdersQuery($user)->latest('business_date')->get(),
            'tomorrowOrder' => $this->tomorrowOrder($user),
        ]);
    }

    public function ordersCreate(Request $request): View
    {
        $user = $this->shopUser($request);

        return view('shop-owner.orders.create', $this->buildOrderFormData($user));
    }

    public function ordersShow(Request $request, string $orderNumber): View
    {
        return view('shop-owner.orders.show', [
            'order' => $this->shopOrderByNumber($request, $orderNumber),
        ]);
    }

    public function ordersHistory(Request $request): View
    {
        $user = $this->shopUser($request);

        return view('shop-owner.orders.history', [
            'orders' => $this->shopOrdersQuery($user)->latest('business_date')->paginate(12),
        ]);
    }

    public function dailyPricesIndex(Request $request): View
    {
        $user = $this->shopUser($request);
        $priceDate = Carbon::parse($request->input('date', Carbon::tomorrow()->toDateString()));
        $frequentProducts = $this->frequentProducts($user)->keyBy(fn (array $item): int => (int) $item['product']->id);
        $products = Product::query()
            ->with('category')
            ->active()
            ->orderBy('name')
            ->get();

        $products->each(function (Product $product) use ($frequentProducts): void {
            /** @var array<string, mixed>|null $frequentProduct */
            $frequentProduct = $frequentProducts->get($product->id);

            $product->setAttribute('order_count', (int) ($frequentProduct['order_count'] ?? 0));
            $product->setAttribute('last_order_quantity', (float) ($frequentProduct['last_quantity'] ?? 0));
            $product->setAttribute('total_ordered_quantity', (float) ($frequentProduct['total_quantity'] ?? 0));
        });

        return view('shop-owner.prices.index', [
            'priceDate' => $priceDate,
            'products' => $products,
            'dailyPrices' => DailyProductPrice::query()
                ->whereDate('price_date', $priceDate)
                ->pluck('price', 'product_id'),
            'frequentProducts' => $this->frequentProducts($user),
        ]);
    }

    public function deliveriesIndex(Request $request): View
    {
        $user = $this->shopUser($request);

        return view('shop-owner.deliveries.index', [
            'deliveries' => $this->shopOrdersQuery($user)
                ->where(function ($query): void {
                    $query->where('is_allocation_completed', true)
                        ->orWhere('is_delivered', true);
                })
                ->latest('business_date')
                ->get(),
        ]);
    }

    public function deliveriesShow(Request $request, string $orderNumber): View
    {
        return view('shop-owner.deliveries.show', [
            'order' => $this->shopOrderByNumber($request, $orderNumber),
        ]);
    }

    public function financeIndex(Request $request): View
    {
        $user = $this->shopUser($request);
        $orders = $this->shopOrdersQuery($user)
            ->where(function ($query): void {
                $query->where('is_delivered', true)
                    ->orWhere('cash_collected', '>', 0);
            })
            ->latest('business_date')
            ->get();

        return view('shop-owner.finance.index', [
            'orders' => $orders,
            'outstandingBalance' => (float) $orders->sum(fn (ShopOrder $order): float => (float) $order->balance_amount),
            'paidAmount' => (float) $orders->sum(fn (ShopOrder $order): float => (float) $order->cash_collected),
            'shortageValue' => (float) $orders->sum(fn (ShopOrder $order): float => (float) $order->total_shortage_value),
        ]);
    }

    public function financeShow(Request $request, string $orderNumber): View
    {
        return view('shop-owner.finance.show', [
            'order' => $this->shopOrderByNumber($request, $orderNumber),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardData(User $user): array
    {
        $recentOrders = $this->shopOrdersQuery($user)->latest('business_date')->limit(8)->get();
        $deliveredOrders = $recentOrders->where('is_delivered', true);
        $pendingDeliveries = $recentOrders->filter(
            fn (ShopOrder $order): bool => $order->is_allocation_completed && ! $order->is_delivered
        );

        return [
            'stats' => [
                'pending_approval_count' => $recentOrders->whereIn('state', ['submitted', 'update_requested'])->count(),
                'pending_delivery_count' => $pendingDeliveries->count(),
                'delivered_orders_count' => $deliveredOrders->count(),
                'outstanding_balance' => (float) $recentOrders->sum(fn (ShopOrder $order): float => (float) $order->balance_amount),
            ],
            'todayOrder' => $this->todayOrder($user),
            'tomorrowOrder' => $this->tomorrowOrder($user),
            'pendingDeliveries' => $pendingDeliveries,
            'recentOrders' => $recentOrders,
            'financeSummary' => [
                'paid_amount' => (float) $deliveredOrders->sum(fn (ShopOrder $order): float => (float) $order->cash_collected),
                'shortage_value' => (float) $deliveredOrders->sum(fn (ShopOrder $order): float => (float) $order->total_shortage_value),
                'outstanding_balance' => (float) $recentOrders->sum(fn (ShopOrder $order): float => (float) $order->balance_amount),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderFormData(User $user): array
    {
        $tomorrowDate = Carbon::tomorrow();
        $dailyPriceMap = DailyProductPrice::query()
            ->whereDate('price_date', $tomorrowDate)
            ->pluck('price', 'product_id');

        $productsByCategory = Category::with(['products' => function ($query): void {
            $query->where('is_active', true)->orderBy('name');
        }])
            ->where('is_active', true)
            ->get()
            ->filter(fn (Category $category): bool => $category->products->isNotEmpty());

        $productsByCategory->each(function (Category $category) use ($dailyPriceMap): void {
            $category->products->each(function ($product) use ($dailyPriceMap): void {
                $product->setAttribute('effective_price', (float) ($dailyPriceMap[$product->id] ?? $product->base_price));
            });
        });

        $frequentProducts = $this->frequentProducts($user);
        $frequentProducts->each(function (array $item) use ($dailyPriceMap): void {
            $product = $item['product'];
            $product->setAttribute('effective_price', (float) ($dailyPriceMap[$product->id] ?? $product->base_price));
        });

        return [
            'productsByCategory' => $productsByCategory,
            'frequentProducts' => $frequentProducts,
            'presets' => ShopPreset::where('shop_id', $user->shop_id)->with('items.product')->get(),
            'yesterdayOrder' => $this->yesterdayOrder($user),
            'tomorrowOrder' => $this->tomorrowOrder($user),
            'tomorrowDate' => $tomorrowDate,
            'cutoffPassed' => now()->greaterThan(now()->copy()->setTime(21, 30)),
            'purchaseOrdersGeneratedForTomorrow' => PurchaseOrder::whereDate('order_date', $tomorrowDate)->exists(),
        ];
    }

    private function shopUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasRole('shop') && $user->shop_id, 403);

        return $user;
    }

    private function shopOrderByNumber(Request $request, string $orderNumber): ShopOrder
    {
        $user = $this->shopUser($request);

        return $this->shopOrdersQuery($user)
            ->where('order_number', $orderNumber)
            ->firstOrFail();
    }

    private function todayOrder(User $user): ?ShopOrder
    {
        return $this->shopOrdersQuery($user)
            ->whereDate('business_date', today())
            ->first();
    }

    private function tomorrowOrder(User $user): ?ShopOrder
    {
        return $this->shopOrdersQuery($user)
            ->whereDate('business_date', Carbon::tomorrow())
            ->first();
    }

    private function yesterdayOrder(User $user): ?ShopOrder
    {
        $yesterdayOrder = $this->shopOrdersQuery($user)
            ->whereDate('business_date', today()->subDay())
            ->first();

        if ($yesterdayOrder) {
            return $yesterdayOrder;
        }

        return $this->shopOrdersQuery($user)
            ->whereDate('business_date', '<', today())
            ->latest('business_date')
            ->first();
    }

    private function shopOrdersQuery(User $user)
    {
        return ShopOrder::query()
            ->where('shop_id', $user->shop_id)
            ->with(['shop', 'items.product', 'deliveredBy', 'creator']);
    }

    private function frequentProducts(User $user)
    {
        $historicalOrders = $this->shopOrdersQuery($user)
            ->whereDate('business_date', '<', Carbon::tomorrow())
            ->latest('business_date')
            ->limit(20)
            ->get();

        $productStats = [];

        foreach ($historicalOrders as $order) {
            foreach ($order->items as $item) {
                if (! $item->product) {
                    continue;
                }

                if (! isset($productStats[$item->product_id])) {
                    $productStats[$item->product_id] = [
                        'product' => $item->product,
                        'order_count' => 0,
                        'total_quantity' => 0.0,
                        'last_quantity' => (float) $item->requested_qty,
                    ];
                }

                $productStats[$item->product_id]['order_count']++;
                $productStats[$item->product_id]['total_quantity'] += (float) $item->requested_qty;
            }
        }

        return collect($productStats)
            ->sortByDesc(fn (array $product): array => [$product['order_count'], $product['total_quantity']])
            ->take(12)
            ->values();
    }
}
