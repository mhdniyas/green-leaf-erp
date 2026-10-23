<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\ShopOrder;
use App\Models\ShopOrderLoadoutState;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoLoadAllService
{
    public function __construct(
        private readonly StockLedgerService $stockLedgerService,
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    /**
     * Get orders eligible for Auto Load All.
     *
     * @param  array<int, int>|null  $shopIds
     * @return Collection<int, ShopOrder>
     */
    public function eligibleOrders(string $businessDate, ?array $shopIds = null): Collection
    {
        return ShopOrder::query()
            ->whereDate('business_date', $businessDate)
            ->where('state', 'approved')
            ->whereIn('delivery_status', ['pending_delivery', 'ready_for_dispatch'])
            ->where('order_source', '!=', 'admin_direct_purchase')
            ->whereHas('shop', fn ($q) => $q->where('status', 'active'))
            ->when(! empty($shopIds), fn ($q) => $q->whereIn('shop_id', array_map('intval', $shopIds)))
            ->whereHas('items', fn ($q) => $q->whereNotNull('approved_qty')->where('approved_qty', '>', 0))
            ->withCount('items')
            ->withCount([
                'items as loaded_items_count' => fn ($query) => $query->where('sorting_status', 'loaded'),
                'items as not_available_items_count' => fn ($query) => $query->where('sorting_status', 'not_available'),
            ])
            ->orderBy('id')
            ->get()
            ->filter(fn (ShopOrder $order): bool => ($order->loaded_items_count + $order->not_available_items_count) < $order->items_count)
            ->values();
    }

    /**
     * Safely load all approved items for a single order preserving line identities and quality grades.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     skipped: bool,
     *     order_id: int,
     *     order_number: string,
     *     loaded_items_count: int,
     *     already_loaded_items_count: int,
     *     skipped_items_count: int,
     * }
     */
    public function loadOrder(ShopOrder $order, User $actor, ?int $warehouseId = null): array
    {
        $lockKey = "auto_load_all_order_{$order->id}";
        $lock = Cache::lock($lockKey, 60);

        if (! $lock->get()) {
            return [
                'success' => false,
                'message' => "Order {$order->order_number} is currently locked by another process.",
                'skipped' => true,
                'order_id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'loaded_items_count' => 0,
                'already_loaded_items_count' => 0,
                'skipped_items_count' => 0,
            ];
        }

        try {
            return DB::transaction(function () use ($order, $actor, $warehouseId): array {
                /** @var ShopOrder $lockedOrder */
                $lockedOrder = ShopOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if (! in_array($lockedOrder->delivery_status, ['pending_delivery', 'ready_for_dispatch'], true)) {
                    return [
                        'success' => false,
                        'message' => "Order {$lockedOrder->order_number} is in delivery status '{$lockedOrder->delivery_status}' and cannot be loaded.",
                        'skipped' => true,
                        'order_id' => (int) $lockedOrder->id,
                        'order_number' => (string) $lockedOrder->order_number,
                        'loaded_items_count' => 0,
                        'already_loaded_items_count' => 0,
                        'skipped_items_count' => 0,
                    ];
                }

                if ($lockedOrder->state !== 'approved') {
                    return [
                        'success' => false,
                        'message' => "Order {$lockedOrder->order_number} is not approved (state: '{$lockedOrder->state}').",
                        'skipped' => true,
                        'order_id' => (int) $lockedOrder->id,
                        'order_number' => (string) $lockedOrder->order_number,
                        'loaded_items_count' => 0,
                        'already_loaded_items_count' => 0,
                        'skipped_items_count' => 0,
                    ];
                }

                if ($lockedOrder->order_source === 'admin_direct_purchase') {
                    return [
                        'success' => false,
                        'message' => "Order {$lockedOrder->order_number} is an admin direct purchase order.",
                        'skipped' => true,
                        'order_id' => (int) $lockedOrder->id,
                        'order_number' => (string) $lockedOrder->order_number,
                        'loaded_items_count' => 0,
                        'already_loaded_items_count' => 0,
                        'skipped_items_count' => 0,
                    ];
                }

                $lockedOrder->loadMissing('shop');
                if ($lockedOrder->shop && $lockedOrder->shop->status !== 'active') {
                    return [
                        'success' => false,
                        'message' => "Shop for order {$lockedOrder->order_number} is inactive.",
                        'skipped' => true,
                        'order_id' => (int) $lockedOrder->id,
                        'order_number' => (string) $lockedOrder->order_number,
                        'loaded_items_count' => 0,
                        'already_loaded_items_count' => 0,
                        'skipped_items_count' => 0,
                    ];
                }

                $items = $lockedOrder->items()
                    ->when($warehouseId !== null, function ($query) use ($warehouseId): void {
                        $query->whereHas('product', fn ($q) => $q->where('default_warehouse_id', $warehouseId));
                    })
                    ->with(['product.orderUnits', 'product.defaultWarehouse'])
                    ->lockForUpdate()
                    ->get();

                if ($items->isEmpty()) {
                    return [
                        'success' => true,
                        'message' => "Order {$lockedOrder->order_number} has no items.",
                        'skipped' => true,
                        'order_id' => (int) $lockedOrder->id,
                        'order_number' => (string) $lockedOrder->order_number,
                        'loaded_items_count' => 0,
                        'already_loaded_items_count' => 0,
                        'skipped_items_count' => 0,
                    ];
                }

                $loadedCount = 0;
                $alreadyLoadedCount = 0;
                $skippedCount = 0;
                $warehousesInvolved = [];

                foreach ($items as $item) {
                    $approvedQty = (float) ($item->approved_qty ?? 0.0);

                    // Skip unapproved, rejected, or cancelled items
                    if ($approvedQty <= 0.001) {
                        $skippedCount++;

                        continue;
                    }

                    // Check shop grade permission if shop exists
                    $gradeStr = strtoupper(trim((string) ($item->product_grade ?? 'A')));
                    if ($lockedOrder->shop && ! $lockedOrder->shop->canOrderGrade($gradeStr)) {
                        $skippedCount++;

                        continue;
                    }

                    $resolvedGrade = ProductGrade::tryResolve($gradeStr) ?? ProductGrade::GradeA;
                    $itemWarehouseId = $item->product?->default_warehouse_id ?? $warehouseId;
                    if ($itemWarehouseId !== null) {
                        $warehousesInvolved[$itemWarehouseId] = true;
                    }

                    // Preserve items marked not_available by warehouse workers
                    if ($item->sorting_status === 'not_available') {
                        $alreadyLoadedCount++;

                        continue;
                    }

                    // Preserve already loaded (full or partial) items without overwriting worker work
                    if ($item->sorting_status === 'loaded' && (float) ($item->loaded_qty ?? 0) > 0.001) {
                        $alreadyLoadedCount++;

                        continue;
                    }

                    // Load full approved quantity for untouched item
                    $targetQty = $approvedQty;
                    $conversionToBase = (float) ($item->requested_unit_conversion_to_base ?? 1.0);
                    $hasRequestedUnit = filled($item->requested_unit)
                        && strtolower((string) $item->requested_unit) !== 'kg';

                    $requestedUnitQty = (float) ($item->requested_unit_quantity ?? 0.0);
                    $loadedOrderUnitQty = $hasRequestedUnit && $conversionToBase > 0
                        ? ($requestedUnitQty > 0 ? $requestedUnitQty : round($targetQty / $conversionToBase, 2))
                        : null;

                    $currentLoadedQty = (float) ($item->loaded_qty ?? 0.0);
                    $diff = $targetQty - $currentLoadedQty;

                    if ($diff > 0.001) {
                        $this->stockLedgerService->consumeStockForProductAllowingNegative(
                            (int) $item->product_id,
                            $diff,
                            (int) $actor->id,
                            StockMovementType::Out,
                            "Auto Load All dispatch — Order: {$lockedOrder->order_number}",
                            $item->id,
                            $itemWarehouseId,
                            $resolvedGrade,
                        );
                    }

                    $unitSellingPrice = (float) ($item->locked_selling_price ?? 0.0);

                    $item->update([
                        'loaded_qty' => $targetQty,
                        'loaded_order_unit_qty' => $loadedOrderUnitQty,
                        'actual_weight' => $hasRequestedUnit ? null : $targetQty,
                        'excess_qty' => 0.0,
                        'excess_value' => 0.0,
                        'loadout_discrepancy_type' => 'none',
                        'loadout_discrepancy_note' => null,
                        'sorting_status' => 'loaded',
                        'is_sorted' => true,
                        'sorted_at' => now(),
                        'sorted_by' => $actor->id,
                        'line_total' => round($targetQty * $unitSellingPrice, 2),
                    ]);

                    $loadedCount++;
                }

                // Update / complete warehouse loadout states
                foreach (array_keys($warehousesInvolved) as $whId) {
                    $state = ShopOrderLoadoutState::firstOrNew([
                        'shop_order_id' => $lockedOrder->id,
                        'warehouse_id' => $whId,
                    ]);
                    $state->started_at = $state->started_at ?? now();
                    $state->completed_at = $state->completed_at ?? now();
                    $state->completed_by = $state->completed_by ?? $actor->id;
                    $state->save();
                }

                // Update order delivery status
                $hasAnyLoaded = $lockedOrder->items()
                    ->where('sorting_status', 'loaded')
                    ->where('loaded_qty', '>', 0)
                    ->exists();

                if ($hasAnyLoaded) {
                    $lockedOrder->update(['delivery_status' => 'ready_for_dispatch']);
                }

                // Synchronize and reprice invoice
                $lockedOrder->loadMissing(['shop.priceGroup', 'items.product', 'invoice.items']);
                $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($lockedOrder, (int) $actor->id);
                if ($invoice && ! $invoice->isFinalLocked()) {
                    try {
                        $this->shopInvoiceService->repriceInvoice(
                            $invoice,
                            (int) $actor->id,
                            "Invoice synchronized during Auto Load All for order {$lockedOrder->order_number}."
                        );
                    } catch (Throwable $e) {
                        Log::warning('AutoLoadAllService: Invoice repricing warning', [
                            'order_id' => $lockedOrder->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                return [
                    'success' => true,
                    'message' => "Order {$lockedOrder->order_number} processed successfully: {$loadedCount} loaded, {$alreadyLoadedCount} already completed, {$skippedCount} skipped.",
                    'skipped' => ($loadedCount === 0 && $alreadyLoadedCount > 0),
                    'order_id' => (int) $lockedOrder->id,
                    'order_number' => (string) $lockedOrder->order_number,
                    'loaded_items_count' => $loadedCount,
                    'already_loaded_items_count' => $alreadyLoadedCount,
                    'skipped_items_count' => $skippedCount,
                ];
            });
        } catch (Throwable $e) {
            Log::error('AutoLoadAllService: Order loading failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => "Load All failed for order {$order->order_number}: {$e->getMessage()}",
                'skipped' => false,
                'order_id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'loaded_items_count' => 0,
                'already_loaded_items_count' => 0,
                'skipped_items_count' => 0,
            ];
        } finally {
            $lock->release();
        }
    }
}
