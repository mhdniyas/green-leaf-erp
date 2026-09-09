<?php

declare(strict_types=1);

namespace App\Console\Commands\Purchasing;

use App\Models\BillReconciliation;
use App\Models\GoodsReceived;
use App\Models\Warehouse;
use App\Services\Purchasing\AdvanceAvailableBalanceCalculator;
use App\Services\Purchasing\WarehouseReceiptReadScope;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class RepairStaleReconciliationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:repair-stale-reconciliations
                            {--apply : Explicitly apply the reconciliation repairs (dry-run by default)}
                            {--warehouse= : Optional warehouse ID to filter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely detect and repair historical bill GRNs that are fully matched but linger in pending status';

    public function handle(AdvanceAvailableBalanceCalculator $calc, WarehouseReceiptReadScope $readScope): int
    {
        $isApply = (bool) $this->option('apply');
        $warehouseFilter = $this->option('warehouse') !== null ? (int) $this->option('warehouse') : null;

        $this->info('Scanning historical bill GRNs for stale pending status ('.($isApply ? 'APPLY MODE' : 'DRY RUN MODE').')...');

        $warehouses = Warehouse::query()
            ->when($warehouseFilter !== null, fn ($q) => $q->where('id', $warehouseFilter))
            ->orderBy('id')
            ->get();

        if ($warehouses->isEmpty()) {
            $this->error('No warehouses found.');

            return 1;
        }

        $allStaleByWarehouse = [];
        $totalStaleCount = 0;

        foreach ($warehouses as $wh) {
            $query = GoodsReceived::query()
                ->where('goods_received.status', 'approved')
                ->where('goods_received.bill_status', 'bill_pending')
                ->where(function (Builder $q): void {
                    $q->whereNull('goods_received.receipt_type')
                        ->orWhere('goods_received.receipt_type', '!=', 'warehouse_advance');
                })
                ->whereNotNull('goods_received.purchase_order_id')
                ->with([
                    'items.product.orderUnits',
                    'stockBatches',
                    'advanceMatchesAsBill',
                    'billReconciliation',
                ])
                ->orderBy('received_at')
                ->orderBy('id');

            $readScope->receipts($query, [$wh->id]);
            $candidateGrns = $query->get();

            $staleGrns = [];
            foreach ($candidateGrns as $grn) {
                $totalBillBase = (float) $grn->items->sum(function ($item) use ($calc): float {
                    $conv = $calc->resolveStrictUnitConversion($item->product, $item->received_unit) ?? 1.0;

                    return round((float) $item->received_qty * $conv, 3);
                });

                $totalMatchedBase = (float) $grn->advanceMatchesAsBill->sum('base_qty');

                if ($totalBillBase > 0.0001 && $totalMatchedBase >= $totalBillBase - 0.0001) {
                    $staleGrns[] = [
                        'grn' => $grn,
                        'total_bill_base' => $totalBillBase,
                        'total_matched_base' => $totalMatchedBase,
                        'warehouse_id' => $wh->id,
                        'warehouse_name' => $wh->name,
                    ];
                }
            }

            $allStaleByWarehouse[$wh->id] = [
                'name' => $wh->name,
                'items' => $staleGrns,
            ];
            $totalStaleCount += count($staleGrns);
        }

        foreach ($allStaleByWarehouse as $whData) {
            $this->line('');
            $this->info("{$whData['name']}");
            $this->line('Stale fully matched bills: '.count($whData['items']));
        }
        $this->line('');

        if ($totalStaleCount === 0) {
            $this->info('No stale fully matched bills found. Everything is up to date.');

            return 0;
        }

        if (! $isApply) {
            $this->warn('No changes made. Run with --apply to execute.');

            return 0;
        }

        // Apply repairs in database transactions per warehouse
        $repairedCount = 0;
        foreach ($allStaleByWarehouse as $whId => $whData) {
            if ($whData['items'] === []) {
                continue;
            }

            $this->info("Applying repairs for {$whData['name']} (".count($whData['items']).' bills)...');

            foreach ($whData['items'] as $itemData) {
                /** @var GoodsReceived $grn */
                $grn = $itemData['grn'];
                $totalBillBase = $itemData['total_bill_base'];
                $totalMatchedBase = $itemData['total_matched_base'];

                try {
                    DB::transaction(function () use ($grn, $totalBillBase, $totalMatchedBase, $whId, &$repairedCount): void {
                        /** @var GoodsReceived $lockedGrn */
                        $lockedGrn = GoodsReceived::query()->whereKey($grn->id)->lockForUpdate()->firstOrFail();

                        // Update or create canonical BillReconciliation
                        BillReconciliation::query()->updateOrCreate(
                            ['goods_received_id' => $lockedGrn->id],
                            [
                                'purchase_order_id' => $lockedGrn->purchase_order_id,
                                'warehouse_id' => $lockedGrn->warehouse_id ?? $whId,
                                'supplier_id' => $lockedGrn->supplier_id,
                                'status' => 'confirmed',
                                'total_bill_base_qty' => $totalBillBase,
                                'total_matched_base_qty' => $totalMatchedBase,
                                'total_new_receive_base_qty' => 0.0,
                                'confirmed_at' => $lockedGrn->approved_at ?? now(),
                                'confirmed_by' => $lockedGrn->approved_by ?? 1,
                            ]
                        );

                        // Mark bill_status as completed/available
                        $lockedGrn->update([
                            'bill_status' => 'bill_available',
                        ]);

                        // Zero out provisional bill stock batches to prevent double-counting physical stock
                        foreach ($lockedGrn->stockBatches as $batch) {
                            $batch->update([
                                'total_kg' => 0.0,
                                'warehouse_receive_pending' => false,
                                'warehouse_confirmed_at' => $batch->warehouse_confirmed_at ?? now(),
                                'warehouse_confirmed_by' => $batch->warehouse_confirmed_by ?? $lockedGrn->approved_by ?? 1,
                                'notes' => 'Reconciled 100% from Advance (Stock effect: 0 kg)',
                            ]);
                        }

                        $repairedCount++;
                    });
                } catch (Throwable $e) {
                    $this->error("Failed repairing GRN #{$grn->id} ({$grn->grn_number}): {$e->getMessage()}");
                }
            }
        }

        $this->info("Successfully repaired {$repairedCount} stale bill(s).");

        return 0;
    }
}
