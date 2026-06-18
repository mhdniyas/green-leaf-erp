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
            ['name' => 'Casio Shop Owner', 'email' => 'shop@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_CASIO', 'password' => 'Casio17'],
            ['name' => 'Budegere Shop Owner', 'email' => 'shop-budegere@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_BUDEGERE', 'password' => 'Budegere18'],
            ['name' => 'Grancity Shop Owner', 'email' => 'shop-grancity@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_GRANCITY', 'password' => 'Grancity19'],
            ['name' => 'Ashirwad Shop Owner', 'email' => 'shop-ashirwad@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_ASHIRWAD', 'password' => 'Ashirwad20'],
            ['name' => 'Metro Retail Owner', 'email' => 'shop-metro@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_METRO', 'password' => 'Metro21'],
            ['name' => 'Reliance Fresh Owner', 'email' => 'shop-reliance@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_RELIANCE', 'password' => 'Reliance22'],
            ['name' => 'Spar Hypermarket Owner', 'email' => 'shop-spar@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_SPAR', 'password' => 'Spar23'],
            ['name' => 'More Supermarket Owner', 'email' => 'shop-more@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_MORE', 'password' => 'More24'],
            ['name' => 'Lulu Express Owner', 'email' => 'shop-lulu@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_LULU', 'password' => 'Lulu25'],
            ['name' => 'Star Bazaar Owner', 'email' => 'shop-star@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_STAR', 'password' => 'Star26'],
            ['name' => 'Foodworld Owner', 'email' => 'shop-foodworld@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_FOODWORLD', 'password' => 'Foodworld27'],
            ['name' => 'Nilgiris Owner', 'email' => 'shop-nilgiris@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_NILGIRIS', 'password' => 'Nilgiris28'],
            ['name' => 'DMart Owner', 'email' => 'shop-dmart@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_DMART', 'password' => 'Dmart29'],
            ['name' => 'Easyday Owner', 'email' => 'shop-easyday@greenleaf.com', 'role' => 'shop', 'shop_code' => 'SHOP_EASYDAY', 'password' => 'Easyday30'],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'email_verified_at' => now(),
                    'shop_id' => $account['shop_code'] ? $shops[$account['shop_code']]->id : null,
                ]
            );

            $user->syncRoles([$account['role']]);
        }

        $this->command?->info('Core role accounts seeded. Shop owner accounts: 14. Passwords updated with two-digit suffixes.');
    }
}
