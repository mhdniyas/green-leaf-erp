<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Purchasing\PurchaserReadCacheService;
use Database\Seeders\ProductSeeder;
use Illuminate\Console\Command;

class CatalogCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'greenleaf:catalog-cleanup {--confirm : Force execution without interactive prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate products that are not part of the standard catalog seeder';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('confirm')) {
            if (! $this->confirm('This will deactivate all manually created products not in the catalog seeder. Are you sure?')) {
                $this->info('Catalog cleanup cancelled.');

                return self::SUCCESS;
            }
        }

        // Run catalog seeder to gather catalog SKUs
        $seeder = new ProductSeeder;
        $reflector = new \ReflectionClass($seeder);
        $method = $reflector->getMethod('catalog');
        $method->setAccessible(true);
        $catalog = $method->invoke($seeder);

        $catalogSkus = collect($catalog)->pluck('sku')->all();

        $deactivated = Product::query()
            ->whereNotIn('sku', $catalogSkus)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        if ($deactivated > 0) {
            app(PurchaserReadCacheService::class)->invalidate(['products']);
        }

        $this->info("Catalog cleanup complete. Deactivated {$deactivated} non-catalog products.");

        return self::SUCCESS;
    }
}
