<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Seed the core shops and role-based login accounts used across the app.
     */
    public function run(): void
    {
        $shops = collect([
            ['code' => 'SHOP_CASIO', 'name' => 'Casio Hypermarket', 'warehouse_tag' => 'A'],
            ['code' => 'SHOP_BUDEGERE', 'name' => 'Budegere', 'warehouse_tag' => 'B'],
            ['code' => 'SHOP_GRANCITY', 'name' => 'Grancity', 'warehouse_tag' => 'C'],
            ['code' => 'SHOP_ASHIRWAD', 'name' => 'Ashirwad', 'warehouse_tag' => 'D'],
        ])->mapWithKeys(function (array $shop): array {
            $record = Shop::updateOrCreate(
                ['code' => $shop['code']],
                [
                    'name' => $shop['name'],
                    'warehouse_tag' => $shop['warehouse_tag'],
                    'status' => 'active',
                ]
            );

            return [$shop['code'] => $record];
        });

        $accounts = [
            ['name' => 'Administrator', 'email' => 'admin@greenleaf.com', 'role' => 'admin', 'shop_code' => null],
            ['name' => 'Purchase Manager', 'email' => 'purchase@greenleaf.com', 'role' => 'purchase', 'shop_code' => null],
            ['name' => 'Warehouse Manager', 'email' => 'warehouse@greenleaf.com', 'role' => 'warehouse', 'shop_code' => null],
            ['name' => 'Purchaser', 'email' => 'purchaser@greenleaf.com', 'role' => 'purchaser', 'shop_code' => null],
            ['name' => 'Warehouse Receiver', 'email' => 'receiver@greenleaf.com', 'role' => 'warehouse_receiver', 'shop_code' => null],
            ['name' => 'Casio Shop Owner', 'email' => 'shop@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_CASIO'],
            ['name' => 'Budegere Shop Owner', 'email' => 'shop-budegere@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_BUDEGERE'],
            ['name' => 'Grancity Shop Owner', 'email' => 'shop-grancity@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_GRANCITY'],
            ['name' => 'Ashirwad Shop Owner', 'email' => 'shop-ashirwad@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_ASHIRWAD'],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'shop_id' => $account['shop_code'] ? $shops[$account['shop_code']]->id : null,
                ]
            );

            $user->syncRoles([$account['role']]);
        }

        $this->command?->info('Core role accounts seeded. Password for all users: password');
    }
}
