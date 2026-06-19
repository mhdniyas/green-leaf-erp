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
            ['name' => 'Administrator', 'email' => 'admin@greenleaf.com', 'role' => 'admin', 'shop_code' => null, 'password' => 'Admin11'],
            ['name' => 'Purchase Manager', 'email' => 'purchase@greenleaf.com', 'role' => 'purchase', 'shop_code' => null, 'password' => 'Purchase12'],
            ['name' => 'Warehouse Manager', 'email' => 'warehouse@greenleaf.com', 'role' => 'warehouse', 'shop_code' => null, 'password' => 'Warehouse13'],
            ['name' => 'Purchaser Niyas', 'email' => 'purchaser@greenleaf.com', 'role' => 'purchaser', 'shop_code' => null, 'password' => 'Purchaser14'],
            ['name' => 'Purchaser Rahul', 'email' => 'purchaser2@greenleaf.com', 'role' => 'purchaser', 'shop_code' => null, 'password' => 'Purchaser15'],
            ['name' => 'Warehouse Receiver', 'email' => 'receiver@greenleaf.com', 'role' => 'warehouse_receiver', 'shop_code' => null, 'password' => 'Receiver16'],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'email_verified_at' => now(),
                    'shop_id' => $account['shop_code'] ? $shops[$account['shop_code']]->id : null,
                    'registration_status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => null,
                ]
            );

            $user->syncRoles([$account['role']]);
        }

        $this->command?->info('Core staff accounts seeded. Shop records are available for self-registration.');
    }
}
