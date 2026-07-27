<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $aishwaryaVeg = Client::query()->updateOrCreate(
            ['code' => 'AISHWARYA_VEG'],
            [
                'name' => 'Aishwarya Veg',
                'status' => 'active',
                'notes' => 'Default client for client-shop accounting.',
            ],
        );

        Shop::query()
            ->where('accounting_enabled', true)
            ->where('accounting_mode', 'owned')
            ->whereNull('client_id')
            ->update(['client_id' => $aishwaryaVeg->id]);
    }
}
