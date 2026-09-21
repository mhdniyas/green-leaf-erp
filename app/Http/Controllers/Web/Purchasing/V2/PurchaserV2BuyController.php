<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing\V2;

use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\ShopOrderItem;
use App\Queries\Purchasing\V2\PurchaserV2BuyQuery;
use App\Services\Purchasing\PurchaseGradePriceResolver;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\PurchaserReadCacheService;
use App\Support\Purchasing\V2\PurchaserV2Telemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PurchaserV2BuyController extends PurchaserV2BaseController
{
    public function __construct(
        PurchaserBusinessDayService $businessDayService,
        private readonly PurchaserV2BuyQuery $buyQuery,
        private readonly PurchaseGradePriceResolver $purchaseGradePriceResolver,
        private readonly PurchaserReadCacheService $readCacheService,
    ) {
        parent::__construct($businessDayService);
    }

    /**
     * Display the Purchaser V2 Buy workspace.
     */
    public function index(Request $request): Response
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $date = $this->resolveBusinessDate($request);
        $grade = $this->resolveGrade($request);

        // Collect preselected product IDs from URL parameters
        $selectedProductIds = [];
        if ($request->filled('product_id')) {
            $selectedProductIds[] = $request->integer('product_id');
        }
        if ($request->filled('product_ids') && is_array($request->input('product_ids'))) {
            foreach ($request->input('product_ids') as $id) {
                $intId = (int) $id;
                if ($intId > 0) {
                    $selectedProductIds[] = $intId;
                }
            }
        }
        $selectedProductIds = array_values(array_unique($selectedProductIds));

        // Load structured data for selected products using bounded grouped query
        $selectedProducts = $this->buyQuery->getSelectedProducts(
            productIds: $selectedProductIds,
            date: $date,
            grade: $grade,
            user: $user,
        );

        // Load first page of pending intended products (for checkbox multi-selection)
        $pendingIntendedProducts = $this->buyQuery->getPendingIntendedProducts(
            date: $date,
            grade: $grade,
            user: $user,
            limit: 30,
        );

        // Active draft carts count
        $draftCartsCount = $this->buyQuery->getDraftCartsCount(
            user: $user,
            date: $date,
            grade: $grade,
        );

        $view = view('purchasing.purchaser-v2.buy', [
            'date' => $date->toDateString(),
            'grade' => $grade,
            'selectedProducts' => $selectedProducts,
            'pendingIntendedProducts' => $pendingIntendedProducts,
            'draftCartsCount' => $draftCartsCount,
            'preselectedIds' => $selectedProductIds,
            'user' => $user,
        ]);

        $response = response($view);

        return $this->attachTelemetry($response, $telemetry, $user);
    }

    /**
     * AJAX endpoint to fetch full product details/units for selected or search-added products.
     */
    public function productDetails(Request $request): JsonResponse
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $date = $this->resolveBusinessDate($request);
        $grade = $this->resolveGrade($request);

        $productIds = [];
        if ($request->filled('product_id')) {
            $productIds[] = $request->integer('product_id');
        }
        if ($request->filled('product_ids') && is_array($request->input('product_ids'))) {
            foreach ($request->input('product_ids') as $id) {
                $intId = (int) $id;
                if ($intId > 0) {
                    $productIds[] = $intId;
                }
            }
        }

        $items = $this->buyQuery->getSelectedProducts(
            productIds: $productIds,
            date: $date,
            grade: $grade,
            user: $user,
        );

        $data = [
            'status' => 'success',
            'data' => $items,
        ];

        return $this->attachTelemetry(response()->json($data), $telemetry, $user);
    }

    /**
     * Store items into a draft cart (reproducing legacy bulkStoreCart with O(1) read slope).
     */
    public function storeCart(Request $request): RedirectResponse
    {
        $user = $this->ensurePurchaser($request);

        $validated = $request->validate([
            'business_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->businessDayService->isSelectableDate((string) $value)) {
                        $fail('The selected business date is not available for purchaser flow.');
                    }
                },
            ],
            'purchase_grade' => ['sometimes', 'string', 'in:A,B'],
            'cart_id' => ['nullable', 'integer'],
            'submission_key' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.conversion_to_base' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $date = Carbon::parse($validated['business_date'])->startOfDay();
        $purchaseGrade = (string) ($validated['purchase_grade'] ?? 'A');
        $cartId = filled($validated['cart_id'] ?? null) ? (int) $validated['cart_id'] : null;
        $submissionKey = trim((string) ($validated['submission_key'] ?? ''));

        // Idempotency check via session key
        $sessionKey = $submissionKey !== '' ? 'purchaser_v2.cart_store.'.$user->id.'.'.$submissionKey : '';
        if ($sessionKey !== '' && $request->session()->has($sessionKey)) {
            return redirect()
                ->route('purchaser-v2.cart.index', ['date' => $date->toDateString(), 'grade' => $purchaseGrade])
                ->with('info', 'These items were already added to your cart.');
        }

        $itemsInput = collect($validated['items'])
            ->filter(fn (array $item): bool => (float) ($item['quantity'] ?? 0) > 0)
            ->values();

        if ($itemsInput->isEmpty()) {
            return back()->withInput()->with('error', 'Please enter a valid purchase quantity for at least one product.');
        }

        // Validate positive unit price for Grade A
        if ($purchaseGrade === 'A') {
            foreach ($itemsInput as $idx => $item) {
                if ((float) ($item['unit_price'] ?? 0) <= 0) {
                    return back()->withInput()->with('error', 'Enter a unit price greater than zero for all Grade A items.');
                }
            }
        }

        $productIds = $itemsInput->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();

        // ── Bounded O(1) Pre-fetches for approved & submitted demand ───────
        $approvedMap = ShopOrderItem::query()
            ->whereIn('product_id', $productIds)
            ->where('product_grade', $purchaseGrade)
            ->whereHas('order', function ($query) use ($date): void {
                $query->whereDate('business_date', $date)->where('state', 'approved');
            })
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(approved_qty) as total_approved'))
            ->pluck('total_approved', 'product_id')
            ->map(fn ($qty): float => (float) $qty)
            ->all();

        $submittedMap = PurchaserCartItem::query()
            ->whereIn('product_id', $productIds)
            ->where('grade', $purchaseGrade)
            ->whereHas('cart', function ($query) use ($date, $purchaseGrade, $cartId): void {
                $query->whereDate('business_date', $date)
                    ->where('status', 'submitted')
                    ->where('purchase_grade', $purchaseGrade)
                    ->when($cartId !== null, fn ($q) => $q->whereKeyNot($cartId));
            })
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(quantity) as total_submitted'))
            ->pluck('total_submitted', 'product_id')
            ->map(fn ($qty): float => (float) $qty)
            ->all();

        $addedCount = 0;
        $hasRegularPurchase = false;
        $hasExtraPurchase = false;

        DB::transaction(function () use (
            $user,
            $date,
            $purchaseGrade,
            $cartId,
            $itemsInput,
            $approvedMap,
            $submittedMap,
            &$addedCount,
            &$hasRegularPurchase,
            &$hasExtraPurchase
        ): void {
            // Find existing draft cart or create new one
            $cart = $cartId
                ? PurchaserCart::query()
                    ->whereKey($cartId)
                    ->where('user_id', $user->id)
                    ->whereDate('business_date', $date)
                    ->where('status', 'draft')
                    ->where('purchase_grade', $purchaseGrade)
                    ->firstOrFail()
                : PurchaserCart::query()
                    ->where('user_id', $user->id)
                    ->whereDate('business_date', $date)
                    ->whereNull('supplier_id')
                    ->where('status', 'draft')
                    ->where('purchase_grade', $purchaseGrade)
                    ->first();

            if (! $cart) {
                $cart = PurchaserCart::query()->create([
                    'user_id' => $user->id,
                    'business_date' => $date,
                    'cart_number' => PurchaserCart::generateCartNumber($date),
                    'status' => 'draft',
                    'purchase_grade' => $purchaseGrade,
                    'destination_shop_id' => null,
                    'purchase_source' => $purchaseGrade === 'B' ? 'green_leaf_direct_purchase' : 'shop_order',
                ]);
            }

            foreach ($itemsInput as $itemData) {
                $productId = (int) $itemData['product_id'];
                $quantityInput = (float) $itemData['quantity'];
                $conversionToBase = (float) ($itemData['conversion_to_base'] ?? 1.0);
                if ($conversionToBase <= 0) {
                    $conversionToBase = 1.0;
                }

                // Base unit quantity
                $baseQuantity = round($quantityInput * $conversionToBase, 2);

                $unitPrice = $this->purchaseGradePriceResolver->resolve(
                    productId: $productId,
                    businessDate: $date->toDateString(),
                    grade: $purchaseGrade,
                    gradeAFallback: (float) $itemData['unit_price']
                );

                $approvedQty = $approvedMap[$productId] ?? 0.0;
                $submittedQty = $submittedMap[$productId] ?? 0.0;
                $remainingApproved = max(0.0, round($approvedQty - $submittedQty, 2));

                $existingItem = $cart->items()
                    ->where('product_id', $productId)
                    ->where('grade', $purchaseGrade)
                    ->first();

                $newQuantity = $existingItem instanceof PurchaserCartItem
                    ? (float) $existingItem->quantity + $baseQuantity
                    : $baseQuantity;

                $isExtraPurchase = $newQuantity > $remainingApproved;
                $hasExtraPurchase = $hasExtraPurchase || $isExtraPurchase;
                $hasRegularPurchase = $hasRegularPurchase || ! $isExtraPurchase;

                if ($existingItem instanceof PurchaserCartItem) {
                    $existingItem->update([
                        'quantity' => $newQuantity,
                        'unit_price' => $unitPrice,
                        'line_total' => round($newQuantity * $unitPrice, 2),
                        'is_extra_purchase' => $isExtraPurchase,
                    ]);
                } else {
                    $cart->items()->create([
                        'product_id' => $productId,
                        'grade' => $purchaseGrade,
                        'quantity' => $baseQuantity,
                        'unit_price' => $unitPrice,
                        'line_total' => round($baseQuantity * $unitPrice, 2),
                        'is_extra_purchase' => $isExtraPurchase,
                    ]);
                }

                $addedCount++;
            }

            if ($purchaseGrade === 'B') {
                $cart->update([
                    'purchase_source' => $hasRegularPurchase
                        ? ($hasExtraPurchase ? 'mixed' : 'shop_order')
                        : 'green_leaf_direct_purchase',
                ]);
            }

            $this->readCacheService->invalidate(['carts']);
        });

        if ($sessionKey !== '') {
            $request->session()->put($sessionKey, true);
        }

        $label = $addedCount === 1 ? 'product' : 'products';

        return redirect()
            ->route('purchaser-v2.cart.index', ['date' => $date->toDateString(), 'grade' => $purchaseGrade])
            ->with('success', "{$addedCount} {$label} added to draft cart.");
    }

    /**
     * Minimal safe placeholder for Purchaser V2 Cart Hub (Phase C).
     */
    public function cartStub(Request $request): Response
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $date = $this->resolveBusinessDate($request);
        $grade = $this->resolveGrade($request);

        $draftCarts = PurchaserCart::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->where('purchase_grade', $grade)
            ->with(['items.product.category'])
            ->orderByDesc('id')
            ->get();

        $view = view('purchasing.purchaser-v2.cart_stub', [
            'date' => $date->toDateString(),
            'grade' => $grade,
            'draftCarts' => $draftCarts,
            'user' => $user,
        ]);

        $response = response($view);

        return $this->attachTelemetry($response, $telemetry, $user);
    }
}
