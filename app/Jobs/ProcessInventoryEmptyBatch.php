<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InventoryEmptyProcess;
use App\Models\InventoryEmptyProcessItem;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessInventoryEmptyBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function __construct(public int $processId) {}

    public function handle(StockAdjustmentService $stock): void
    {
        $process = InventoryEmptyProcess::find($this->processId);
        if (! $process || ! in_array($process->status, ['pending', 'running'], true)) {
            return;
        }

        // When running in console (queue worker or artisan command), loop sequentially until finished
        // to avoid recursive self-dispatch causing stack overflow or memory issues when queue is sync.
        if (app()->runningInConsole()) {
            while (true) {
                $process = InventoryEmptyProcess::find($this->processId);
                if (! $process || ! in_array($process->status, ['pending', 'running'], true)) {
                    return;
                }

                $hasMore = $this->processSingleBatch($process, $stock);
                if (! $hasMore) {
                    break;
                }
            }

            return;
        }

        // In web request context, process a batch:
        $hasMore = $this->processSingleBatch($process, $stock);
        if ($hasMore && config('queue.default') !== 'sync') {
            self::dispatch($process->id);
        }
    }

    /**
     * Process up to specified number of items for the process.
     * Returns true if there are more pending items remaining.
     */
    public function processSingleBatch(InventoryEmptyProcess $process, StockAdjustmentService $stock, int $limit = 10): bool
    {
        if (! in_array($process->status, ['pending', 'running'], true)) {
            return false;
        }

        $process->update([
            'status' => 'running',
            'started_at' => $process->started_at ?? now(),
        ]);

        $items = InventoryEmptyProcessItem::where('process_id', $process->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($items->isEmpty()) {
            $process->refresh();
            $process->update([
                'status' => $process->failed_records ? 'completed_with_errors' : 'completed',
                'current_product_id' => null,
                'completed_at' => now(),
            ]);

            return false;
        }

        foreach ($items as $item) {
            // Abort immediately if the process was cancelled mid-batch
            if ($process->fresh()->status === 'cancelled') {
                return false;
            }

            try {
                DB::transaction(function () use ($process, $item, $stock) {
                    $locked = InventoryEmptyProcessItem::lockForUpdate()->findOrFail($item->id);
                    if ($locked->status !== 'pending') {
                        return;
                    }
                    $process->update(['current_product_id' => $locked->product_id]);
                    $stock->emptyProductInventory($locked->product_id, $process->started_by);
                    $locked->update(['status' => 'success']);
                    $process->increment('processed_records');
                    $process->increment('successful_records');
                });
            } catch (\Throwable $e) {
                $item->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $process->increment('processed_records');
                $process->increment('failed_records');
            }
        }

        $hasRemaining = InventoryEmptyProcessItem::where('process_id', $process->id)
            ->where('status', 'pending')
            ->exists();

        if (! $hasRemaining) {
            $process->refresh();
            $process->update([
                'status' => $process->failed_records ? 'completed_with_errors' : 'completed',
                'current_product_id' => null,
                'completed_at' => now(),
            ]);

            return false;
        }

        return true;
    }

    public function failed(\Throwable $exception): void
    {
        InventoryEmptyProcess::whereKey($this->processId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
