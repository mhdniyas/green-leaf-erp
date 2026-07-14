<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PurchaserDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            WarehouseSeeder::class,
            SupplierSeeder::class,
            DemoUserSeeder::class,
            ChartOfAccountsSeeder::class,
            PurchaseOrderSeeder::class,
        ]);

        $this->command?->info('Purchaser demo reference data and June 10 purchase orders seeded.');
    }
}
