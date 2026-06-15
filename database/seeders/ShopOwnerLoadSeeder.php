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
use Illuminate\Support\Facades\Hash;

class ShopOwnerLoadSeeder extends Seeder
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
     * @var array<int, array{code: string, name: string, warehouse_tag: string, owner_name: string, owner_email: string, contact_phone: string}>
     */
    private const LOAD_SHOPS = [
        ['code' => 'SHOP_CASIO', 'name' => 'Casio Hypermarket', 'warehouse_tag' => 'A', 'owner_name' => 'Casio Shop Owner', 'owner_email' => 'shop@greenleaf.com', 'contact_phone' => '9100000001'],
        ['code' => 'SHOP_BUDEGERE', 'name' => 'Budegere', 'warehouse_tag' => 'B', 'owner_name' => 'Budegere Shop Owner', 'owner_email' => 'shop-budegere@greenleaf.com', 'contact_phone' => '9100000002'],
        ['code' => 'SHOP_GRANCITY', 'name' => 'Grancity', 'warehouse_tag' => 'C', 'owner_name' => 'Grancity Shop Owner', 'owner_email' => 'shop-grancity@greenleaf.com', 'contact_phone' => '9100000003'],
        ['code' => 'SHOP_ASHIRWAD', 'name' => 'Ashirwad', 'warehouse_tag' => 'D', 'owner_name' => 'Ashirwad Shop Owner', 'owner_email' => 'shop-ashirwad@greenleaf.com', 'contact_phone' => '9100000004'],
        ['code' => 'SHOP_LOAD_05', 'name' => 'RT Nagar', 'warehouse_tag' => 'E', 'owner_name' => 'RT Nagar Shop Owner', 'owner_email' => 'shop-load-05@greenleaf.com', 'contact_phone' => '9100000005'],
        ['code' => 'SHOP_LOAD_06', 'name' => 'Indiranagar', 'warehouse_tag' => 'F', 'owner_name' => 'Indiranagar Shop Owner', 'owner_email' => 'shop-load-06@greenleaf.com', 'contact_phone' => '9100000006'],
        ['code' => 'SHOP_LOAD_07', 'name' => 'Whitefield', 'warehouse_tag' => 'G', 'owner_name' => 'Whitefield Shop Owner', 'owner_email' => 'shop-load-07@greenleaf.com', 'contact_phone' => '9100000007'],
        ['code' => 'SHOP_LOAD_08', 'name' => 'Jayanagar', 'warehouse_tag' => 'H', 'owner_name' => 'Jayanagar Shop Owner', 'owner_email' => 'shop-load-08@greenleaf.com', 'contact_phone' => '9100000008'],
        ['code' => 'SHOP_LOAD_09', 'name' => 'Koramangala', 'warehouse_tag' => 'I', 'owner_name' => 'Koramangala Shop Owner', 'owner_email' => 'shop-load-09@greenleaf.com', 'contact_phone' => '9100000009'],
        ['code' => 'SHOP_LOAD_10', 'name' => 'Hebbal', 'warehouse_tag' => 'J', 'owner_name' => 'Hebbal Shop Owner', 'owner_email' => 'shop-load-10@greenleaf.com', 'contact_phone' => '9100000010'],
        ['code' => 'SHOP_LOAD_11', 'name' => 'Hennur', 'warehouse_tag' => 'K', 'owner_name' => 'Hennur Shop Owner', 'owner_email' => 'shop-load-11@greenleaf.com', 'contact_phone' => '9100000011'],
        ['code' => 'SHOP_LOAD_12', 'name' => 'Marathahalli', 'warehouse_tag' => 'L', 'owner_name' => 'Marathahalli Shop Owner', 'owner_email' => 'shop-load-12@greenleaf.com', 'contact_phone' => '9100000012'],
        ['code' => 'SHOP_LOAD_13', 'name' => 'Kalyan Nagar', 'warehouse_tag' => 'M', 'owner_name' => 'Kalyan Nagar Shop Owner', 'owner_email' => 'shop-load-13@greenleaf.com', 'contact_phone' => '9100000013'],
        ['code' => 'SHOP_LOAD_14', 'name' => 'Electronic City', 'warehouse_tag' => 'N', 'owner_name' => 'Electronic City Shop Owner', 'owner_email' => 'shop-load-14@greenleaf.com', 'contact_phone' => '9100000014'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $businessDate = Carbon::yesterday()->startOfDay();
            $products = Product::query()
                ->active()
                ->orderBy('id')
                ->get(['id', 'sku', 'unit', 'base_price']);

            if ($products->count() < 200) {
                throw new \RuntimeException('ShopOwnerLoadSeeder requires at least 200 active products.');
            }

            $sharedProducts = $products
                ->whereIn('sku', array_keys(self::SHARED_PRODUCT_QUANTITIES))
                ->keyBy('sku');

            if ($sharedProducts->count() !== count(self::SHARED_PRODUCT_QUANTITIES)) {
                throw new \RuntimeException('ShopOwnerLoadSeeder is missing required shared products.');
            }

            $rotatingProducts = $products
                ->reject(fn (Product $product): bool => array_key_exists($product->sku, self::SHARED_PRODUCT_QUANTITIES))
                ->values();

            collect(self::LOAD_SHOPS)
                ->values()
                ->each(function (array $definition, int $index) use ($businessDate, $sharedProducts, $rotatingProducts): void {
                    $shop = $this->upsertShop($definition);
                    $owner = $this->upsertShopOwner($shop, $definition);
                    $order = $this->upsertOrder($shop, $owner, $businessDate, $index + 1);

                    ShopOrderItem::query()
                        ->where('shop_order_id', $order->id)
                        ->delete();

                    $this->seedOrderItems(
                        order: $order,
                        sharedProducts: $sharedProducts,
                        rotatingProducts: $rotatingProducts,
                        shopOffset: $index,
                    );
                });
        });

        $this->command?->info('Shop owner load seed complete for previous day orders.');
    }

    /**
     * @param  array{code: string, name: string, warehouse_tag: string, owner_name: string, owner_email: string, contact_phone: string}  $definition
     */
    private function upsertShop(array $definition): Shop
    {
        return Shop::query()->updateOrCreate(
            ['code' => $definition['code']],
            [
                'name' => $definition['name'],
                'warehouse_tag' => $definition['warehouse_tag'],
                'status' => 'active',
                'contact_name' => $definition['owner_name'],
                'contact_phone' => $definition['contact_phone'],
            ]
        );
    }

    /**
     * @param  array{code: string, name: string, warehouse_tag: string, owner_name: string, owner_email: string, contact_phone: string}  $definition
     */
    private function upsertShopOwner(Shop $shop, array $definition): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $definition['owner_email']],
            [
                'name' => $definition['owner_name'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'shop_id' => $shop->id,
            ]
        );

        $user->syncRoles(['shop']);

        return $user;
    }

    private function upsertOrder(Shop $shop, User $owner, Carbon $businessDate, int $sequence): ShopOrder
    {
        return ShopOrder::query()->updateOrCreate(
            ['order_number' => sprintf('RQ-LOAD-%s-%02d', $businessDate->format('Ymd'), $sequence)],
            [
                'shop_id' => $shop->id,
                'state' => 'approved',
                'delivery_status' => 'pending_delivery',
                'payment_status' => 'pending',
                'business_date' => $businessDate->toDateString(),
                'submitted_at' => $businessDate->copy()->subDay()->setTime(20, 15),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
                'created_by' => $owner->id,
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
    }

    /**
     * @param  Collection<int, Product>  $sharedProducts
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

            $this->createOrderItem(
                order: $order,
                product: $product,
                quantity: $quantity,
                notes: 'Shared demand item seeded across multiple shops.',
            );
        }

        $remainingCount = self::ORDER_ITEM_COUNT - count(self::SHARED_PRODUCT_QUANTITIES);
        $productCount = $rotatingProducts->count();

        foreach (range(0, $remainingCount - 1) as $itemIndex) {
            /** @var Product $product */
            $product = $rotatingProducts[($shopOffset * $remainingCount + $itemIndex) % $productCount];
            $quantity = (float) (($itemIndex % 6) + 2 + ($shopOffset % 4));

            $this->createOrderItem(
                order: $order,
                product: $product,
                quantity: $quantity,
                notes: 'Seeded load order item for purchaser volume testing.',
            );
        }
    }

    private function createOrderItem(ShopOrder $order, Product $product, float $quantity, string $notes): void
    {
        $price = max(1.0, (float) ($product->base_price ?? 1));

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => $quantity,
            'approved_qty' => $quantity,
            'unit' => $product->unit ?: 'kg',
            'locked_selling_price' => $price,
            'locked_price_source' => 'seeded_load',
            'line_total' => round($quantity * $price, 2),
            'notes' => $notes,
            'fulfillment_type' => 'warehouse',
            'is_sorted' => false,
            'sorting_status' => 'pending',
            'delivered_qty' => 0,
            'shortage_qty' => 0,
            'unit_cost' => round($price * 0.82, 4),
            'shortage_value' => 0,
        ]);
    }
}
