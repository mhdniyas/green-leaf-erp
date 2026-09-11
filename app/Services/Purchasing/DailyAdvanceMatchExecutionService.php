<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\DailyAdvanceMatchRun;
use App\Models\DailyAdvanceMatchRunItem;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DailyAdvanceMatchExecutionService
{
    public function __construct(
        private readonly DailyAdvanceMatchPlanningService $planningService,
        private readonly AdvanceAvailableBalanceCalculator $balanceCalculator,
        private readonly WarehouseReceiptReadScope $readScope,
    ) {}

    /**
     * Execute a daily advance match run idempotently and safely without inventory side effects.
     *
     * @return array<string, mixed>
     */
    public function execute(
        int $warehouseId,
        string $billDate,
        string $requestedPlanHash,
        string $clientSubmissionId,
        int $userId,
        ?int $cursor = null,
        int $batchSize = 100
    ): array {
        // 1. Idempotency Check: Look for existing run by client_submission_id
        /** @var DailyAdvanceMatchRun|null $existingRun */
        $existingRun = DailyAdvanceMatchRun::query()
            ->where('client_submission_id', $clientSubmissionId)
            ->with(['items.goodsReceived', 'items.purchaseOrder'])
            ->first();

        if ($existingRun) {
            if ($existingRun->warehouse_id !== $warehouseId || $existingRun->requested_plan_hash !== $requestedPlanHash) {
                throw ValidationException::withMessages([
                    'client_submission_id' => 'The submission payload does not match the original daily match run.',
                ]);
            }

            // Recover and complete initialization if interrupted
            if ($existingRun->initialized_at === null) {
                DB::transaction(function () use ($existingRun): void {
                    /** @var DailyAdvanceMatchRun $locked */
                    $locked = DailyAdvanceMatchRun::query()->whereKey($existingRun->id)->lockForUpdate()->firstOrFail();
                    if ($locked->initialized_at === null) {
                        $locked->items()->delete();
                        $position = 0;
                        $matchedBills = array_merge(
                            $locked->plan_snapshot['ready_bills'] ?? [],
                            $locked->plan_snapshot['partial_bills'] ?? []
                        );
                        foreach ($matchedBills as $billPlan) {
                            $locked->items()->create([
                                'position' => ++$position,
                                'goods_received_id' => $billPlan['goods_received_id'],
                                'purchase_order_id' => $billPlan['purchase_order_id'] ?? null,
                                'planned_base_qty' => $billPlan['matched_base_qty'],
                                'status' => 'pending',
                                'attempt_count' => 0,
                            ]);
                        }
                        $locked->update(['initialized_at' => now()]);
                    }
                });
                $existingRun->refresh()->load('items');
            }

            $uncompletedItems = $existingRun->items->filter(fn ($it) => in_array($it->status, ['pending', 'processing', 'failed'], true));
            if ($uncompletedItems->isEmpty() && $existingRun->items->isNotEmpty()) {
                return $this->formatRunResponse($existingRun);
            }

            return $this->processRunItems($existingRun, $userId);
        }

        // 2. Fresh Plan Build & Hash Verification (for new runs)
        $currentPlan = $this->planningService->buildDailyPlan($warehouseId, $billDate, $cursor, $batchSize, $userId);
        $currentHash = $currentPlan['plan_hash'];

        if ($currentHash !== $requestedPlanHash) {
            return [
                'status_code' => 409,
                'error' => [
                    'success' => false,
                    'code' => 'preview_changed',
                    'message' => 'Advance or bill quantities changed. Refresh the preview.',
                    'data' => [
                        'requested_plan_hash' => $requestedPlanHash,
                        'current_plan_hash' => $currentHash,
                    ],
                ],
            ];
        }

        $allMatchedBills = array_merge($currentPlan['ready_bills'], $currentPlan['partial_bills']);
        if (empty($allMatchedBills)) {
            return [
                'status_code' => 422,
                'error' => [
                    'success' => false,
                    'code' => 'no_matches_to_execute',
                    'message' => 'No matchable bills found for the selected date and warehouse.',
                ],
            ];
        }

        // 3. Atomic Run Creation & Item Initialization
        try {
            /** @var DailyAdvanceMatchRun $run */
            $run = DB::transaction(function () use ($warehouseId, $billDate, $cursor, $batchSize, $clientSubmissionId, $requestedPlanHash, $userId, $currentPlan, $allMatchedBills): DailyAdvanceMatchRun {
                /** @var DailyAdvanceMatchRun|null $lockedExisting */
                $lockedExisting = DailyAdvanceMatchRun::query()
                    ->where('client_submission_id', $clientSubmissionId)
                    ->lockForUpdate()
                    ->first();

                if ($lockedExisting) {
                    return $lockedExisting;
                }

                /** @var DailyAdvanceMatchRun $createdRun */
                $createdRun = DailyAdvanceMatchRun::create([
                    'public_uuid' => (string) Str::uuid(),
                    'client_submission_id' => $clientSubmissionId,
                    'warehouse_id' => $warehouseId,
                    'bill_date' => $billDate,
                    'cursor' => $cursor,
                    'batch_size' => $batchSize,
                    'requested_by' => $userId,
                    'requested_plan_hash' => $requestedPlanHash,
                    'status' => 'pending',
                    'plan_snapshot' => $currentPlan,
                    'started_at' => now(),
                    'initialized_at' => null,
                ]);

                $position = 0;
                foreach ($allMatchedBills as $billPlan) {
                    $createdRun->items()->create([
                        'position' => ++$position,
                        'goods_received_id' => $billPlan['goods_received_id'],
                        'purchase_order_id' => $billPlan['purchase_order_id'] ?? null,
                        'planned_base_qty' => $billPlan['matched_base_qty'],
                        'status' => 'pending',
                        'attempt_count' => 0,
                    ]);
                }

                $createdRun->update(['initialized_at' => now()]);

                return $createdRun;
            });
        } catch (QueryException) {
            $run = DailyAdvanceMatchRun::query()
                ->where('client_submission_id', $clientSubmissionId)
                ->firstOrFail();
        }

        if ($run->warehouse_id !== $warehouseId || $run->requested_plan_hash !== $requestedPlanHash) {
            throw ValidationException::withMessages([
                'client_submission_id' => 'The submission payload does not match the original daily match run.',
            ]);
        }

        activity('purchasing')
            ->performedOn($run)
            ->causedBy(User::find($userId))
            ->withProperties([
                'run_public_uuid' => $run->public_uuid,
                'warehouse_id' => $run->warehouse_id,
                'bill_date' => $run->bill_date,
                'requested_plan_hash' => $run->requested_plan_hash,
                'status' => 'started',
                'planned_count' => count($allMatchedBills),
            ])
            ->log('Daily Auto Match run started');

        return $this->processRunItems($run, $userId);
    }

    private function processRunItems(DailyAdvanceMatchRun $run, int $userId): array
    {
        $warehouseId = (int) $run->warehouse_id;
        $planSnapshot = $run->plan_snapshot ?? [];
        $matchedBills = array_merge($planSnapshot['ready_bills'] ?? [], $planSnapshot['partial_bills'] ?? []);
        $matchedBillsMap = collect($matchedBills)->keyBy('goods_received_id');

        $run->update(['status' => 'processing', 'started_at' => $run->started_at ?? now()]);

        // Preload product records
        $allProductIds = collect($matchedBills)
            ->flatMap(fn ($b) => $b['lines'] ?? [])
            ->pluck('product_id')
            ->unique()
            ->values()
            ->all();

        $productsMap = Product::query()
            ->whereIn('id', $allProductIds)
            ->with('orderUnits')
            ->get()
            ->keyBy('id');

        $run->load('items');

        foreach ($run->items as $runItem) {
            if (in_array($runItem->status, ['completed', 'skipped'], true)) {
                continue;
            }

            $plannedBill = $matchedBillsMap->get($runItem->goods_received_id);
            if (! $plannedBill) {
                $runItem->update(['status' => 'skipped', 'reason_code' => 'plan_item_missing']);

                continue;
            }

            try {
                DB::transaction(function () use ($runItem, $plannedBill, $run, $warehouseId, $userId, $productsMap): void {
                    /** @var DailyAdvanceMatchRunItem $lockedItem */
                    $lockedItem = DailyAdvanceMatchRunItem::query()->whereKey($runItem->id)->lockForUpdate()->firstOrFail();
                    if (in_array($lockedItem->status, ['completed', 'skipped'], true)) {
                        return;
                    }

                    $lockedItem->increment('attempt_count');
                    $lockedItem->update([
                        'status' => 'processing',
                        'last_attempted_at' => now(),
                    ]);

                    $this->executeBillMatch($lockedItem, $plannedBill, $run, $warehouseId, $userId, $productsMap);
                });
            } catch (Throwable $e) {
                Log::error("DailyAdvanceMatch item execution failed for run #{$run->id}, item #{$runItem->id}: {$e->getMessage()}", [
                    'run_id' => $run->id,
                    'run_public_uuid' => $run->public_uuid,
                    'item_id' => $runItem->id,
                    'goods_received_id' => $runItem->goods_received_id,
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]);

                DB::transaction(function () use ($runItem): void {
                    DailyAdvanceMatchRunItem::query()->whereKey($runItem->id)->update([
                        'status' => 'failed',
                        'reason_code' => 'matching_failed',
                        'result_payload' => [
                            'error' => 'Matching execution failed during processing.',
                        ],
                    ]);
                });
            }
        }

        return $this->finalizeRun($run, $userId);
    }

    private function executeBillMatch(
        DailyAdvanceMatchRunItem $item,
        array $plannedBill,
        DailyAdvanceMatchRun $run,
        int $warehouseId,
        int $userId,
        Collection $productsMap
    ): void {
        // 1. Lock bill GRN and lines
        /** @var GoodsReceived|null $billGrn */
        $billGrn = GoodsReceived::query()
            ->whereKey($item->goods_received_id)
            ->lockForUpdate()
            ->first();

        if (! $billGrn || $billGrn->status !== 'approved' || ! $this->readScope->receiptMatchesWarehouse($billGrn, $warehouseId)) {
            $item->update(['status' => 'skipped', 'reason_code' => 'target_state_changed']);

            return;
        }

        $billItems = GoodsReceivedItem::query()
            ->where('goods_received_id', $billGrn->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($billGrn->purchase_order_id) {
            PurchaseOrder::query()->whereKey($billGrn->purchase_order_id)->lockForUpdate()->first();
            PurchaseOrderItem::query()->where('purchase_order_id', $billGrn->purchase_order_id)->orderBy('id')->lockForUpdate()->get();
        }

        // Lock existing matches on this bill
        AdvanceReceiveMatch::query()
            ->where('bill_goods_received_id', $billGrn->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $billRemainingByItem = $this->balanceCalculator->calculateBillRemainingBase($billGrn, $productsMap);
        if (array_sum($billRemainingByItem) <= 0.0001) {
            $item->update(['status' => 'skipped', 'reason_code' => 'already_processed']);

            return;
        }

        // 2. Lock advance GRNs and Items in ascending ID order
        $advGrnIds = collect($plannedBill['lines'])
            ->flatMap(fn ($l) => collect($l['matches'] ?? [])->pluck('advance_goods_received_id'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (empty($advGrnIds)) {
            $item->update(['status' => 'skipped', 'reason_code' => 'no_advance_allocations']);

            return;
        }

        $lockedAdvanceGrns = GoodsReceived::query()->whereIn('id', $advGrnIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $lockedAdvanceItems = GoodsReceivedItem::query()->whereIn('goods_received_id', $advGrnIds)->orderBy('id')->lockForUpdate()->get();
        $lockedAdvanceMatches = AdvanceReceiveMatch::query()->whereIn('advance_goods_received_id', $advGrnIds)->orderBy('id')->lockForUpdate()->get();

        // 3. Compute fresh balances across locked advance GRNs
        $calculatedBalancesByGrn = [];
        foreach ($lockedAdvanceGrns as $aGrn) {
            $calculatedBalancesByGrn[$aGrn->id] = $this->balanceCalculator->calculateItemAvailableBase($aGrn, $productsMap, $lockedAdvanceMatches);
        }

        // 4. Validate coverage and match quantities
        $validatedMatches = [];
        $coverageOk = true;

        foreach ($plannedBill['lines'] as $line) {
            if (empty($line['matches'])) {
                continue;
            }

            $lineProductId = (int) $line['product_id'];
            $billItemId = (int) ($line['item_id'] ?? 0);
            $plannedBillBaseQty = (float) collect($line['matches'])->sum('base_qty');

            if ($billItemId <= 0 || $plannedBillBaseQty > (float) ($billRemainingByItem[$billItemId] ?? 0.0) + 0.0001) {
                $coverageOk = false;
                break;
            }

            /** @var Product|null $lineProduct */
            $lineProduct = $productsMap->get($lineProductId);
            $lineConv = $this->balanceCalculator->resolveStrictUnitConversion($lineProduct, $line['unit']);
            if ($lineConv === null || $lineConv <= 0.0) {
                $normLine = ProductUnit::normalizeUnit($line['unit']);
                $normProd = ProductUnit::normalizeUnit($lineProduct?->unit);
                if ($normLine === $normProd) {
                    $lineConv = 1.0;
                }
            }

            if ($lineConv === null || $lineConv <= 0.0) {
                $item->update(['status' => 'skipped', 'reason_code' => 'invalid_unit_conversion']);

                return;
            }

            $billItemModel = $billItems->firstWhere('id', $billItemId);

            foreach ($line['matches'] as $m) {
                $advGrn = $lockedAdvanceGrns->get((int) $m['advance_goods_received_id']);
                if (! $advGrn || $advGrn->status !== 'approved' || $advGrn->bill_status !== 'bill_pending' || ! $this->readScope->receiptMatchesWarehouse($advGrn, $warehouseId)) {
                    $coverageOk = false;
                    break 2;
                }

                $advItem = $lockedAdvanceItems->firstWhere('id', (int) $m['advance_goods_received_item_id']);
                if (! $advItem || (int) $advItem->product_id !== $lineProductId) {
                    $coverageOk = false;
                    break 2;
                }

                $advProduct = $productsMap->get((int) $advItem->product_id);
                $advConv = $this->balanceCalculator->resolveStrictUnitConversion($advProduct, $advItem->received_unit);
                if ($advConv === null || $advConv <= 0.0) {
                    $normAdv = ProductUnit::normalizeUnit($advItem->received_unit);
                    $normAdvProd = ProductUnit::normalizeUnit($advProduct?->unit);
                    if ($normAdv === $normAdvProd) {
                        $advConv = 1.0;
                    }
                }

                if ($advConv === null || $advConv <= 0.0) {
                    $coverageOk = false;
                    break 2;
                }

                $origAvailBase = (float) ($calculatedBalancesByGrn[$advGrn->id][$advItem->id] ?? 0.0);
                $alreadyInBatch = (float) collect($validatedMatches)
                    ->where('advance_goods_received_id', $advGrn->id)
                    ->where('advance_goods_received_item_id', $advItem->id)
                    ->sum('base_qty');

                $availBase = round($origAvailBase - $alreadyInBatch, 3);
                if ((float) $m['base_qty'] > $availBase + 0.0001) {
                    $coverageOk = false;
                    break 2;
                }

                $validatedMatches[] = [
                    'advance_goods_received_id' => $advGrn->id,
                    'advance_goods_received_item_id' => $advItem->id,
                    'advance_stock_batch_id' => $advGrn->stockBatches->first()?->id,
                    'bill_goods_received_id' => $billGrn->id,
                    'bill_goods_received_item_id' => $billItemId,
                    'purchase_order_id' => $billGrn->purchase_order_id,
                    'purchase_order_item_id' => $billItemModel?->purchase_order_item_id ?? $line['purchase_order_item_id'] ?? null,
                    'product_id' => $lineProductId,
                    'matched_qty' => round((float) $m['base_qty'] / $lineConv, 3),
                    'matched_unit' => $line['unit'],
                    'base_qty' => (float) $m['base_qty'],
                    'conversion_to_base' => $lineConv,
                    'confirmed_by' => $userId,
                    'confirmed_at' => now(),
                    'client_submission_id' => $run->client_submission_id,
                    'notes' => "Daily Auto Match for bill date: {$run->bill_date}",
                ];
            }
        }

        if (! $coverageOk || empty($validatedMatches)) {
            $item->update(['status' => 'skipped', 'reason_code' => 'allocation_changed']);

            return;
        }

        // 5. Insert AdvanceReceiveMatch records
        foreach ($validatedMatches as $matchData) {
            AdvanceReceiveMatch::create($matchData);
        }

        // 6. Check if any touched advance GRN is fully cleared across all items
        $freshAdvanceMatches = AdvanceReceiveMatch::query()->whereIn('advance_goods_received_id', $advGrnIds)->get();
        foreach ($lockedAdvanceGrns as $aGrn) {
            $freshItemBalances = $this->balanceCalculator->calculateItemAvailableBase($aGrn, $productsMap, $freshAdvanceMatches);
            if (array_sum($freshItemBalances) <= 0.0001) {
                $aGrn->update(['bill_status' => 'bill_available']);
            }
        }

        $totalItemMatchedBase = (float) collect($validatedMatches)->sum('base_qty');

        $item->update([
            'status' => 'completed',
            'result_payload' => [
                'matched_base_qty' => $totalItemMatchedBase,
                'grn_number' => $billGrn->grn_number,
                'matches_count' => count($validatedMatches),
            ],
        ]);
    }

    private function finalizeRun(DailyAdvanceMatchRun $run, int $userId): array
    {
        $run->load('items');
        $items = $run->items;

        $completedCount = $items->where('status', 'completed')->count();
        $skippedCount = $items->where('status', 'skipped')->count();
        $failedCount = $items->where('status', 'failed')->count();
        $totalPlanned = $items->count();

        $matchedBase = (float) $items->where('status', 'completed')->sum('planned_base_qty');

        $status = 'completed';
        if ($failedCount > 0 && $completedCount === 0) {
            $status = 'failed';
        } elseif ($skippedCount > 0 || $failedCount > 0) {
            $status = $completedCount > 0 ? 'partial' : 'failed';
        }

        $fullyClearedCount = 0;
        $partiallyClearedCount = 0;

        $planSnapshot = $run->plan_snapshot ?? [];
        $matchedBills = array_merge($planSnapshot['ready_bills'] ?? [], $planSnapshot['partial_bills'] ?? []);
        $completedGrnIds = $items->where('status', 'completed')->pluck('goods_received_id')->all();

        $touchedAdvIds = collect($matchedBills)
            ->whereIn('goods_received_id', $completedGrnIds)
            ->flatMap(fn ($b) => $b['lines'] ?? [])
            ->flatMap(fn ($l) => collect($l['matches'] ?? [])->pluck('advance_goods_received_id'))
            ->unique()
            ->values()
            ->all();

        if (! empty($touchedAdvIds)) {
            $advances = GoodsReceived::query()->whereIn('id', $touchedAdvIds)->get();
            foreach ($advances as $adv) {
                if ($adv->bill_status === 'bill_available') {
                    $fullyClearedCount++;
                } else {
                    $partiallyClearedCount++;
                }
            }
        }

        $resultSummary = [
            'planned' => $totalPlanned,
            'processed' => $completedCount,
            'skipped' => $skippedCount,
            'failed' => $failedCount,
            'matched_base_qty' => round($matchedBase, 3),
            'advances_fully_cleared' => $fullyClearedCount,
            'advances_partially_cleared' => $partiallyClearedCount,
        ];

        $run->update([
            'status' => $status,
            'result_summary' => $resultSummary,
            'completed_at' => now(),
        ]);

        activity('purchasing')
            ->performedOn($run)
            ->causedBy(User::find($userId))
            ->withProperties([
                'run_public_uuid' => $run->public_uuid,
                'warehouse_id' => $run->warehouse_id,
                'bill_date' => $run->bill_date,
                'requested_plan_hash' => $run->requested_plan_hash,
                'status' => $status,
                'summary' => $resultSummary,
            ])
            ->log("Daily Auto Match run {$status}");

        return $this->formatRunResponse($run);
    }

    private function formatRunResponse(DailyAdvanceMatchRun $run): array
    {
        $run->load(['items.goodsReceived', 'items.purchaseOrder']);

        $processed = [];
        $skipped = [];
        $failed = [];

        foreach ($run->items as $item) {
            $entry = [
                'item_id' => $item->id,
                'goods_received_id' => $item->goods_received_id,
                'purchase_order_id' => $item->purchase_order_id,
                'grn_number' => $item->goods_received?->grn_number,
                'matched_base_qty' => (float) $item->planned_base_qty,
                'reason_code' => $item->reason_code,
            ];

            if ($item->status === 'completed') {
                $processed[] = $entry;
            } elseif ($item->status === 'skipped') {
                $skipped[] = $entry;
            } else {
                $failed[] = $entry;
            }
        }

        return [
            'run_id' => $run->public_uuid,
            'client_submission_id' => $run->client_submission_id,
            'status' => $run->status,
            'plan_hash' => $run->requested_plan_hash,
            'summary' => $run->result_summary ?? [],
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }
}
