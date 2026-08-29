<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Inventory\ProductWarehouseResolver;
use Illuminate\Console\Command;

class AssignRecommendedWarehousesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'greenleaf:assign-recommended-warehouses {--dry-run : Preview allocations without committing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely assign recommended warehouses to unallocated products using category and domain mappings';

    /**
     * Execute the console command.
     */
    public function handle(ProductWarehouseResolver $resolver): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $unallocated = Product::whereNull('default_warehouse_id')->with('category')->get();

        $this->info("Found {$unallocated->count()} unallocated products.");

        if ($unallocated->isEmpty()) {
            $this->info('No unallocated products found.');

            return self::SUCCESS;
        }

        $tableRows = [];
        $assignableCount = 0;
        $unresolvableCount = 0;

        foreach ($unallocated as $product) {
            $recommendedWarehouse = $resolver->recommendForProduct($product);

            if ($recommendedWarehouse !== null) {
                $assignableCount++;
                $tableRows[] = [
                    $product->id,
                    $product->name,
                    $product->category?->name ?? 'None',
                    "{$recommendedWarehouse->name} ({$recommendedWarehouse->code})",
                    $isDryRun ? 'Preview (would assign)' : 'Assigned',
                ];

                if (! $isDryRun) {
                    $product->update(['default_warehouse_id' => $recommendedWarehouse->id]);
                }
            } else {
                $unresolvableCount++;
                $tableRows[] = [
                    $product->id,
                    $product->name,
                    $product->category?->name ?? 'None',
                    'None',
                    'Needs manual review',
                ];
            }
        }

        $this->table(['Product ID', 'Name', 'Category', 'Recommended Warehouse', 'Status'], $tableRows);

        if ($isDryRun) {
            $this->warn("DRY RUN: {$assignableCount} products can be assigned, {$unresolvableCount} need manual review. No changes made.");
        } else {
            $this->info("Successfully assigned {$assignableCount} products. {$unresolvableCount} products remain unallocated for manual review.");
        }

        return self::SUCCESS;
    }
}
