<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Cashbook\CashbookSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CashbookSeeder::class,
        ]);
    }
}
