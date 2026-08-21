<?php

declare(strict_types=1);

namespace Database\Seeders\Cashbook;

use App\Models\Cashbook\LedgerClient;
use Illuminate\Database\Seeder;

class LedgerClientSeeder extends Seeder
{
    public function run(): void
    {
        // Primary retail client in Green Leaf ERP
        LedgerClient::updateOrCreate(
            ['slug' => 'aiswarya-veg'],
            [
                'name' => 'Aiswarya Veg',
                'contact_name' => 'Aiswarya',
                'contact_phone' => null,
                'gstin' => null,
                'address' => 'Bengaluru, Karnataka',
                'enabled' => true,
            ]
        );
    }
}
