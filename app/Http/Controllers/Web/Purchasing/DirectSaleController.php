<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Enums\Inventory\ProductGrade;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
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
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DirectSaleController extends Controller
{
    private const DEFAULT_SHOP_SETTING_KEY = 'default_direct_sale_shop_id';

    public function __construct(
        private readonly PriceBoardService $priceBoardService,
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly ShopOrderItemSyncService $shopOrderItemSyncService,
    ) {}

    public function create(Request $request): View
    {
        $this->authorizeAccess($request);

        $businessDate = Carbon::parse($request->input('date', $this->businessDayService->operationalDate()->toDateString()));
        $shop = $this->defaultDirectSaleShop();

        return view('purchase-manager.direct-sales.create', [
            ...$this->buildOrderFormData($shop, $businessDate),
            'businessDate' => $businessDate,
            'shop' => $shop,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'business_date' => ['required', 'date'],
            'items' => ['required', 'array'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['required', 'string', 'in:cash,online_upi,card'],
        ]);

        $businessDate = Carbon::parse($validated['business_date'])->toDateString();
        $items = $this->shopOrderItemSyncService->resolveRequestedProducts(
            $validated['items'],
            $request->input('item_units', []),
            $request->input('item_measures', []),
        );

        if ($items === []) {
            return redirect()
                ->route('purchasing.direct-sales.create', ['date' => $businessDate])
                ->withErrors(['items' => 'Direct sale cannot be empty.'])
                ->withInput();
        }

        $shop = $this->defaultDirectSaleShop();
        $user = $request->user();
        $customerName = trim((string) ($validated['customer_name'] ?? ''));
        $customerPhone = trim((string) ($validated['customer_phone'] ?? ''));
        $paymentMethod = (string) $validated['payment_method'];

        $order = DB::transaction(function () use ($shop, $businessDate, $items, $user, $customerName, $customerPhone, $paymentMethod): ShopOrder {
            $noteParts = ['Direct Sale'];

            if ($customerName !== '') {
                $noteParts[] = 'Customer: '.$customerName;
            }

            if ($customerPhone !== '') {
                $noteParts[] = 'Phone: '.$customerPhone;
            }

            $noteParts[] = 'Payment: '.$paymentMethod;
            $managerNote = implode(' | ', $noteParts);

            $order = ShopOrder::query()->create([
                'shop_id' => $shop->id,
                'order_source' => 'direct_sale',
                'business_date' => $businessDate,
                'state' => 'approved',
                'is_late' => false,
                'submitted_at' => now(),
                'deadline_at' => $this->businessDayService->rolloverStartsAt(Carbon::parse($businessDate)->subDay()),
                'created_by' => $user->id,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'manager_note' => $managerNote,
                'update_reason' => null,
                'has_pending_revision' => false,
            ]);

            $this->shopOrderItemSyncService->syncShopOrderItems($order, $items);

            $order->items()->update([
                'approved_qty' => DB::raw('requested_qty'),
                'notes' => $managerNote,
            ]);

            return $order->fresh(['items.product', 'shop']);
        });

        return redirect()
            ->route('warehouse.loadout.show', $order)
            ->with('success', 'Direct sale '.$order->order_number.' created. Complete warehouse loadout next.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderFormData(Shop $shop, Carbon $businessDate): array
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

        return [
            'productsByCategory' => $productsByCategory,
            'frequentProducts' => collect(),
            'presets' => ShopPreset::query()->whereRaw('1 = 0')->with('items.product')->get(),
            'yesterdayOrder' => null,
            'tomorrowOrder' => null,
            'tomorrowDate' => $businessDate,
            'cutoffPassed' => false,
            'cutoffLabel' => $this->businessDayService->cutoffLabel(),
            'purchaseOrdersLockedForTomorrow' => false,
            'orderFormAction' => route('purchasing.direct-sales.store'),
            'orderFormMode' => 'admin-shop-order',
            'allowPresetSave' => false,
            'directPurchaseTitle' => 'Direct Cash Sale',
            'directPurchaseDescription' => 'Create a counter sale order for warehouse loadout. The order is linked to the internal direct-sales shop.',
            'adminSubmitLabel' => 'Create Direct Sale',
        ];
    }

    private function defaultDirectSaleShop(): Shop
    {
        $setting = BusinessSetting::query()->where('key', self::DEFAULT_SHOP_SETTING_KEY)->first();
        $shop = $setting?->value ? Shop::query()->find((int) $setting->value) : null;

        if ($shop) {
            return $shop;
        }

        if ($setting?->value) {
            $setting->delete();
        }

        return DB::transaction(function (): Shop {
            $shop = Shop::query()->firstOrCreate(
                ['code' => 'DIRECT-SALES'],
                [
                    'name' => 'Green Leaf Direct Sales',
                    'warehouse_tag' => 'DIRECT',
                    'status' => 'active',
                    'accounting_mode' => 'owned',
                    'accounting_enabled' => true,
                    'contact_name' => 'Counter Sales',
                ],
            );

            BusinessSetting::query()->updateOrCreate(
                ['key' => self::DEFAULT_SHOP_SETTING_KEY],
                ['value' => (string) $shop->id],
            );

            return $shop;
        });
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->hasRole('admin') || $user->hasRole('purchase') || $user->can('purchasing.order.approve')),
            403,
            'Unauthorized access.'
        );
    }
}
