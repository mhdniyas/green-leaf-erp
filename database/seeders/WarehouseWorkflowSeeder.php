<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class WarehouseWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(JulySeventeenShopOwnerPurchaseOrderSeeder::class);

        $this->command?->info('Warehouse workflow seeded from July 17 shop-owner purchase orders.');
    }
}
