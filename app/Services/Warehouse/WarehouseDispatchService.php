<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\Inventory\ProductGrade;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseDispatchService
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    /**
     * Dispatch an order to 'in_transit' with consistent validation, locking, and invoice synchronization.
     *
     * @throws ValidationException
     */
    public function dispatch(ShopOrder $order, User $user, bool $partialDelivery = false): ShopInvoice
    {
        return DB::transaction(function () use ($order, $user, $partialDelivery): ShopInvoice {
            /** @var ShopOrder $lockedOrder */
            $lockedOrder = ShopOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with(['items.product', 'shop.priceGroup', 'invoice.items'])
                ->firstOrFail();

            if ($this->hasDuplicateProducts($lockedOrder->items)) {
                throw ValidationException::withMessages([
                    'order' => ['Duplicate product rows found. Merge duplicates first.'],
                ]);
            }

            if ($lockedOrder->delivery_status === 'in_transit') {
                throw ValidationException::withMessages([
                    'order' => ['Order is already in transit.'],
                ]);
            }

            if ($lockedOrder->delivery_status === 'delivered') {
                throw ValidationException::withMessages([
                    'order' => ['This order is already delivered.'],
                ]);
            }

            if ($lockedOrder->delivery_status !== 'ready_for_dispatch') {
                throw ValidationException::withMessages([
                    'order' => ['Order is not ready for delivery.'],
                ]);
            }

            $loadedItems = $lockedOrder->items
                ->filter(fn (ShopOrderItem $item): bool => $item->sorting_status === 'loaded' && (float) ($item->loaded_qty ?? 0) > 0);

            if ($loadedItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['Please load at least one item before moving to delivery.'],
                ]);
            }

            $remainingItems = $lockedOrder->items
                ->filter(function (ShopOrderItem $item): bool {
                    $approvedQty = (float) ($item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty);
                    $loadedQty = (float) ($item->loaded_qty ?? 0);

                    return $item->sorting_status !== 'loaded' || $loadedQty < $approvedQty;
                });

            if (! $partialDelivery && $remainingItems->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'order' => ['Order has short-loaded items. Use partial delivery.'],
                ]);
            }

            if ($partialDelivery && $remainingItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' => ['All items are fully loaded. Use regular delivery.'],
                ]);
            }

            // Synchronize and reprice invoice BEFORE committing status transition.
            // If any loaded billable item is missing pricing, this throws ValidationException,
            // which rolls back the transaction and leaves delivery_status unchanged.
            $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($lockedOrder, (int) $user->id);

            if (! $invoice->isFinalLocked()) {
                $this->shopInvoiceService->repriceInvoice(
                    $invoice,
                    (int) $user->id,
                    $partialDelivery
                        ? "Invoice recalculated during partial delivery transition for order {$lockedOrder->order_number}."
                        : "Invoice recalculated during delivery transition for order {$lockedOrder->order_number}."
                );
            }

            // Commit order transition to in_transit
            $lockedOrder->update([
                'delivery_status' => 'in_transit',
                'is_allocation_completed' => true,
                'delivery_notes' => $partialDelivery
                    ? ('Moved to delivery as a partial delivery with '.$remainingItems->count().' item(s) still pending loadout.')
                    : $lockedOrder->delivery_notes,
            ]);

            if (function_exists('activity')) {
                activity()
                    ->performedOn($lockedOrder)
                    ->causedBy($user)
                    ->log($partialDelivery ? 'shop_order.moved_to_delivery_partial' : 'shop_order.marked_out_for_delivery');
            }

            return $invoice->fresh('items');
        });
    }

    /**
     * @param  Collection<int, ShopOrderItem>  $rows
     */
    private function hasDuplicateProducts(Collection $rows): bool
    {
        return $rows
            ->groupBy(fn (ShopOrderItem $item): string => $item->product_id.'|'.(ProductGrade::tryResolve($item->product_grade)?->value ?? $item->product_grade ?? 'A'))
            ->contains(fn (Collection $productRows): bool => $this->needsMerge($productRows));
    }

    /**
     * @param  Collection<int, ShopOrderItem>  $rows
     */
    private function needsMerge(Collection $rows): bool
    {
        if ($rows->count() <= 1) {
            return false;
        }

        $loadedRows = $rows->where('sorting_status', 'loaded')->count();
        $allocatedRows = $rows->where('sorting_status', 'allocated')->count();
        $notAvailableRows = $rows->where('sorting_status', 'not_available')->count();

        if ($notAvailableRows > 0) {
            return $rows->count() > 1;
        }

        if ($rows->count() > 2) {
            return true;
        }

        if ($loadedRows > 1 || $allocatedRows > 1) {
            return true;
        }

        return ! ($rows->count() === 2 && $loadedRows === 1 && $allocatedRows === 1);
    }
}
