<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaserRoleTestSeeder extends Seeder
{
    private const ORDER_ITEM_COUNT = 40;

    /**
     * @var array<string, float>
     */
    private const SHARED_PRODUCT_QUANTITIES = [
        'TOMATOH-001' => 5.0,
        'ONION-003' => 8.0,
        'POTATOAGRA-005' => 10.0,
        'CORRIANDER-101' => 3.0,
        'BANANANENDRAN-164' => 6.0,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $products = Product::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($products->count() < 200) {
                throw new \RuntimeException('PurchaserRoleTestSeeder requires at least 200 active products.');
            }

            $this->command?->info("Found {$products->count()} active products.");

            // Define 14 shops with unique warehouse tags (A to N)
            $shopData = [
                ['code' => 'SHOP_CASIO', 'name' => 'Casio Hypermarket', 'warehouse_tag' => 'A'],
                ['code' => 'SHOP_BUDEGERE', 'name' => 'Budegere', 'warehouse_tag' => 'B'],
                ['code' => 'SHOP_GRANCITY', 'name' => 'Grancity', 'warehouse_tag' => 'C'],
                ['code' => 'SHOP_ASHIRWAD', 'name' => 'Ashirwad', 'warehouse_tag' => 'D'],
                ['code' => 'SHOP_METRO', 'name' => 'Metro Retail', 'warehouse_tag' => 'E'],
                ['code' => 'SHOP_RELIANCE', 'name' => 'Reliance Fresh', 'warehouse_tag' => 'F'],
                ['code' => 'SHOP_SPAR', 'name' => 'Spar Hypermarket', 'warehouse_tag' => 'G'],
                ['code' => 'SHOP_MORE', 'name' => 'More Supermarket', 'warehouse_tag' => 'H'],
                ['code' => 'SHOP_LULU', 'name' => 'Lulu Express', 'warehouse_tag' => 'I'],
                ['code' => 'SHOP_STAR', 'name' => 'Star Bazaar', 'warehouse_tag' => 'J'],
                ['code' => 'SHOP_FOODWORLD', 'name' => 'Foodworld', 'warehouse_tag' => 'K'],
                ['code' => 'SHOP_NILGIRIS', 'name' => 'Nilgiris', 'warehouse_tag' => 'L'],
                ['code' => 'SHOP_DMART', 'name' => 'DMart', 'warehouse_tag' => 'M'],
                ['code' => 'SHOP_EASYDAY', 'name' => 'Easyday', 'warehouse_tag' => 'N'],
            ];

            $priceGroupMap = [
                'A' => 1, 'D' => 1, 'G' => 1, 'J' => 1, 'M' => 1,
                'B' => 2, 'E' => 2, 'H' => 2, 'K' => 2, 'N' => 2,
                'C' => 3, 'F' => 3, 'I' => 3, 'L' => 3,
            ];

            $shops = collect();
            foreach ($shopData as $data) {
                $shop = Shop::updateOrCreate(
                    ['code' => $data['code']],
                    [
                        'name' => $data['name'],
                        'warehouse_tag' => $data['warehouse_tag'],
                        'shop_price_group_id' => $priceGroupMap[$data['warehouse_tag']] ?? 1,
                        'status' => 'active',
                    ]
                );
                $shops->push($shop);
            }

            $this->command?->info('Ensured 14 shops are seeded with unique warehouse tags.');

            ShopOrder::query()
                ->whereIn('shop_id', $shops->pluck('id'))
                ->delete();

            $orderCreator = User::query()->where('email', 'purchase@greenleaf.com')->firstOrFail();

            $businessDate = Carbon::today()->startOfDay();
            $sharedProducts = $products
                ->whereIn('sku', array_keys(self::SHARED_PRODUCT_QUANTITIES))
                ->keyBy('sku');

            if ($sharedProducts->count() !== count(self::SHARED_PRODUCT_QUANTITIES)) {
                throw new \RuntimeException('PurchaserRoleTestSeeder is missing required shared products.');
            }

            $rotatingProducts = $products
                ->reject(fn (Product $product): bool => array_key_exists($product->sku, self::SHARED_PRODUCT_QUANTITIES))
                ->values();

            $this->command?->info(sprintf(
                'Seeding today orders for %s...',
                $businessDate->toDateString()
            ));

            // Disable model events for performance
            ShopOrder::unsetEventDispatcher();
            ShopOrderItem::unsetEventDispatcher();

            $orderCount = 0;
            foreach ($shops->values() as $shopIndex => $shop) {
                $orderDateString = $businessDate->toDateString();
                $datePrefix = $businessDate->format('Ymd');
                $suffix = strtoupper(substr(md5($shop->code.$orderDateString), 0, 4));
                $orderNumber = "RQ-{$datePrefix}-{$suffix}";

                $order = ShopOrder::updateOrCreate(
                    ['order_number' => $orderNumber],
                    [
                        'shop_id' => $shop->id,
                        'state' => 'approved',
                        'delivery_status' => 'pending_delivery',
                        'payment_status' => 'pending',
                        'business_date' => $orderDateString,
                        'submitted_at' => $businessDate->copy()->subDay()->setTime(18, 0, 0),
                        'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30, 0),
                        'created_by' => $orderCreator->id,
                        'latest_revision_no' => 1,
                        'has_pending_revision' => false,
                        'is_allocation_completed' => false,
                        'is_delivered' => false,
                        'is_late' => false,
                        'cash_collected' => 0,
                        'cash_discrepancy' => 0,
                        'balance_amount' => 0,
                        'total_shortage_value' => 0,
                    ]
                );

                ShopOrderItem::query()
                    ->where('shop_order_id', $order->id)
                    ->delete();

                $this->seedOrderItems(
                    order: $order,
                    sharedProducts: $sharedProducts,
                    rotatingProducts: $rotatingProducts,
                    shopOffset: $shopIndex,
                );

                $orderCount++;
            }

            ShopOrder::setEventDispatcher(app('events'));
            ShopOrderItem::setEventDispatcher(app('events'));

            $this->command?->info("Seeded {$orderCount} today orders with items successfully.");
        });
    }

    /**
     * @param  Collection<int|string, Product>  $sharedProducts
     * @param  Collection<int, Product>  $rotatingProducts
     */
    private function seedOrderItems(
        ShopOrder $order,
        Collection $sharedProducts,
        Collection $rotatingProducts,
        int $shopOffset,
    ): void {
        foreach (self::SHARED_PRODUCT_QUANTITIES as $sku => $quantity) {
            /** @var Product $product */
            $product = $sharedProducts->get($sku);

            $this->createOrderItem($order, $product, $quantity);
        }

        $remainingCount = self::ORDER_ITEM_COUNT - count(self::SHARED_PRODUCT_QUANTITIES);
        $productCount = $rotatingProducts->count();

        foreach (range(0, $remainingCount - 1) as $itemIndex) {
            /** @var Product $product */
            $product = $rotatingProducts[($shopOffset * $remainingCount + $itemIndex) % $productCount];
            $quantity = (float) (($itemIndex % 6) + 2 + ($shopOffset % 4));

            $this->createOrderItem($order, $product, $quantity);
        }
    }

    private function createOrderItem(ShopOrder $order, Product $product, float $quantity): void
    {
        $price = max(1.0, (float) ($product->base_price ?? 1));

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => $quantity,
            'approved_qty' => $quantity,
            'unit' => $product->unit,
            'locked_selling_price' => $price,
            'locked_price_source' => 'seeded_load',
            'line_total' => round($quantity * $price, 2),
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'pending',
            'is_sorted' => false,
            'delivered_qty' => 0,
            'shortage_qty' => 0,
            'unit_cost' => round($price * 0.82, 4),
            'shortage_value' => 0,
        ]);
    }
}
