<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\DTOs\Purchasing\GoodsReceivedData;
use App\Models\AdvanceAutoClearRun;
use App\Models\AdvanceAutoClearRunItem;
use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
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

class AutoAdvanceClearExecutionService
{
    public function __construct(
        private readonly AutoAdvanceClearPlanningService $planningService,
        private readonly AdvanceReceiveReconciliationService $reconciliationService,
        private readonly WarehouseReceiptStateResolver $receiptStateResolver,
        private readonly AdvanceAvailableBalanceCalculator $balanceCalculator,
    ) {}

    /**
     * Execute an auto-clear run idempotently and safely.
     *
     * @return array<string, mixed>
     */
    public function execute(int $warehouseId, string $requestedPlanHash, string $clientSubmissionId, int $userId): array
    {
        // 1. Idempotency Check: Look for existing run by client_submission_id
        /** @var AdvanceAutoClearRun|null $existingRun */
        $existingRun = AdvanceAutoClearRun::query()
            ->where('client_submission_id', $clientSubmissionId)
            ->with(['items.purchaseOrder', 'items.sourceGoodsReceived', 'items.resultGoodsReceived'])
            ->first();

        if ($existingRun) {
            if ($existingRun->warehouse_id !== $warehouseId || $existingRun->requested_plan_hash !== $requestedPlanHash) {
                throw ValidationException::withMessages([
                    'client_submission_id' => 'The submission payload does not match the original auto-clear run.',
                ]);
            }

            // If interrupted before initialization completed, recover and complete initialization
            if ($existingRun->initialized_at === null) {
                DB::transaction(function () use ($existingRun): void {
                    /** @var AdvanceAutoClearRun $locked */
                    $locked = AdvanceAutoClearRun::query()->whereKey($existingRun->id)->lockForUpdate()->firstOrFail();
                    if ($locked->initialized_at === null) {
                        $locked->items()->delete();
                        $position = 0;
                        foreach ($locked->plan_snapshot['ready_bills'] ?? [] as $billPlan) {
                            $locked->items()->create([
                                'position' => ++$position,
                                'execution_mode' => $billPlan['execution_mode'],
                                'purchase_order_id' => $billPlan['purchase_order_id'],
                                'source_goods_received_id' => $billPlan['source_goods_received_id'] ?? null,
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
        $currentPlan = $this->planningService->buildAutoClearPlan($warehouseId, $userId);
        $currentHash = $currentPlan['plan_hash'];

        if ($currentHash !== $requestedPlanHash) {
            return [
                'status_code' => 409,
                'error' => [
                    'success' => false,
                    'code' => 'preview_changed',
                    'message' => 'Advance or pending bill quantities changed. Refresh the preview.',
                    'data' => [
                        'requested_plan_hash' => $requestedPlanHash,
                        'current_plan_hash' => $currentHash,
                    ],
                ],
            ];
        }

        // 3. Atomic Run Creation & Item Initialization in a Single DB Transaction
        try {
            /** @var AdvanceAutoClearRun $run */
            $run = DB::transaction(function () use ($warehouseId, $clientSubmissionId, $requestedPlanHash, $userId, $currentPlan): AdvanceAutoClearRun {
                /** @var AdvanceAutoClearRun|null $lockedExisting */
                $lockedExisting = AdvanceAutoClearRun::query()
                    ->where('client_submission_id', $clientSubmissionId)
                    ->lockForUpdate()
                    ->first();

                if ($lockedExisting) {
                    return $lockedExisting;
                }

                /** @var AdvanceAutoClearRun $createdRun */
                $createdRun = AdvanceAutoClearRun::create([
                    'public_uuid' => (string) Str::uuid(),
                    'client_submission_id' => $clientSubmissionId,
                    'warehouse_id' => $warehouseId,
                    'requested_by' => $userId,
                    'requested_plan_hash' => $requestedPlanHash,
                    'status' => 'pending',
                    'plan_snapshot' => $currentPlan,
                    'started_at' => now(),
                    'initialized_at' => null,
                ]);

                $position = 0;
                foreach ($currentPlan['ready_bills'] as $billPlan) {
                    $createdRun->items()->create([
                        'position' => ++$position,
                        'execution_mode' => $billPlan['execution_mode'],
                        'purchase_order_id' => $billPlan['purchase_order_id'],
                        'source_goods_received_id' => $billPlan['source_goods_received_id'] ?? null,
                        'planned_base_qty' => $billPlan['matched_base_qty'],
                        'status' => 'pending',
                        'attempt_count' => 0,
                    ]);
                }

                $createdRun->update(['initialized_at' => now()]);

                return $createdRun;
            });
        } catch (QueryException) {
            $run = AdvanceAutoClearRun::query()
                ->where('client_submission_id', $clientSubmissionId)
                ->firstOrFail();
        }

        if ($run->warehouse_id !== $warehouseId || $run->requested_plan_hash !== $requestedPlanHash) {
            throw ValidationException::withMessages([
                'client_submission_id' => 'The submission payload does not match the original auto-clear run.',
            ]);
        }

        activity('purchasing')
            ->performedOn($run)
            ->causedBy(User::find($userId))
            ->withProperties([
                'run_public_uuid' => $run->public_uuid,
                'warehouse_id' => $run->warehouse_id,
                'requested_plan_hash' => $run->requested_plan_hash,
                'status' => 'started',
                'planned_count' => count($run->plan_snapshot['ready_bills'] ?? []),
            ])
            ->log('Auto-clear run started');

        return $this->processRunItems($run, $userId);
    }

    private function processRunItems(AdvanceAutoClearRun $run, int $userId): array
    {
        $warehouseId = (int) $run->warehouse_id;
        $planSnapshot = $run->plan_snapshot ?? [];
        $readyBillsPlan = collect($planSnapshot['ready_bills'] ?? [])->keyBy(function ($b) {
            return "{$b['execution_mode']}_{$b['purchase_order_id']}_".($b['source_goods_received_id'] ?? 'none');
        });

        $run->update(['status' => 'processing', 'started_at' => $run->started_at ?? now()]);

        // Preload all product records involved in this run into a product map
        $allProductIds = collect($planSnapshot['ready_bills'] ?? [])
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
            // Completed and skipped items are never repeated
            if (in_array($runItem->status, ['completed', 'skipped'], true)) {
                continue;
            }

            $itemKey = "{$runItem->execution_mode}_{$runItem->purchase_order_id}_".($runItem->source_goods_received_id ?? 'none');
            $plannedItem = $readyBillsPlan->get($itemKey);

            if (! $plannedItem) {
                $runItem->update(['status' => 'skipped', 'reason_code' => 'plan_item_missing']);

                continue;
            }

            try {
                DB::transaction(function () use ($runItem, $plannedItem, $run, $warehouseId, $userId, $productsMap): void {
                    /** @var AdvanceAutoClearRunItem $lockedItem */
                    $lockedItem = AdvanceAutoClearRunItem::query()->whereKey($runItem->id)->lockForUpdate()->firstOrFail();
                    if (in_array($lockedItem->status, ['completed', 'skipped'], true)) {
                        return;
                    }

                    $lockedItem->increment('attempt_count');
                    $lockedItem->update([
                        'status' => 'processing',
                        'last_attempted_at' => now(),
                    ]);

                    if ($lockedItem->execution_mode === 'reconcile_existing_grn') {
                        $this->executeReconcileExistingGrn($lockedItem, $plannedItem, $run, $warehouseId, $userId, $productsMap);
                    } else {
                        $this->executeCreateBillGrn($lockedItem, $plannedItem, $run, $warehouseId, $userId, $productsMap);
                    }
                });
            } catch (Throwable $e) {
                // Log failure with identifiers server-side
                Log::error("AutoAdvanceClear item execution failed for run #{$run->id}, item #{$runItem->id}: {$e->getMessage()}", [
                    'run_id' => $run->id,
                    'run_public_uuid' => $run->public_uuid,
                    'item_id' => $runItem->id,
                    'execution_mode' => $runItem->execution_mode,
                    'purchase_order_id' => $runItem->purchase_order_id,
                    'source_goods_received_id' => $runItem->source_goods_received_id,
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]);

                // Update item status to failed in a separate transaction
                DB::transaction(function () use ($runItem): void {
                    AdvanceAutoClearRunItem::query()->whereKey($runItem->id)->update([
                        'status' => 'failed',
                        'reason_code' => 'reconciliation_failed',
                        'result_payload' => [
                            'error' => 'Reconciliation execution failed during processing.',
                        ],
                    ]);
                });
                // Continue with next items in the run!
            }
        }

        return $this->finalizeRun($run, $userId);
    }

    private function executeReconcileExistingGrn(
        AdvanceAutoClearRunItem $item,
        array $plannedItem,
        AdvanceAutoClearRun $run,
        int $warehouseId,
        int $userId,
        Collection $productsMap
    ): void {
        // 1. Lock rows in ascending order
        /** @var GoodsReceived|null $grn */
        $grn = GoodsReceived::query()
            ->whereKey($item->source_goods_received_id)
            ->lockForUpdate()
            ->first();

        if (! $grn || (int) $grn->warehouse_id !== $warehouseId || (int) $grn->purchase_order_id !== (int) $item->purchase_order_id) {
            $item->update(['status' => 'skipped', 'reason_code' => 'target_state_changed']);

            return;
        }

        // Lock source GRN items
        $grnItems = GoodsReceivedItem::query()->where('goods_received_id', $grn->id)->orderBy('id')->lockForUpdate()->get();

        // Lock target PO and PO items
        $po = PurchaseOrder::query()->whereKey($item->purchase_order_id)->lockForUpdate()->first();
        if ($po) {
            PurchaseOrderItem::query()->where('purchase_order_id', $po->id)->orderBy('id')->lockForUpdate()->get();
        }

        $facts = $this->receiptStateResolver->forReceipt($grn);
        if (($facts['receipt_status'] ?? '') === 'received') {
            $item->update(['status' => 'skipped', 'reason_code' => 'already_processed', 'result_goods_received_id' => $grn->id]);

            return;
        }

        // Lock advance GRNs and Items in ascending ID order
        $advGrnIds = collect($plannedItem['lines'])->flatMap(fn ($l) => collect($l['matches'])->pluck('advance_goods_received_id'))->unique()->sort()->values()->all();
        $lockedAdvanceGrns = GoodsReceived::query()->whereIn('id', $advGrnIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $lockedAdvanceItems = GoodsReceivedItem::query()->whereIn('goods_received_id', $advGrnIds)->orderBy('id')->lockForUpdate()->get();
        AdvanceReceiveMatch::query()->whereIn('advance_goods_received_id', $advGrnIds)->orderBy('id')->lockForUpdate()->get();

        // Precompute available balances across locked advance GRNs using shared calculator
        $calculatedBalancesByGrn = [];
        foreach ($lockedAdvanceGrns as $aGrn) {
            $calculatedBalancesByGrn[$aGrn->id] = $this->balanceCalculator->calculateItemAvailableBase($aGrn, $productsMap);
        }

        // Revalidate coverage and quantities
        $validatedMatches = [];
        $coverageOk = true;

        foreach ($plannedItem['lines'] as $line) {
            $lineProductId = (int) $line['product_id'];
            /** @var Product|null $lineProduct */
            $lineProduct = $productsMap->get($lineProductId);
            $lineConv = $this->balanceCalculator->resolveStrictUnitConversion($lineProduct, $line['unit']);

            if ($lineConv === null || $lineConv <= 0.0) {
                $item->update(['status' => 'skipped', 'reason_code' => 'invalid_unit_conversion']);

                return;
            }

            foreach ($line['matches'] as $m) {
                $advGrn = $lockedAdvanceGrns->get((int) $m['advance_goods_received_id']);
                if (! $advGrn || $advGrn->status !== 'approved' || $advGrn->bill_status !== 'bill_pending' || (int) $advGrn->warehouse_id !== $warehouseId) {
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
                    'purchase_order_item_id' => $line['purchase_order_item_id'] ?? null,
                    'goods_received_item_id' => $line['source_item_id'] ?? null,
                    'product_id' => $lineProductId,
                    'matched_qty' => round((float) $m['base_qty'] / $lineConv, 3),
                    'matched_unit' => $line['unit'],
                    'base_qty' => (float) $m['base_qty'],
                ];
            }
        }

        if (! $coverageOk) {
            $item->update(['status' => 'skipped', 'reason_code' => 'allocation_changed']);

            return;
        }

        $grnItemsData = [];
        foreach ($grnItems as $gItem) {
            $grnItemsData[$gItem->id] = [
                'received_qty' => (float) $gItem->received_qty,
                'received_unit' => $gItem->received_unit,
                'discrepancy_type' => 'none',
            ];
        }

        $reconciledGrn = $this->reconciliationService->reconcileExistingGrn(
            $grn,
            $grnItemsData,
            $validatedMatches,
            $warehouseId,
            $userId
        );

        $item->update([
            'status' => 'completed',
            'result_goods_received_id' => $reconciledGrn->id,
            'result_payload' => [
                'matched_base_qty' => (float) $item->planned_base_qty,
                'grn_number' => $reconciledGrn->grn_number,
            ],
        ]);
    }

    private function executeCreateBillGrn(
        AdvanceAutoClearRunItem $item,
        array $plannedItem,
        AdvanceAutoClearRun $run,
        int $warehouseId,
        int $userId,
        Collection $productsMap
    ): void {
        // 1. Lock rows in ascending order
        /** @var PurchaseOrder|null $order */
        $order = PurchaseOrder::query()
            ->whereKey($item->purchase_order_id)
            ->lockForUpdate()
            ->first();

        if (! $order || ! in_array($order->status->value, ['approved', 'sent_to_supplier', 'partially_received'], true)) {
            $item->update(['status' => 'skipped', 'reason_code' => 'target_state_changed']);

            return;
        }

        $poItems = PurchaseOrderItem::query()->where('purchase_order_id', $order->id)->orderBy('id')->lockForUpdate()->get();
        $existingOrderGrns = GoodsReceived::query()->where('purchase_order_id', $order->id)->orderBy('id')->lockForUpdate()->get();
        $existingOrderGrnItems = GoodsReceivedItem::query()->whereIn('goods_received_id', $existingOrderGrns->pluck('id'))->orderBy('id')->lockForUpdate()->get();

        // Check if a new pending GRN appeared after run was created
        foreach ($existingOrderGrns as $eGrn) {
            $facts = $this->receiptStateResolver->forReceipt($eGrn);
            if (($facts['receipt_status'] ?? '') === 'pending') {
                $item->update(['status' => 'skipped', 'reason_code' => 'target_state_changed']);

                return;
            }
        }

        // Lock advance GRNs and Items in ascending ID order
        $advGrnIds = collect($plannedItem['lines'])->flatMap(fn ($l) => collect($l['matches'])->pluck('advance_goods_received_id'))->unique()->sort()->values()->all();
        $lockedAdvanceGrns = GoodsReceived::query()->whereIn('id', $advGrnIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $lockedAdvanceItems = GoodsReceivedItem::query()->whereIn('goods_received_id', $advGrnIds)->orderBy('id')->lockForUpdate()->get();
        AdvanceReceiveMatch::query()->whereIn('advance_goods_received_id', $advGrnIds)->orderBy('id')->lockForUpdate()->get();

        // Precompute available balances across locked advance GRNs using shared calculator
        $calculatedBalancesByGrn = [];
        foreach ($lockedAdvanceGrns as $aGrn) {
            $calculatedBalancesByGrn[$aGrn->id] = $this->balanceCalculator->calculateItemAvailableBase($aGrn, $productsMap);
        }

        // Calculate already completed receipts by PO item
        $completedByPoItem = [];
        foreach ($existingOrderGrns as $eGrn) {
            $facts = $this->receiptStateResolver->forReceipt($eGrn);
            if (($facts['receipt_status'] ?? '') === 'received') {
                foreach ($existingOrderGrnItems->where('goods_received_id', $eGrn->id) as $gi) {
                    if ($gi->purchase_order_item_id) {
                        $p = $productsMap->get($gi->product_id);
                        $c = $this->balanceCalculator->resolveStrictUnitConversion($p, $gi->received_unit) ?? 1.0;
                        $bQty = round((float) $gi->received_qty * $c, 3);
                        $completedByPoItem[$gi->purchase_order_item_id] = ($completedByPoItem[$gi->purchase_order_item_id] ?? 0.0) + $bQty;
                    }
                }
            }
        }

        // Revalidate target PO line quantities match remaining outstanding quantities
        foreach ($plannedItem['lines'] as $line) {
            $poItemId = (int) $line['purchase_order_item_id'];
            $poItem = $poItems->firstWhere('id', $poItemId);
            if (! $poItem) {
                $item->update(['status' => 'skipped', 'reason_code' => 'target_state_changed']);

                return;
            }

            $product = $productsMap->get((int) $poItem->product_id);
            $conv = $this->balanceCalculator->resolveStrictUnitConversion($product, $line['unit']);
            if ($conv === null || $conv <= 0.0) {
                $item->update(['status' => 'skipped', 'reason_code' => 'invalid_unit_conversion']);

                return;
            }

            $orderedBase = round((float) $poItem->quantity * $conv, 3);
            $compBase = (float) ($completedByPoItem[$poItemId] ?? 0.0);
            $currentOutstandingBase = max(0.0, round($orderedBase - $compBase, 3));

            if (abs($currentOutstandingBase - (float) $line['required_base_qty']) > 0.001) {
                $item->update(['status' => 'skipped', 'reason_code' => 'target_state_changed']);

                return;
            }
        }

        // Revalidate advance coverage
        $validatedMatches = [];
        $coverageOk = true;

        foreach ($plannedItem['lines'] as $line) {
            $lineProductId = (int) $line['product_id'];
            /** @var Product|null $lineProduct */
            $lineProduct = $productsMap->get($lineProductId);
            $lineConv = $this->balanceCalculator->resolveStrictUnitConversion($lineProduct, $line['unit']);

            if ($lineConv === null || $lineConv <= 0.0) {
                $item->update(['status' => 'skipped', 'reason_code' => 'invalid_unit_conversion']);

                return;
            }

            foreach ($line['matches'] as $m) {
                $advGrn = $lockedAdvanceGrns->get((int) $m['advance_goods_received_id']);
                if (! $advGrn || $advGrn->status !== 'approved' || $advGrn->bill_status !== 'bill_pending' || (int) $advGrn->warehouse_id !== $warehouseId) {
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
                    'purchase_order_item_id' => $line['purchase_order_item_id'] ?? null,
                    'product_id' => $lineProductId,
                    'matched_qty' => round((float) $m['base_qty'] / $lineConv, 3),
                    'unit' => $line['unit'],
                    'base_qty' => (float) $m['base_qty'],
                ];
            }
        }

        if (! $coverageOk) {
            $item->update(['status' => 'skipped', 'reason_code' => 'allocation_changed']);

            return;
        }

        $itemsData = [];
        foreach ($plannedItem['lines'] as $line) {
            $itemsData[] = [
                'purchase_order_item_id' => $line['purchase_order_item_id'],
                'product_id' => $line['product_id'],
                'received_qty' => (float) $line['quantity'],
                'received_unit' => $line['unit'],
            ];
        }

        $targetClientSubId = hash('sha256', "{$run->client_submission_id}:po-{$order->id}:item-{$item->id}");

        $grnData = new GoodsReceivedData(
            purchaseOrderId: $order->id,
            receivedAt: now()->toDateTimeString(),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: "Auto-cleared via advance reconciliation run #{$run->id}",
            items: $itemsData,
            billStatus: 'bill_available',
            billNumber: null,
            destinationShopId: $warehouseId,
            warehouseId: $warehouseId,
            clientSubmissionId: $targetClientSubId,
            advanceMatches: $validatedMatches,
            receiptType: 'normal_purchase',
        );

        $createdGrn = $this->reconciliationService->reconcileAndExecute($grnData, $userId);

        $item->update([
            'status' => 'completed',
            'result_goods_received_id' => $createdGrn->id,
            'result_payload' => [
                'matched_base_qty' => (float) $item->planned_base_qty,
                'grn_number' => $createdGrn->grn_number,
            ],
        ]);
    }

    private function finalizeRun(AdvanceAutoClearRun $run, int $userId): array
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
        $readyBills = collect($planSnapshot['ready_bills'] ?? []);
        $completedPoIds = $items->where('status', 'completed')->pluck('purchase_order_id')->all();

        $touchedAdvIds = $readyBills
            ->whereIn('purchase_order_id', $completedPoIds)
            ->flatMap(fn ($b) => $b['lines'] ?? [])
            ->flatMap(fn ($l) => collect($l['matches'] ?? [])->pluck('advance_goods_received_id'))
            ->unique()
            ->values()
            ->all();

        if ($touchedAdvIds !== []) {
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
                'requested_plan_hash' => $run->requested_plan_hash,
                'status' => $status,
                'summary' => $resultSummary,
            ])
            ->log("Auto-clear run {$status}");

        return $this->formatRunResponse($run);
    }

    private function formatRunResponse(AdvanceAutoClearRun $run): array
    {
        $run->load(['items.purchaseOrder', 'items.sourceGoodsReceived', 'items.resultGoodsReceived']);

        $processed = [];
        $skipped = [];
        $failed = [];

        foreach ($run->items as $item) {
            $entry = [
                'item_id' => $item->id,
                'execution_mode' => $item->execution_mode,
                'purchase_order_id' => $item->purchase_order_id,
                'source_goods_received_id' => $item->source_goods_received_id,
                'result_goods_received_id' => $item->result_goods_received_id,
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
