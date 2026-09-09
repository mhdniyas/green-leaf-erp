<?php

declare(strict_types=1);

namespace App\Console\Commands\Purchasing;

use App\Models\GoodsReceived;
use App\Models\Warehouse;
use App\Services\Purchasing\WarehouseReceiptReadScope;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ClearOldAdvancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:clear-old-advances
                            {--through=2026-09-03 : The cutoff date (inclusive) for clearing old advances}
                            {--apply : Explicitly apply the status update (dry-run by default)}
                            {--warehouse= : Optional warehouse ID to filter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely clear historical warehouse advances dated on or before cutoff date so they no longer participate in Auto Match';

    public function handle(WarehouseReceiptReadScope $readScope): int
    {
        $throughInput = (string) ($this->option('through') ?: '2026-09-03');
        try {
            $cutoffDate = Carbon::parse($throughInput)->endOfDay();
        } catch (Throwable $e) {
            $this->error("Invalid cutoff date format: {$throughInput}");

            return 1;
        }

        $isApply = (bool) $this->option('apply');
        $warehouseFilter = $this->option('warehouse') !== null ? (int) $this->option('warehouse') : null;

        $this->info("Scanning warehouse advances dated on or before {$cutoffDate->toDateString()} (".($isApply ? 'APPLY MODE' : 'DRY RUN MODE').')...');

        $warehouses = Warehouse::query()
            ->when($warehouseFilter !== null, fn ($q) => $q->where('id', $warehouseFilter))
            ->orderBy('id')
            ->get();

        if ($warehouses->isEmpty()) {
            $this->error('No warehouses found.');

            return 1;
        }

        $allAdvancesByWarehouse = [];
        $totalEligibleCount = 0;
        $totalMatchedAllocations = 0.0;
        $globalEarliestDate = null;
        $globalLatestDate = null;

        foreach ($warehouses as $wh) {
            $query = GoodsReceived::query()
                ->where('goods_received.status', 'approved')
                ->where('goods_received.bill_status', 'bill_pending')
                ->where(function (Builder $typeQ): void {
                    $typeQ->where('goods_received.receipt_type', 'warehouse_advance')
                        ->orWhere(function (Builder $legacy): void {
                            $legacy->whereNull('goods_received.receipt_type')
                                ->whereNull('goods_received.purchase_order_id');
                        });
                })
                ->whereDate('goods_received.received_at', '<=', $cutoffDate->toDateString())
                ->with([
                    'items.product',
                    'advanceMatchesAsAdvance',
                ])
                ->orderBy('received_at')
                ->orderBy('id');

            $readScope->receipts($query, [$wh->id]);
            $advances = $query->get();

            $whAdvances = [];
            foreach ($advances as $adv) {
                $matchedQty = (float) $adv->advanceMatchesAsAdvance->sum('base_qty');
                $receivedDate = $adv->received_at instanceof Carbon ? $adv->received_at->toDateString() : (string) $adv->received_at;

                if ($globalEarliestDate === null || $receivedDate < $globalEarliestDate) {
                    $globalEarliestDate = $receivedDate;
                }
                if ($globalLatestDate === null || $receivedDate > $globalLatestDate) {
                    $globalLatestDate = $receivedDate;
                }

                $totalMatchedAllocations += $matchedQty;

                $whAdvances[] = [
                    'id' => $adv->id,
                    'grn_number' => $adv->grn_number,
                    'warehouse_name' => $wh->name,
                    'received_at' => $receivedDate,
                    'old_bill_status' => $adv->bill_status,
                    'matched_qty' => $matchedQty,
                ];
            }

            $allAdvancesByWarehouse[$wh->id] = [
                'name' => $wh->name,
                'items' => $whAdvances,
            ];
            $totalEligibleCount += count($whAdvances);
        }

        $this->line('');
        foreach ($allAdvancesByWarehouse as $whData) {
            $this->info("{$whData['name']}");
            $this->line('Old open advances to clear: '.count($whData['items']));
            $this->line('');
        }

        $this->info("Total: {$totalEligibleCount}");
        $this->line('Earliest date: '.($globalEarliestDate ?? 'N/A'));
        $this->line('Latest date: '.($globalLatestDate ?? 'N/A'));
        $this->line("Total GRNs: {$totalEligibleCount}");
        $this->line("Total existing matched allocations: {$totalMatchedAllocations} base qty");
        $this->line('');

        if ($totalEligibleCount === 0) {
            $this->info('No eligible old advances found.');

            return 0;
        }

        if (! $isApply) {
            $this->warn('No changes made. Run with --apply to execute.');

            return 0;
        }

        // Apply mode: update bill_status to bill_available in DB transactions with auditing
        $appliedCount = 0;
        foreach ($allAdvancesByWarehouse as $whData) {
            if ($whData['items'] === []) {
                continue;
            }

            $this->info("Clearing advances for {$whData['name']} (".count($whData['items']).' GRNs)...');

            foreach ($whData['items'] as $item) {
                try {
                    DB::transaction(function () use ($item, &$appliedCount): void {
                        /** @var GoodsReceived $lockedGrn */
                        $lockedGrn = GoodsReceived::query()->whereKey($item['id'])->lockForUpdate()->firstOrFail();

                        $lockedGrn->update([
                            'bill_status' => 'bill_available',
                            'updated_at' => now(),
                        ]);

                        $appliedCount++;
                    });
                } catch (Throwable $e) {
                    $this->error("Failed updating GRN #{$item['id']} ({$item['grn_number']}): {$e->getMessage()}");
                }
            }
        }

        $this->info("Successfully cleared {$appliedCount} historical advance(s).");

        return 0;
    }
}
