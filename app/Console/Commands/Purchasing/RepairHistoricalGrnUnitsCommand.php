<?php

declare(strict_types=1);

namespace App\Console\Commands\Purchasing;

use App\Models\BillReconciliation;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\ProductUnit;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Services\Purchasing\AdvanceAvailableBalanceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RepairHistoricalGrnUnitsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchasing:repair-historical-grn-units
                            {--dry-run : Preview changes without applying them (default unless --apply is specified)}
                            {--apply : Explicitly apply the safe unit repairs to the database}
                            {--from= : Start date filter for GRN received_at (YYYY-MM-DD)}
                            {--to= : End date filter for GRN received_at (YYYY-MM-DD)}
                            {--grn= : Filter by specific GRN ID or GRN number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely detect and repair historical GRN items with mismatched units between PO and GRN without changing inventory quantities';

    public function handle(AdvanceAvailableBalanceCalculator $balanceCalculator): int
    {
        $isApply = (bool) $this->option('apply');
        $isDryRun = (bool) $this->option('dry-run') || ! $isApply;

        $fromInput = $this->option('from');
        $toInput = $this->option('to');
        $grnFilter = $this->option('grn');

        $fromDate = $fromInput ? Carbon::parse((string) $fromInput)->startOfDay() : null;
        $toDate = $toInput ? Carbon::parse((string) $toInput)->endOfDay() : null;

        $modeLabel = $isApply ? '<fg=yellow;options=bold>APPLY MODE</>' : '<fg=cyan;options=bold>DRY RUN MODE</>';
        $this->newLine();
        $this->info("Scanning historical GRNs for unit mismatches ({$modeLabel})...");

        // Query candidate GRN items with PO relations
        $query = GoodsReceivedItem::query()
            ->with([
                'goodsReceived.purchaseOrder',
                'purchaseOrderItem',
                'product.orderUnits',
                'advanceMatchesAsAdvance',
                'advanceMatchesAsBill',
            ])
            ->whereNotNull('goods_received_items.purchase_order_item_id')
            ->whereHas('purchaseOrderItem')
            ->whereHas('goodsReceived', function ($q) use ($fromDate, $toDate, $grnFilter): void {
                $q->whereNull('deleted_at');
                if ($fromDate) {
                    $q->whereDate('received_at', '>=', $fromDate->toDateString());
                }
                if ($toDate) {
                    $q->whereDate('received_at', '<=', $toDate->toDateString());
                }
                if ($grnFilter) {
                    if (is_numeric($grnFilter)) {
                        $q->where(fn ($sub) => $sub->where('id', (int) $grnFilter)->orWhere('grn_number', (string) $grnFilter));
                    } else {
                        $q->where('grn_number', (string) $grnFilter);
                    }
                }
            })
            ->whereNull('goods_received_items.deleted_at')
            ->orderBy('goods_received_items.goods_received_id')
            ->orderBy('goods_received_items.id');

        $candidateItems = $query->get();

        $mismatchedRows = [];

        foreach ($candidateItems as $item) {
            $poItem = $item->purchaseOrderItem;
            if (! $poItem) {
                continue;
            }

            $currentUnit = (string) $item->received_unit;
            $correctUnit = (string) $poItem->purchase_unit;

            if (ProductUnit::normalizeUnit($currentUnit) === ProductUnit::normalizeUnit($correctUnit)) {
                continue;
            }

            $grn = $item->goodsReceived;
            $po = $grn?->purchaseOrder ?? $poItem->purchaseOrder;
            $product = $item->product;

            $hasAdvanceMatch = $item->advanceMatchesAsAdvance->isNotEmpty() || $item->advanceMatchesAsBill->isNotEmpty();
            $matchedQty = (float) ($item->advanceMatchesAsBill->sum('matched_qty') + $item->advanceMatchesAsAdvance->sum('matched_qty'));

            $hasBillRecon = false;
            if ($grn) {
                $hasBillRecon = BillReconciliation::query()
                    ->where('goods_received_id', $grn->id)
                    ->orWhere('purchase_order_id', $poItem->purchase_order_id)
                    ->exists();
            }

            $stockBatch = StockBatch::query()
                ->where('goods_received_item_id', $item->id)
                ->first();

            if (! $stockBatch && $grn) {
                $stockBatch = StockBatch::query()
                    ->where('goods_received_id', $grn->id)
                    ->where('product_id', $item->product_id)
                    ->first();
            }

            $conversionConfigured = false;
            if ($product) {
                $normCorrect = ProductUnit::normalizeUnit($correctUnit);
                $normBase = ProductUnit::normalizeUnit($product->unit);
                if ($normCorrect === $normBase) {
                    $conversionConfigured = true;
                } else {
                    $conversionConfigured = $balanceCalculator->resolveStrictUnitConversion($product, $correctUnit) !== null;
                }
            }

            $classification = (! $hasAdvanceMatch && ! $hasBillRecon) ? 'SAFE' : 'REVIEW';

            $mismatchedRows[] = [
                'item' => $item,
                'grn_id' => $grn?->id,
                'grn_number' => $grn?->grn_number ?? 'N/A',
                'po_id' => $po?->id ?? $poItem->purchase_order_id,
                'po_number' => $po?->po_number ?? 'PO #'.$poItem->purchase_order_id,
                'product_id' => $product?->id,
                'product_name' => $product?->name ?? 'Unknown',
                'grn_item_id' => $item->id,
                'po_qty_unit' => (float) $poItem->quantity.' '.$correctUnit,
                'grn_qty_unit' => (float) $item->received_qty.' '.$currentUnit,
                'correct_unit' => $correctUnit,
                'old_unit' => $currentUnit,
                'matched_qty' => $matchedQty,
                'has_advance_match' => $hasAdvanceMatch,
                'has_bill_recon' => $hasBillRecon,
                'stock_batch_id' => $stockBatch?->id,
                'stock_batch_qty' => $stockBatch ? (float) $stockBatch->total_kg : null,
                'conversion_configured' => $conversionConfigured,
                'classification' => $classification,
            ];
        }

        $totalMismatches = count($mismatchedRows);

        if ($totalMismatches === 0) {
            $this->info('No historical GRN unit mismatches found matching the criteria.');

            return 0;
        }

        // Print table of matches (limit display to top 50 in console if too large, but show counts)
        $displayRows = array_slice($mismatchedRows, 0, 50);
        $tableData = array_map(fn (array $r): array => [
            $r['grn_number'].' (#'.$r['grn_id'].')',
            $r['po_number'],
            $r['product_name'],
            $r['grn_item_id'],
            $r['po_qty_unit'],
            $r['grn_qty_unit'],
            $r['matched_qty'] > 0 ? (string) $r['matched_qty'] : '0',
            $r['has_advance_match'] ? 'YES' : 'NO',
            $r['has_bill_recon'] ? 'YES' : 'NO',
            $r['stock_batch_id'] ? '#'.$r['stock_batch_id'] : 'N/A',
            $r['stock_batch_qty'] !== null ? (string) $r['stock_batch_qty'] : 'N/A',
            $r['conversion_configured'] ? 'YES' : 'NO',
            $r['classification'] === 'SAFE' ? '<fg=green>SAFE</>' : '<fg=yellow>REVIEW</>',
        ], $displayRows);

        $this->table([
            'GRN',
            'PO',
            'Product',
            'Item ID',
            'PO Qty/Unit',
            'GRN Qty/Unit',
            'Matched',
            'Adv Match',
            'Bill Recon',
            'Batch ID',
            'Batch Qty',
            'Conv OK',
            'Class',
        ], $tableData);

        if ($totalMismatches > 50) {
            $this->line('... and '.($totalMismatches - 50).' more mismatched records.');
        }

        $safeRows = array_filter($mismatchedRows, fn ($r): bool => $r['classification'] === 'SAFE');
        $reviewRows = array_filter($mismatchedRows, fn ($r): bool => $r['classification'] === 'REVIEW');

        $safeCount = count($safeRows);
        $reviewCount = count($reviewRows);

        $this->newLine();
        $this->info("Found {$totalMismatches} total mismatches: {$safeCount} SAFE, {$reviewCount} REVIEW.");

        if (! $isApply) {
            $this->warn('DRY RUN complete. No changes were applied. Run with --apply to repair SAFE records.');

            $this->newLine();
            $this->line('Summary:');
            $this->line("Total mismatches: {$totalMismatches}");
            $this->line("Safe candidates: {$safeCount}");
            $this->line("Matched/reconciliation review: {$reviewCount}");
            $this->line('Failed: 0');
            $this->line('Inventory delta: 0.000');
            $this->line('GRNs created: 0');
            $this->line('Stock movements created: 0');

            return 0;
        }

        // Apply mode: record initial state to verify invariants
        $initialStockBatchCount = StockBatch::query()->count();
        $initialStockBatchWeight = (float) StockBatch::query()->sum('total_kg');
        $initialStockMovementCount = StockMovement::query()->count();
        $initialGrnCount = GoodsReceived::query()->count();

        $safeRepaired = 0;
        $failedCount = 0;

        // Process in batches of 100 with DB transactions
        $chunks = array_chunk(array_values($safeRows), 100);

        foreach ($chunks as $chunkIndex => $batch) {
            try {
                DB::transaction(function () use ($batch, &$safeRepaired): void {
                    foreach ($batch as $row) {
                        /** @var GoodsReceivedItem $item */
                        $item = $row['item'];
                        $oldUnit = $row['old_unit'];
                        $newUnit = $row['correct_unit'];

                        // Preserve received_qty exactly, update received_unit metadata only
                        $item->update([
                            'received_unit' => $newUnit,
                        ]);

                        activity()
                            ->performedOn($item)
                            ->withProperties([
                                'action' => 'historical_grn_unit_bug_repair',
                                'goods_received_id' => $item->goods_received_id,
                                'goods_received_item_id' => $item->id,
                                'product_id' => $item->product_id,
                                'product_name' => $row['product_name'],
                                'old_unit' => $oldUnit,
                                'new_unit' => $newUnit,
                                'reason' => 'historical_grn_unit_bug_repair',
                            ])
                            ->log("Repaired historical GRN item #{$item->id} unit from {$oldUnit} to {$newUnit}");

                        $safeRepaired++;
                    }
                });
            } catch (Throwable $e) {
                $failedCount += count($batch);
                $this->error("Batch {$chunkIndex} failed: {$e->getMessage()}");
            }
        }

        // Verify post-execution invariants
        $finalStockBatchCount = StockBatch::query()->count();
        $finalStockBatchWeight = (float) StockBatch::query()->sum('total_kg');
        $finalStockMovementCount = StockMovement::query()->count();
        $finalGrnCount = GoodsReceived::query()->count();

        $batchCountDelta = $finalStockBatchCount - $initialStockBatchCount;
        $batchWeightDelta = round($finalStockBatchWeight - $initialStockBatchWeight, 3);
        $movementCountDelta = $finalStockMovementCount - $initialStockMovementCount;
        $grnCountDelta = $finalGrnCount - $initialGrnCount;

        $this->newLine();
        $this->info('<fg=green;options=bold>REPAIR EXECUTION COMPLETED</>');
        $this->newLine();

        $this->line("Total mismatches: {$totalMismatches}");
        $this->line("Safe repaired: {$safeRepaired}");
        $this->line("Matched/reconciliation review: {$reviewCount}");
        $this->line("Failed: {$failedCount}");
        $this->line("Inventory delta: {$batchWeightDelta} kg");
        $this->line("GRNs created: {$grnCountDelta}");
        $this->line("Stock movements created: {$movementCountDelta}");

        if ($batchCountDelta !== 0 || $batchWeightDelta !== 0.0 || $movementCountDelta !== 0 || $grnCountDelta !== 0) {
            $this->error('CRITICAL: Invariant violation detected! StockBatches or movements changed unexpectedly.');

            return 1;
        }

        return 0;
    }
}
