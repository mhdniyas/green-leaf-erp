<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Inventory\BatchStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOwnerAssignment;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaserRoleTestSeeder extends Seeder
{
    private const ORDER_ITEM_COUNT = 8;

    private const BUSINESS_DATE = '2026-07-09';

    public function run(): void
    {
        DB::transaction(function (): void {
            $businessDate = Carbon::parse(self::BUSINESS_DATE)->startOfDay();
            $cutoffDate = $businessDate->copy()->subDay()->setTime(21, 30, 0);

            $products = Product::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->values();

            if ($products->count() < self::ORDER_ITEM_COUNT) {
                throw new \RuntimeException('PurchaserRoleTestSeeder requires enough active products to seed shop orders.');
            }

            $shops = Shop::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->get();

            if ($shops->isEmpty()) {
                throw new \RuntimeException('PurchaserRoleTestSeeder requires active shops.');
            }

            foreach ($shops->values() as $shopIndex => $shop) {
                $shopOwner = $this->shopOwnerFor($shop);

                if (! $shopOwner instanceof User) {
                    continue;
                }

                $orderNumber = sprintf(
                    'RQ-SHOP-%s-%02d',
                    $businessDate->format('Ymd'),
                    $shopIndex + 1,
                );

                $submittedAt = $cutoffDate->copy()->setTime(18 + ($shopIndex % 3), 10 + (($shopIndex * 7) % 40), 0);

                $shopOrder = ShopOrder::query()->updateOrCreate(
                    ['order_number' => $orderNumber],
                    [
                        'shop_id' => $shop->id,
                        'business_date' => $businessDate->toDateString(),
                        'state' => 'submitted',
                        'delivery_status' => 'pending_delivery',
                        'payment_status' => 'unpaid',
                        'is_late' => false,
                        'submitted_at' => $submittedAt,
                        'deadline_at' => $cutoffDate,
                        'created_by' => $shopOwner->id,
                        'latest_revision_no' => 1,
                        'has_pending_revision' => false,
                        'is_allocation_completed' => false,
                        'is_delivered' => false,
                        'cash_collected' => 0,
                        'cash_discrepancy' => 0,
                        'balance_amount' => 0,
                        'total_shortage_value' => 0,
                    ]
                );

                $this->seedOrderItems(
                    order: $shopOrder,
                    products: $products,
                    shopIndex: $shopIndex,
                );
            }

            $this->seedInventoryCoverage($businessDate);
        });

        $this->command?->info('Seeded July 9, 2026 shop-owner orders with July 8, 2026 submission times and enough confirmed stock for loadout.');
    }

    private function shopOwnerFor(Shop $shop): ?User
    {
        $assignment = ShopOwnerAssignment::query()
            ->where('shop_id', $shop->id)
            ->with('user')
            ->first();

        return $assignment?->user;
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function seedOrderItems(ShopOrder $order, Collection $products, int $shopIndex): void
    {
        $selectedProducts = collect(range(0, self::ORDER_ITEM_COUNT - 1))
            ->map(function (int $offset) use ($products, $shopIndex): Product {
                return $products[($shopIndex * 3 + $offset) % $products->count()];
            });

        $productIds = $selectedProducts->pluck('id')->all();

        ShopOrderItem::query()
            ->where('shop_order_id', $order->id)
            ->whereNotIn('product_id', $productIds)
            ->delete();

        foreach ($selectedProducts->values() as $itemIndex => $product) {
            $requestedQuantity = (float) random_int(2, 12);
            $unitPrice = round((float) ($product->base_price ?? $product->vendor_price ?? 1.0), 2);

            ShopOrderItem::query()->updateOrCreate(
                [
                    'shop_order_id' => $order->id,
                    'product_id' => $product->id,
                ],
                [
                    'product_grade' => 'A',
                    'requested_qty' => $requestedQuantity,
                    'approved_qty' => $requestedQuantity,
                    'unit' => $product->unit,
                    'locked_selling_price' => max(1.0, $unitPrice),
                    'locked_price_source' => 'seeded_load',
                    'line_total' => round($requestedQuantity * max(1.0, $unitPrice), 2),
                    'notes' => sprintf('Seeded shop-owner order item %d for July 9, 2026.', $itemIndex + 1),
                    'fulfillment_type' => 'warehouse',
                    'sorting_status' => 'pending',
                    'is_sorted' => false,
                    'delivered_qty' => 0,
                    'shortage_qty' => 0,
                    'unit_cost' => round(max(1.0, $unitPrice) * 0.82, 4),
                    'shortage_value' => 0,
                ]
            );
        }
    }

    private function seedInventoryCoverage(Carbon $businessDate): void
    {
        $warehouseReceiver = User::query()->where('email', 'receiver@greenleaf.com')->first()
            ?? User::query()->where('email', 'admin@greenleaf.com')->first()
            ?? User::query()->orderBy('id')->first();

        $fallbackWarehouse = Warehouse::query()->where('is_active', true)->orderBy('id')->first();

        if (! $warehouseReceiver instanceof User || ! $fallbackWarehouse instanceof Warehouse) {
            return;
        }

        $requiredQuantities = ShopOrderItem::query()
            ->selectRaw('product_id, SUM(approved_qty) as total_required_qty')
            ->whereHas('order', function ($query) use ($businessDate): void {
                $query->whereDate('business_date', $businessDate);
            })
            ->groupBy('product_id')
            ->get();

        foreach ($requiredQuantities as $requiredQuantity) {
            $product = Product::query()->find($requiredQuantity->product_id);

            if (! $product instanceof Product) {
                continue;
            }

            $warehouseId = $product->default_warehouse_id ?: $fallbackWarehouse->id;
            $targetQuantity = round(((float) $requiredQuantity->total_required_qty * 3) + 12, 3);
            $costPerKg = round((float) ($product->vendor_price ?? $product->base_price ?? 1.0), 4);

            StockBatch::query()->updateOrCreate(
                [
                    'reference' => sprintf('SEED-LOADOUT-%s-%s', $businessDate->format('Ymd'), $product->sku),
                ],
                [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'goods_received_id' => null,
                    'created_by' => $warehouseReceiver->id,
                    'received_at' => $businessDate->toDateString(),
                    'total_kg' => max(1.0, $targetQuantity),
                    'cost_per_kg' => max(1.0, $costPerKg),
                    'transport_cost' => 0,
                    'labour_cost' => 0,
                    'status' => BatchStatus::Pending,
                    'warehouse_receive_pending' => false,
                    'warehouse_confirmed_at' => $businessDate->copy()->setTime(6, 30),
                    'warehouse_confirmed_by' => $warehouseReceiver->id,
                    'notes' => 'Seeded inventory coverage for July 9, 2026 shop-order loadout.',
                    'sorted_at' => null,
                ]
            );
        }
    }
}
