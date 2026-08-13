<?php

declare(strict_types=1);

namespace Database\Seeders\Cashbook;

use Illuminate\Database\Seeder;

/**
 * Master Cashbook Seeder.
 * Seeds entry types, company accounts, clients, config presets, and connects
 * all owned shops dynamically from Green Leaf ERP.
 *
 * Run with: php artisan db:seed --class="Database\Seeders\Cashbook\CashbookSeeder"
 */
class CashbookSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LedgerEntryTypeSeeder::class,
            CompanyAccountSeeder::class,
            LedgerClientSeeder::class,
            ShopConfigPresetSeeder::class,
            GreenLeafOwnedShopsSeeder::class,
        ]);
    }
}
