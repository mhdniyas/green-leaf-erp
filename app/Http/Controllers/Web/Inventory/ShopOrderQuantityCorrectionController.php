<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ShopOrderQuantityCorrectionRequest;
use App\Models\Category;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShopOrderQuantityCorrectionController extends Controller
{
    /**
     * Display the Admin Quantity Corrections interface.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $date = $request->input('date', app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());
        $selectedShopId = $request->integer('shop_id') ?: null;
        $selectedCategoryId = $request->integer('category_id') ?: null;
        $search = trim((string) $request->input('search', ''));
        $warningsOnly = $request->boolean('warnings_only');

        $shops = Shop::query()->orderBy('name')->get(['id', 'name', 'code', 'warehouse_tag']);
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // Identify duplicate (shop_order_id + product_id) pairs for this date
        $duplicatePairs = DB::table('shop_order_items')
            ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.shop_order_id')
            ->whereDate('shop_orders.business_date', $date)
            ->whereNull('shop_order_items.deleted_at')
            ->select('shop_order_items.shop_order_id', 'shop_order_items.product_id', DB::raw('COUNT(*) as item_count'))
            ->groupBy('shop_order_items.shop_order_id', 'shop_order_items.product_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->shop_order_id}_{$row->product_id}" => (int) $row->item_count])
            ->all();

        $query = ShopOrderItem::query()
            ->whereHas('order', function ($orderQuery) use ($date, $selectedShopId): void {
                $orderQuery->whereDate('business_date', $date)
                    ->when($selectedShopId, fn ($sq) => $sq->where('shop_id', $selectedShopId));
            })
            ->with(['order.shop', 'product.category'])
            ->when($selectedCategoryId, function ($iq) use ($selectedCategoryId): void {
                $iq->whereHas('product', fn ($pq) => $pq->where('category_id', $selectedCategoryId));
            })
            ->when($search !== '', function ($iq) use ($search): void {
                $iq->where(function ($subQuery) use ($search): void {
                    $subQuery->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                        ->orWhereHas('order.shop', fn ($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            });

        $allItems = $query->get()->map(function (ShopOrderItem $item) use ($duplicatePairs) {
            $unitQty = (float) ($item->requested_unit_quantity ?? 0.0);
            $conv = (float) ($item->requested_unit_conversion_to_base ?? 1.0);
            $expectedBaseQty = round($unitQty * $conv, 3);
            $actualBaseQty = (float) $item->requested_qty;
            $actualApprovedQty = (float) $item->approved_qty;

            $item->expected_base_qty = $expectedBaseQty;
            $item->has_inflated_requested = $actualBaseQty > 5000;
            $item->has_inflated_approved = $actualApprovedQty > 5000;
            $item->has_mismatch = abs($actualBaseQty - $expectedBaseQty) > 0.01;
            $item->is_duplicate = isset($duplicatePairs["{$item->shop_order_id}_{$item->product_id}"]);
            $item->has_any_warning = $item->has_inflated_requested || $item->has_inflated_approved || $item->has_mismatch || $item->is_duplicate;

            return $item;
        });

        // Summary counts
        $totalItemsCount = $allItems->count();
        $inflatedCount = $allItems->filter(fn (ShopOrderItem $i): bool => $i->has_inflated_requested || $i->has_inflated_approved)->count();
        $mismatchCount = $allItems->filter(fn (ShopOrderItem $i): bool => $i->has_mismatch)->count();
        $duplicateCount = $allItems->filter(fn (ShopOrderItem $i): bool => $i->is_duplicate)->count();
        $totalWarningsCount = $allItems->filter(fn (ShopOrderItem $i): bool => $i->has_any_warning)->count();

        $items = $warningsOnly
            ? $allItems->filter(fn (ShopOrderItem $i): bool => $i->has_any_warning)->values()
            : $allItems->values();

        return view('inventory.quantity-corrections.index', compact(
            'date',
            'shops',
            'categories',
            'selectedShopId',
            'selectedCategoryId',
            'search',
            'warningsOnly',
            'items',
            'totalItemsCount',
            'inflatedCount',
            'mismatchCount',
            'duplicateCount',
            'totalWarningsCount'
        ));
    }

    /**
     * Update requested and approved quantities for a shop order item.
     */
    public function update(ShopOrderQuantityCorrectionRequest $request, ShopOrderItem $item): RedirectResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        DB::transaction(function () use ($item, $validated, $user): void {
            $oldRequestedQty = (float) $item->requested_qty;
            $oldApprovedQty = (float) $item->approved_qty;
            $oldUnitQty = (float) ($item->requested_unit_quantity ?? 0.0);
            $oldUnit = (string) ($item->requested_unit ?? $item->unit);

            $newUnitQty = (float) $validated['requested_unit_quantity'];
            $newUnit = (string) $validated['requested_unit'];
            $newConv = (float) $validated['requested_unit_conversion_to_base'];
            $newRequestedQty = (float) $validated['requested_qty'];
            $newApprovedQty = (float) $validated['approved_qty'];
            $reason = trim((string) ($validated['reason'] ?? 'Admin quantity correction'));

            $sellingPrice = (float) ($item->locked_selling_price ?? 0.0);
            $newLineTotal = round($newRequestedQty * $sellingPrice, 2);

            $item->update([
                'requested_unit_quantity' => $newUnitQty,
                'requested_unit' => $newUnit,
                'requested_unit_label' => strtoupper($newUnit),
                'requested_unit_conversion_to_base' => $newConv,
                'requested_qty' => $newRequestedQty,
                'approved_qty' => $newApprovedQty,
                'line_total' => $newLineTotal,
            ]);

            activity()
                ->causedBy($user)
                ->performedOn($item)
                ->withProperties([
                    'old_requested_qty' => $oldRequestedQty,
                    'new_requested_qty' => $newRequestedQty,
                    'old_approved_qty' => $oldApprovedQty,
                    'new_approved_qty' => $newApprovedQty,
                    'old_unit_qty' => $oldUnitQty,
                    'new_unit_qty' => $newUnitQty,
                    'old_unit' => $oldUnit,
                    'new_unit' => $newUnit,
                    'conversion_to_base' => $newConv,
                    'reason' => $reason,
                    'updated_by' => $user->name,
                    'updated_at' => now()->toIso8601String(),
                ])
                ->log('Shop order item quantity corrected by admin');
        });

        return redirect()->back()->with('success', "Quantities updated for {$item->product?->name} ({$item->order?->shop?->name}).");
    }

    /**
     * Action: Recalculate requested_qty from requested_unit_quantity * conversion.
     */
    public function recalculate(Request $request, ShopOrderItem $item): RedirectResponse
    {
        $this->authorizeAccess($request);

        $user = $request->user();

        DB::transaction(function () use ($item, $user): void {
            $oldRequestedQty = (float) $item->requested_qty;
            $oldApprovedQty = (float) $item->approved_qty;

            $unitQty = (float) ($item->requested_unit_quantity ?? 0.0);
            $conv = (float) ($item->requested_unit_conversion_to_base ?? 1.0);
            $recalculatedBaseQty = round($unitQty * $conv, 3);

            if (
                $recalculatedBaseQty <= 0.0001
                && $item->sorting_status === 'loaded'
                && ((float) ($item->loaded_qty ?? 0.0) > 0.0001 || (float) ($item->actual_weight ?? 0.0) > 0.0001)
            ) {
                $recalculatedBaseQty = round((float) ($item->actual_weight ?: $item->loaded_qty), 3);
            }

            $sellingPrice = (float) ($item->locked_selling_price ?? 0.0);
            $newLineTotal = round($recalculatedBaseQty * $sellingPrice, 2);

            $item->update([
                'requested_qty' => $recalculatedBaseQty,
                'approved_qty' => $recalculatedBaseQty,
                'line_total' => $newLineTotal,
            ]);

            activity()
                ->causedBy($user)
                ->performedOn($item)
                ->withProperties([
                    'old_requested_qty' => $oldRequestedQty,
                    'new_requested_qty' => $recalculatedBaseQty,
                    'old_approved_qty' => $oldApprovedQty,
                    'new_approved_qty' => $recalculatedBaseQty,
                    'reason' => 'Recalculated from unit_qty * conversion_factor',
                    'updated_by' => $user->name,
                    'updated_at' => now()->toIso8601String(),
                ])
                ->log('Shop order item quantity recalculated by admin');
        });

        return redirect()->back()->with('success', "Recalculated quantity for {$item->product?->name} to {$item->requested_qty}.");
    }

    /**
     * Action: Copy Loaded Qty to Approved Qty.
     */
    public function copyLoaded(Request $request, ShopOrderItem $item): RedirectResponse
    {
        $this->authorizeAccess($request);

        $loadedQty = (float) ($item->loaded_qty ?? 0.0);
        $actualWeight = (float) ($item->actual_weight ?? 0.0);
        $targetQty = $loadedQty > 0.0001 ? $loadedQty : ($actualWeight > 0.0001 ? $actualWeight : 0.0);

        if ($targetQty <= 0.0001) {
            return redirect()->back()->withErrors(["No loaded quantity or actual weight recorded for {$item->product?->name}."]);
        }

        $user = $request->user();

        DB::transaction(function () use ($item, $targetQty, $user): void {
            $oldApprovedQty = (float) $item->approved_qty;

            $item->update([
                'approved_qty' => $targetQty,
            ]);

            activity()
                ->causedBy($user)
                ->performedOn($item)
                ->withProperties([
                    'old_approved_qty' => $oldApprovedQty,
                    'new_approved_qty' => $targetQty,
                    'reason' => 'Copied loaded/actual weight to approved quantity',
                    'updated_by' => $user->name,
                    'updated_at' => now()->toIso8601String(),
                ])
                ->log('Shop order item approved quantity set to loaded quantity by admin');
        });

        return redirect()->back()->with('success', "Updated approved quantity to {$targetQty} for {$item->product?->name}.");
    }

    /**
     * Action: Soft Delete duplicate or corrupted row.
     */
    public function softDeleteDuplicate(Request $request, ShopOrderItem $item): RedirectResponse
    {
        $this->authorizeAccess($request);

        $user = $request->user();

        DB::transaction(function () use ($item, $user): void {
            activity()
                ->causedBy($user)
                ->performedOn($item)
                ->withProperties([
                    'deleted_item_id' => $item->id,
                    'shop_order_id' => $item->shop_order_id,
                    'product_id' => $item->product_id,
                    'requested_qty' => (float) $item->requested_qty,
                    'approved_qty' => (float) $item->approved_qty,
                    'reason' => 'Soft-deleted corrupted duplicate row by admin',
                    'updated_by' => $user->name,
                    'updated_at' => now()->toIso8601String(),
                ])
                ->log('Corrupted duplicate shop order item soft-deleted by admin');

            $item->delete();
        });

        return redirect()->back()->with('success', "Soft-deleted duplicate row #{$item->id} for {$item->product?->name}.");
    }

    private function authorizeAccess(Request $request): void
    {
        if (
            ! $request->user()?->hasRole('admin')
            && ! $request->user()?->can('inventory.stock.adjust')
            && ! $request->user()?->can('inventory.product.edit')
        ) {
            abort(403, 'Unauthorized. Admin or Stock Adjust permission required.');
        }
    }
}
