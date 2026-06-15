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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PurchaserRoleTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $products = Product::query()->where('is_active', true)->get();

            if ($products->isEmpty()) {
                $this->command?->error('No active products found. Please seed products first.');

                return;
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

            // Create users (owners) for the shops
            foreach ($shops as $shop) {
                $email = 'shop-'.strtolower(str_replace('SHOP_', '', $shop->code)).'@greenleaf.com';
                if ($shop->code === 'SHOP_CASIO') {
                    $email = 'shop@greenleaf.com';
                }

                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $shop->name.' Owner',
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                        'shop_id' => $shop->id,
                    ]
                );
                $user->syncRoles(['shop']);
            }

            $this->command?->info('Ensured shop owner users are seeded.');

            // Generate 20 dates (last 20 days leading to today)
            $dates = [];
            for ($i = 19; $i >= 0; $i--) {
                $dates[] = Carbon::today()->subDays($i);
            }

            $this->command?->info('Seeding orders for the last 20 days...');

            // Disable model events for performance
            ShopOrder::unsetEventDispatcher();
            ShopOrderItem::unsetEventDispatcher();

            $orderCount = 0;
            foreach ($shops as $shop) {
                $shopOwner = $shop->users()->first();

                foreach ($dates as $date) {
                    $orderDateString = $date->toDateString();

                    // Check if order already exists
                    $order = ShopOrder::where('shop_id', $shop->id)
                        ->whereDate('business_date', $date)
                        ->first();

                    if (! $order) {
                        $datePrefix = $date->format('Ymd');
                        $suffix = strtoupper(substr(md5($shop->code.$orderDateString), 0, 4));
                        $orderNumber = "RQ-{$datePrefix}-{$suffix}";

                        $order = ShopOrder::create([
                            'shop_id' => $shop->id,
                            'order_number' => $orderNumber,
                            'state' => 'approved',
                            'delivery_status' => 'pending_delivery',
                            'business_date' => $orderDateString,
                            'submitted_at' => $date->copy()->subDay()->setTime(18, 0, 0),
                            'deadline_at' => $date->copy()->subDay()->setTime(21, 30, 0),
                            'created_by' => $shopOwner->id,
                            'is_allocation_completed' => false,
                            'is_delivered' => false,
                        ]);
                    } else {
                        $order->update([
                            'state' => 'approved',
                        ]);
                    }

                    // Pick 5 to 10 random products
                    $randomProducts = $products->random(rand(5, 10));

                    foreach ($randomProducts as $product) {
                        $requestedQty = (float) rand(5, 50);
                        $approvedQty = $requestedQty;
                        $price = (float) ($product->base_price ?? rand(20, 150));

                        ShopOrderItem::updateOrCreate(
                            [
                                'shop_order_id' => $order->id,
                                'product_id' => $product->id,
                            ],
                            [
                                'product_grade' => 'A',
                                'requested_qty' => $requestedQty,
                                'approved_qty' => $approvedQty,
                                'unit' => $product->unit,
                                'locked_selling_price' => $price,
                                'line_total' => $approvedQty * $price,
                                'fulfillment_type' => 'warehouse',
                                'sorting_status' => 'pending',
                            ]
                        );
                    }
                    $orderCount++;
                }
            }

            $this->command?->info("Seeded {$orderCount} orders with items successfully.");
        });
    }
}
