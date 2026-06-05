<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Purchasing\POStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoUserSeeder extends Seeder
{
    /**
     * Demo accounts for testing Phase 1.
     * All accounts use password: "password"
     *
     * @var array<int, array<string, string>>
     */
    private array $demoUsers = [
        [
            'name' => 'Administrator',
            'email' => 'admin@greenleaf.com',
            'role' => 'admin',
        ],
        [
            'name' => 'Shop Owner',
            'email' => 'shop@greenleaf.com',
            'role' => 'shop',
        ],
        [
            'name' => 'Purchase Manager',
            'email' => 'purchase@greenleaf.com',
            'role' => 'purchase',
        ],
        [
            'name' => 'Warehouse Manager',
            'email' => 'warehouse@greenleaf.com',
            'role' => 'warehouse',
        ],
    ];

    public function run(): void
    {
        // 1. Seed shops
        $shopsData = [
            ['code' => 'SHOP_CASIO', 'name' => 'Casio'],
            ['code' => 'SHOP_BUDEGERE', 'name' => 'Budegere'],
            ['code' => 'SHOP_GRANCITY', 'name' => 'Grancity'],
            ['code' => 'SHOP_ASHIRWAD', 'name' => 'Ashirwad'],
            ['code' => 'SHOP_SANA', 'name' => 'Sana'],
            ['code' => 'SHOP_BAZARO', 'name' => 'Bazaro'],
            ['code' => 'SHOP_SANA_JP', 'name' => 'Sana JP'],
            ['code' => 'SHOP_VARTHUR', 'name' => 'varthur'],
            ['code' => 'SHOP_GM', 'name' => 'GM'],
            ['code' => 'SHOP_HSR', 'name' => 'HSR'],
            ['code' => 'SHOP_BEGUR', 'name' => 'Begur'],
            ['code' => 'SHOP_JINDAL', 'name' => 'Jindal City'],
            ['code' => 'SHOP_CARRY', 'name' => 'Carry Food'],
            ['code' => 'SHOP_FORTUNE', 'name' => 'Fortune SM'],
        ];

        $seededShops = [];
        foreach ($shopsData as $sData) {
            $seededShops[] = Shop::updateOrCreate(
                ['code' => $sData['code']],
                [
                    'name' => $sData['name'],
                    'status' => 'active',
                ]
            );
        }

        // Keep SHOP_001 for legacy references
        $legacyShop = Shop::updateOrCreate(
            ['code' => 'SHOP_001'],
            [
                'name' => 'CASIO HYPERMARKET',
                'status' => 'active',
            ]
        );

        // Clean up any extra/old demo users
        Schema::disableForeignKeyConstraints();
        User::whereNotIn('email', array_column($this->demoUsers, 'email'))->delete();
        Schema::enableForeignKeyConstraints();

        foreach ($this->demoUsers as $demo) {
            $user = User::updateOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'shop_id' => in_array($demo['role'], ['shop'], true) ? $seededShops[0]->id : null,
                ]
            );

            $user->syncRoles([$demo['role']]);

            $this->command->line("  ✓ {$demo['role']}: {$demo['email']}");
        }

        // 2. Seed some products for order items
        $products = Product::limit(5)->get();
        if ($products->isNotEmpty()) {
            $shopOwner = User::where('email', 'shop@greenleaf.com')->first();
            // Seed a few past orders for Casio
            for ($i = 5; $i >= 1; $i--) {
                $date = now()->subDays($i);
                $order = ShopOrder::updateOrCreate(
                    [
                        'shop_id' => $seededShops[0]->id,
                        'business_date' => $date->format('Y-m-d'),
                    ],
                    [
                        'state' => 'approved',
                        'submitted_at' => $date->copy()->setTime(18, 30, 0),
                        'deadline_at' => $date->copy()->setTime(21, 30, 0),
                        'created_by' => $shopOwner->id,
                    ]
                );

                foreach ($products as $product) {
                    ShopOrderItem::updateOrCreate(
                        [
                            'shop_order_id' => $order->id,
                            'product_id' => $product->id,
                        ],
                        [
                            'requested_qty' => 15.00,
                            'approved_qty' => 12.00,
                            'unit' => $product->unit,
                            'notes' => 'Seeded automatically',
                        ]
                    );
                }
            }

            // Seed tomorrow's requisitions for some shops
            $tomorrow = Carbon::tomorrow()->format('Y-m-d');
            foreach ($seededShops as $index => $shop) {
                // Seed for roughly 75% of shops
                if ($index % 4 === 3) {
                    continue;
                }

                $order = ShopOrder::updateOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'business_date' => $tomorrow,
                    ],
                    [
                        'state' => 'submitted',
                        'submitted_at' => now()->setTime(19, 0, 0),
                        'deadline_at' => Carbon::tomorrow()->subDay()->setTime(21, 30, 0),
                        'created_by' => $shopOwner->id,
                    ]
                );

                // Seed random quantities for 3-5 random products
                $randomProducts = $products->random(min(3, $products->count()));
                foreach ($randomProducts as $product) {
                    $qty = (($shop->id * 3 + $product->id * 7) % 15) + 2.5;
                    ShopOrderItem::updateOrCreate(
                        [
                            'shop_order_id' => $order->id,
                            'product_id' => $product->id,
                        ],
                        [
                            'requested_qty' => $qty,
                            'unit' => $product->unit,
                            'notes' => 'Auto-seeded',
                        ]
                    );
                }
            }

            // 3. Seed some sample Purchase Orders
            $supplier1 = Supplier::where('name', 'Green Valley Farm')->first();
            $supplier2 = Supplier::where('name', 'Global Produce Direct')->first();
            $purchaseManager = User::where('email', 'purchase@greenleaf.com')->first();

            if ($supplier1 && $purchaseManager) {
                // PO 1: Warehouse (Bulk)
                $po1 = PurchaseOrder::updateOrCreate(
                    [
                        'po_number' => 'PO-'.now()->format('Ymd').'-W1',
                    ],
                    [
                        'supplier_id' => $supplier1->id,
                        'status' => POStatus::Approved,
                        'order_date' => now()->format('Y-m-d'),
                        'created_by' => $purchaseManager->id,
                        'fulfillment_type' => 'warehouse',
                        'notes' => 'Auto-seeded for testing',
                    ]
                );

                $product = Product::first();
                if ($product) {
                    $po1->items()->updateOrCreate(
                        ['product_id' => $product->id],
                        [
                            'quantity' => 150.00,
                            'unit_price' => 12.50,
                        ]
                    );
                }
            }

            if ($supplier2 && $purchaseManager) {
                // PO 2: Selection (Packet)
                $po2 = PurchaseOrder::updateOrCreate(
                    [
                        'po_number' => 'PO-'.now()->format('Ymd').'-S1',
                    ],
                    [
                        'supplier_id' => $supplier2->id,
                        'status' => POStatus::Approved,
                        'order_date' => now()->format('Y-m-d'),
                        'created_by' => $purchaseManager->id,
                        'fulfillment_type' => 'selection',
                        'notes' => 'Auto-seeded for testing',
                    ]
                );

                $product = Product::skip(1)->first();
                if ($product) {
                    $po2->items()->updateOrCreate(
                        ['product_id' => $product->id],
                        [
                            'quantity' => 80.00,
                            'unit_price' => 24.00,
                        ]
                    );
                }
            }

            // Seed more POs for the redesigned shop owner dashboard/tabs
            $adminUser = User::where('email', 'admin@greenleaf.com')->first();
            if ($supplier1 && $purchaseManager && $adminUser) {
                // Draft PO: PO-1022
                $poDraft = PurchaseOrder::updateOrCreate(
                    ['po_number' => 'PO-1022'],
                    [
                        'supplier_id' => $supplier1->id,
                        'status' => POStatus::Draft,
                        'order_date' => now()->subDay()->format('Y-m-d'),
                        'created_by' => $purchaseManager->id,
                        'fulfillment_type' => 'warehouse',
                        'notes' => 'Awaiting owner approval',
                    ]
                );
                $product = Product::first();
                if ($product) {
                    $poDraft->items()->updateOrCreate(
                        ['product_id' => $product->id],
                        [
                            'quantity' => 200.00,
                            'unit_price' => 15.00,
                        ]
                    );
                }

                // Approved PO: PO-1023
                $poApproved = PurchaseOrder::updateOrCreate(
                    ['po_number' => 'PO-1023'],
                    [
                        'supplier_id' => $supplier1->id,
                        'status' => POStatus::Approved,
                        'order_date' => now()->format('Y-m-d'),
                        'created_by' => $purchaseManager->id,
                        'fulfillment_type' => 'warehouse',
                        'notes' => 'Stock Required',
                    ]
                );
                if ($product) {
                    $poApproved->items()->updateOrCreate(
                        ['product_id' => $product->id],
                        [
                            'quantity' => 100.00,
                            'unit_price' => 12.50,
                        ]
                    );
                }

                // Add activity log for PO-1023 approval
                activity()
                    ->performedOn($poApproved)
                    ->causedBy($adminUser)
                    ->withProperties(['status' => 'approved', 'remarks' => 'Stock Required'])
                    ->log('Approved');

                // Rejected PO: PO-1021
                $poRejected = PurchaseOrder::updateOrCreate(
                    ['po_number' => 'PO-1021'],
                    [
                        'supplier_id' => $supplier1->id,
                        'status' => POStatus::Rejected,
                        'order_date' => now()->subDays(2)->format('Y-m-d'),
                        'created_by' => $purchaseManager->id,
                        'fulfillment_type' => 'warehouse',
                        'notes' => 'Duplicate Order',
                    ]
                );
                if ($product) {
                    $poRejected->items()->updateOrCreate(
                        ['product_id' => $product->id],
                        [
                            'quantity' => 50.00,
                            'unit_price' => 10.00,
                        ]
                    );
                }

                // Add activity log for PO-1021 rejection
                activity()
                    ->performedOn($poRejected)
                    ->causedBy($adminUser)
                    ->withProperties(['status' => 'rejected', 'remarks' => 'Duplicate Order'])
                    ->log('Rejected');
            }
        }

        $this->command->info('✅ Demo users, shop orders, and purchase orders seeded successfully. Password: password');
    }
}
