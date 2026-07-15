<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class JulyFourteenShopOwnerOrderSeeder extends Seeder
{
    private const BUSINESS_DATE = '2026-07-14';

    private const SHOP_COUNT = 4;

    private const PRODUCTS_PER_SHOP = 5;

    private const PRODUCT_SKUS = ['1', '3', '5', '12', '23', '33', '60', '101'];

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ProductSeeder::class,
        ]);

        DB::transaction(function (): void {
            $businessDate = Carbon::parse(self::BUSINESS_DATE)->startOfDay();
            $deadlineAt = $businessDate->copy()->subDay()->setTime(21, 30);

            $products = Product::query()
                ->whereIn('sku', self::PRODUCT_SKUS)
                ->where('is_active', true)
                ->ordered()
                ->get()
                ->values();

            if ($products->count() < self::PRODUCTS_PER_SHOP) {
                throw new \RuntimeException('JulyFourteenShopOwnerOrderSeeder requires at least five active seeded products.');
            }

            for ($shopIndex = 0; $shopIndex < self::SHOP_COUNT; $shopIndex++) {
                $shop = $this->ensureShop($shopIndex);
                $shopOwner = $this->ensureShopOwner($shop, $shopIndex);

                $orderNumber = sprintf('RQ-SHOP-%s-%02d', $businessDate->format('Ymd'), $shopIndex + 1);

                $order = ShopOrder::query()->updateOrCreate(
                    ['order_number' => $orderNumber],
                    [
                        'shop_id' => $shop->id,
                        'business_date' => $businessDate->toDateString(),
                        'state' => 'submitted',
                        'delivery_status' => 'pending_delivery',
                        'payment_status' => 'unpaid',
                        'is_late' => false,
                        'submitted_at' => $businessDate->copy()->subDay()->setTime(18 + $shopIndex, 10, 0),
                        'deadline_at' => $deadlineAt,
                        'created_by' => $shopOwner->id,
                        'latest_revision_no' => 1,
                        'has_pending_revision' => false,
                        'is_allocation_completed' => false,
                        'is_delivered' => false,
                        'cash_collected' => 0,
                        'cash_discrepancy' => 0,
                        'balance_amount' => 0,
                        'total_shortage_value' => 0,
                    ],
                );

                $this->seedOrderItems($order, $products, $shopIndex);
            }
        });

        $this->command?->info('Seeded 4 shop-owner orders with 5 products each for July 14, 2026.');
    }

    private function ensureShop(int $shopIndex): Shop
    {
        return Shop::query()->updateOrCreate(
            ['code' => sprintf('SHOP_JUL14_%02d', $shopIndex + 1)],
            [
                'name' => sprintf('July 14 Demo Shop %02d', $shopIndex + 1),
                'warehouse_tag' => sprintf('J14-%02d', $shopIndex + 1),
                'status' => 'active',
                'accounting_mode' => 'standard',
                'accounting_enabled' => false,
                'approved_at' => now(),
            ],
        );
    }

    private function ensureShopOwner(Shop $shop, int $shopIndex): User
    {
        $owner = User::query()->updateOrCreate(
            ['email' => sprintf('july14.shop.owner.%02d@greenleaf.com', $shopIndex + 1)],
            [
                'name' => sprintf('July 14 Shop Owner %02d', $shopIndex + 1),
                'password' => Hash::make('ShopOwner17'),
                'email_verified_at' => now(),
                'shop_id' => $shop->id,
                'registration_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => null,
            ],
        );

        $owner->syncRoles(['shop']);

        ShopOwnerAssignment::query()->updateOrCreate(
            [
                'user_id' => $owner->id,
                'shop_id' => $shop->id,
            ],
            [],
        );

        return $owner;
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function seedOrderItems(ShopOrder $order, Collection $products, int $shopIndex): void
    {
        $selectedProducts = collect(range(0, self::PRODUCTS_PER_SHOP - 1))
            ->map(fn (int $offset): Product => $products[($shopIndex + $offset) % $products->count()])
            ->values();

        ShopOrderItem::query()
            ->where('shop_order_id', $order->id)
            ->whereNotIn('product_id', $selectedProducts->pluck('id')->all())
            ->delete();

        foreach ($selectedProducts as $itemIndex => $product) {
            $requestedQuantity = (float) (5 + $shopIndex + $itemIndex);
            $unitPrice = max(1.0, round((float) ($product->base_price ?? $product->vendor_price ?? 1.0), 2));

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
                    'locked_selling_price' => $unitPrice,
                    'locked_price_source' => 'seeded_order',
                    'line_total' => round($requestedQuantity * $unitPrice, 2),
                    'notes' => 'Seeded shop-owner order item for July 14, 2026.',
                    'fulfillment_type' => 'warehouse',
                    'sorting_status' => 'pending',
                    'is_sorted' => false,
                    'delivered_qty' => 0,
                    'shortage_qty' => 0,
                    'unit_cost' => round($unitPrice * 0.82, 4),
                    'shortage_value' => 0,
                ],
            );
        }
    }
}
