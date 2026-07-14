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

class OwnedShopDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
        ]);

        $shops = collect([
            [
                'code' => 'SHOP_OWNED_01',
                'name' => 'Green Leaf Owned One',
                'warehouse_tag' => 'A',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'owner_name' => 'Owned Shop One Demo',
                'owner_email' => 'owner.one@greenleaf.com',
            ],
            [
                'code' => 'SHOP_OWNED_02',
                'name' => 'Green Leaf Owned Two',
                'warehouse_tag' => 'B',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'owner_name' => 'Owned Shop Two Demo',
                'owner_email' => 'owner.two@greenleaf.com',
            ],
            [
                'code' => 'SHOP_STANDARD_01',
                'name' => 'Green Leaf Standard One',
                'warehouse_tag' => 'C',
                'accounting_mode' => 'standard',
                'accounting_enabled' => false,
                'owner_name' => 'Standard Shop Demo',
                'owner_email' => 'owner.standard@greenleaf.com',
            ],
        ]);

        foreach ($shops as $shopSeed) {
            $shop = Shop::query()->updateOrCreate(
                ['code' => $shopSeed['code']],
                [
                    'name' => $shopSeed['name'],
                    'warehouse_tag' => $shopSeed['warehouse_tag'],
                    'status' => 'active',
                    'accounting_mode' => $shopSeed['accounting_mode'],
                    'accounting_enabled' => $shopSeed['accounting_enabled'],
                    'approved_at' => now(),
                ],
            );

            $owner = User::query()->updateOrCreate(
                ['email' => $shopSeed['owner_email']],
                [
                    'name' => $shopSeed['owner_name'],
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

            if ($shop->isOwnedAccountingEnabled()) {
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
            } else {
                ShopOwnership::query()
                    ->where('shop_id', $shop->id)
                    ->delete();
            }

            app(EmployeeSyncService::class)->ensureForUser($owner->fresh());
        }

        $this->command?->info('Owned shop demo seeder created 3 shops with 2 owned-accounting shops.');
    }
}
