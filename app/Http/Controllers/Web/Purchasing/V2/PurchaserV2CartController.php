<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing\V2;

use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Queries\Purchasing\V2\PurchaserV2CartQuery;
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

class PurchaserV2CartController extends PurchaserV2BaseController
{
    public function __construct(
        PurchaserBusinessDayService $businessDayService,
        private readonly PurchaserV2CartQuery $cartQuery,
        private readonly PurchaseGradePriceResolver $purchaseGradePriceResolver,
        private readonly PurchaserReadCacheService $readCacheService,
    ) {
        parent::__construct($businessDayService);
    }

    /**
     * Display the Purchaser V2 Draft Cart Hub.
     */
    public function index(Request $request): Response
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $date = $this->resolveBusinessDate($request);
        $grade = $this->resolveGrade($request);

        $draftCarts = $this->cartQuery->getDraftCarts(
            user: $user,
            date: $date,
            grade: $grade,
            limit: 25,
        );

        $view = view('purchasing.purchaser-v2.cart', [
            'date' => $date->toDateString(),
            'grade' => $grade,
            'draftCarts' => $draftCarts,
            'user' => $user,
        ]);

        $response = response($view);

        return $this->attachTelemetry($response, $telemetry, $user);
    }

    /**
     * On-demand JSON endpoint returning items for a specific draft cart.
     */
    public function items(Request $request, PurchaserCart $cart): JsonResponse
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $this->authorizeCart($cart, $user);

        $items = $this->cartQuery->getCartItems($cart, $user);

        $data = [
            'status' => 'success',
            'cart_id' => $cart->id,
            'cart_number' => $cart->cart_number,
            'supplier_id' => $cart->supplier_id,
            'supplier_name' => $cart->supplier?->name,
            'items' => $items,
        ];

        return $this->attachTelemetry(response()->json($data), $telemetry, $user);
    }

    /**
     * Update multiple items in a draft cart with grouped quantity validation.
     */
    public function updateItems(Request $request, PurchaserCart $cart): JsonResponse|RedirectResponse
    {
        $user = $this->ensurePurchaser($request);
        $this->authorizeCart($cart, $user);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $itemsData = collect($validated['items'])->keyBy(fn ($row) => (int) $row['id']);
        $cartItems = $cart->items()->whereIn('id', $itemsData->keys())->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'No matching cart items found.'], 422);
        }

        $date = $cart->business_date ?? Carbon::today();
        $grade = (string) ($cart->purchase_grade ?? 'A');
        $productIds = $cartItems->pluck('product_id')->unique()->all();

        // ── Bounded O(1) Pre-fetches for approved & submitted demand ───────
        $approvedMap = ShopOrderItem::query()
            ->whereIn('product_id', $productIds)
            ->where('product_grade', $grade)
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
            ->where('grade', $grade)
            ->whereHas('cart', function ($query) use ($date, $grade, $cart): void {
                $query->whereDate('business_date', $date)
                    ->where('status', 'submitted')
                    ->where('purchase_grade', $grade)
                    ->whereKeyNot($cart->id);
            })
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(quantity) as total_submitted'))
            ->pluck('total_submitted', 'product_id')
            ->map(fn ($qty): float => (float) $qty)
            ->all();

        DB::transaction(function () use ($cartItems, $itemsData, $approvedMap, $submittedMap, $cart): void {
            foreach ($cartItems as $item) {
                $input = $itemsData->get($item->id);
                if (! $input) {
                    continue;
                }

                $productId = (int) $item->product_id;
                $quantity = (float) $input['quantity'];
                $unitPrice = (float) $input['unit_price'];
                $approved = $approvedMap[$productId] ?? 0.0;
                $submitted = $submittedMap[$productId] ?? 0.0;
                $remaining = max(0.0, round($approved - $submitted, 2));

                $item->update([
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                    'is_extra_purchase' => $quantity > $remaining,
                    'notes' => $input['notes'] ?? null,
                ]);
            }

            $cart->touch();
            $this->readCacheService->invalidate(['carts']);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Cart items updated successfully.',
                'items' => $this->cartQuery->getCartItems($cart, $user),
            ]);
        }

        return redirect()->back()->with('success', 'Cart items updated successfully.');
    }

    /**
     * Remove a single item from a draft cart.
     */
    public function destroyItem(Request $request, PurchaserCart $cart, PurchaserCartItem $item): JsonResponse|RedirectResponse
    {
        $user = $this->ensurePurchaser($request);
        $this->authorizeCart($cart, $user);

        if ($item->purchaser_cart_id !== $cart->id) {
            abort(404, 'Cart item does not belong to this cart.');
        }

        $cartDeleted = false;

        DB::transaction(function () use ($cart, $item, &$cartDeleted): void {
            $item->delete();

            if ($cart->items()->count() === 0) {
                $cart->delete();
                $cartDeleted = true;
            } else {
                $cart->touch();
            }

            $this->readCacheService->invalidate(['carts']);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $cartDeleted ? 'Cart was deleted as it became empty.' : 'Cart item removed.',
                'cart_deleted' => $cartDeleted,
            ]);
        }

        return redirect()->back()->with('success', 'Cart item removed.');
    }

    /**
     * Delete an unsubmitted draft cart.
     */
    public function destroyCart(Request $request, PurchaserCart $cart): JsonResponse|RedirectResponse
    {
        $user = $this->ensurePurchaser($request);
        $this->authorizeCart($cart, $user);

        DB::transaction(function () use ($cart): void {
            $cart->items()->delete();
            $cart->delete();
            $this->readCacheService->invalidate(['carts']);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Draft cart deleted successfully.',
            ]);
        }

        return redirect()->route('purchaser-v2.cart.index', [
            'date' => $cart->business_date?->toDateString() ?? now()->toDateString(),
            'grade' => $cart->purchase_grade ?? 'A',
        ])->with('success', 'Draft cart deleted.');
    }

    /**
     * Server-side search for suppliers respecting purchaser visibility.
     */
    public function searchSuppliers(Request $request): JsonResponse
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $query = (string) $request->query('q', '');

        $results = $this->cartQuery->searchSuppliers(
            query: $query,
            user: $user,
            limit: 20,
        );

        $data = [
            'status' => 'success',
            'data' => $results,
        ];

        return $this->attachTelemetry(response()->json($data), $telemetry, $user);
    }

    /**
     * Quick-create a new supplier and optionally assign to an active cart.
     */
    public function storeSupplier(Request $request): JsonResponse
    {
        $user = $this->ensurePurchaser($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'cart_id' => ['nullable'],
        ]);

        $loc = $validated['location'] ?? $validated['city'] ?? null;
        $supplier = Supplier::query()->create([
            'name' => trim($validated['name']),
            'mobile_number' => isset($validated['mobile_number']) ? trim((string) $validated['mobile_number']) : null,
            'contact' => isset($validated['mobile_number']) ? trim((string) $validated['mobile_number']) : null,
            'location' => $loc !== null ? trim((string) $loc) : null,
            'type' => ! empty($validated['type']) ? trim((string) $validated['type']) : 'Vendor',
            'category' => 'market',
            'is_default_purchase' => false,
            'payment_terms' => 'Cash',
            'preferred_payment_method' => 'Cash',
            'credit_approved' => true,
            'quality_score' => 100,
        ]);

        $this->readCacheService->invalidate(['suppliers', 'carts']);

        $assigned = false;
        if (! empty($validated['cart_id'])) {
            $cart = PurchaserCart::query()->where(function ($q) use ($validated): void {
                if (is_numeric($validated['cart_id'])) {
                    $q->where('id', $validated['cart_id'])->orWhere('cart_number', (string) $validated['cart_id']);
                } else {
                    $q->where('cart_number', $validated['cart_id']);
                }
            })->first();

            if ($cart && $cart->user_id === $user->id && $cart->status === 'draft') {
                $cart->update(['supplier_id' => $supplier->id]);
                $assigned = true;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier created successfully.',
            'assigned' => $assigned,
            'cart_id' => $validated['cart_id'] ?? null,
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'city' => $supplier->city,
                'type' => $supplier->type,
                'mobile_number' => $supplier->mobile_number,
                'location' => $supplier->location,
                'credit_approved' => (bool) $supplier->credit_approved,
            ],
        ]);
    }

    /**
     * Assign / update supplier for a draft cart.
     */
    public function updateSupplier(Request $request, PurchaserCart $cart): JsonResponse|RedirectResponse
    {
        $user = $this->ensurePurchaser($request);
        $this->authorizeCart($cart, $user);

        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ]);

        $supplierId = filled($validated['supplier_id'] ?? null) ? (int) $validated['supplier_id'] : null;
        $supplierName = null;

        if ($supplierId !== null) {
            $supplier = Supplier::query()->findOrFail($supplierId);
            $supplierName = $supplier->name;
        }

        $cart->update([
            'supplier_id' => $supplierId,
        ]);
        $this->readCacheService->invalidate(['carts']);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Supplier updated successfully.',
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
            ]);
        }

        return redirect()->back()->with('success', 'Supplier updated successfully.');
    }

    /**
     * On-demand vendor price hints for cart items from the selected supplier.
     */
    public function priceHints(Request $request, PurchaserCart $cart): JsonResponse
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $this->authorizeCart($cart, $user);

        $supplierId = $request->filled('supplier_id') ? $request->integer('supplier_id') : $cart->supplier_id;
        $hints = $this->cartQuery->getPriceHints($cart, $supplierId);

        $data = [
            'status' => 'success',
            'supplier_id' => $supplierId,
            'hints' => $hints,
        ];

        return $this->attachTelemetry(response()->json($data), $telemetry, $user);
    }

    /**
     * Merge compatible draft carts into the current target cart.
     */
    public function mergeDrafts(Request $request, PurchaserCart $cart): JsonResponse|RedirectResponse
    {
        $user = $this->ensurePurchaser($request);
        $this->authorizeCart($cart, $user);

        // Find all other compatible draft carts for the user, date, grade, and supplier
        $sourceCarts = PurchaserCart::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $cart->business_date)
            ->where('status', 'draft')
            ->where('purchase_grade', $cart->purchase_grade ?? 'A')
            ->whereKeyNot($cart->id)
            ->when(
                $cart->supplier_id !== null,
                fn ($q) => $q->where('supplier_id', $cart->supplier_id),
                fn ($q) => $q->whereNull('supplier_id')
            )
            ->with('items')
            ->get();

        if ($sourceCarts->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'No compatible draft carts found to merge.'], 422);
            }

            return redirect()->back()->with('error', 'No compatible draft carts found to merge.');
        }

        DB::transaction(function () use ($cart, $sourceCarts): void {
            $productIds = $sourceCarts->flatMap(fn ($c) => $c->items->pluck('product_id'))->merge($cart->items->pluck('product_id'))->unique()->values()->all();
            $date = $cart->business_date ?? Carbon::today();
            $grade = (string) ($cart->purchase_grade ?? 'A');

            $approvedMap = ShopOrderItem::query()
                ->whereIn('product_id', $productIds)
                ->where('product_grade', $grade)
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
                ->where('grade', $grade)
                ->whereHas('cart', function ($query) use ($date, $grade, $cart): void {
                    $query->whereDate('business_date', $date)
                        ->where('status', 'submitted')
                        ->where('purchase_grade', $grade)
                        ->whereKeyNot($cart->id);
                })
                ->groupBy('product_id')
                ->select('product_id', DB::raw('SUM(quantity) as total_submitted'))
                ->pluck('total_submitted', 'product_id')
                ->map(fn ($qty): float => (float) $qty)
                ->all();

            foreach ($sourceCarts as $sourceCart) {
                foreach ($sourceCart->items as $sourceItem) {
                    $targetItem = $cart->items()
                        ->where('product_id', $sourceItem->product_id)
                        ->where('grade', $grade)
                        ->first();

                    $mergedQty = (float) $sourceItem->quantity + (float) ($targetItem?->quantity ?? 0);
                    $productId = (int) $sourceItem->product_id;
                    $approved = $approvedMap[$productId] ?? 0.0;
                    $submitted = $submittedMap[$productId] ?? 0.0;
                    $remaining = max(0.0, round($approved - $submitted, 2));

                    if ($targetItem instanceof PurchaserCartItem) {
                        $unitPrice = (float) ($sourceItem->unit_price > 0 ? $sourceItem->unit_price : $targetItem->unit_price);
                        $targetItem->update([
                            'quantity' => $mergedQty,
                            'unit_price' => $unitPrice,
                            'line_total' => round($mergedQty * $unitPrice, 2),
                            'is_extra_purchase' => $mergedQty > $remaining,
                            'notes' => $targetItem->notes ?: $sourceItem->notes,
                        ]);
                        $sourceItem->delete();
                    } else {
                        $sourceItem->update([
                            'purchaser_cart_id' => $cart->id,
                            'quantity' => $mergedQty,
                            'line_total' => round($mergedQty * (float) $sourceItem->unit_price, 2),
                            'is_extra_purchase' => $mergedQty > $remaining,
                        ]);
                    }
                }

                $sourceCart->delete();
            }

            $cart->touch();
            $this->readCacheService->invalidate(['carts']);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Compatible draft carts merged successfully.',
                'items' => $this->cartQuery->getCartItems($cart, $user),
            ]);
        }

        return redirect()->back()->with('success', 'Compatible draft carts merged successfully.');
    }

    /**
     * Authorize cart ownership and draft state.
     */
    private function authorizeCart(PurchaserCart $cart, $user): void
    {
        if ($cart->user_id !== $user->id) {
            abort(403, 'You do not have access to this cart.');
        }

        if ($cart->status !== 'draft') {
            abort(422, 'Only draft carts can be managed in the draft cart hub.');
        }
    }
}
