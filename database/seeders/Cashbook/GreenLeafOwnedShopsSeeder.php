<?php

declare(strict_types=1);

namespace Database\Seeders\Cashbook;

use App\Services\Cashbook\CashbookShopSyncService;
use Illuminate\Database\Seeder;

class GreenLeafOwnedShopsSeeder extends Seeder
{
    /**
     * Connects and synchronizes existing owned shops from Green Leaf ERP into Cashbook profiles.
     */
    public function run(): void
    {
        $syncService = app(CashbookShopSyncService::class);
        $profiles    = $syncService->syncAndGetProfiles();

        $this->command->info('Connected ' . $profiles->count() . ' client-owned & direct shops from Green Leaf ERP into Cashbook.');
    }
}
