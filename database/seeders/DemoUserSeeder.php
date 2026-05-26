<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
            'name' => 'Admin User',
            'email' => 'admin@greenleaf.com',
            'role' => 'admin',
        ],
        [
            'name' => 'Inventory Manager',
            'email' => 'manager@greenleaf.com',
            'role' => 'inventory-manager',
        ],
        [
            'name' => 'Cashier',
            'email' => 'cashier@greenleaf.com',
            'role' => 'cashier',
        ],
        [
            'name' => 'Sales Manager',
            'email' => 'sales@greenleaf.com',
            'role' => 'sales-manager',
        ],
        [
            'name' => 'Accountant',
            'email' => 'accounts@greenleaf.com',
            'role' => 'accountant',
        ],
        [
            'name' => 'Shop Owner',
            'email' => 'shop@greenleaf.com',
            'role' => 'shop-owner',
        ],
        [
            'name' => 'Viewer',
            'email' => 'viewer@greenleaf.com',
            'role' => 'viewer',
        ],
    ];

    public function run(): void
    {
        // 1. Seed default shop
        $shop = Shop::updateOrCreate(
            ['code' => 'SHOP_001'],
            [
                'name' => 'CASIO HYPERMARKET',
                'status' => 'active',
                'address' => 'Casio St, Kuala Lumpur',
                'contact_name' => 'John Casio',
                'contact_phone' => '+60123456789',
            ]
        );

        foreach ($this->demoUsers as $demo) {
            $user = User::updateOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'shop_id' => $demo['role'] === 'shop-owner' ? $shop->id : null,
                ]
            );

            $user->syncRoles([$demo['role']]);

            $this->command->line("  ✓ {$demo['role']}: {$demo['email']}");
        }

        // 2. Seed some products for order items
        $products = Product::limit(5)->get();
        if ($products->isNotEmpty()) {
            $shopOwner = User::where('email', 'shop@greenleaf.com')->first();
            // Seed a few past orders
            for ($i = 5; $i >= 1; $i--) {
                $date = now()->subDays($i);
                $order = ShopOrder::updateOrCreate(
                    [
                        'shop_id' => $shop->id,
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
        }

        $this->command->info('✅ Demo users and shop orders seeded successfully. Password: password');
    }
}
