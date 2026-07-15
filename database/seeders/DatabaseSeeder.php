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
            ChartOfAccountsSeeder::class,
            EmployeeCategorySeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            WarehouseSeeder::class,
            ShopAccountingCategorySeeder::class,
            WarehouseWorkflowSeeder::class,
            AdminOwnPurchasePurchaserSeeder::class,
        ]);
    }
}
