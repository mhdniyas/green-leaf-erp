<?php

declare(strict_types=1);

namespace App\Console\Commands\Purchasing;

use App\Models\GoodsReceived;
use App\Models\StockBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairWarehouseAdvanceConfirmationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchasing:repair-warehouse-advance-confirmation
                            {--warehouse= : The warehouse ID to repair (required)}
                            {--execute : Explicitly apply the confirmation updates (dry-run by default)}
                            {--user= : User ID to set as confirmed_by if batch/GRN has none}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely and idempotently repair confirmation flags on approved explicit warehouse advances';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $warehouseOption = $this->option('warehouse');
        if ($warehouseOption === null || ! is_numeric($warehouseOption) || (int) $warehouseOption <= 0) {
            $this->error('The --warehouse=<id> option is required and must be a positive integer.');

            return 1;
        }

        $warehouseId = (int) $warehouseOption;
        $isExecute = (bool) $this->option('execute');
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;

        $this->info("Scanning warehouse advances for warehouse ID {$warehouseId} (".($isExecute ? 'EXECUTE MODE' : 'DRY RUN MODE').')...');

        // Query only explicit approved bill_pending warehouse_advance receipts with pending batches
        $query = GoodsReceived::query()
            ->where('goods_received.receipt_type', 'warehouse_advance')
            ->where('goods_received.status', 'approved')
            ->where('goods_received.bill_status', 'bill_pending')
            ->where(function ($w) use ($warehouseId): void {
                $w->where('goods_received.warehouse_id', $warehouseId)
                    ->orWhere('goods_received.destination_shop_id', $warehouseId);
            })
            ->whereHas('stockBatches', function ($b): void {
                $b->where('warehouse_receive_pending', true);
            })
            ->with(['stockBatches' => function ($b): void {
                $b->where('warehouse_receive_pending', true);
            }])
            ->orderBy('goods_received.received_at')
            ->orderBy('goods_received.id');

        $advances = $query->get();

        if ($advances->isEmpty()) {
            $this->info("No unconfirmed stock batches found for approved warehouse advances in warehouse {$warehouseId}. Everything is up to date.");

            return 0;
        }

        $totalBatches = 0;
        $batchIdsToUpdate = [];

        $this->table(
            ['GRN ID', 'GRN Number', 'Received At', 'Pending Batch IDs', 'Total Batch Kg'],
            $advances->map(function (GoodsReceived $grn) use (&$totalBatches, &$batchIdsToUpdate) {
                $batches = $grn->stockBatches->where('warehouse_receive_pending', true);
                $batchIds = $batches->pluck('id')->all();
                $batchIdsToUpdate = array_merge($batchIdsToUpdate, $batchIds);
                $totalBatches += count($batchIds);

                return [
                    $grn->id,
                    $grn->grn_number,
                    $grn->received_at ? (string) $grn->received_at : 'N/A',
                    implode(', ', $batchIds),
                    $batches->sum('total_kg'),
                ];
            })
        );

        $this->info("Found {$advances->count()} eligible warehouse advances with {$totalBatches} pending stock batches.");

        if (! $isExecute) {
            $this->warn('DRY RUN: No database changes were made. Run with --execute to apply confirmation updates.');

            return 0;
        }

        $updatedCount = 0;

        DB::transaction(function () use ($batchIdsToUpdate, $advances, $userId, &$updatedCount): void {
            // Lock batches for update
            $batches = StockBatch::query()
                ->whereIn('id', $batchIdsToUpdate)
                ->lockForUpdate()
                ->get();

            $grnMap = $advances->keyBy('id');

            foreach ($batches as $batch) {
                /** @var GoodsReceived|null $grn */
                $grn = $grnMap->get($batch->goods_received_id);

                $confirmedAt = $batch->warehouse_confirmed_at
                    ?? $grn?->approved_at
                    ?? $grn?->received_at
                    ?? now();

                $confirmedBy = $batch->warehouse_confirmed_by
                    ?? $grn?->approved_by
                    ?? $grn?->received_by
                    ?? $userId;

                $batch->update([
                    'warehouse_receive_pending' => false,
                    'warehouse_confirmed_at' => $confirmedAt,
                    'warehouse_confirmed_by' => $confirmedBy,
                ]);

                $updatedCount++;
            }
        });

        $this->info("SUCCESS: Successfully confirmed {$updatedCount} stock batches across {$advances->count()} warehouse advances.");

        return 0;
    }
}
