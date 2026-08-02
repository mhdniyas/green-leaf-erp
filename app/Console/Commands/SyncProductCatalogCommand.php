<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\ProductSeeder;
use Illuminate\Console\Command;

class SyncProductCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'greenleaf:sync-product-catalog';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Explicitly synchronize product catalog using ProductSeeder without overwriting admin edits';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting product catalog synchronization...');
        
        $seeder = new ProductSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        $this->info('Product catalog synchronization completed.');

        return self::SUCCESS;
    }
}
