<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOrderLoadoutState;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedgerService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseLoadoutCompletionService
{
    public function __construct(
        private readonly StockLedgerService $stockLedgerService,
        private readonly ShopInvoiceService $invoiceService,
        private readonly WarehouseScopedLoadoutService $loadoutService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function complete(User $user, Warehouse $warehouse, ShopOrder $order): array
    {
        abort_unless($user->canAccessWarehouse($warehouse), 403, 'Unauthorized warehouse access.');

        DB::transaction(function () use ($user, $warehouse, $order): void {
            $lockedOrder = ShopOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedOrder->delivery_status, ['pending_delivery', 'ready_for_dispatch'], true)) {
                throw ValidationException::withMessages([
                    'order' => ['This order is not eligible for warehouse completion.'],
                ]);
            }

            $items = $this->applicableItems($lockedOrder, $warehouse, lock: true);
            if ($items->isEmpty()) {
                abort(404, 'This order has no applicable items for the selected warehouse.');
            }

            $state = $this->lockOrCreateState($lockedOrder->id, $warehouse->id);
            if ($state->completed_at === null) {
                $this->validateFinalizedItems($items);
                $this->createStockEffects($items, $lockedOrder, $warehouse, $user);
                $state->forceFill([
                    'started_at' => $state->started_at ?? now(),
                    'completed_at' => now(),
                    'completed_by' => $user->id,
                ])->save();
            }

            $this->coordinateOverallTransition($lockedOrder, $user);
        });

        return $this->loadoutService->detail($user, $warehouse, $order->fresh());
    }

    /** @return Collection<int, ShopOrderItem> */
    private function applicableItems(ShopOrder $order, Warehouse $warehouse, bool $lock = false): Collection
    {
        return ShopOrderItem::query()
            ->where('shop_order_id', $order->id)
            ->whereHas('product', fn (Builder $query): Builder => $query
                ->where('default_warehouse_id', $warehouse->id)
                ->where('is_active', true))
            ->with('product:id,default_warehouse_id,is_active')
            ->orderBy('id')
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->get();
    }

    /** @param Collection<int, ShopOrderItem> $items */
    private function validateFinalizedItems(Collection $items): void
    {
        $invalidIds = $items
            ->filter(function (ShopOrderItem $item): bool {
                if ($item->sorting_status === 'not_available') {
                    return false;
                }

                return $item->sorting_status !== 'loaded' || (float) ($item->loaded_qty ?? 0) <= 0;
            })
            ->pluck('id')
            ->values()
            ->all();

        if ($invalidIds !== []) {
            throw ValidationException::withMessages([
                'items' => ['Every applicable item must be saved as loaded or Not Available before completion.'],
            ]);
        }
    }

    /** @param Collection<int, ShopOrderItem> $items */
    private function createStockEffects(
        Collection $items,
        ShopOrder $order,
        Warehouse $warehouse,
        User $user,
    ): void {
        foreach ($items as $item) {
            if ($item->sorting_status === 'not_available') {
                continue;
            }

            $quantity = (float) ($item->actual_weight ?? $item->loaded_qty ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $this->stockLedgerService->consumeStockForProductAllowingNegative(
                $item->product_id,
                $quantity,
                $user->id,
                StockMovementType::Out,
                "Warehouse loadout completion — Order: {$order->order_number}; Warehouse: {$warehouse->code}",
                $item->id,
                $warehouse->id,
                ProductGrade::from($item->product_grade ?? 'A'),
            );
        }
    }

    private function coordinateOverallTransition(ShopOrder $order, User $user): void
    {
        $requiredWarehouseIds = DB::table('shop_order_items')
            ->join('products', 'products.id', '=', 'shop_order_items.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'products.default_warehouse_id')
            ->where('shop_order_items.shop_order_id', $order->id)
            ->whereNull('shop_order_items.deleted_at')
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->whereNull('warehouses.deleted_at')
            ->where('warehouses.is_active', true)
            ->distinct()
            ->orderBy('products.default_warehouse_id')
            ->pluck('products.default_warehouse_id')
            ->map(fn ($id): int => (int) $id);

        $completedWarehouseIds = ShopOrderLoadoutState::query()
            ->where('shop_order_id', $order->id)
            ->whereIn('warehouse_id', $requiredWarehouseIds)
            ->whereNotNull('completed_at')
            ->lockForUpdate()
            ->pluck('warehouse_id')
            ->map(fn ($id): int => (int) $id);

        if ($requiredWarehouseIds->isEmpty()
            || $requiredWarehouseIds->diff($completedWarehouseIds)->isNotEmpty()) {
            return;
        }

        $order->loadMissing('invoice');
        if ($order->delivery_status === 'ready_for_dispatch' && $order->invoice !== null) {
            return;
        }

        $order->forceFill(['delivery_status' => 'ready_for_dispatch'])->save();
        $order->load(['shop.priceGroup', 'items.product', 'invoice.items']);
        $invoice = $this->invoiceService->synchronizeOrderInvoice($order, $user->id);
        if (! $invoice->isFinalLocked()) {
            $this->invoiceService->repriceInvoice(
                $invoice,
                $user->id,
                "Invoice recalculated after all warehouse loadouts completed for order {$order->order_number}."
            );
        }
    }

    private function lockOrCreateState(int $orderId, int $warehouseId): ShopOrderLoadoutState
    {
        $state = ShopOrderLoadoutState::query()
            ->where('shop_order_id', $orderId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
        if ($state) {
            return $state;
        }

        ShopOrderLoadoutState::query()->create([
            'shop_order_id' => $orderId,
            'warehouse_id' => $warehouseId,
        ]);

        return ShopOrderLoadoutState::query()
            ->where('shop_order_id', $orderId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
