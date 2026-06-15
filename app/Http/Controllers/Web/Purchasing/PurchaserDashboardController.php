<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\StorePurchaserCartItemRequest;
use App\Http\Requests\Web\Purchasing\StorePurchaserCorrectionRequest;
use App\Http\Requests\Web\Purchasing\SubmitPurchaserCartRequest;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserCorrectionRequest;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaserDashboardController extends Controller
{
    private const array QUICK_FILTERS = [
        'Frequent',
        'All',
        'Supply',
        'VEG',
        'Leaf',
        'English',
        'Kolkata',
        'Banana',
        'Onion',
        'C',
        'Frut',
        'Stationory',
    ];

    public function index(): RedirectResponse
    {
        return redirect()->route('purchaser.daily');
    }

    public function daily(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $selectedChip = $this->resolveQuickFilter($request->string('chip')->toString());
        $search = trim($request->string('search')->toString());
        $user = $request->user();
        $frequentProductIds = $this->frequentProductIds((int) $user->id);

        $dailySummary = $this->buildDailySummary($date, $frequentProductIds);
        $filteredDailySummary = $this->filterProductsForChip($dailySummary, $selectedChip, $search, $frequentProductIds);

        $draftCarts = $this->draftCartsForDate((int) $user->id, $date);

        $productCatalog = Product::query()
            ->with('category')
            ->active()
            ->orderBy('name')
            ->get();

        return view('purchasing.purchaser.daily', [
            'date' => $date->format('Y-m-d'),
            'quickFilters' => self::QUICK_FILTERS,
            'selectedChip' => $selectedChip,
            'search' => $search,
            'dailySummary' => $filteredDailySummary,
            'draftCarts' => $draftCarts,
            'buyOtherProducts' => $this->filterProductsForChip($productCatalog, $selectedChip, $search, $frequentProductIds),
            'dailySummaryShareUrl' => 'https://api.whatsapp.com/send?text='.rawurlencode($this->buildDailySummaryShareText($dailySummary, $date)),
            'dailyFulfillment' => [
                'products' => $dailySummary->count(),
                'approved_qty' => (float) $dailySummary->sum('total_approved_qty'),
                'bought_qty' => (float) $dailySummary->sum('bought_qty'),
                'remaining_qty' => (float) $dailySummary->sum('remaining_qty'),
                'draft_carts' => $draftCarts->count(),
            ],
        ]);
    }

    public function dailyShare(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $user = $request->user();
        $frequentProductIds = $this->frequentProductIds((int) $user->id);
        $dailySummary = $this->buildDailySummary($date, $frequentProductIds);

        $availableTags = $dailySummary
            ->pluck('category_name')
            ->filter(fn (?string $categoryName): bool => filled($categoryName))
            ->unique()
            ->sort()
            ->values();

        $shareMode = $this->resolveDailyShareMode($request->string('share_mode')->toString());
        $availableProductIds = $dailySummary
            ->pluck('product_id')
            ->map(fn ($productId): int => (int) $productId)
            ->all();

        $selectedTags = collect($request->input('tags', []))
            ->filter(fn ($tag): bool => is_string($tag) && $availableTags->contains($tag))
            ->values()
            ->all();
        $selectedProductIds = collect($request->input('product_ids', []))
            ->map(fn ($productId): int => (int) $productId)
            ->filter(fn (int $productId): bool => in_array($productId, $availableProductIds, true))
            ->unique()
            ->values()
            ->all();

        $selectedProductId = $request->integer('product_id');
        if (! $dailySummary->contains(fn (array $summary): bool => (int) $summary['product_id'] === $selectedProductId)) {
            $selectedProductId = 0;
        }

        $shareSummary = $this->filterDailySummaryForShare(
            dailySummary: $dailySummary,
            shareMode: $shareMode,
            selectedTags: $selectedTags,
            selectedProductIds: $selectedProductIds,
            selectedProductId: $selectedProductId,
        );

        $sharePreviewText = $this->buildDailySummaryShareText($shareSummary, $date);
        $shareUrl = 'https://api.whatsapp.com/send?text='.rawurlencode($sharePreviewText);

        return view('purchasing.purchaser.daily_share', [
            'date' => $date->format('Y-m-d'),
            'shareMode' => $shareMode,
            'selectedTags' => $selectedTags,
            'selectedProductIds' => $selectedProductIds,
            'selectedProductId' => $selectedProductId,
            'availableTags' => $availableTags,
            'availableProducts' => $dailySummary
                ->sortBy('product_name')
                ->map(fn (array $summary): array => [
                    'product_id' => (int) $summary['product_id'],
                    'product_name' => (string) $summary['product_name'],
                    'category_name' => (string) $summary['category_name'],
                    'remaining_qty' => (float) $summary['remaining_qty'],
                    'unit' => (string) $summary['unit'],
                    'search_index' => strtolower(trim(implode(' ', [
                        (string) $summary['product_name'],
                        (string) $summary['category_name'],
                        (string) ($summary['sku'] ?? ''),
                    ]))),
                ])
                ->values(),
            'shareSummary' => $shareSummary,
            'sharePreviewText' => $sharePreviewText,
            'shareUrl' => $shareUrl,
        ]);
    }

    public function bulkBuy(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $user = $request->user();
        $frequentProductIds = $this->frequentProductIds((int) $user->id);
        $dailySummary = $this->buildDailySummary($date, $frequentProductIds);

        return view('purchasing.purchaser.bulk_buy', [
            'date' => $date->format('Y-m-d'),
            'quickFilters' => self::QUICK_FILTERS,
            'dailySummary' => $dailySummary,
        ]);
    }

    public function bulkBuyDetails(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $productIds = $request->input('product_ids');
        if (empty($productIds) || ! is_array($productIds)) {
            return redirect()
                ->route('purchaser.bulk-buy', ['date' => $date->format('Y-m-d')])
                ->with('error', 'Please select at least one product.');
        }

        $user = $request->user();
        $frequentProductIds = $this->frequentProductIds((int) $user->id);

        $dailySummary = $this->buildDailySummary($date, $frequentProductIds)
            ->filter(fn ($item) => in_array((int) $item['product_id'], array_map('intval', $productIds), true))
            ->values();

        if ($dailySummary->isEmpty()) {
            return redirect()
                ->route('purchaser.bulk-buy', ['date' => $date->format('Y-m-d')])
                ->with('error', 'Selected products do not have approved demand.');
        }

        $draftCarts = $this->draftCartsForDate((int) $user->id, $date);

        return view('purchasing.purchaser.bulk_buy_details', [
            'date' => $date->format('Y-m-d'),
            'dailySummary' => $dailySummary,
            'draftCarts' => $draftCarts,
        ]);
    }

    public function cart(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        return redirect()->route('purchaser.vendors', ['date' => $date->format('Y-m-d')]);
    }

    public function bill(Request $request, PurchaserCart $cart): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['draft']);

        if ($cart->items->isEmpty()) {
            return redirect()
                ->route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d')])
                ->withErrors(['The selected cart is empty.']);
        }

        return view('purchasing.purchaser.bill', [
            'date' => $cart->business_date->format('Y-m-d'),
            'cart' => $cart,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'subtotal' => (float) $cart->items->sum('line_total'),
        ]);
    }

    public function history(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $historyCarts = PurchaserCart::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('business_date', $date)
            ->with([
                'supplier',
                'items.product.category',
                'purchaseOrder',
                'goodsReceived',
                'purchaseInvoice',
            ])
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->get();

        $groupedCarts = collect([
            'draft' => $historyCarts->where('workflow_status', 'draft')->values(),
            'whatsapp_sent' => $historyCarts->where('workflow_status', 'whatsapp_sent')->values(),
            'submitted' => $historyCarts->where('workflow_status', 'submitted')->values(),
            'approved' => $historyCarts->where('workflow_status', 'approved')->values(),
            'rejected' => $historyCarts->where('workflow_status', 'rejected')->values(),
        ]);

        return view('purchasing.purchaser.history', [
            'date' => $date->format('Y-m-d'),
            'groupedCarts' => $groupedCarts,
        ]);
    }

    public function vendors(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $user = $request->user();

        $carts = PurchaserCart::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $date)
            ->with(['supplier', 'items.product.category', 'goodsReceived', 'purchaseOrder', 'purchaseInvoice'])
            ->orderByDesc('updated_at')
            ->get();

        $orders = $carts->whereNull('goods_received_at')->values();
        $delivered = $carts->whereNotNull('goods_received_at')->values();
        $draftOrders = $orders->where('status', 'draft')->values();
        $mergeSuggestions = $this->buildDraftMergeSuggestions($draftOrders);
        $mergeableDraftCounts = $mergeSuggestions
            ->mapWithKeys(fn (array $suggestion): array => [
                (int) $suggestion['target_cart']->id => (int) $suggestion['count'] - 1,
            ])
            ->all();

        $productCatalog = Product::query()
            ->with('category')
            ->active()
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::query()->orderBy('name')->get();

        return view('purchasing.purchaser.vendors', [
            'date' => $date->format('Y-m-d'),
            'orders' => $orders,
            'delivered' => $delivered,
            'mergeSuggestions' => $mergeSuggestions,
            'mergeableDraftCounts' => $mergeableDraftCounts,
            'productCatalog' => $productCatalog,
            'suppliers' => $suppliers,
        ]);
    }

    public function finance(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $invoices = PurchaseInvoice::query()
            ->with(['supplier', 'goodsReceived', 'purchaserCart'])
            ->whereHas('purchaserCart', function ($query) use ($request, $date): void {
                $query
                    ->where('user_id', $request->user()->id)
                    ->whereDate('business_date', $date);
            })
            ->orderByDesc('id')
            ->paginate(20);

        return view('purchasing.purchaser.finance', [
            'date' => $date->format('Y-m-d'),
            'invoices' => $invoices,
            'financeAudience' => 'purchaser',
            'canManageSuppliers' => true,
        ]);
    }

    public function invoicePdf(Request $request, PurchaseInvoice $invoice): View
    {
        $this->ensurePurchaser($request);

        $invoice = PurchaseInvoice::query()
            ->whereKey($invoice->id)
            ->whereHas('purchaserCart', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->with([
                'supplier',
                'purchaserCart.items.product',
                'goodsReceived.items.product',
                'goodsReceived.purchaseOrder',
            ])
            ->firstOrFail();

        return view('purchasing.invoices.pdf', compact('invoice'));
    }

    public function invoiceShow(Request $request, PurchaseInvoice $invoice): View
    {
        $this->ensurePurchaser($request);

        $invoice = PurchaseInvoice::query()
            ->whereKey($invoice->id)
            ->whereHas('purchaserCart', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->with([
                'supplier',
                'purchaserCart.items.product',
                'goodsReceived.items.product',
                'goodsReceived.purchaseOrder',
                'purchaserCart',
            ])
            ->firstOrFail();

        return view('purchasing.invoices.show', [
            'invoice' => $invoice,
            'paymentUpdateRouteName' => 'purchaser.invoices.payment',
            'billPdfRouteName' => 'purchaser.invoices.pdf',
            'backRouteName' => 'purchaser.finance',
            'backRouteParameters' => ['date' => $invoice->purchaserCart?->business_date?->format('Y-m-d')],
            'financeAudience' => 'purchaser',
        ]);
    }

    public function mergeDraftCarts(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['draft']);
        $mergeGroup = $this->mergeGroupDraftCarts($cart)->values();

        if ($mergeGroup->count() <= 1) {
            return redirect()
                ->route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d')])
                ->with('error', 'No other draft carts are available to merge.');
        }

        /** @var PurchaserCart $targetCart */
        $targetCart = $mergeGroup->sortByDesc('updated_at')->first();

        foreach ($mergeGroup as $sourceCart) {
            if ($sourceCart->is($targetCart)) {
                continue;
            }

            $this->mergeDraftCartIntoTarget($sourceCart, $targetCart);
            $targetCart = $targetCart->fresh(['supplier', 'items.product.category', 'goodsReceived']);
        }

        return redirect()
            ->route('purchaser.vendors', ['date' => $targetCart->business_date->format('Y-m-d')])
            ->with('success', 'Draft carts merged into one cart.');
    }

    public function bulkStoreCart(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $request->validate([
            'business_date' => ['required', 'date'],
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['required', 'exists:products,id'],
            'cart_id' => ['nullable', 'integer'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $date = Carbon::parse($validated['business_date']);
        $user = $request->user();
        $cartId = filled($validated['cart_id'] ?? null) ? (int) $validated['cart_id'] : null;

        $cart = $cartId
            ? PurchaserCart::query()
                ->whereKey($cartId)
                ->where('user_id', $user->id)
                ->whereDate('business_date', $date)
                ->where('status', 'draft')
                ->firstOrFail()
            : PurchaserCart::query()
                ->where('user_id', $user->id)
                ->whereDate('business_date', $date)
                ->whereNull('supplier_id')
                ->where('status', 'draft')
                ->first();

        if (! $cart) {
            $cart = PurchaserCart::query()->create([
                'user_id' => $user->id,
                'business_date' => $date,
                'cart_number' => PurchaserCart::generateCartNumber($date),
                'status' => 'draft',
            ]);
        }

        $addedCount = 0;
        foreach ($validated['product_ids'] as $productId) {
            $productId = (int) $productId;
            $product = Product::query()->findOrFail($productId);
            $itemData = $validated['items'][$productId] ?? null;

            if (! is_array($itemData)) {
                continue;
            }

            $remainingApproved = $this->remainingApprovedQuantityForProduct($date, $productId, (int) $cart->id);
            $quantity = (float) $itemData['quantity'];
            $unitPrice = (float) ($itemData['unit_price'] ?? 0);

            $existingItem = $cart->items()->where('product_id', $productId)->first();
            $newQuantity = $existingItem instanceof PurchaserCartItem
                ? (float) $existingItem->quantity + $quantity
                : $quantity;

            if ($existingItem instanceof PurchaserCartItem) {
                $existingItem->update([
                    'quantity' => $newQuantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($newQuantity * $unitPrice, 2),
                    'is_extra_purchase' => $newQuantity > $remainingApproved,
                ]);
            } else {
                $cart->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                    'is_extra_purchase' => $quantity > $remainingApproved,
                ]);
                $addedCount++;
            }
        }

        return redirect()
            ->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])
            ->with('success', "Added {$addedCount} products to vendor cart.");
    }

    public function storeCart(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $request->validate([
            'business_date' => ['required', 'date', Rule::date()->todayOrBefore()],
        ]);

        $date = Carbon::parse($validated['business_date']);
        $cart = $this->findReusableDraftCart(
            userId: (int) $request->user()->id,
            date: $date,
            supplierId: null,
        ) ?? PurchaserCart::query()->create([
            'user_id' => $request->user()->id,
            'business_date' => $date,
            'cart_number' => PurchaserCart::generateCartNumber($date),
            'status' => 'draft',
        ]);

        return redirect()
            ->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])
            ->with('success', 'Draft cart ready.');
    }

    public function storeCartItem(StorePurchaserCartItemRequest $request): RedirectResponse
    {
        $date = Carbon::parse($request->validated('business_date'));
        $user = $request->user();
        $cartId = $request->integer('cart_id');

        $cart = $cartId > 0
            ? PurchaserCart::query()
                ->whereKey($cartId)
                ->where('user_id', $user->id)
                ->where('status', 'draft')
                ->with(['items.product', 'goodsReceived'])
                ->firstOrFail()
            : (PurchaserCart::query()
                ->where('user_id', $user->id)
                ->whereDate('business_date', $date)
                ->whereNull('supplier_id')
                ->where('status', 'draft')
                ->first()
              ?? PurchaserCart::query()->create([
                  'user_id' => $user->id,
                  'business_date' => $date,
                  'cart_number' => PurchaserCart::generateCartNumber($date),
                  'status' => 'draft',
              ]));

        $product = Product::query()->with('category')->findOrFail($request->integer('product_id'));
        $quantity = (float) $request->validated('quantity');
        $unitPrice = (float) $request->input('unit_price', 0);
        $existingItem = $cart->items()->where('product_id', $product->id)->first();
        $newQuantity = $existingItem instanceof PurchaserCartItem
            ? (float) $existingItem->quantity + $quantity
            : $quantity;

        $remainingApproved = $this->remainingApprovedQuantityForProduct($date, (int) $product->id, (int) $cart->id);
        $isExtraPurchase = $newQuantity > $remainingApproved;

        if ($existingItem instanceof PurchaserCartItem) {
            $existingItem->update([
                'quantity' => $newQuantity,
                'unit_price' => $unitPrice,
                'line_total' => round($newQuantity * $unitPrice, 2),
                'is_extra_purchase' => $isExtraPurchase,
                'notes' => $request->validated('notes'),
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($quantity * $unitPrice, 2),
                'is_extra_purchase' => $isExtraPurchase,
                'notes' => $request->validated('notes'),
            ]);
        }

        return $this->redirectAfterMutation(
            $request->string('return_to')->toString(),
            $date,
            (int) $cart->id,
            $isExtraPurchase
                ? "{$product->name} added to cart. Over-demand quantity will be flagged as extra purchase."
                : "{$product->name} added to cart."
        );
    }

    public function updateCartItem(Request $request, PurchaserCartItem $item): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $item->cart()
            ->where('user_id', $request->user()->id)
            ->where('status', 'draft')
            ->with('items')
            ->firstOrFail();

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $quantity = (float) $validated['quantity'];
        $unitPrice = (float) ($validated['unit_price'] ?? 0);
        $remainingApproved = $this->remainingApprovedQuantityForProduct($cart->business_date, (int) $item->product_id, (int) $cart->id);

        $item->update([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
            'is_extra_purchase' => $quantity > $remainingApproved,
            'notes' => $validated['notes'] ?? null,
        ]);

        // ALSO UPDATE DAILY ORDER (ShopOrderItem)
        $shopOrderItems = ShopOrderItem::query()
            ->whereHas('order', function ($query) use ($cart): void {
                $query->whereDate('business_date', $cart->business_date);
            })
            ->where('product_id', $item->product_id)
            ->get();

        if ($shopOrderItems->isNotEmpty()) {
            if ($shopOrderItems->count() === 1) {
                $shopOrderItems->first()->update(['approved_qty' => $quantity]);
            } else {
                $totalCurrentApproved = $shopOrderItems->sum('approved_qty');
                if ($totalCurrentApproved > 0) {
                    $ratio = $quantity / $totalCurrentApproved;
                    foreach ($shopOrderItems as $shopOrderItem) {
                        $shopOrderItem->update([
                            'approved_qty' => round($shopOrderItem->approved_qty * $ratio, 2),
                        ]);
                    }
                } else {
                    $shopOrderItems->first()->update(['approved_qty' => $quantity]);
                }
            }
        }

        return $this->redirectAfterMutation(
            $request->string('return_to')->toString(),
            $cart->business_date,
            (int) $cart->id,
            'Vendor cart item updated.'
        );
    }

    public function destroyCartItem(Request $request, PurchaserCartItem $item): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $item->cart()->where('user_id', $request->user()->id)->where('status', 'draft')->firstOrFail();
        $item->delete();

        return $this->redirectAfterMutation(
            $request->string('return_to')->toString(),
            $cart->business_date,
            (int) $cart->id,
            'Vendor cart item removed.'
        );
    }

    public function markCartSent(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['draft', 'submitted']);

        $returnTo = $request->input('return_to', 'cart');

        if ($cart->items->isEmpty()) {
            return $this->redirectAfterMutation($returnTo, $cart->business_date, $cart->id, '')
                ->withErrors(['The selected cart is empty.']);
        }

        $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'vendor_name' => ['nullable', 'string', 'max:255', 'required_without:supplier_id'],
            'vendor_location' => ['nullable', 'string', 'max:255'],
            'vendor_mobile_number' => ['nullable', 'string', 'max:50', 'required_without:supplier_id'],
            'vendor_type' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'preferred_payment_method' => ['nullable', 'string', 'max:100'],
            'share_mode' => ['nullable', 'string', 'in:saved,custom,any'],
            'show_price' => ['nullable', 'boolean'],
        ]);

        if ($request->string('share_mode')->toString() === 'custom') {
            $digits = preg_replace('/\D+/', '', (string) $request->input('vendor_mobile_number'));

            if ($digits === null || strlen($digits) !== 10) {
                return $this->redirectAfterMutation($request->input('return_to', 'cart'), $cart->business_date, $cart->id, '')
                    ->withErrors(['Enter a valid 10 digit India mobile number.']);
            }
        }

        $supplier = $this->resolveSubmissionSupplier($request);
        $cart = $this->assignSupplierToCart($cart, $supplier);

        $cart->update([
            'supplier_id' => $supplier->id,
            'whatsapp_sent_at' => now(),
        ]);

        if ($cart->status === 'submitted') {
            if ($cart->purchaseOrder) {
                $cart->purchaseOrder->update(['supplier_id' => $supplier->id]);
            }
            if ($cart->purchaseInvoice) {
                $cart->purchaseInvoice->update(['supplier_id' => $supplier->id]);
            }
        }

        $showPrice = $request->boolean('show_price', false);
        $message = $this->buildCartShareText($cart->fresh(['items.product', 'supplier']), $showPrice);

        $shareMode = $request->string('share_mode')->toString() ?: 'saved';
        $customMobile = $request->input('vendor_mobile_number');

        if ($shareMode === 'any') {
            $whatsAppUrl = 'https://api.whatsapp.com/send?text='.rawurlencode($message);
        } elseif ($shareMode === 'custom') {
            $digits = preg_replace('/\D+/', '', (string) $customMobile);
            if ($digits !== null && strlen($digits) === 10) {
                $digits = '91'.$digits;
            }
            $whatsAppUrl = $digits ? 'https://api.whatsapp.com/send?phone='.$digits.'&text='.rawurlencode($message) : null;
        } else {
            $whatsAppUrl = $this->buildSupplierWhatsAppUrl($supplier, $message);
        }

        if ($whatsAppUrl === null) {
            return $this->redirectAfterMutation($returnTo, $cart->business_date, $cart->id, '')
                ->withErrors(['Selected vendor does not have a mobile number for WhatsApp.']);
        }

        return redirect()->away($whatsAppUrl);
    }

    public function updateCartSupplier(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['draft', 'submitted']);

        $returnTo = $request->input('return_to', 'vendors');

        $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'vendor_name' => ['nullable', 'string', 'max:255', 'required_without:supplier_id'],
            'vendor_location' => ['nullable', 'string', 'max:255'],
            'vendor_mobile_number' => ['nullable', 'string', 'max:50', 'required_without:supplier_id'],
        ]);

        $supplier = $this->resolveSubmissionSupplier($request);
        $cart = $this->assignSupplierToCart($cart, $supplier);

        $cart->update([
            'supplier_id' => $supplier->id,
        ]);

        if ($cart->status === 'submitted') {
            if ($cart->purchaseOrder) {
                $cart->purchaseOrder->update(['supplier_id' => $supplier->id]);
            }
            if ($cart->purchaseInvoice) {
                $cart->purchaseInvoice->update(['supplier_id' => $supplier->id]);
            }
        }

        return $this->redirectAfterMutation($returnTo, $cart->business_date, $cart->id, 'Vendor updated successfully.');
    }

    public function submitCart(SubmitPurchaserCartRequest $request): RedirectResponse
    {
        $date = Carbon::parse($request->validated('business_date'));
        $user = $request->user();

        /** @var PurchaserCart $cart */
        $cart = PurchaserCart::query()
            ->whereKey($request->integer('cart_id'))
            ->where('user_id', $user->id)
            ->where('status', 'draft')
            ->with(['items.product', 'supplier'])
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return redirect()
                ->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])
                ->withErrors(['The selected cart is empty.']);
        }

        $supplier = $this->resolveSubmissionSupplier($request);
        $paymentMethod = $request->validated('payment_method');

        if (strcasecmp($paymentMethod, 'Credit') === 0 && ! $supplier->credit_approved) {
            return redirect()
                ->route('purchaser.bill', ['cart' => $cart, 'date' => $date->format('Y-m-d')])
                ->withErrors(['This vendor is not approved for credit. Change payment method or contact your purchase manager.'])
                ->withInput();
        }

        $cartItemsData = collect($request->input('items', []));
        foreach ($cart->items as $cartItem) {
            $itemInput = $cartItemsData->get((string) $cartItem->id, []);
            $unitPrice = (float) ($itemInput['unit_price'] ?? $cartItem->unit_price ?? 0);

            $cartItem->update([
                'unit_price' => $unitPrice,
                'line_total' => round((float) $cartItem->quantity * $unitPrice, 2),
            ]);
        }

        $cart->refresh()->load('items.product');

        DB::transaction(function () use ($request, $cart, $user, $date, $supplier, $paymentMethod): void {
            $subtotalAmount = 0.0;
            $discountAmount = (float) $request->input('discount_amount', 0);
            $paidAmountInput = (float) $request->input('paid_amount', 0);
            $regularLines = [];
            $addOnLines = [];

            foreach ($cart->items as $cartItem) {
                $unitPrice = (float) $cartItem->unit_price;
                $quantity = (float) $cartItem->quantity;
                $subtotalAmount += round($quantity * $unitPrice, 2);

                $remainingApproved = $this->remainingApprovedQuantityForProduct($date, (int) $cartItem->product_id, (int) $cart->id);
                $regularQuantity = min($quantity, $remainingApproved);
                $addOnQuantity = max(0, $quantity - $regularQuantity);

                if ($regularQuantity > 0) {
                    $regularLines[] = [
                        'cart_item' => $cartItem,
                        'quantity' => $regularQuantity,
                        'unit_price' => $unitPrice,
                    ];
                }

                if ($addOnQuantity > 0) {
                    $addOnLines[] = [
                        'cart_item' => $cartItem,
                        'quantity' => $addOnQuantity,
                        'unit_price' => $unitPrice,
                    ];
                }
            }

            $regularDocuments = $regularLines === []
                ? null
                : $this->createPurchaseDocumentsFromLines(
                    supplier: $supplier,
                    date: $date,
                    userId: (int) $user->id,
                    lines: $regularLines,
                    isExtra: false,
                    notes: $request->string('notes')->toString() ?: 'Generated from purchaser vendor cart.'
                );

            $addOnDocuments = $addOnLines === []
                ? null
                : $this->createPurchaseDocumentsFromLines(
                    supplier: $supplier,
                    date: $date,
                    userId: (int) $user->id,
                    lines: $addOnLines,
                    isExtra: true,
                    notes: trim(($request->string('notes')->toString() ?: '')."\nAdd-on quantity from purchaser vendor cart.")
                );

            $primaryDocuments = $regularDocuments ?? $addOnDocuments;
            $invoiceAmount = max(0, round($subtotalAmount - $discountAmount, 2));
            $paidAmount = min($invoiceAmount, round($paidAmountInput, 2));
            $paymentStatus = $this->resolvePaymentStatus($paymentMethod, $invoiceAmount, $paidAmount);

            $invoice = PurchaseInvoice::query()->create([
                'goods_received_id' => $primaryDocuments['grn']->id,
                'supplier_id' => $supplier->id,
                'purchaser_cart_id' => $cart->id,
                'invoice_number' => $request->validated('bill_number') ?: 'PENDING-BILL-'.$cart->cart_number,
                'amount' => $invoiceAmount,
                'discount_amount' => round($discountAmount, 2),
                'status' => InvoiceStatus::Pending,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => $request->validated('payment_note'),
                'payment_details' => $request->validated('payment_details'),
                'purchaser_submitted_by' => $user->id,
                'purchaser_submitted_at' => now(),
                'notes' => $request->validated('notes'),
            ]);

            $cart->update([
                'supplier_id' => $supplier->id,
                'bill_number' => $request->validated('bill_number'),
                'discount_amount' => round($discountAmount, 2),
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => $request->validated('payment_note'),
                'payment_details' => $request->validated('payment_details'),
                'notes' => $request->validated('notes'),
                'status' => 'submitted',
                'purchase_order_id' => $primaryDocuments['purchase_order']->id,
                'goods_received_id' => $primaryDocuments['grn']->id,
                'purchase_invoice_id' => $invoice->id,
                'submitted_at' => now(),
                'bill_received_at' => now(),
                'goods_received_at' => $paymentStatus === 'paid' ? now() : null,
                'payment_made_at' => $paymentStatus === 'paid' ? now() : null,
            ]);
        });

        return redirect()
            ->route('purchaser.history', ['date' => $date->format('Y-m-d')])
            ->with('success', 'Cart submitted successfully.');
    }

    public function updateOperationalStatus(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['submitted']);

        $validated = $request->validate([
            'flag' => ['required', 'string', 'in:goods_received'],
        ]);

        $column = match ($validated['flag']) {
            'goods_received' => 'goods_received_at',
        };

        $cart->update([
            $column => $cart->{$column} ? null : now(),
        ]);

        return redirect()
            ->route('purchaser.history', ['date' => $cart->business_date->format('Y-m-d')])
            ->with('success', 'Purchase status updated.');
    }

    public function updateInvoicePayment(Request $request, PurchaseInvoice $invoice): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $invoice = PurchaseInvoice::query()
            ->whereKey($invoice->id)
            ->whereHas('purchaserCart', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->with(['supplier', 'purchaserCart'])
            ->firstOrFail();

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:Cash,Online,GPay,Credit'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            'payment_details' => ['nullable', 'string', 'max:1000'],
        ]);

        $updatedInvoice = app(PurchaseInvoiceService::class)->updatePayment($invoice, [
            'payment_method' => $validated['payment_method'],
            'paid_amount' => (float) $validated['paid_amount'],
            'payment_note' => $validated['payment_note'] ?? null,
            'payment_details' => $validated['payment_details'] ?? null,
        ]);

        $remainingBalance = max(0, round((float) $updatedInvoice->amount - (float) $updatedInvoice->paid_amount, 2));
        $message = $remainingBalance > 0 || $updatedInvoice->payment_method === 'Credit'
            ? 'Payment updated. Remaining balance or credit is still pending.'
            : 'Payment completed successfully.';

        return redirect()
            ->route('purchaser.finance', ['date' => $updatedInvoice->purchaserCart?->business_date?->format('Y-m-d') ?? now()->format('Y-m-d')])
            ->with('success', $message);
    }

    public function storeCorrectionRequest(StorePurchaserCorrectionRequest $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $shopOrderItem = ShopOrderItem::query()
            ->with('order')
            ->findOrFail($request->integer('shop_order_item_id'));

        PurchaserCorrectionRequest::query()->create([
            'business_date' => $request->validated('business_date'),
            'shop_order_item_id' => $shopOrderItem->id,
            'current_approved_qty' => (float) $shopOrderItem->approved_qty,
            'proposed_corrected_qty' => (float) $request->validated('proposed_corrected_qty'),
            'purchaser_note' => $request->validated('purchaser_note'),
            'requester_user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('purchaser.daily', ['date' => Carbon::parse($request->validated('business_date'))->format('Y-m-d')])
            ->with('success', 'Correction request sent to purchase manager.');
    }

    public function approveCorrectionRequest(Request $request, PurchaserCorrectionRequest $correctionRequest): RedirectResponse
    {
        $this->ensurePurchaseManager($request);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $correctionRequest, $validated): void {
            $shopOrderItem = $correctionRequest->shopOrderItem()->lockForUpdate()->firstOrFail();

            $shopOrderItem->update([
                'approved_qty' => $correctionRequest->proposed_corrected_qty,
                'notes' => trim(implode("\n", array_filter([
                    $shopOrderItem->notes,
                    'Purchaser correction approved: '.($validated['review_note'] ?? 'No note'),
                ]))),
            ]);

            $correctionRequest->update([
                'status' => 'approved',
                'review_note' => $validated['review_note'] ?? null,
                'reviewer_user_id' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Correction request approved and approved qty updated.');
    }

    public function rejectCorrectionRequest(Request $request, PurchaserCorrectionRequest $correctionRequest): RedirectResponse
    {
        $this->ensurePurchaseManager($request);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $correctionRequest->update([
            'status' => 'rejected',
            'review_note' => $validated['review_note'] ?? null,
            'reviewer_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Correction request rejected.');
    }

    private function draftCartsForDate(int $userId, Carbon $date): Collection
    {
        return PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->get();
    }

    private function findReusableDraftCart(int $userId, Carbon $date, ?int $supplierId, ?int $exceptCartId = null): ?PurchaserCart
    {
        return PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->when(
                $supplierId !== null,
                fn ($query) => $query->where('supplier_id', $supplierId),
                fn ($query) => $query->whereNull('supplier_id'),
            )
            ->when($exceptCartId !== null, fn ($query) => $query->whereKeyNot($exceptCartId))
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->first();
    }

    private function assignSupplierToCart(PurchaserCart $cart, Supplier $supplier): PurchaserCart
    {
        if ($cart->status !== 'draft') {
            return $cart;
        }

        $targetCart = $this->findReusableDraftCart(
            userId: (int) $cart->user_id,
            date: $cart->business_date,
            supplierId: (int) $supplier->id,
            exceptCartId: (int) $cart->id,
        );

        if (! $targetCart instanceof PurchaserCart) {
            return $cart;
        }

        $targetCart->update(['supplier_id' => $supplier->id]);

        return $this->mergeDraftCartIntoTarget($cart, $targetCart);
    }

    private function mergeDraftCartIntoTarget(PurchaserCart $sourceCart, PurchaserCart $targetCart): PurchaserCart
    {
        if ($sourceCart->is($targetCart)) {
            return $targetCart;
        }

        return DB::transaction(function () use ($sourceCart, $targetCart): PurchaserCart {
            $sourceCart->loadMissing('items.product');
            $targetCart->loadMissing('items.product');

            foreach ($sourceCart->items as $sourceItem) {
                $targetItem = $targetCart->items()->where('product_id', $sourceItem->product_id)->first();
                $mergedQuantity = $sourceItem->quantity + (float) ($targetItem?->quantity ?? 0);
                $remainingApproved = $this->remainingApprovedQuantityForProduct($sourceCart->business_date, (int) $sourceItem->product_id, (int) $targetCart->id);

                if ($targetItem instanceof PurchaserCartItem) {
                    $unitPrice = (float) ($sourceItem->unit_price > 0 ? $sourceItem->unit_price : $targetItem->unit_price);

                    $targetItem->update([
                        'quantity' => $mergedQuantity,
                        'unit_price' => $unitPrice,
                        'line_total' => round($mergedQuantity * $unitPrice, 2),
                        'is_extra_purchase' => $mergedQuantity > $remainingApproved,
                        'notes' => $targetItem->notes ?: $sourceItem->notes,
                    ]);

                    $sourceItem->delete();

                    continue;
                }

                $sourceItem->update([
                    'purchaser_cart_id' => $targetCart->id,
                    'quantity' => $mergedQuantity,
                    'line_total' => round($mergedQuantity * (float) $sourceItem->unit_price, 2),
                    'is_extra_purchase' => $mergedQuantity > $remainingApproved,
                ]);
            }

            $targetCart->touch();
            $sourceCart->delete();

            return $targetCart->fresh(['supplier', 'items.product.category', 'goodsReceived']);
        });
    }

    private function mergeGroupDraftCarts(PurchaserCart $cart): Collection
    {
        return PurchaserCart::query()
            ->where('user_id', $cart->user_id)
            ->whereDate('business_date', $cart->business_date)
            ->where('status', 'draft')
            ->when(
                $cart->supplier_id !== null,
                fn ($query) => $query->where('supplier_id', $cart->supplier_id),
                fn ($query) => $query->whereNull('supplier_id'),
            )
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->get();
    }

    private function buildDraftMergeSuggestions(Collection $draftOrders): Collection
    {
        return $draftOrders
            ->groupBy(fn (PurchaserCart $cart): string => $cart->supplier_id !== null ? 'supplier:'.$cart->supplier_id : 'pending')
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(function (Collection $group): array {
                /** @var PurchaserCart $targetCart */
                $targetCart = $group->sortByDesc('updated_at')->first();

                return [
                    'target_cart' => $targetCart,
                    'count' => $group->count(),
                    'label' => $targetCart->supplier?->name ?: 'draft carts',
                ];
            })
            ->values();
    }

    private function ownedCart(Request $request, PurchaserCart $cart, array $statuses): PurchaserCart
    {
        return PurchaserCart::query()
            ->whereKey($cart->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', $statuses)
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->firstOrFail();
    }

    private function redirectAfterMutation(string $returnTo, Carbon $date, int $cartId, string $message): RedirectResponse
    {
        return match ($returnTo) {
            'bill' => redirect()->route('purchaser.bill', ['cart' => $cartId, 'date' => $date->format('Y-m-d')])->with('success', $message),
            'cart' => redirect()->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])->with('success', $message),
            'vendors' => redirect()->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])->with('success', $message),
            default => redirect()->route('purchaser.daily', array_filter([
                'date' => $date->format('Y-m-d'),
                'chip' => request()->input('chip'),
                'search' => request()->input('search'),
            ]))->with('success', $message),
        };
    }

    private function buildDailySummary(Carbon $date, array $frequentProductIds): Collection
    {
        $approvedItems = ShopOrderItem::query()
            ->whereHas('order', function ($query) use ($date): void {
                $query->whereDate('business_date', '<=', $date)->where('state', 'approved');
            })
            ->with(['product.category', 'order.shop', 'order'])
            ->get();

        $draftCartItems = PurchaserCartItem::query()
            ->whereHas('cart', function ($query) use ($date): void {
                $query->whereDate('business_date', '<=', $date)->where('status', 'draft');
            })
            ->with('cart.user')
            ->get()
            ->groupBy(fn ($item) => $item->product_id.'_'.$item->cart->business_date->timezone(config('app.timezone'))->format('Y-m-d'));

        $submittedQuantities = PurchaserCartItem::query()
            ->whereHas('cart', function ($query) use ($date): void {
                $query->whereDate('business_date', '<=', $date)->where('status', 'submitted');
            })
            ->with('cart')
            ->get()
            ->groupBy(fn ($item) => $item->product_id.'_'.$item->cart->business_date->timezone(config('app.timezone'))->format('Y-m-d'))
            ->map(fn ($group) => (float) $group->sum('quantity'));

        return $approvedItems
            ->groupBy(fn (ShopOrderItem $item) => $item->product_id.'_'.$item->order->business_date->timezone(config('app.timezone'))->format('Y-m-d'))
            ->map(function (Collection $items, string $key) use ($draftCartItems, $submittedQuantities, $frequentProductIds, $date): ?array {
                [$productId, $itemDateStr] = explode('_', $key);
                $itemDate = Carbon::parse($itemDateStr);

                /** @var ShopOrderItem $firstItem */
                $firstItem = $items->first();
                $product = $firstItem->product;

                $productDraftItems = $draftCartItems->get($key) ?? collect();
                $draftQty = (float) $productDraftItems->sum('quantity');
                $draftPurchasers = $productDraftItems
                    ->groupBy('cart.user_id')
                    ->map(function ($itemsByPurchaser) use ($product) {
                        $user = $itemsByPurchaser->first()->cart->user;
                        $purchaserQty = (float) $itemsByPurchaser->sum('quantity');
                        $formattedQty = $product->unit === 'kg' ? number_format($purchaserQty, 1) : number_format($purchaserQty, 0);

                        return $user ? "{$user->name} ({$formattedQty} {$product->unit})" : null;
                    })
                    ->filter()
                    ->values()
                    ->all();

                $boughtQty = (float) ($submittedQuantities->get($key) ?? 0);
                $totalApprovedQty = (float) $items->sum('approved_qty');
                $remainingQty = max(0, $totalApprovedQty - $boughtQty);

                if ($itemDate->lt($date) && $remainingQty <= 0) {
                    return null;
                }

                $categoryName = (string) ($product->category?->name ?? '');

                $quantityBuckets = $items
                    ->groupBy(fn (ShopOrderItem $item): string => $this->normalizeBucketKey((float) $item->approved_qty))
                    ->map(function (Collection $bucketItems, string $bucketKey) use ($firstItem): array {
                        $bucketQuantity = (float) $bucketItems->first()->approved_qty;

                        return [
                            'quantity' => $bucketQuantity,
                            'formatted' => $this->formatBucketLabel($bucketQuantity, $firstItem->unit),
                            'count' => $bucketItems->count(),
                        ];
                    })
                    ->sortBy('quantity')
                    ->values()
                    ->all();

                return [
                    'product_id' => (int) $productId,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                    'category_name' => $categoryName,
                    'is_frequent' => in_array((int) $productId, $frequentProductIds, true),
                    'total_approved_qty' => $totalApprovedQty,
                    'bought_qty' => $boughtQty,
                    'draft_qty' => $draftQty,
                    'draft_purchasers' => $draftPurchasers,
                    'remaining_qty' => $remainingQty,
                    'quantity_buckets' => $quantityBuckets,
                    'order_date' => $itemDate,
                    'shop_details' => $items->map(fn (ShopOrderItem $item): array => [
                        'shop_order_item_id' => $item->id,
                        'shop_name' => $item->order->shop->name,
                        'approved_qty' => (float) $item->approved_qty,
                        'unit' => $item->unit,
                        'order_number' => $item->order->order_number,
                        'notes' => $item->notes,
                    ])->sortBy('shop_name')->values()->all(),
                    'search_index' => strtolower(implode(' ', [
                        $product->name,
                        $product->sku,
                        $categoryName,
                    ])),
                ];
            })
            ->filter()
            ->sortBy(fn ($item) => $item['product_name'].'_'.$item['order_date']->format('Y-m-d'))
            ->values();
    }

    private function filterProductsForChip(Collection $items, string $selectedChip, string $search, array $frequentProductIds): Collection
    {
        return $items->filter(function ($item) use ($selectedChip, $search, $frequentProductIds): bool {
            $categoryName = is_array($item)
                ? (string) ($item['category_name'] ?? '')
                : (string) ($item->category?->name ?? '');
            $productId = is_array($item) ? (int) ($item['product_id'] ?? 0) : (int) $item->id;
            $searchIndex = is_array($item)
                ? (string) ($item['search_index'] ?? '')
                : strtolower(implode(' ', [$item->name, $item->sku, $categoryName]));

            $matchesChip = match ($selectedChip) {
                'Frequent' => in_array($productId, $frequentProductIds, true),
                'All' => true,
                default => $categoryName === $selectedChip,
            };

            if (! $matchesChip) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            return str_contains($searchIndex, strtolower($search));
        })->values();
    }

    private function frequentProductIds(int $userId): array
    {
        $cartItems = PurchaserCartItem::query()
            ->selectRaw('product_id, COUNT(*) as usage_count')
            ->whereHas('cart', function ($query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->whereDate('business_date', '>=', now()->subDays(14)->toDateString());
            })
            ->groupBy('product_id')
            ->orderByDesc('usage_count')
            ->limit(12)
            ->pluck('product_id')
            ->map(fn ($productId): int => (int) $productId)
            ->all();

        if ($cartItems !== []) {
            return $cartItems;
        }

        return Product::query()
            ->whereHas('category', function ($query): void {
                $query->whereIn('name', ['Supply', 'VEG']);
            })
            ->orderBy('name')
            ->limit(12)
            ->pluck('id')
            ->map(fn ($productId): int => (int) $productId)
            ->all();
    }

    private function resolveSubmissionSupplier(Request $request): Supplier
    {
        $supplierId = $request->integer('supplier_id');

        if ($supplierId > 0) {
            return Supplier::query()->findOrFail($supplierId);
        }

        return Supplier::query()->create([
            'name' => $request->string('vendor_name')->toString(),
            'type' => $request->string('vendor_type')->toString() ?: 'Vendor',
            'category' => 'market',
            'is_default_purchase' => false,
            'contact' => (string) $request->input('vendor_mobile_number', ''),
            'location' => $request->input('vendor_location'),
            'mobile_number' => $request->input('vendor_mobile_number'),
            'payment_terms' => $request->input('payment_terms', 'Cash'),
            'preferred_payment_method' => $request->input('preferred_payment_method', $request->string('payment_method')->toString() ?: 'Cash'),
            'credit_approved' => false,
            'credit_terms' => null,
            'quality_score' => 100,
        ]);
    }

    /**
     * @param  array<int, array{cart_item: PurchaserCartItem, quantity: float, unit_price: float}>  $lines
     * @return array{purchase_order: PurchaseOrder, grn: GoodsReceived}
     */
    private function createPurchaseDocumentsFromLines(Supplier $supplier, Carbon $date, int $userId, array $lines, bool $isExtra, string $notes): array
    {
        $purchaseOrder = PurchaseOrder::query()->create([
            'supplier_id' => $supplier->id,
            'po_number' => $this->generatePurchaseOrderNumber($date),
            'status' => POStatus::Received,
            'fulfillment_type' => 'warehouse',
            'order_date' => $date,
            'created_by' => $userId,
            'notes' => $notes,
        ]);

        $grn = GoodsReceived::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'grn_number' => $this->generateGrnNumber($date),
            'status' => 'pending_approval',
            'received_by' => $userId,
            'received_at' => $date,
            'notes' => $notes,
            'is_extra' => $isExtra,
        ]);

        foreach ($lines as $line) {
            $cartItem = $line['cart_item'];
            $quantity = $line['quantity'];

            $purchaseOrderItem = $purchaseOrder->items()->create([
                'product_id' => $cartItem->product_id,
                'purchase_unit' => $cartItem->product->unit,
                'quantity' => $quantity,
                'unit_price' => $line['unit_price'],
                'price_basis' => $cartItem->product->unit === 'kg' ? 'per_kg' : 'per_unit',
            ]);

            $grn->items()->create([
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'product_id' => $cartItem->product_id,
                'received_qty' => $quantity,
                'variance' => 0,
            ]);
        }

        return [
            'purchase_order' => $purchaseOrder,
            'grn' => $grn,
        ];
    }

    private function remainingApprovedQuantityForProduct(Carbon $date, int $productId, int $currentCartId): float
    {
        $approvedQuantity = (float) ShopOrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', function ($query) use ($date): void {
                $query->whereDate('business_date', '<=', $date)->where('state', 'approved');
            })
            ->sum('approved_qty');

        $alreadySubmittedQuantity = (float) PurchaserCartItem::query()
            ->where('product_id', $productId)
            ->whereHas('cart', function ($query) use ($date, $currentCartId): void {
                $query->whereDate('business_date', '<=', $date)
                    ->where('status', 'submitted')
                    ->whereKeyNot($currentCartId);
            })
            ->sum('quantity');

        return max(0, $approvedQuantity - $alreadySubmittedQuantity);
    }

    private function resolveBusinessDate(Request $request): Carbon|RedirectResponse
    {
        $date = Carbon::parse($request->input('date', Carbon::today()->format('Y-m-d')));

        if ($date->isFuture()) {
            return redirect()
                ->route('purchaser.daily', [
                    'date' => Carbon::today()->format('Y-m-d'),
                    'chip' => $request->input('chip'),
                    'search' => $request->input('search'),
                ])
                ->with('error', 'Future purchase dates are not allowed. Showing today instead.');
        }

        return $date;
    }

    private function resolveQuickFilter(string $selectedChip): string
    {
        return in_array($selectedChip, self::QUICK_FILTERS, true) ? $selectedChip : 'Frequent';
    }

    private function resolveDailyShareMode(string $shareMode): string
    {
        return in_array($shareMode, ['all', 'tag', 'product'], true) ? $shareMode : 'all';
    }

    /**
     * @param  array<int, string>  $selectedTags
     * @param  array<int, int>  $selectedProductIds
     */
    private function filterDailySummaryForShare(
        Collection $dailySummary,
        string $shareMode,
        array $selectedTags,
        array $selectedProductIds,
        int $selectedProductId,
    ): Collection {
        return match ($shareMode) {
            'tag' => $dailySummary
                ->filter(fn (array $summary): bool => in_array((int) $summary['product_id'], $selectedProductIds, true))
                ->values(),
            'product' => $dailySummary
                ->filter(fn (array $summary): bool => (int) $summary['product_id'] === $selectedProductId)
                ->values(),
            default => $dailySummary->values(),
        };
    }

    private function buildDailySummaryShareText(Collection $dailySummary, Carbon $date): string
    {
        $lines = [
            '*Daily Purchase Summary*',
            $date->format('d M Y'),
            '---',
            '',
        ];

        foreach ($dailySummary as $summary) {
            $productHeader = '*'.$summary['product_name'].'*';
            $orderDate = $summary['order_date'];
            if ($orderDate->format('Y-m-d') !== $date->format('Y-m-d')) {
                $productHeader .= ' (Pending '.$orderDate->format('d M Y').')';
            }
            $lines[] = $productHeader;

            foreach ($summary['quantity_buckets'] as $bucket) {
                $lines[] = $bucket['formatted'].' x '.$bucket['count'];
            }

            $lines[] = 'Total '.$this->formatShareQuantity((float) $summary['total_approved_qty'], $summary['unit']);
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    private function buildCartShareText(PurchaserCart $cart, bool $includePrice): string
    {
        $lines = [
            'Green Leaf Traders - Purchase Order',
            'Date: '.$cart->business_date->format('d/m/Y').' | '.$cart->cart_number,
            '---',
        ];

        foreach ($cart->items as $item) {
            $line = str_pad($item->product->name, 14).' '.$this->formatShareQuantity((float) $item->quantity, $item->product->unit);

            if ($includePrice) {
                $line .= ' @ '.number_format((float) $item->unit_price, 2);
            }

            $lines[] = $line;
        }

        $lines[] = '---';
        $lines[] = 'Please pack and confirm.';

        return implode("\n", $lines);
    }

    private function buildSupplierWhatsAppUrl(Supplier $supplier, string $message): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $supplier->mobile_number);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return 'https://api.whatsapp.com/send?phone='.$digits.'&text='.rawurlencode($message);
    }

    private function resolvePaymentStatus(string $paymentMethod, float $invoiceAmount, float $paidAmount): string
    {
        if (strcasecmp($paymentMethod, 'Credit') === 0) {
            return 'credit_pending_approval';
        }

        if ($paidAmount <= 0) {
            return 'unpaid';
        }

        if ($paidAmount < $invoiceAmount) {
            return 'partial';
        }

        return 'paid';
    }

    private function generatePurchaseOrderNumber(Carbon $date): string
    {
        do {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $number = 'PO-PURCH-'.$date->format('Ymd').'-'.$suffix;
        } while (PurchaseOrder::query()->where('po_number', $number)->exists());

        return $number;
    }

    private function generateGrnNumber(Carbon $date): string
    {
        do {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $number = 'GRN-PURCH-'.$date->format('Ymd').'-'.$suffix;
        } while (GoodsReceived::query()->where('grn_number', $number)->exists());

        return $number;
    }

    private function normalizeBucketKey(float $quantity): string
    {
        return number_format($quantity, 3, '.', '');
    }

    private function formatBucketLabel(float $quantity, string $unit): string
    {
        $value = $this->trimTrailingZeros($quantity);

        return $unit === 'kg' ? $value.'kg' : $value;
    }

    private function formatShareQuantity(float $quantity, string $unit): string
    {
        return $this->trimTrailingZeros($quantity).' '.$unit;
    }

    private function trimTrailingZeros(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function ensurePurchaser(Request $request): void
    {
        if (! $request->user()->hasRole('purchaser')) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function ensurePurchaseManager(Request $request): void
    {
        if (
            ! $request->user()->hasRole('purchase')
            && ! $request->user()->hasRole('admin')
            && ! $request->user()->can('purchasing.order.approve')
        ) {
            abort(403, 'Unauthorized access.');
        }
    }
}
