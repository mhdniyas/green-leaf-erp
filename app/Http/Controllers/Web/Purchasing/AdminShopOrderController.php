<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Enums\Inventory\ProductGrade;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopPreset;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Requisition\ShopOrderItemSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminShopOrderController extends Controller
{
    private const MANAGER_NOTE = 'Admin marketplace edit';

    public function __construct(
        private readonly PriceBoardService $priceBoardService,
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly ShopOrderItemSyncService $shopOrderItemSyncService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $date = Carbon::parse($request->input('date', $this->businessDayService->operationalDate()->toDateString()))->toDateString();
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));

        $ordersByShopId = ShopOrder::query()
            ->whereDate('business_date', $date)
            ->where('order_source', 'shop_owner')
            ->whereNotNull('shop_id')
            ->with(['items', 'shop'])
            ->withCount('items')
            ->get()
            ->keyBy('shop_id');

        $allRows = Shop::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(function (Shop $shop) use ($ordersByShopId): array {
                /** @var ShopOrder|null $order */
                $order = $ordersByShopId->get($shop->id);

                return [
                    'shop' => $shop,
                    'order' => $order,
                    'state' => $order?->state,
                    'items_count' => $order ? (int) $order->items_count : 0,
                    'requested_qty' => $order ? (float) $order->items->sum('requested_qty') : 0.0,
                    'approved_qty' => $order ? (float) $order->items->sum(fn ($item) => (float) ($item->approved_qty ?? 0)) : 0.0,
                    'is_locked' => $order ? $this->isOrderLocked($order) : false,
                    'can_edit' => $order ? $this->canEditOrder($order) : true,
                ];
            });

        $statusCounts = [
            'none' => $allRows->filter(fn (array $row): bool => $row['order'] === null)->count(),
            'submitted' => $allRows->filter(fn (array $row): bool => ($row['state'] ?? null) === 'submitted')->count(),
            'update_requested' => $allRows->filter(fn (array $row): bool => ($row['state'] ?? null) === 'update_requested')->count(),
            'approved' => $allRows->filter(fn (array $row): bool => ($row['state'] ?? null) === 'approved')->count(),
            'rejected' => $allRows->filter(fn (array $row): bool => ($row['state'] ?? null) === 'rejected')->count(),
        ];

        $shops = $allRows
            ->when($search !== '', function (Collection $rows) use ($search): Collection {
                $needle = mb_strtolower($search);

                return $rows->filter(function (array $row) use ($needle): bool {
                    $shop = $row['shop'];

                    return str_contains(mb_strtolower((string) $shop->name), $needle)
                        || str_contains(mb_strtolower((string) $shop->code), $needle)
                        || str_contains(mb_strtolower((string) ($row['order']?->order_number ?? '')), $needle);
                })->values();
            })
            ->when($status !== '', function (Collection $rows) use ($status): Collection {
                if ($status === 'none') {
                    return $rows->filter(fn (array $row): bool => $row['order'] === null)->values();
                }

                return $rows->filter(fn (array $row): bool => ($row['state'] ?? null) === $status)->values();
            })
            ->values();

        return view('purchase-manager.shop-orders.index', [
            'date' => $date,
            'search' => $search,
            'status' => $status,
            'shops' => $shops,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function edit(Request $request, Shop $shop): View
    {
        $this->authorizeAccess($request);

        $date = Carbon::parse($request->input('date', $this->businessDayService->operationalDate()->toDateString()));
        $order = $this->shopOrderForDate($shop, $date->toDateString());
        $isLocked = $order ? $this->isOrderLocked($order) : false;
        $canEdit = $order ? $this->canEditOrder($order) : true;

        return view('purchase-manager.shop-orders.edit', [
            ...$this->buildOrderFormData($shop, $date, $order),
            'shop' => $shop,
            'businessDate' => $date,
            'isLocked' => $isLocked,
            'canEdit' => $canEdit,
            'lockReason' => $this->lockReason($order),
        ]);
    }

    public function store(Request $request, Shop $shop): RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'business_date' => ['required', 'date'],
            'items' => ['required', 'array'],
        ]);

        $businessDate = Carbon::parse($validated['business_date'])->toDateString();
        $existingOrder = $this->shopOrderForDate($shop, $businessDate);

        if ($existingOrder && ! $this->canEditOrder($existingOrder)) {
            return redirect()
                ->route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => $businessDate])
                ->with('error', $this->lockReason($existingOrder) ?? 'This shop order can no longer be edited.');
        }

        if ($existingOrder?->state === 'rejected') {
            return redirect()
                ->route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => $businessDate])
                ->with('error', 'Rejected shop orders cannot be edited from the marketplace. Create a new order or re-open via approval workflow.');
        }

        $items = $this->shopOrderItemSyncService->resolveRequestedProducts(
            $validated['items'],
            $request->input('item_units', []),
            $request->input('item_measures', []),
        );

        if ($items === []) {
            return redirect()
                ->route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => $businessDate])
                ->withErrors(['items' => 'Shop order cannot be empty.'])
                ->withInput();
        }

        $user = $request->user();

        $order = DB::transaction(function () use ($shop, $businessDate, $existingOrder, $items, $user): ShopOrder {
            $order = $existingOrder;

            if (! $order) {
                $order = ShopOrder::query()->create([
                    'shop_id' => $shop->id,
                    'order_source' => 'shop_owner',
                    'shop_daily_order_key' => ShopOrder::dailyOrderKey((int) $shop->id, $businessDate),
                    'business_date' => $businessDate,
                    'state' => 'approved',
                    'is_late' => false,
                    'submitted_at' => now(),
                    'deadline_at' => $this->businessDayService->rolloverStartsAt(Carbon::parse($businessDate)->subDay()),
                    'created_by' => $user->id,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    'manager_note' => self::MANAGER_NOTE,
                    'update_reason' => null,
                    'has_pending_revision' => false,
                ]);
            } else {
                $wasApproved = $order->state === 'approved';

                $order->update([
                    'submitted_at' => now(),
                    'update_reason' => null,
                    'has_pending_revision' => false,
                    'manager_note' => self::MANAGER_NOTE,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    'state' => $wasApproved || $order->state === 'update_requested' || $order->state === 'submitted'
                        ? 'approved'
                        : $order->state,
                ]);
            }

            $order->loadMissing('shop');
            $this->shopOrderItemSyncService->syncShopOrderItems($order, $items);

            if ($order->state === 'approved') {
                $order->items()->update([
                    'approved_qty' => DB::raw('requested_qty'),
                    'notes' => self::MANAGER_NOTE,
                ]);
            }

            return $order->fresh(['items.product', 'shop']);
        });

        return redirect()
            ->route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => $businessDate])
            ->with('success', 'Shop order '.$order->order_number.' saved successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderFormData(Shop $shop, Carbon $businessDate, ?ShopOrder $order): array
    {
        $productsByCategory = Category::with(['products' => function ($query): void {
            $query->where('is_active', true)->with(['orderUnits' => fn ($q) => $q->where('is_orderable', true)])->ordered();
        }])
            ->where('is_active', true)
            ->get()
            ->filter(fn (Category $category): bool => $category->products->isNotEmpty());

        $productsByCategory->each(function (Category $category) use ($shop): void {
            $category->products->each(function ($product) use ($shop): void {
                $price = $this->priceBoardService->sellingPriceFor($product, $shop, ProductGrade::GradeA);
                $product->setAttribute('effective_price', $price['price']);
            });
        });

        $frequentProducts = $this->frequentProducts($shop);
        $frequentProducts->each(function (array $item) use ($shop): void {
            $product = $item['product'];
            $price = $this->priceBoardService->sellingPriceFor($product, $shop, ProductGrade::GradeA);
            $product->setAttribute('effective_price', $price['price']);
        });

        $yesterdayOrder = ShopOrder::query()
            ->where('shop_id', $shop->id)
            ->where('order_source', 'shop_owner')
            ->whereDate('business_date', '<', $businessDate->toDateString())
            ->with(['items.product'])
            ->latest('business_date')
            ->first();

        return [
            'productsByCategory' => $productsByCategory,
            'frequentProducts' => $frequentProducts,
            'presets' => ShopPreset::query()->where('shop_id', $shop->id)->with('items.product')->get(),
            'yesterdayOrder' => $yesterdayOrder,
            'tomorrowOrder' => $order?->load(['items.product.orderUnits']),
            'tomorrowDate' => $businessDate,
            'cutoffPassed' => false,
            'cutoffLabel' => $this->businessDayService->cutoffLabel(),
            'purchaseOrdersLockedForTomorrow' => $order?->linkedPurchaseOrdersHaveGoodsReceived() ?? false,
            'orderFormAction' => route('purchasing.shop-orders.store', $shop),
            'orderFormMode' => 'admin-shop-order',
            'allowPresetSave' => false,
            'directPurchaseTitle' => $shop->name.' Marketplace',
            'directPurchaseDescription' => 'Admin can add products and update quantities for this shop on the selected business date. Changes bypass the shop cutoff.',
        ];
    }

    private function frequentProducts(Shop $shop): Collection
    {
        $historicalOrders = ShopOrder::query()
            ->where('shop_id', $shop->id)
            ->where('order_source', 'shop_owner')
            ->whereDate('business_date', '<', Carbon::tomorrow())
            ->with(['items.product'])
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

    private function shopOrderForDate(Shop $shop, string $businessDate): ?ShopOrder
    {
        return ShopOrder::query()
            ->where('shop_id', $shop->id)
            ->where('order_source', 'shop_owner')
            ->whereDate('business_date', $businessDate)
            ->with(['items.product.orderUnits', 'invoice', 'shop'])
            ->first();
    }

    private function canEditOrder(ShopOrder $order): bool
    {
        if ($order->state === 'rejected') {
            return false;
        }

        return ! $this->isOrderLocked($order);
    }

    private function isOrderLocked(ShopOrder $order): bool
    {
        return $order->isFinanciallyLocked()
            || $order->is_delivered
            || $order->linkedPurchaseOrdersHaveGoodsReceived();
    }

    private function lockReason(?ShopOrder $order): ?string
    {
        if (! $order) {
            return null;
        }

        if ($order->state === 'rejected') {
            return 'This order was rejected.';
        }

        if ($order->isFinanciallyLocked() || $order->is_delivered) {
            return 'This order is locked because delivery or shop invoice workflow is finalized.';
        }

        if ($order->linkedPurchaseOrdersHaveGoodsReceived()) {
            return 'This order can no longer be updated because goods receipt has already started for its linked purchase order.';
        }

        return null;
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user
            && ($user->hasRole('admin') || $user->hasRole('purchase') || $user->can('purchasing.order.approve')),
            403,
            'Unauthorized access.'
        );
    }
}
