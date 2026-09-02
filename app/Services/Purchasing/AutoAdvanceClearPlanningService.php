<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AutoAdvanceClearPlanningService
{
    public function __construct(
        private readonly WarehouseReceiptStateResolver $receiptStateResolver,
        private readonly AdvanceAvailableBalanceCalculator $balanceCalculator,
        private readonly WarehouseReceiptReadScope $readScope,
    ) {}

    /**
     * Generate a deterministic, read-only plan preview for auto-matching pending bills against open advances.
     *
     * @return array{
     *     warehouse_id: int,
     *     generated_at: string,
     *     plan_hash: string,
     *     summary: array{
     *         pending_bills: int,
     *         ready_bills: int,
     *         skipped_bills: int,
     *         advances_fully_cleared: int,
     *         advances_partially_cleared: int,
     *         matched_base_qty: float
     *     },
     *     ready_bills: array<int, array<string, mixed>>,
     *     skipped_bills: array<int, array<string, mixed>>,
     *     advance_allocations: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>
     * }
     */
    public function buildAutoClearPlan(int $warehouseId, int $userId): array
    {
        $warnings = [];

        // 1. Pre-load all eligible pending purchase orders and their goods receipts
        $pendingOrdersQuery = PurchaseOrder::query()
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->where(function (Builder $pending): void {
                $pending->whereHas('goodsReceiveds', fn ($receipts) => $this->receiptStateResolver->filter($receipts, 'pending'))
                    ->orWhere(function ($withoutPendingReceipt): void {
                        $withoutPendingReceipt->whereDoesntHave('goodsReceiveds', fn ($receipts) => $this->receiptStateResolver->filter($receipts, 'pending'))
                            ->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received']);
                    });
            });

        $this->readScope->orders($pendingOrdersQuery, [$warehouseId]);

        $pendingOrders = $pendingOrdersQuery
            ->with([
                'items.product.orderUnits',
                'supplier:id,name',
                'destinationShop:id,name',
                'goodsReceiveds' => fn ($grnQuery) => $this->receiptStateResolver->withFacts(
                    $grnQuery->select('goods_received.*')->with(['items.product.orderUnits', 'stockBatches'])
                ),
            ])
            ->orderBy('order_date')
            ->orderBy('id')
            ->get();

        // 2. Pre-load all confirmed open advances for this warehouse (ordered deterministically by received_at ASC, id ASC)
        $openAdvancesQuery = GoodsReceived::query()
            ->where(function (Builder $typeQuery): void {
                $typeQuery->where('goods_received.receipt_type', 'warehouse_advance')
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('goods_received.receipt_type')
                            ->whereNull('goods_received.purchase_order_id');
                    });
            })
            ->where('goods_received.status', 'approved')
            ->where('goods_received.bill_status', 'bill_pending')
            ->whereDoesntHave('purchaseInvoices')
            ->whereHas('stockBatches', function (Builder $batchQuery): void {
                $batchQuery->where('warehouse_receive_pending', false);
            });

        $this->readScope->receipts($openAdvancesQuery, [$warehouseId]);

        $openAdvances = $openAdvancesQuery
            ->with([
                'items.product.orderUnits',
                'stockBatches',
            ])
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        // Bulk load all existing matches for these open advances in 1 query
        $advanceIds = $openAdvances->pluck('id')->all();
        $existingMatches = $advanceIds !== []
            ? AdvanceReceiveMatch::query()->whereIn('advance_goods_received_id', $advanceIds)->get()
            : collect();

        $matchesByAdvanceId = $existingMatches->groupBy('advance_goods_received_id');

        // 3. Build in-memory virtual advance slots keyed by product_id with STRICT unit conversion
        /** @var array<int, array<int, array<string, mixed>>> $virtualPoolsByProduct */
        $virtualPoolsByProduct = [];
        /** @var array<int, array<string, mixed>> $advanceTracking */
        $advanceTracking = [];

        foreach ($openAdvances as $advGrn) {
            $grnMatches = $matchesByAdvanceId->get($advGrn->id, collect());
            $grnInitialUnbilledBase = 0.0;

            // Legacy unassigned matches for each product on this GRN (advance_goods_received_item_id is NULL)
            $legacyUnassignedByProd = [];
            foreach ($grnMatches->whereNull('advance_goods_received_item_id') as $m) {
                $legacyUnassignedByProd[$m->product_id] = ($legacyUnassignedByProd[$m->product_id] ?? 0.0) + (float) $m->base_qty;
            }

            foreach ($advGrn->items as $item) {
                /** @var Product|null $product */
                $product = $item->product;
                if (! $product) {
                    $warnings[] = [
                        'advance_goods_received_id' => $advGrn->id,
                        'advance_goods_received_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'warning' => 'product_not_found',
                    ];

                    continue;
                }

                $conv = $this->resolveStrictUnitConversion($product, $item->received_unit) ?? 1.0;

                $originalQty = (float) $item->received_qty;
                $originalBaseQty = round($originalQty * $conv, 3);

                // Explicit item-level matches
                $explicitItemMatchedBase = (float) $grnMatches
                    ->where('advance_goods_received_item_id', $item->id)
                    ->sum('base_qty');

                $itemRemainingBase = max(0.0, round($originalBaseQty - $explicitItemMatchedBase, 3));

                // Absorb legacy unassigned matches for this product if any
                $legacyPool = (float) ($legacyUnassignedByProd[$item->product_id] ?? 0.0);
                $legacyApplied = 0.0;
                if ($legacyPool > 0.0001 && $itemRemainingBase > 0.0001) {
                    $legacyApplied = min($itemRemainingBase, $legacyPool);
                    $legacyUnassignedByProd[$item->product_id] = $legacyPool - $legacyApplied;
                    $itemRemainingBase = max(0.0, round($itemRemainingBase - $legacyApplied, 3));
                }

                $totalAlreadyMatchedBase = round($explicitItemMatchedBase + $legacyApplied, 3);

                if ($itemRemainingBase > 0.0001) {
                    $grnInitialUnbilledBase += $itemRemainingBase;

                    $slotIndex = count($virtualPoolsByProduct[$item->product_id] ?? []);
                    $slot = [
                        'advance_goods_received_id' => $advGrn->id,
                        'advance_goods_received_item_id' => $item->id,
                        'grn_number' => $advGrn->grn_number,
                        'received_at' => $advGrn->received_at instanceof Carbon ? $advGrn->received_at->toDateString() : (string) $advGrn->received_at,
                        'product_id' => $item->product_id,
                        'unit' => $item->received_unit ?? $product->unit,
                        'conversion_to_base' => $conv,
                        'received_base_qty' => $originalBaseQty,
                        'already_matched_base_qty' => $totalAlreadyMatchedBase,
                        'initial_available_base_qty' => $itemRemainingBase,
                        'remaining_base_qty' => $itemRemainingBase,
                        'preview_matched_base_qty' => 0.0,
                    ];

                    $virtualPoolsByProduct[$item->product_id][$slotIndex] = $slot;
                }
            }

            $advanceTracking[$advGrn->id] = [
                'grn_number' => $advGrn->grn_number,
                'initial_unbilled_base_qty' => round($grnInitialUnbilledBase, 3),
                'preview_matched_base_qty' => 0.0,
            ];
        }

        // Also pre-load unconfirmed advance quantities for exact unmatch reason resolution
        $unconfirmedAdvancesQuery = GoodsReceived::query()
            ->where(function (Builder $typeQuery): void {
                $typeQuery->where('goods_received.receipt_type', 'warehouse_advance')
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('goods_received.receipt_type')
                            ->whereNull('goods_received.purchase_order_id');
                    });
            })
            ->where('goods_received.status', 'approved')
            ->where('goods_received.bill_status', 'bill_pending')
            ->whereDoesntHave('purchaseInvoices')
            ->whereHas('stockBatches', function (Builder $batchQuery): void {
                $batchQuery->where('warehouse_receive_pending', true);
            });

        $this->readScope->receipts($unconfirmedAdvancesQuery, [$warehouseId]);

        $unconfirmedAdvances = $unconfirmedAdvancesQuery->with(['items.product.orderUnits'])->get();
        $unconfirmedAdvanceBaseByProduct = [];
        foreach ($unconfirmedAdvances as $uGrn) {
            foreach ($uGrn->items as $uItem) {
                $uProd = $uItem->product;
                if (! $uProd) {
                    continue;
                }
                $uConv = $this->resolveStrictUnitConversion($uProd, $uItem->received_unit) ?? 1.0;
                $uBase = round((float) $uItem->received_qty * $uConv, 3);
                $unconfirmedAdvanceBaseByProduct[$uProd->id] = ($unconfirmedAdvanceBaseByProduct[$uProd->id] ?? 0.0) + $uBase;
            }
        }

        $initialConfirmedBaseByProduct = [];
        foreach ($virtualPoolsByProduct as $pId => $slots) {
            $initialConfirmedBaseByProduct[$pId] = round(array_sum(array_column($slots, 'initial_available_base_qty')), 3);
        }

        // 4. Construct Bill Targets (discerning existing pending GRNs vs PO-level remaining receivables)
        $allPendingOrderIds = $pendingOrders->pluck('id')->all();
        $existingMatches = ! empty($allPendingOrderIds)
            ? AdvanceReceiveMatch::query()->whereIn('purchase_order_id', $allPendingOrderIds)->get()
            : collect();

        $billTargets = [];

        foreach ($pendingOrders as $order) {
            $billDateStr = $order->order_date instanceof Carbon ? $order->order_date->toDateString() : (string) ($order->order_date ?? '');

            // Separate completed GRNs vs pending GRNs for this order
            $pendingGrns = collect();
            $completedItemReceivedBase = []; // [po_item_id => base_qty]

            foreach ($order->goodsReceiveds as $grn) {
                $facts = $this->receiptStateResolver->forReceipt($grn);
                if (($facts['receipt_status'] ?? '') === 'received') {
                    // Completed delivery: track already received base quantity by PO item
                    foreach ($grn->items as $gItem) {
                        if ($gItem->purchase_order_item_id) {
                            $prod = $gItem->product;
                            $c = $this->resolveStrictUnitConversion($prod, $gItem->received_unit) ?? 1.0;
                            $bQty = round((float) $gItem->received_qty * $c, 3);
                            $completedItemReceivedBase[$gItem->purchase_order_item_id] = ($completedItemReceivedBase[$gItem->purchase_order_item_id] ?? 0.0) + $bQty;
                        }
                    }
                } else {
                    // Pending GRN
                    $pendingGrns->push($grn);
                }
            }

            if ($pendingGrns->isNotEmpty()) {
                // A. For each existing pending GRN: create a distinct plan entry
                foreach ($pendingGrns->sortBy('id') as $pGrn) {
                    $targetLines = [];
                    foreach ($pGrn->items->sortBy('id') as $pItem) {
                        $prod = $pItem->product;
                        $unit = $pItem->received_unit ?: $pItem->product?->unit;
                        $conv = $this->resolveStrictUnitConversion($prod, $unit) ?? 1.0;
                        $itemBase = round((float) $pItem->received_qty * $conv, 3);

                        $alreadyMatchedBase = (float) $existingMatches
                            ->where('purchase_order_id', $order->id)
                            ->filter(function ($m) use ($pItem, $pGrn): bool {
                                if ($m->bill_goods_received_id === $pGrn->id && $m->bill_goods_received_item_id === $pItem->id) {
                                    return true;
                                }

                                return $pItem->purchase_order_item_id && $m->purchase_order_item_id === $pItem->purchase_order_item_id;
                            })
                            ->sum('base_qty');

                        $remBase = max(0.0, round($itemBase - $alreadyMatchedBase, 3));
                        $remQty = $conv > 0 ? round($remBase / $conv, 3) : $remBase;

                        if ($remBase > 0.0001) {
                            $targetLines[] = [
                                'source_item_id' => $pItem->id,
                                'purchase_order_item_id' => $pItem->purchase_order_item_id,
                                'product' => $pItem->product,
                                'product_id' => $pItem->product_id,
                                'unit' => $unit,
                                'quantity' => $remQty,
                                'already_matched_base_qty' => $alreadyMatchedBase,
                            ];
                        }
                    }

                    $billTargets[] = [
                        'execution_mode' => 'reconcile_existing_grn',
                        'purchase_order_id' => $order->id,
                        'source_goods_received_id' => $pGrn->id,
                        'reference' => $pGrn->grn_number ?: $order->po_number,
                        'supplier_id' => $order->supplier_id,
                        'supplier_name' => $order->supplier?->name ?? 'Vendor',
                        'bill_date' => $billDateStr,
                        'order_date' => $order->order_date,
                        'order_id' => $order->id,
                        'lines' => $targetLines,
                    ];
                }
            } else {
                // B. Order without a pending GRN: calculate remaining outstanding receivable
                $targetLines = [];
                foreach ($order->items->sortBy('id') as $poItem) {
                    $product = $poItem->product;
                    $itemUnit = $poItem->purchase_unit ?: $poItem->unit ?: $product?->unit;
                    $conv = $this->resolveStrictUnitConversion($product, $itemUnit) ?? 1.0;

                    $orderedQty = (float) $poItem->quantity;
                    if ($orderedQty <= 0.0001) {
                        $targetLines[] = [
                            'source_item_id' => $poItem->id,
                            'purchase_order_item_id' => $poItem->id,
                            'product' => $product,
                            'product_id' => $poItem->product_id,
                            'unit' => $itemUnit,
                            'quantity' => $orderedQty,
                            'already_matched_base_qty' => 0.0,
                        ];

                        continue;
                    }

                    $orderedBaseQty = round($orderedQty * $conv, 3);
                    $completedBaseQty = (float) ($completedItemReceivedBase[$poItem->id] ?? 0.0);
                    $alreadyMatchedBaseQty = (float) $existingMatches
                        ->where('purchase_order_id', $order->id)
                        ->where('purchase_order_item_id', $poItem->id)
                        ->sum('base_qty');

                    $totalDeductedBase = round($completedBaseQty + $alreadyMatchedBaseQty, 3);
                    $remainingBaseQty = max(0.0, round($orderedBaseQty - $totalDeductedBase, 3));
                    $remainingItemQty = $conv > 0 ? round($remainingBaseQty / $conv, 3) : $remainingBaseQty;

                    if ($remainingBaseQty > 0.0001) {
                        $targetLines[] = [
                            'source_item_id' => $poItem->id,
                            'purchase_order_item_id' => $poItem->id,
                            'product' => $product,
                            'product_id' => $poItem->product_id,
                            'unit' => $itemUnit,
                            'quantity' => $remainingItemQty,
                            'already_matched_base_qty' => $totalDeductedBase,
                        ];
                    }
                }

                $billTargets[] = [
                    'execution_mode' => 'create_bill_grn',
                    'purchase_order_id' => $order->id,
                    'source_goods_received_id' => null,
                    'reference' => $order->po_number,
                    'supplier_id' => $order->supplier_id,
                    'supplier_name' => $order->supplier?->name ?? 'Vendor',
                    'bill_date' => $billDateStr,
                    'order_date' => $order->order_date,
                    'order_id' => $order->id,
                    'lines' => $targetLines,
                ];
            }
        }

        // 5. Sequential Evaluation across Bill Targets
        $readyBills = [];
        $skippedBills = [];
        $totalMatchedBaseQty = 0.0;

        foreach ($billTargets as $target) {
            $billLines = $target['lines'];

            // Blocker 4: Check empty bill
            if ($billLines === []) {
                $skippedBills[] = [
                    'execution_mode' => $target['execution_mode'],
                    'purchase_order_id' => $target['purchase_order_id'],
                    'source_goods_received_id' => $target['source_goods_received_id'],
                    'reference' => $target['reference'],
                    'supplier_id' => $target['supplier_id'],
                    'supplier_name' => $target['supplier_name'],
                    'bill_date' => $target['bill_date'],
                    'reason' => 'no_reconcilable_items',
                    'shortages' => [],
                    'lines' => [],
                ];

                continue;
            }

            $billTotalRequiredBase = 0.0;
            $evaluatedLines = [];

            foreach ($billLines as $line) {
                /** @var Product|null $product */
                $product = $line['product'];
                $qty = (float) $line['quantity'];
                $alreadyMatchedBase = (float) ($line['already_matched_base_qty'] ?? 0.0);

                $goodsReceivedItemId = $target['execution_mode'] === 'reconcile_existing_grn' ? $line['source_item_id'] : null;
                $initialConfirmedForProduct = $product ? (float) ($initialConfirmedBaseByProduct[$product->id] ?? 0.0) : 0.0;
                $unconfirmedForProduct = $product ? (float) ($unconfirmedAdvanceBaseByProduct[$product->id] ?? 0.0) : 0.0;

                if (! $product) {
                    $evaluatedLines[] = [
                        'source_item_id' => $line['source_item_id'],
                        'purchase_order_item_id' => $line['purchase_order_item_id'],
                        'goods_received_item_id' => $goodsReceivedItemId,
                        'product_id' => $line['product_id'],
                        'product_name' => 'Unknown',
                        'product_sku' => '',
                        'unit' => $line['unit'],
                        'quantity' => $qty,
                        'conversion_to_base' => 1.0,
                        'required_base_qty' => 0.0,
                        'already_matched_base_qty' => $alreadyMatchedBase,
                        'planned_matched_base_qty' => 0.0,
                        'matched_base_qty' => 0.0,
                        'remaining_unmatched_base_qty' => 0.0,
                        'classification' => 'INVALID_DATA',
                        'unmatched_reason' => 'INVALID_DATA',
                        'confirmed_advance_qty' => 0.0,
                        'unconfirmed_advance_qty' => 0.0,
                        'matches' => [],
                        'allocations' => [],
                    ];

                    continue;
                }

                if ($qty <= 0.0001) {
                    $evaluatedLines[] = [
                        'source_item_id' => $line['source_item_id'],
                        'purchase_order_item_id' => $line['purchase_order_item_id'],
                        'goods_received_item_id' => $goodsReceivedItemId,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit' => $line['unit'],
                        'quantity' => $qty,
                        'conversion_to_base' => 1.0,
                        'required_base_qty' => 0.0,
                        'already_matched_base_qty' => $alreadyMatchedBase,
                        'planned_matched_base_qty' => 0.0,
                        'matched_base_qty' => 0.0,
                        'remaining_unmatched_base_qty' => 0.0,
                        'classification' => 'NO_RECONCILABLE_QUANTITY',
                        'unmatched_reason' => 'NO_RECONCILABLE_QUANTITY',
                        'confirmed_advance_qty' => 0.0,
                        'unconfirmed_advance_qty' => 0.0,
                        'matches' => [],
                        'allocations' => [],
                    ];

                    continue;
                }

                $normLineUnit = ProductUnit::normalizeUnit($line['unit']);
                $hasSameUnitSlot = false;
                if (isset($virtualPoolsByProduct[$product->id]) && is_array($virtualPoolsByProduct[$product->id])) {
                    foreach ($virtualPoolsByProduct[$product->id] as $vSlot) {
                        if (ProductUnit::normalizeUnit($vSlot['unit']) === $normLineUnit) {
                            $hasSameUnitSlot = true;
                            break;
                        }
                    }
                }

                $conv = $this->resolveStrictUnitConversion($product, $line['unit']);
                if ($conv === null && $hasSameUnitSlot) {
                    $conv = 1.0;
                }

                if ($conv === null) {
                    $hasAdvanceForProduct = ($initialConfirmedForProduct > 0.0001 || $unconfirmedForProduct > 0.0001);
                    $lineClass = $hasAdvanceForProduct ? 'UNIT_DIFFERENCE' : 'NO_ADVANCE';
                    $unmatchReason = $hasAdvanceForProduct ? 'UNIT_DIFFERENCE' : 'NO_ADVANCE';

                    $evaluatedLines[] = [
                        'source_item_id' => $line['source_item_id'],
                        'purchase_order_item_id' => $line['purchase_order_item_id'],
                        'goods_received_item_id' => $goodsReceivedItemId,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit' => $line['unit'],
                        'quantity' => $qty,
                        'conversion_to_base' => null,
                        'required_base_qty' => $qty,
                        'already_matched_base_qty' => $alreadyMatchedBase,
                        'planned_matched_base_qty' => 0.0,
                        'matched_base_qty' => 0.0,
                        'remaining_unmatched_base_qty' => $qty,
                        'classification' => $lineClass,
                        'unmatched_reason' => $unmatchReason,
                        'confirmed_advance_qty' => $initialConfirmedForProduct,
                        'unconfirmed_advance_qty' => $unconfirmedForProduct,
                        'matches' => [],
                        'allocations' => [],
                    ];

                    continue;
                }

                $requiredBase = round($qty * $conv, 3);
                $billTotalRequiredBase += $requiredBase;

                $poolExists = isset($virtualPoolsByProduct[$product->id]) && is_array($virtualPoolsByProduct[$product->id]);
                $availableBaseInPool = $poolExists ? array_sum(array_column($virtualPoolsByProduct[$product->id], 'remaining_base_qty')) : 0.0;

                $lineMatches = [];
                $allocations = [];
                $neededBase = min($requiredBase, $availableBaseInPool);
                $matchedBase = 0.0;

                if ($neededBase > 0.0001 && $poolExists) {
                    foreach ($virtualPoolsByProduct[$product->id] as $sIdx => &$slot) {
                        if ($neededBase <= 0.0001) {
                            break;
                        }

                        $slotAvail = (float) $slot['remaining_base_qty'];
                        if ($slotAvail <= 0.0001) {
                            continue;
                        }

                        $normLineUnit = ProductUnit::normalizeUnit($line['unit']);
                        $normSlotUnit = ProductUnit::normalizeUnit($slot['unit']);
                        if ($normLineUnit !== $normSlotUnit) {
                            $slotConv = $this->resolveStrictUnitConversion($product, $slot['unit']);
                            if ($slotConv === null || $conv === null) {
                                continue;
                            }
                        }

                        $takeBase = min($slotAvail, $neededBase);
                        $slot['remaining_base_qty'] = round($slotAvail - $takeBase, 3);
                        $slot['preview_matched_base_qty'] = round($slot['preview_matched_base_qty'] + $takeBase, 3);
                        $neededBase = round($neededBase - $takeBase, 3);
                        $matchedBase = round($matchedBase + $takeBase, 3);

                        $advGrnId = $slot['advance_goods_received_id'];
                        if (isset($advanceTracking[$advGrnId])) {
                            $advanceTracking[$advGrnId]['preview_matched_base_qty'] = round(
                                $advanceTracking[$advGrnId]['preview_matched_base_qty'] + $takeBase,
                                3
                            );
                        }

                        $allocItem = [
                            'advance_goods_received_id' => $slot['advance_goods_received_id'],
                            'advance_goods_received_item_id' => $slot['advance_goods_received_item_id'],
                            'grn_number' => $slot['grn_number'],
                            'received_at' => $slot['received_at'],
                            'product_id' => $product->id,
                            'matched_base_qty' => round($takeBase, 3),
                            'base_qty' => round($takeBase, 3),
                            'matched_unit' => $line['unit'],
                            'matched_qty' => $conv > 0 ? round($takeBase / $conv, 3) : $takeBase,
                        ];
                        $lineMatches[] = $allocItem;
                        $allocations[] = $allocItem;
                    }
                    unset($slot);
                }

                $remainingUnmatchedBase = max(0.0, round($requiredBase - $matchedBase, 3));

                if ($matchedBase >= $requiredBase - 0.0001) {
                    $lineClassification = 'FULL_MATCH';
                    $unmatchedReason = 'NONE';
                } elseif ($matchedBase > 0.0001) {
                    $lineClassification = 'PARTIAL_MATCH';
                    $unmatchedReason = 'PARTIAL_REMAINDER';
                } else {
                    if ($initialConfirmedForProduct > 0.0001) {
                        $hasCompatibleSlot = false;
                        if (isset($virtualPoolsByProduct[$product->id]) && is_array($virtualPoolsByProduct[$product->id])) {
                            $normLineUnit = ProductUnit::normalizeUnit($line['unit']);
                            foreach ($virtualPoolsByProduct[$product->id] as $vSlot) {
                                $vSlotNorm = ProductUnit::normalizeUnit($vSlot['unit']);
                                if ($vSlotNorm === $normLineUnit || ($this->resolveStrictUnitConversion($product, $vSlot['unit']) !== null && $conv !== null)) {
                                    $hasCompatibleSlot = true;
                                    break;
                                }
                            }
                        }

                        if (! $hasCompatibleSlot) {
                            $lineClassification = 'UNIT_DIFFERENCE';
                            $unmatchedReason = 'UNIT_DIFFERENCE';
                        } else {
                            $lineClassification = 'ADVANCE_EXHAUSTED';
                            $unmatchedReason = 'ADVANCE_EXHAUSTED';
                        }
                    } elseif ($unconfirmedForProduct > 0.0001) {
                        $lineClassification = 'UNCONFIRMED_ADVANCE';
                        $unmatchedReason = 'UNCONFIRMED_ADVANCE';
                    } else {
                        $lineClassification = 'NO_ADVANCE';
                        $unmatchedReason = 'NO_ADVANCE';
                    }
                }

                $evaluatedLines[] = [
                    'source_item_id' => $line['source_item_id'],
                    'purchase_order_item_id' => $line['purchase_order_item_id'],
                    'goods_received_item_id' => $goodsReceivedItemId,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit' => $line['unit'],
                    'quantity' => $qty,
                    'conversion_to_base' => $conv,
                    'required_base_qty' => $requiredBase,
                    'already_matched_base_qty' => $alreadyMatchedBase,
                    'planned_matched_base_qty' => $matchedBase,
                    'matched_base_qty' => $matchedBase,
                    'remaining_unmatched_base_qty' => $remainingUnmatchedBase,
                    'classification' => $lineClassification,
                    'unmatched_reason' => $unmatchedReason,
                    'confirmed_advance_qty' => $availableBaseInPool,
                    'unconfirmed_advance_qty' => $unconfirmedForProduct,
                    'matches' => $lineMatches,
                    'allocations' => $allocations,
                ];
            }

            $billMatchedBase = round(array_sum(array_column($evaluatedLines, 'planned_matched_base_qty')), 3);
            $billRemainingBase = round(array_sum(array_column($evaluatedLines, 'remaining_unmatched_base_qty')), 3);

            if ($billMatchedBase > 0.0001) {
                $matchType = $billRemainingBase <= 0.0001 ? 'full_match' : 'partial_match';

                $readyBills[] = [
                    'execution_mode' => $target['execution_mode'],
                    'purchase_order_id' => $target['purchase_order_id'],
                    'source_goods_received_id' => $target['source_goods_received_id'],
                    'reference' => $target['reference'],
                    'supplier_id' => $target['supplier_id'],
                    'supplier_name' => $target['supplier_name'],
                    'bill_date' => $target['bill_date'],
                    'match_type' => $matchType,
                    'required_base_qty' => round($billTotalRequiredBase, 3),
                    'matched_base_qty' => $billMatchedBase,
                    'remaining_unmatched_base_qty' => $billRemainingBase,
                    'lines' => $evaluatedLines,
                ];

                $totalMatchedBaseQty = round($totalMatchedBaseQty + $billMatchedBase, 3);
            } else {
                $lines = collect($evaluatedLines);
                $hasUnitDiff = $lines->contains('classification', 'unit_difference') || $lines->contains('classification', 'UNIT_DIFFERENCE');
                $allInvalidQty = $lines->isNotEmpty() && $lines->every(fn ($l) => in_array($l['classification'], ['invalid_quantity', 'product_not_found', 'NO_RECONCILABLE_QUANTITY', 'INVALID_DATA'], true));
                if ($allInvalidQty) {
                    $reason = 'invalid_quantity';
                } elseif ($hasUnitDiff) {
                    $reason = 'unit_difference_requires_manual_review';
                } else {
                    $reason = 'no_advance';
                }

                $skippedBills[] = [
                    'execution_mode' => $target['execution_mode'],
                    'purchase_order_id' => $target['purchase_order_id'],
                    'source_goods_received_id' => $target['source_goods_received_id'],
                    'reference' => $target['reference'],
                    'supplier_id' => $target['supplier_id'],
                    'supplier_name' => $target['supplier_name'],
                    'bill_date' => $target['bill_date'],
                    'match_type' => 'no_match',
                    'reason' => $reason,
                    'shortages' => $evaluatedLines,
                    'lines' => $evaluatedLines,
                ];
            }
        }

        // 6. Calculate summary metrics
        $fullyClearedCount = 0;
        $partiallyClearedCount = 0;

        foreach ($advanceTracking as $advId => $track) {
            $previewMatched = (float) $track['preview_matched_base_qty'];
            $initialUnbilled = (float) $track['initial_unbilled_base_qty'];

            if ($previewMatched > 0.0001) {
                if ($previewMatched >= $initialUnbilled - 0.0001) {
                    $fullyClearedCount++;
                } else {
                    $partiallyClearedCount++;
                }
            }
        }

        $summary = [
            'pending_bills' => count($billTargets),
            'ready_bills' => count($readyBills),
            'full_bills' => count(array_filter($readyBills, fn ($b) => ($b['match_type'] ?? '') === 'full_match')),
            'partial_bills' => count(array_filter($readyBills, fn ($b) => ($b['match_type'] ?? '') === 'partial_match')),
            'skipped_bills' => count($skippedBills),
            'advances_fully_cleared' => $fullyClearedCount,
            'advances_partially_cleared' => $partiallyClearedCount,
            'matched_base_qty' => round($totalMatchedBaseQty, 3),
        ];

        // 7. Generate canonical plan hash (deterministic, hardened against allocation/qty changes)
        $canonicalReadyEntries = array_map(function (array $entry): array {
            return [
                'execution_mode' => $entry['execution_mode'],
                'purchase_order_id' => $entry['purchase_order_id'],
                'source_goods_received_id' => $entry['source_goods_received_id'],
                'matched_base_qty' => round((float) $entry['matched_base_qty'], 3),
                'lines' => array_map(function (array $line): array {
                    return [
                        'source_item_id' => $line['source_item_id'] ?? null,
                        'purchase_order_item_id' => $line['purchase_order_item_id'] ?? null,
                        'product_id' => (int) $line['product_id'],
                        'required_base_qty' => round((float) $line['required_base_qty'], 3),
                        'matched_base_qty' => round((float) $line['matched_base_qty'], 3),
                        'matches' => array_map(function (array $m): array {
                            return [
                                'advance_goods_received_id' => (int) $m['advance_goods_received_id'],
                                'advance_goods_received_item_id' => (int) $m['advance_goods_received_item_id'],
                                'base_qty' => round((float) $m['base_qty'], 3),
                            ];
                        }, $line['matches'] ?? []),
                    ];
                }, $entry['lines'] ?? []),
            ];
        }, $readyBills);

        $canonicalPayload = [
            'warehouse_id' => $warehouseId,
            'ready_entries' => $canonicalReadyEntries,
        ];
        $planHash = hash('sha256', (string) json_encode($canonicalPayload));

        // 8. Flatten advance allocations for preview inspector UI/debug
        $advanceAllocations = [];
        foreach ($virtualPoolsByProduct as $pId => $slots) {
            foreach ($slots as $s) {
                $advanceAllocations[] = [
                    'advance_goods_received_id' => $s['advance_goods_received_id'],
                    'advance_goods_received_item_id' => $s['advance_goods_received_item_id'],
                    'grn_number' => $s['grn_number'],
                    'received_at' => $s['received_at'],
                    'product_id' => $s['product_id'],
                    'unit' => $s['unit'],
                    'conversion_to_base' => $s['conversion_to_base'],
                    'received_base_qty' => $s['received_base_qty'],
                    'already_matched_base_qty' => $s['already_matched_base_qty'],
                    'initial_available_base_qty' => $s['initial_available_base_qty'],
                    'preview_matched_base_qty' => $s['preview_matched_base_qty'],
                    'remaining_base_qty' => $s['remaining_base_qty'],
                ];
            }
        }

        return [
            'warehouse_id' => $warehouseId,
            'generated_at' => now()->toIso8601String(),
            'plan_hash' => $planHash,
            'summary' => $summary,
            'ready_bills' => $readyBills,
            'skipped_bills' => $skippedBills,
            'advance_allocations' => $advanceAllocations,
            'warnings' => $warnings,
        ];
    }

    /**
     * Strict unit conversion helper.
     * Returns null if unit is unknown or has a non-positive conversion factor.
     */
    public function resolveStrictUnitConversion(?Product $product, ?string $unit): ?float
    {
        return $this->balanceCalculator->resolveStrictUnitConversion($product, $unit);
    }
}
