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
        ]);

        $this->command?->info('Purchaser demo seeder cleaned. No shop orders, purchase orders, carts, receipts, or invoices were seeded.');
    }
}
