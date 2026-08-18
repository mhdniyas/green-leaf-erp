<?php

declare(strict_types=1);

namespace Database\Seeders\Cashbook;

use Illuminate\Database\Seeder;

/**
 * Master Cashbook Seeder.
 * Production-safe default: only seeds system catalog rows required by the
 * cashbook engine. Run optional setup seeders manually for local/staging data.
 *
 * Run with: php artisan db:seed --class="Database\Seeders\Cashbook\CashbookSeeder"
 */
class CashbookSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LedgerEntryTypeSeeder::class,
        ]);
    }
}
