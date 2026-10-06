<?php

declare(strict_types=1);

namespace App\Console\Commands\Cashbook;

use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopCashbookUiLayout;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProductEntry;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CleanDuplicateHeaderGroupsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashbook:clean-duplicate-headers 
                            {shop? : Shop ID, code, or slug}
                            {--dry-run : Inspect and report duplicates without committing changes}
                            {--reset-ui-layout : Clear custom visual UI layout cache for affected shops}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect, consolidate, and clean duplicate Cashbook header groups and unassigned expense categories for shops.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $shopIdentifier = $this->argument('shop');
        $isDryRun = (bool) $this->option('dry-run');
        $resetUiLayout = (bool) $this->option('reset-ui-layout');

        $query = Shop::query();
        if ($shopIdentifier) {
            $query->where(function ($q) use ($shopIdentifier): void {
                if (is_numeric($shopIdentifier)) {
                    $q->where('id', (int) $shopIdentifier);
                } else {
                    $q->where('code', $shopIdentifier)
                        ->orWhere('name', $shopIdentifier)
                        ->orWhereIn('id', function ($sub) use ($shopIdentifier): void {
                            $sub->select('shop_id')->from('shop_ledger_profiles')->where('slug', $shopIdentifier)->orWhere('code', $shopIdentifier);
                        });
                }
            });
        }

        $shops = $query->get();
        if ($shops->isEmpty()) {
            $this->error('No shop found matching the given identifier.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Scanning %d shop(s) for duplicate header groups%s...',
            $shops->count(),
            $isDryRun ? ' [DRY RUN]' : ''
        ));

        $totalMerged = 0;
        $totalAssigned = 0;

        foreach ($shops as $shop) {
            $this->line(sprintf('Checking Shop #%d (%s, %s)...', $shop->id, $shop->name, $shop->code ?? 'N/A'));

            $result = $this->processShop($shop, $isDryRun, $resetUiLayout);
            $totalMerged += $result['merged_headers_count'];
            $totalAssigned += $result['assigned_settings_count'];
        }

        $this->newLine();
        $this->info(sprintf(
            'Completed! Total duplicate headers cleaned/merged: %d. Total orphan settings assigned: %d.%s',
            $totalMerged,
            $totalAssigned,
            $isDryRun ? ' (Dry run: no changes saved)' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{merged_headers_count: int, assigned_settings_count: int}
     */
    private function processShop(Shop $shop, bool $isDryRun, bool $resetUiLayout): array
    {
        $mergedCount = 0;
        $assignedCount = 0;

        /** @var Collection<int, ShopLedgerHeaderGroup> $headerGroups */
        $headerGroups = ShopLedgerHeaderGroup::query()
            ->where('shop_id', (int) $shop->id)
            ->orderBy('id')
            ->get();

        // Group headers by normalized name + type
        $grouped = $headerGroups->groupBy(function (ShopLedgerHeaderGroup $h): string {
            $norm = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $h->name));

            return $h->type.'_'.$norm;
        });

        foreach ($grouped as $groupKey => $headers) {
            if ($headers->count() <= 1) {
                continue;
            }

            /** @var ShopLedgerHeaderGroup $primaryHeader */
            $primaryHeader = $headers->first();
            $duplicates = $headers->slice(1);

            $this->warn(sprintf(
                '  Found %d duplicate header(s) for "%s" (%s). Primary Header ID: %d',
                $duplicates->count(),
                $primaryHeader->name,
                $primaryHeader->type,
                $primaryHeader->id
            ));

            foreach ($duplicates as $dup) {
                $this->line(sprintf('    Merging duplicate Header ID %d into Primary ID %d...', $dup->id, $primaryHeader->id));

                if (! $isDryRun) {
                    DB::transaction(function () use ($dup, $primaryHeader, $shop): void {
                        // 1. Re-assign settings
                        ShopLedgerEntrySetting::query()
                            ->where('shop_id', (int) $shop->id)
                            ->where('header_group_id', (int) $dup->id)
                            ->update(['header_group_id' => (int) $primaryHeader->id]);

                        // 2. Re-assign relation items
                        ShopCashbookRelationItem::query()
                            ->where('header_group_id', (int) $dup->id)
                            ->update(['header_group_id' => (int) $primaryHeader->id]);

                        // 3. Re-assign product entries
                        ShopLedgerProductEntry::query()
                            ->where('shop_id', (int) $shop->id)
                            ->where('header_group_id', (int) $dup->id)
                            ->update(['header_group_id' => (int) $primaryHeader->id]);

                        // 4. Detach allowed products & delete duplicate header
                        $dup->allowedProducts()->detach();
                        $dup->delete();
                    });
                }

                $mergedCount++;
            }
        }

        // Check for orphan expense settings (header_group_id is null)
        $orphanExpenseSettings = ShopLedgerEntrySetting::query()
            ->where('shop_id', (int) $shop->id)
            ->whereNull('header_group_id')
            ->where('enabled', true)
            ->where(function ($q): void {
                $q->where('include_in_expense', true)
                    ->orWhereHas('entryType', fn ($t) => $t->where('category', 'expense'));
            })
            ->get();

        if ($orphanExpenseSettings->isNotEmpty()) {
            // Find target expense header group (prefer "OTHER EXPENSES" / "Other Expense", or first active expense header)
            $targetExpenseHeader = $headerGroups->first(function (ShopLedgerHeaderGroup $h): bool {
                $norm = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $h->name));

                return $h->type === 'expense' && $h->enabled && in_array($norm, ['otherexpenses', 'otherexpense', 'expenses', 'shopexpenses'], true);
            }) ?: $headerGroups->firstWhere(fn (ShopLedgerHeaderGroup $h): bool => $h->type === 'expense' && $h->enabled);

            if ($targetExpenseHeader) {
                $this->info(sprintf(
                    '  Assigning %d orphan expense setting(s) to Header "%s" (ID: %d)...',
                    $orphanExpenseSettings->count(),
                    $targetExpenseHeader->name,
                    $targetExpenseHeader->id
                ));

                if (! $isDryRun) {
                    ShopLedgerEntrySetting::query()
                        ->whereIn('id', $orphanExpenseSettings->pluck('id'))
                        ->update(['header_group_id' => (int) $targetExpenseHeader->id]);
                }

                $assignedCount += $orphanExpenseSettings->count();
            }
        }

        if ($resetUiLayout && ! $isDryRun) {
            ShopCashbookUiLayout::query()->where('shop_id', (int) $shop->id)->delete();
            $this->line('  Custom visual UI layout cache cleared.');
        }

        return [
            'merged_headers_count' => $mergedCount,
            'assigned_settings_count' => $assignedCount,
        ];
    }
}
