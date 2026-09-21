<?php

declare(strict_types=1);

namespace App\Queries\Purchasing\V2;

use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\VendorPriceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaserV2CartQuery
{
    public function __construct(
        private readonly VendorPriceService $vendorPriceService,
    ) {}

    /**
     * Get lightweight summary list of today's draft carts for the purchaser.
     *
     * @return array<int, array{
     *     id: int,
     *     cart_number: string,
     *     supplier_id: int|null,
     *     supplier_name: string|null,
     *     item_count: int,
     *     subtotal: float,
     *     grade: string,
     *     business_date: string,
     *     updated_at_human: string,
     *     mergeable_cart_count: int
     * }>
     */
    public function getDraftCarts(User $user, Carbon $date, string $grade, int $limit = 25): array
    {
        $carts = PurchaserCart::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->where('purchase_grade', $grade)
            ->with([
                'supplier:id,name',
                'items:id,purchaser_cart_id,quantity,unit_price,line_total',
            ])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        if ($carts->isEmpty()) {
            return [];
        }

        // Calculate mergeable counts among this user's drafts on same date & grade
        $draftsBySupplier = $carts->groupBy(fn (PurchaserCart $c) => $c->supplier_id !== null ? 's_'.(int) $c->supplier_id : 'unassigned');

        return $carts->map(function (PurchaserCart $cart) use ($draftsBySupplier): array {
            $groupKey = $cart->supplier_id !== null ? 's_'.(int) $cart->supplier_id : 'unassigned';
            $sameGroupCarts = $draftsBySupplier->get($groupKey, collect());
            $mergeableCount = max(0, $sameGroupCarts->count() - 1);

            $subtotal = (float) $cart->items->sum(function ($item) {
                return (float) ($item->line_total ?: round((float) $item->quantity * (float) $item->unit_price, 2));
            });

            return [
                'id' => (int) $cart->id,
                'cart_number' => (string) $cart->cart_number,
                'supplier_id' => $cart->supplier_id ? (int) $cart->supplier_id : null,
                'supplier_name' => $cart->supplier?->name,
                'item_count' => (int) $cart->items->count(),
                'subtotal' => round($subtotal, 2),
                'grade' => (string) ($cart->purchase_grade ?? 'A'),
                'business_date' => $cart->business_date?->toDateString() ?? '',
                'updated_at_human' => $cart->updated_at ? $cart->updated_at->diffForHumans() : 'Just now',
                'mergeable_cart_count' => $mergeableCount,
            ];
        })->all();
    }

    /**
     * Get items for a single draft cart on demand.
     *
     * @return array<int, array{
     *     id: int,
     *     product_id: int,
     *     name: string,
     *     sku: string,
     *     category_name: string,
     *     base_unit: string,
     *     orderable_units: array<int, array{unit: string, label: string, conversion_to_base: float, is_base: bool}>,
     *     quantity: float,
     *     unit: string,
     *     unit_price: float,
     *     line_total: float,
     *     grade: string,
     *     is_extra_purchase: bool,
     *     remaining_approved_qty: float,
     *     notes: string|null
     * }>
     */
    public function getCartItems(PurchaserCart $cart, User $user): array
    {
        $cart->loadMissing([
            'items.product:id,name,sku,unit,category_id,vendor_price,base_price',
            'items.product.category:id,name',
            'items.product.orderUnits',
        ]);

        if ($cart->items->isEmpty()) {
            return [];
        }

        $productIds = $cart->items->pluck('product_id')->unique()->all();
        $date = $cart->business_date ?? Carbon::today();
        $grade = (string) ($cart->purchase_grade ?? 'A');

        // Bounded pre-fetch for approved & submitted demand
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

        return $cart->items->map(function (PurchaserCartItem $item) use ($approvedMap, $submittedMap): array {
            $product = $item->product;
            $productId = (int) $item->product_id;
            $approved = $approvedMap[$productId] ?? 0.0;
            $submitted = $submittedMap[$productId] ?? 0.0;
            $remaining = max(0.0, round($approved - $submitted, 2));

            return [
                'id' => (int) $item->id,
                'item_route_key' => (string) $item->getRouteKey(),
                'product_id' => $productId,
                'name' => (string) ($product?->name ?? 'Unknown Product'),
                'sku' => (string) ($product?->sku ?? ''),
                'category_name' => (string) ($product?->category?->name ?? 'Produce'),
                'base_unit' => (string) ($product?->unit ?: 'kg'),
                'orderable_units' => $product ? $this->resolveOrderableUnits($product) : [],
                'quantity' => (float) $item->quantity,
                'unit' => (string) ($product?->unit ?: 'kg'),
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) ($item->line_total ?: round((float) $item->quantity * (float) $item->unit_price, 2)),
                'grade' => (string) ($item->grade ?? 'A'),
                'is_extra_purchase' => (bool) $item->is_extra_purchase,
                'remaining_approved_qty' => $remaining,
                'notes' => $item->notes,
            ];
        })->values()->all();
    }

    /**
     * Search suppliers respecting purchaser's authorized vendor scope.
     *
     * @return array<int, array{id: int, name: string, mobile_number: string|null, location: string|null, credit_approved: bool}>
     */
    public function searchSuppliers(string $query, User $user, int $limit = 20): array
    {
        $query = trim($query);

        $suppliersQuery = $user->scopedSuppliersQuery();

        if ($query !== '') {
            $suppliersQuery->where(function ($q) use ($query): void {
                $q->where('name', 'like', '%'.$query.'%')
                    ->orWhere('mobile_number', 'like', '%'.$query.'%')
                    ->orWhere('location', 'like', '%'.$query.'%');
            });
        }

        return $suppliersQuery
            ->orderBy('name')
            ->limit(min(50, max(5, $limit)))
            ->get(['id', 'name', 'mobile_number', 'location', 'credit_approved'])
            ->map(fn (Supplier $s): array => [
                'id' => (int) $s->id,
                'name' => (string) $s->name,
                'mobile_number' => $s->mobile_number,
                'location' => $s->location,
                'credit_approved' => (bool) $s->credit_approved,
            ])
            ->all();
    }

    /**
     * Get price hints for cart items from the selected supplier.
     *
     * @return array<int, float>
     */
    public function getPriceHints(PurchaserCart $cart, ?int $supplierId = null): array
    {
        $productIds = $cart->items()->pluck('product_id')->unique()->all();

        return $this->vendorPriceService->previousPricesForSupplier($supplierId, $productIds);
    }

    /**
     * Resolve orderable units with conversion multipliers for a product.
     *
     * @return array<int, array{unit: string, label: string, conversion_to_base: float, is_base: bool}>
     */
    private function resolveOrderableUnits(Product $product): array
    {
        $units = $product->relationLoaded('orderUnits')
            ? $product->orderUnits
            : $product->orderUnits()->orderBy('sort_order')->orderBy('id')->get();

        $measurementUnits = $units
            ->filter(fn ($unit): bool => (float) $unit->conversion_to_base > 0)
            ->values();

        if ($measurementUnits->isEmpty()) {
            return [[
                'unit' => (string) ($product->unit ?: 'kg'),
                'label' => strtoupper((string) ($product->unit ?: 'kg')),
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
}
