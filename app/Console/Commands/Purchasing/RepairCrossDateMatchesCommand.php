<?php

declare(strict_types=1);

namespace App\Console\Commands\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\BillReconciliation;
use App\Models\BillReconciliationLine;
use App\Models\StockBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RepairCrossDateMatchesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchasing:repair-cross-date-matches
                            {--dry-run : Preview changes without applying them (default unless --apply is specified)}
                            {--apply : Explicitly apply the safe cross-date match reversals to the database}
                            {--date= : Filter by specific date (matches involving this date as advance or bill, YYYY-MM-DD)}
                            {--warehouse= : Filter by specific warehouse ID}
                            {--product= : Filter by specific product ID or SKU}
                            {--match-id= : Filter by specific AdvanceReceiveMatch ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely detect and repair historical cross-date AdvanceReceiveMatch records by restoring bill stock and removing invalid cross-date links';

    public function handle(): int
    {
        $isApply = (bool) $this->option('apply');
        $isDryRun = (bool) $this->option('dry-run') || ! $isApply;

        $dateFilter = $this->option('date');
        $warehouseFilter = $this->option('warehouse');
        $productFilter = $this->option('product');
        $matchIdFilter = $this->option('match-id');

        $modeLabel = $isApply ? '<fg=yellow;options=bold>APPLY MODE</>' : '<fg=cyan;options=bold>DRY RUN MODE</>';
        $this->newLine();
        $this->info("Scanning AdvanceReceiveMatch records for cross-date matches ({$modeLabel})...");

        $query = AdvanceReceiveMatch::query()
            ->with([
                'advanceGoodsReceivedItem.goodsReceived',
                'billGoodsReceivedItem.goodsReceived',
                'product',
                'billReconciliation',
                'billReconciliationLine',
            ])
            ->orderBy('id');

        if ($matchIdFilter) {
            $query->where('id', (int) $matchIdFilter);
        }

        if ($productFilter) {
            if (is_numeric($productFilter)) {
                $query->where(fn ($q) => $q->where('product_id', (int) $productFilter)->orWhereHas('product', fn ($pq) => $pq->where('sku', (string) $productFilter)));
            } else {
                $query->whereHas('product', fn ($pq) => $pq->where('sku', (string) $productFilter)->orWhere('name', 'like', "%{$productFilter}%"));
            }
        }

        $allMatches = $query->get();

        $crossDateRows = [];

        foreach ($allMatches as $match) {
            $advGrn = $match->advanceGoodsReceivedItem?->goodsReceived;
            $billGrn = $match->billGoodsReceivedItem?->goodsReceived;

            $advDate = $advGrn?->received_at ? Carbon::parse($advGrn->received_at)->toDateString() : null;
            $billDate = $billGrn?->received_at ? Carbon::parse($billGrn->received_at)->toDateString() : null;

            if (! $advDate || ! $billDate || $advDate === $billDate) {
                continue;
            }

            if ($dateFilter) {
                $targetDate = Carbon::parse((string) $dateFilter)->toDateString();
                if ($advDate !== $targetDate && $billDate !== $targetDate) {
                    continue;
                }
            }

            if ($warehouseFilter) {
                $targetWh = (int) $warehouseFilter;
                $advWh = $advGrn?->warehouse_id;
                $billWh = $billGrn?->warehouse_id ?? $match->product?->default_warehouse_id;
                if ($advWh !== $targetWh && $billWh !== $targetWh) {
                    continue;
                }
            }

            // Locate the Bill StockBatch
            $billBatch = null;
            if ($match->bill_goods_received_item_id) {
                $billBatch = StockBatch::query()->where('goods_received_item_id', $match->bill_goods_received_item_id)->first();
            }
            if (! $billBatch && $match->bill_goods_received_id) {
                $billBatch = StockBatch::query()->where('goods_received_id', $match->bill_goods_received_id)
                    ->where('product_id', $match->product_id)
                    ->first();
            }

            $classification = ($advGrn && $billGrn) ? 'SAFE' : 'REVIEW';
            $matchedQty = (float) $match->matched_qty;
            $baseQty = (float) ($match->base_qty > 0 ? $match->base_qty : $match->matched_qty);

            $crossDateRows[] = [
                'match' => $match,
                'match_id' => $match->id,
                'product_id' => $match->product_id,
                'product_name' => $match->product?->name ?? 'Unknown',
                'sku' => $match->product?->sku ?? 'N/A',
                'adv_date' => $advDate,
                'bill_date' => $billDate,
                'matched_qty' => $matchedQty,
                'base_qty' => $baseQty,
                'unit' => $match->matched_unit ?? $match->product?->unit ?? 'kg',
                'adv_item_id' => $match->advance_goods_received_item_id,
                'bill_item_id' => $match->bill_goods_received_item_id,
                'bill_recon_id' => $match->bill_reconciliation_id,
                'bill_batch_id' => $billBatch?->id,
                'current_bill_stock' => (float) ($billBatch?->total_kg ?? 0),
                'classification' => $classification,
            ];
        }

        $totalFound = count($crossDateRows);
        $safeCount = count(array_filter($crossDateRows, fn ($r) => $r['classification'] === 'SAFE'));
        $reviewCount = count(array_filter($crossDateRows, fn ($r) => $r['classification'] === 'REVIEW'));

        $this->info("Found {$totalFound} cross-date match records ({$safeCount} SAFE, {$reviewCount} REVIEW).");

        if ($totalFound === 0) {
            $this->info('No cross-date matches found. All records adhere to same-day rule.');

            return self::SUCCESS;
        }

        // Print preview table
        $tableRows = [];
        foreach (array_slice($crossDateRows, 0, 50) as $row) {
            $classTag = $row['classification'] === 'SAFE' ? '<fg=green>SAFE</>' : '<fg=yellow>REVIEW</>';
            $tableRows[] = [
                $row['match_id'],
                $row['sku'].' · '.$row['product_name'],
                $row['adv_date'],
                $row['bill_date'],
                $row['matched_qty'].' '.$row['unit'],
                $row['adv_item_id'] ?? 'N/A',
                $row['bill_item_id'] ?? 'N/A',
                $row['bill_recon_id'] ?? '-',
                $row['bill_batch_id'] ?? 'Missing',
                $row['current_bill_stock'].' '.$row['unit'],
                $classTag,
            ];
        }

        $this->table(
            ['Match ID', 'Product', 'Adv Date', 'Bill Date', 'Matched Qty', 'Adv Item', 'Bill Item', 'Recon ID', 'Bill Batch', 'Bill Stock', 'Status'],
            $tableRows
        );

        if ($totalFound > 50) {
            $remaining = $totalFound - 50;
            $this->comment("... and {$remaining} more cross-date match rows.");
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info('<fg=cyan;options=bold>DRY RUN SUMMARY:</>');
            $this->line("Cross-date matches found: {$totalFound}");
            $this->line("Safe to reverse:         {$safeCount}");
            $this->line("Review required:         {$reviewCount}");
            $this->line('Advance stock changed:   0');
            $this->line('New stock movements:     0');
            $this->comment('To apply these safe repairs, run with --apply flag.');

            return self::SUCCESS;
        }

        // APPLY MODE
        $this->newLine();
        $this->warn("APPLYING REVERSAL FOR {$safeCount} SAFE CROSS-DATE MATCHES...");

        $safeRows = array_filter($crossDateRows, fn ($r) => $r['classification'] === 'SAFE');
        $reversedCount = 0;
        $failedCount = 0;
        $totalRestoredStock = 0.0;

        $bar = $this->output->createProgressBar(count($safeRows));
        $bar->start();

        foreach (array_chunk($safeRows, 100) as $chunk) {
            DB::beginTransaction();
            try {
                foreach ($chunk as $row) {
                    /** @var AdvanceReceiveMatch|null $lockedMatch */
                    $lockedMatch = AdvanceReceiveMatch::query()
                        ->where('id', $row['match_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $lockedMatch) {
                        continue;
                    }

                    $restoreQty = (float) ($lockedMatch->base_qty > 0 ? $lockedMatch->base_qty : $lockedMatch->matched_qty);

                    // 1. Lock and restore StockBatch on BILL side
                    $billBatch = null;
                    if ($lockedMatch->bill_goods_received_item_id) {
                        $billBatch = StockBatch::query()
                            ->where('goods_received_item_id', $lockedMatch->bill_goods_received_item_id)
                            ->lockForUpdate()
                            ->first();
                    }

                    if (! $billBatch && $lockedMatch->bill_goods_received_id) {
                        $billBatch = StockBatch::query()
                            ->where('goods_received_id', $lockedMatch->bill_goods_received_id)
                            ->where('product_id', $lockedMatch->product_id)
                            ->lockForUpdate()
                            ->first();
                    }

                    if ($billBatch) {
                        $billBatch->total_kg = round((float) $billBatch->total_kg + $restoreQty, 3);
                        $billBatch->notes = trim(($billBatch->notes ?? '')." | Restored {$restoreQty} {$lockedMatch->matched_unit} from cross-date match #{$lockedMatch->id} reversal");
                        $billBatch->save();
                    } elseif ($lockedMatch->billGoodsReceivedItem?->goodsReceived) {
                        $billGrn = $lockedMatch->billGoodsReceivedItem->goodsReceived;
                        $ref = 'BATCH-REPAIR-'.date('Ymd').'-'.str_pad((string) $lockedMatch->id, 6, '0', STR_PAD_LEFT);
                        $billBatch = StockBatch::create([
                            'product_id' => $lockedMatch->product_id,
                            'warehouse_id' => $billGrn->warehouse_id ?? $lockedMatch->product?->default_warehouse_id ?? 1,
                            'goods_received_id' => $billGrn->id,
                            'goods_received_item_id' => $lockedMatch->bill_goods_received_item_id,
                            'reference' => $ref,
                            'received_at' => $billGrn->received_at ?? now(),
                            'total_kg' => $restoreQty,
                            'cost_per_kg' => 0.00,
                            'status' => 'pending',
                            'warehouse_receive_pending' => false,
                            'warehouse_confirmed_at' => now(),
                            'warehouse_confirmed_by' => 1,
                            'notes' => "Restored {$restoreQty} {$lockedMatch->matched_unit} from cross-date match #{$lockedMatch->id} reversal",
                            'created_by' => 1,
                        ]);
                    }

                    // 2. Reverse / Update BillReconciliation if linked
                    if ($lockedMatch->bill_reconciliation_id) {
                        /** @var BillReconciliation|null $recon */
                        $recon = BillReconciliation::query()
                            ->where('id', $lockedMatch->bill_reconciliation_id)
                            ->lockForUpdate()
                            ->first();

                        if ($recon) {
                            $recon->total_matched_base_qty = max(0.0, round((float) $recon->total_matched_base_qty - $restoreQty, 3));
                            $recon->total_new_receive_base_qty = round((float) $recon->total_new_receive_base_qty + $restoreQty, 3);
                            $recon->save();
                        }
                    }

                    if ($lockedMatch->bill_reconciliation_line_id) {
                        /** @var BillReconciliationLine|null $reconLine */
                        $reconLine = BillReconciliationLine::query()
                            ->where('id', $lockedMatch->bill_reconciliation_line_id)
                            ->lockForUpdate()
                            ->first();

                        if ($reconLine) {
                            $reconLine->advance_matched_qty = max(0.0, round((float) $reconLine->advance_matched_qty - (float) $lockedMatch->matched_qty, 3));
                            $reconLine->advance_matched_base_qty = max(0.0, round((float) $reconLine->advance_matched_base_qty - $restoreQty, 3));
                            $reconLine->new_receive_qty = round((float) $reconLine->new_receive_qty + (float) $lockedMatch->matched_qty, 3);
                            $reconLine->new_receive_base_qty = round((float) $reconLine->new_receive_base_qty + $restoreQty, 3);
                            $reconLine->save();
                        }
                    }

                    // 3. Activity Audit
                    activity()
                        ->performedOn($lockedMatch)
                        ->withProperties([
                            'action' => 'historical_cross_date_match_repair',
                            'match_id' => $lockedMatch->id,
                            'product_id' => $lockedMatch->product_id,
                            'product_name' => $lockedMatch->product?->name,
                            'adv_grn_id' => $lockedMatch->advance_goods_received_id,
                            'adv_date' => $row['adv_date'],
                            'bill_grn_id' => $lockedMatch->bill_goods_received_id,
                            'bill_date' => $row['bill_date'],
                            'matched_qty' => (float) $lockedMatch->matched_qty,
                            'matched_unit' => $lockedMatch->matched_unit,
                            'restored_batch_id' => $billBatch?->id,
                            'restored_qty' => $restoreQty,
                        ])
                        ->log("Reversed cross-date match #{$lockedMatch->id} ({$row['adv_date']} -> {$row['bill_date']}) and restored {$restoreQty} {$lockedMatch->matched_unit} to Bill StockBatch");

                    // 4. Delete the invalid cross-date match record
                    $lockedMatch->delete();

                    $reversedCount++;
                    $totalRestoredStock += $restoreQty;
                    $bar->advance();
                }

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                $failedCount += count($chunk);
                $this->error("Failed processing chunk: {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('<fg=green;options=bold>FINAL REPAIR REPORT:</>');
        $this->line("Cross-date matches found:    {$totalFound}");
        $this->line("Safe reversed:               {$reversedCount}");
        $this->line("Review required:             {$reviewCount}");
        $this->line("Bill stock restored:         {$totalRestoredStock}");
        $this->line('Advance stock changed:       0');
        $this->line('New stock movements created: 0');
        $this->line("Failed:                      {$failedCount}");

        return self::SUCCESS;
    }
}
