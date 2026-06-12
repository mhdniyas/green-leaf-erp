<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
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
            PriceBoardSeeder::class,
            ChartOfAccountsSeeder::class,
            WarehouseWorkflowSeeder::class,
        ]);
    }
}
