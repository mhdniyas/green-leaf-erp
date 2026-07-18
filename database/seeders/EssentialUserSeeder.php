<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\ShopOwnership;
use App\Models\User;
use App\Services\HR\EmployeeSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EssentialUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('BASE_ROLE_USER_PASSWORD');

        foreach ($this->roleAccounts() as $account) {
            $user = $this->upsertUser(
                name: $account['name'],
                email: $account['email'],
                password: $password,
                shop: null,
            );

            $user->syncRoles([$account['role']]);
            app(EmployeeSyncService::class)->ensureForUser($user->fresh());
        }

        foreach ($this->shops() as $shopSeed) {
            $shop = Shop::query()->updateOrCreate(
                ['code' => $shopSeed['code']],
                [
                    'name' => $shopSeed['name'],
                    'warehouse_tag' => $shopSeed['warehouse_tag'],
                    'status' => 'active',
                    'accounting_mode' => 'owned',
                    'accounting_enabled' => true,
                    'approved_at' => now(),
                ],
            );

            $owner = $this->upsertUser(
                name: $shopSeed['name'].' Owner',
                email: $shopSeed['owner_email'],
                password: $password,
                shop: $shop,
            );

            $owner->syncRoles(['shop']);

            ShopOwnerAssignment::query()->updateOrCreate([
                'user_id' => $owner->id,
                'shop_id' => $shop->id,
            ]);

            ShopOwnership::query()->updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'user_id' => $owner->id,
                ],
                [
                    'owner_name' => $owner->name,
                    'ownership_percent' => 100,
                    'role_label' => 'Primary Owner',
                ],
            );

            app(EmployeeSyncService::class)->ensureForUser($owner->fresh());
        }

        $this->command?->info('Essential role users and real shop-owner users seeded successfully.');
    }

    private function upsertUser(string $name, string $email, ?string $password, ?Shop $shop): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $attributes = [
            'name' => $name,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'shop_id' => $shop?->id,
            'registration_status' => 'approved',
            'approved_at' => $user->approved_at ?? now(),
            'approved_by' => null,
        ];

        if ($password !== null && $password !== '') {
            $attributes['password'] = Hash::make($password);
        } elseif (! $user->exists) {
            $attributes['password'] = Hash::make(Str::password(32));
        }

        $user->forceFill($attributes)->save();

        return $user;
    }

    /**
     * @return array<int, array{name:string, email:string, role:string}>
     */
    private function roleAccounts(): array
    {
        return [
            ['name' => 'Administrator', 'email' => 'admin@greenleaf.com', 'role' => 'admin'],
            ['name' => 'HR Manager', 'email' => 'hr@greenleaf.com', 'role' => 'hr_manager'],
            ['name' => 'Purchase Manager', 'email' => 'purchase@greenleaf.com', 'role' => 'purchase'],
            ['name' => 'Purchaser', 'email' => 'purchaser@greenleaf.com', 'role' => 'purchaser'],
            ['name' => 'Warehouse Receiver', 'email' => 'receiver@greenleaf.com', 'role' => 'warehouse_receiver'],
        ];
    }

    /**
     * @return array<int, array{code:string, name:string, warehouse_tag:string, owner_email:string}>
     */
    private function shops(): array
    {
        return [
            ['code' => 'SHOP_CASIO', 'name' => 'Casio Hypermarket', 'warehouse_tag' => 'A', 'owner_email' => 'shop-casio@greenleaf.com'],
            ['code' => 'SHOP_BUDEGERE', 'name' => 'Budegere', 'warehouse_tag' => 'B', 'owner_email' => 'shop-budegere@greenleaf.com'],
            ['code' => 'SHOP_GRANCITY', 'name' => 'Grancity', 'warehouse_tag' => 'C', 'owner_email' => 'shop-grancity@greenleaf.com'],
            ['code' => 'SHOP_ASHIRWAD', 'name' => 'Ashirwad', 'warehouse_tag' => 'D', 'owner_email' => 'shop-ashirwad@greenleaf.com'],
            ['code' => 'SHOP_METRO', 'name' => 'Metro Retail', 'warehouse_tag' => 'E', 'owner_email' => 'shop-metro@greenleaf.com'],
            ['code' => 'SHOP_RELIANCE', 'name' => 'Reliance Fresh', 'warehouse_tag' => 'F', 'owner_email' => 'shop-reliance@greenleaf.com'],
            ['code' => 'SHOP_SPAR', 'name' => 'Spar Hypermarket', 'warehouse_tag' => 'G', 'owner_email' => 'shop-spar@greenleaf.com'],
            ['code' => 'SHOP_MORE', 'name' => 'More Supermarket', 'warehouse_tag' => 'H', 'owner_email' => 'shop-more@greenleaf.com'],
            ['code' => 'SHOP_LULU', 'name' => 'Lulu Express', 'warehouse_tag' => 'I', 'owner_email' => 'shop-lulu@greenleaf.com'],
            ['code' => 'SHOP_STAR', 'name' => 'Star Bazaar', 'warehouse_tag' => 'J', 'owner_email' => 'shop-star@greenleaf.com'],
            ['code' => 'SHOP_FOODWORLD', 'name' => 'Foodworld', 'warehouse_tag' => 'K', 'owner_email' => 'shop-foodworld@greenleaf.com'],
            ['code' => 'SHOP_NILGIRIS', 'name' => 'Nilgiris', 'warehouse_tag' => 'L', 'owner_email' => 'shop-nilgiris@greenleaf.com'],
            ['code' => 'SHOP_DMART', 'name' => 'DMart', 'warehouse_tag' => 'M', 'owner_email' => 'shop-dmart@greenleaf.com'],
            ['code' => 'SHOP_EASYDAY', 'name' => 'Easyday', 'warehouse_tag' => 'N', 'owner_email' => 'shop-easyday@greenleaf.com'],
        ];
    }
}
