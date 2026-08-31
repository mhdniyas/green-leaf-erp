<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AutoAdvanceClearPlanningService
{
    public function __construct(
        private readonly WarehouseReceiptStateResolver $receiptStateResolver,
        private readonly AdvanceAvailableBalanceCalculator $balanceCalculator,
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
        $pendingOrders = PurchaseOrder::query()
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->where(function (Builder $pending): void {
                $pending->whereHas('goodsReceiveds', fn ($receipts) => $this->receiptStateResolver->filter($receipts, 'pending'))
                    ->orWhere(function ($withoutPendingReceipt): void {
                        $withoutPendingReceipt->whereDoesntHave('goodsReceiveds', fn ($receipts) => $this->receiptStateResolver->filter($receipts, 'pending'))
                            ->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received']);
                    });
            })
            ->where(function (Builder $w) use ($warehouseId): void {
                $w->where('destination_shop_id', $warehouseId)
                    ->orWhere('warehouse_id', $warehouseId);
            })
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
        $openAdvances = GoodsReceived::query()
            ->openWarehouseAdvance($warehouseId)
            ->with([
                'items.product.orderUnits',
                'stockBatches' => fn ($q) => $q->where('warehouse_receive_pending', false),
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

                $conv = $this->resolveStrictUnitConversion($product, $item->received_unit);
                if ($conv === null) {
                    $warnings[] = [
                        'advance_goods_received_id' => $advGrn->id,
                        'advance_goods_received_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'unit' => $item->received_unit,
                        'warning' => 'invalid_advance_unit_conversion',
                    ];

                    continue;
                }

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

        // 4. Construct Bill Targets (discerning existing pending GRNs vs PO-level remaining receivables)
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
                        $targetLines[] = [
                            'source_item_id' => $pItem->id,
                            'purchase_order_item_id' => $pItem->purchase_order_item_id,
                            'product' => $pItem->product,
                            'product_id' => $pItem->product_id,
                            'unit' => $pItem->received_unit ?: $pItem->product?->unit,
                            'quantity' => (float) $pItem->received_qty,
                        ];
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
                        ];

                        continue;
                    }

                    $orderedBaseQty = round($orderedQty * $conv, 3);
                    $completedBaseQty = (float) ($completedItemReceivedBase[$poItem->id] ?? 0.0);
                    $remainingBaseQty = max(0.0, round($orderedBaseQty - $completedBaseQty, 3));

                    $remainingItemQty = $conv > 0 ? round($remainingBaseQty / $conv, 3) : $remainingBaseQty;

                    if ($remainingBaseQty > 0.0001) {
                        $targetLines[] = [
                            'source_item_id' => $poItem->id,
                            'purchase_order_item_id' => $poItem->id,
                            'product' => $product,
                            'product_id' => $poItem->product_id,
                            'unit' => $itemUnit,
                            'quantity' => $remainingItemQty,
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
                ];

                continue;
            }

            $tentativeAllocations = [];
            $tentativeShortages = [];
            $hasInvalidQuantity = false;
            $hasProductNotFound = false;
            $hasInvalidUnit = false;
            $billTotalRequiredBase = 0.0;
            $evaluatedLines = [];
            $poolSnapshots = [];

            foreach ($billLines as $line) {
                /** @var Product|null $product */
                $product = $line['product'];

                if (! $product) {
                    $hasProductNotFound = true;
                    $tentativeShortages[] = [
                        'source_item_id' => $line['source_item_id'],
                        'purchase_order_item_id' => $line['purchase_order_item_id'],
                        'product_id' => $line['product_id'],
                        'reason' => 'product_not_found',
                        'required_base_qty' => 0.0,
                        'available_base_qty' => 0.0,
                        'shortage_base_qty' => 0.0,
                    ];
                    break;
                }

                $qty = (float) $line['quantity'];
                if ($qty <= 0.0001) {
                    $hasInvalidQuantity = true;
                    $tentativeShortages[] = [
                        'source_item_id' => $line['source_item_id'],
                        'purchase_order_item_id' => $line['purchase_order_item_id'],
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit' => $line['unit'],
                        'quantity' => $qty,
                        'reason' => 'invalid_quantity',
                        'required_base_qty' => 0.0,
                        'available_base_qty' => 0.0,
                        'shortage_base_qty' => 0.0,
                    ];
                    break;
                }

                $conv = $this->resolveStrictUnitConversion($product, $line['unit']);
                if ($conv === null) {
                    $hasInvalidUnit = true;
                    $tentativeShortages[] = [
                        'source_item_id' => $line['source_item_id'],
                        'purchase_order_item_id' => $line['purchase_order_item_id'],
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit' => $line['unit'],
                        'reason' => 'invalid_unit_conversion',
                        'required_base_qty' => 0.0,
                        'available_base_qty' => 0.0,
                        'shortage_base_qty' => 0.0,
                    ];
                    break;
                }

                $requiredBase = round($qty * $conv, 3);
                $billTotalRequiredBase += $requiredBase;

                // Ensure snapshot of pool for this product
                if (! isset($poolSnapshots[$product->id])) {
                    $poolSnapshots[$product->id] = $virtualPoolsByProduct[$product->id] ?? [];
                }

                $availableBaseInSnapshot = array_sum(array_column($poolSnapshots[$product->id], 'remaining_base_qty'));

                if ($availableBaseInSnapshot < $requiredBase - 0.0001) {
                    $shortageBase = max(0.0, round($requiredBase - $availableBaseInSnapshot, 3));
                    $tentativeShortages[] = [
                        'source_item_id' => $line['source_item_id'],
                        'purchase_order_item_id' => $line['purchase_order_item_id'],
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit' => $line['unit'],
                        'quantity' => $qty,
                        'conversion_to_base' => $conv,
                        'required_base_qty' => $requiredBase,
                        'available_base_qty' => round($availableBaseInSnapshot, 3),
                        'shortage_base_qty' => $shortageBase,
                    ];
                } else {
                    // Tentatively deduct from pool snapshot in FIFO order
                    $neededBase = $requiredBase;
                    $lineMatches = [];

                    foreach ($poolSnapshots[$product->id] as $sIdx => &$slot) {
                        if ($neededBase <= 0.0001) {
                            break;
                        }

                        $slotAvail = (float) $slot['remaining_base_qty'];
                        if ($slotAvail <= 0.0001) {
                            continue;
                        }

                        $matchBase = min($slotAvail, $neededBase);
                        $slot['remaining_base_qty'] = round($slotAvail - $matchBase, 3);
                        $neededBase = round($neededBase - $matchBase, 3);

                        $lineMatches[] = [
                            'advance_goods_received_id' => $slot['advance_goods_received_id'],
                            'advance_goods_received_item_id' => $slot['advance_goods_received_item_id'],
                            'grn_number' => $slot['grn_number'],
                            'received_at' => $slot['received_at'],
                            'product_id' => $product->id,
                            'matched_base_qty' => round($matchBase, 3),
                            'pool_slot_product_id' => $product->id,
                            'pool_slot_index' => $sIdx,
                        ];
                    }
                    unset($slot);

                    $evaluatedLines[] = [
                        'source_item_id' => $line['source_item_id'],
                        'purchase_order_item_id' => $line['purchase_order_item_id'],
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit' => $line['unit'],
                        'quantity' => $qty,
                        'conversion_to_base' => $conv,
                        'required_base_qty' => $requiredBase,
                        'matched_base_qty' => $requiredBase,
                        'matches' => array_map(fn ($m) => [
                            'advance_goods_received_id' => $m['advance_goods_received_id'],
                            'advance_goods_received_item_id' => $m['advance_goods_received_item_id'],
                            'grn_number' => $m['grn_number'],
                            'received_at' => $m['received_at'],
                            'base_qty' => $m['matched_base_qty'],
                        ], $lineMatches),
                    ];

                    $tentativeAllocations = array_merge($tentativeAllocations, $lineMatches);
                }
            }

            // Evaluation decision
            if ($hasProductNotFound) {
                $skippedBills[] = [
                    'execution_mode' => $target['execution_mode'],
                    'purchase_order_id' => $target['purchase_order_id'],
                    'source_goods_received_id' => $target['source_goods_received_id'],
                    'reference' => $target['reference'],
                    'supplier_id' => $target['supplier_id'],
                    'supplier_name' => $target['supplier_name'],
                    'bill_date' => $target['bill_date'],
                    'reason' => 'product_not_found',
                    'shortages' => $tentativeShortages,
                ];
            } elseif ($hasInvalidQuantity) {
                $skippedBills[] = [
                    'execution_mode' => $target['execution_mode'],
                    'purchase_order_id' => $target['purchase_order_id'],
                    'source_goods_received_id' => $target['source_goods_received_id'],
                    'reference' => $target['reference'],
                    'supplier_id' => $target['supplier_id'],
                    'supplier_name' => $target['supplier_name'],
                    'bill_date' => $target['bill_date'],
                    'reason' => 'invalid_quantity',
                    'shortages' => $tentativeShortages,
                ];
            } elseif ($hasInvalidUnit) {
                $skippedBills[] = [
                    'execution_mode' => $target['execution_mode'],
                    'purchase_order_id' => $target['purchase_order_id'],
                    'source_goods_received_id' => $target['source_goods_received_id'],
                    'reference' => $target['reference'],
                    'supplier_id' => $target['supplier_id'],
                    'supplier_name' => $target['supplier_name'],
                    'bill_date' => $target['bill_date'],
                    'reason' => 'invalid_unit_conversion',
                    'shortages' => $tentativeShortages,
                ];
            } elseif ($tentativeShortages !== []) {
                $anyAvailableOnBill = $tentativeAllocations !== [];
                if (! $anyAvailableOnBill) {
                    foreach ($tentativeShortages as $sh) {
                        if (($sh['available_base_qty'] ?? 0.0) > 0.0001) {
                            $anyAvailableOnBill = true;
                            break;
                        }
                    }
                }

                $reason = $anyAvailableOnBill ? 'insufficient_advance' : 'no_advance';

                $skippedBills[] = [
                    'execution_mode' => $target['execution_mode'],
                    'purchase_order_id' => $target['purchase_order_id'],
                    'source_goods_received_id' => $target['source_goods_received_id'],
                    'reference' => $target['reference'],
                    'supplier_id' => $target['supplier_id'],
                    'supplier_name' => $target['supplier_name'],
                    'bill_date' => $target['bill_date'],
                    'reason' => $reason,
                    'shortages' => $tentativeShortages,
                ];
            } else {
                // Bill is 100% COVERED! Commit tentative allocations to global virtual pools
                foreach ($tentativeAllocations as $alloc) {
                    $pId = $alloc['pool_slot_product_id'];
                    $sIdx = $alloc['pool_slot_index'];
                    $matchedBase = (float) $alloc['matched_base_qty'];

                    $virtualPoolsByProduct[$pId][$sIdx]['remaining_base_qty'] = round(
                        $virtualPoolsByProduct[$pId][$sIdx]['remaining_base_qty'] - $matchedBase,
                        3
                    );
                    $virtualPoolsByProduct[$pId][$sIdx]['preview_matched_base_qty'] = round(
                        $virtualPoolsByProduct[$pId][$sIdx]['preview_matched_base_qty'] + $matchedBase,
                        3
                    );

                    $advGrnId = $alloc['advance_goods_received_id'];
                    if (isset($advanceTracking[$advGrnId])) {
                        $advanceTracking[$advGrnId]['preview_matched_base_qty'] = round(
                            $advanceTracking[$advGrnId]['preview_matched_base_qty'] + $matchedBase,
                            3
                        );
                    }
                }

                $readyBills[] = [
                    'execution_mode' => $target['execution_mode'],
                    'purchase_order_id' => $target['purchase_order_id'],
                    'source_goods_received_id' => $target['source_goods_received_id'],
                    'reference' => $target['reference'],
                    'supplier_id' => $target['supplier_id'],
                    'supplier_name' => $target['supplier_name'],
                    'bill_date' => $target['bill_date'],
                    'matched_base_qty' => round($billTotalRequiredBase, 3),
                    'lines' => $evaluatedLines,
                ];

                $totalMatchedBaseQty = round($totalMatchedBaseQty + $billTotalRequiredBase, 3);
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
