<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class WarehouseWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('WarehouseWorkflowSeeder cleaned. No workflow orders, receipts, or invoices were seeded.');
    }
}
