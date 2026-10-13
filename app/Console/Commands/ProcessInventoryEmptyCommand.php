<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessInventoryEmptyBatch;
use App\Models\InventoryEmptyProcess;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Console\Command;

class ProcessInventoryEmptyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:empty-process {process? : Process ID to run or cancel} {--cancel : Cancel the active process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process, resume, or cancel an empty warehouse inventory job';

    /**
     * Execute the console command.
     */
    public function handle(StockAdjustmentService $stock): int
    {
        $processId = $this->argument('process');

        $query = InventoryEmptyProcess::query();
        if ($processId !== null) {
            $process = $query->find((int) $processId);
        } else {
            $process = $query->whereIn('status', ['pending', 'running'])->latest()->first();
        }

        if (! $process) {
            $this->info('No matching active empty-inventory process found.');

            return self::SUCCESS;
        }

        if ($this->option('cancel')) {
            if (! in_array($process->status, ['pending', 'running'], true)) {
                $this->warn("Process #{$process->id} is already in status '{$process->status}' and cannot be cancelled.");

                return self::FAILURE;
            }

            $process->update([
                'status' => 'cancelled',
                'error_message' => 'Cancelled via artisan CLI command on '.now()->toDateTimeString(),
                'completed_at' => now(),
                'current_product_id' => null,
            ]);

            $this->info("Process #{$process->id} has been cancelled.");

            return self::SUCCESS;
        }

        if (! in_array($process->status, ['pending', 'running'], true)) {
            $this->warn("Process #{$process->id} is already in status '{$process->status}'.");

            return self::SUCCESS;
        }

        $this->info("Starting / resuming empty warehouse process #{$process->id} (Total: {$process->total_records})...");

        $batchJob = new ProcessInventoryEmptyBatch($process->id);

        while (true) {
            $current = InventoryEmptyProcess::find($process->id);
            if (! $current || ! in_array($current->status, ['pending', 'running'], true)) {
                $this->warn("Process #{$process->id} is no longer active (status: {$current?->status}).");
                break;
            }

            $hasMore = $batchJob->processSingleBatch($current, $stock);
            $current->refresh();

            $this->line("Progress: {$current->processed_records} / {$current->total_records} (Success: {$current->successful_records}, Failed: {$current->failed_records})");

            if (! $hasMore) {
                break;
            }
        }

        $process->refresh();
        $this->info("Completed! Final status: {$process->status} at {$process->completed_at}.");

        return self::SUCCESS;
    }
}
