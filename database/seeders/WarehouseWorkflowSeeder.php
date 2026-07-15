<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class WarehouseWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(WarehouseReceiverSeeder::class);

        $this->command?->info('Warehouse workflow receiver handoff seeded.');
    }
}
