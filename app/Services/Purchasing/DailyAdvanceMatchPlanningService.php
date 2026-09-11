<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailyAdvanceMatchPlanningService
{
    public function __construct(
        private readonly AdvanceAvailableBalanceCalculator $balanceCalculator,
        private readonly WarehouseReceiptReadScope $readScope,
    ) {}

    /**
     * Build a deterministic, read-only daily auto-match plan preview for a specific warehouse and bill date.
     *
     * @return array{
     *     warehouse_id: int,
     *     bill_date: string,
     *     cursor: int|null,
     *     next_cursor: int|null,
     *     batch_size: int,
     *     generated_at: string,
     *     plan_hash: string,
     *     summary: array{
     *         total_bills_on_date: int,
     *         batch_bills_count: int,
     *         ready_bills: int,
     *         partial_bills: int,
     *         blocked_bills: int,
     *         advances_fully_cleared: int,
     *         advances_partially_cleared: int,
     *         matched_base_qty: float
     *     },
     *     ready_bills: array<int, array<string, mixed>>,
     *     partial_bills: array<int, array<string, mixed>>,
     *     blocked_bills: array<int, array<string, mixed>>,
     *     open_advances: array<int, array<string, mixed>>,
     *     inventory_without_bills: array<int, array<string, mixed>>,
     *     advance_allocations: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>
     * }
     */
    public function buildDailyPlan(
        int $warehouseId,
        string $billDate,
        ?int $cursor = null,
        int $batchSize = 100,
        ?int $userId = null
    ): array {
        $warnings = [];
        $carbonDate = Carbon::parse($billDate)->toDateString();

        // 1. Total bill count on selected date
        $totalBillsQuery = GoodsReceived::query()
            ->where('goods_received.status', 'approved')
            ->where(function (Builder $q): void {
                $q->where('goods_received.receipt_type', '!=', 'warehouse_advance')
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('goods_received.receipt_type')
                            ->whereNotNull('goods_received.purchase_order_id');
                    });
            })
            ->whereDate('goods_received.received_at', $carbonDate);

        $this->readScope->receipts($totalBillsQuery, [$warehouseId]);
        $totalBillsOnDate = $totalBillsQuery->count();

        // 2. Fetch the exact batch of bill GRNs (100 bills using cursor pagination ordered by id ASC)
        $batchBillsQuery = GoodsReceived::query()
            ->where('goods_received.status', 'approved')
            ->where(function (Builder $q): void {
                $q->where('goods_received.receipt_type', '!=', 'warehouse_advance')
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('goods_received.receipt_type')
                            ->whereNotNull('goods_received.purchase_order_id');
                    });
            })
            ->whereDate('goods_received.received_at', $carbonDate)
            ->when($cursor !== null && $cursor > 0, fn (Builder $q) => $q->where('goods_received.id', '>', $cursor))
            ->with([
                'items.product.orderUnits',
                'purchaseOrder.supplier',
                'purchaseOrder.destinationShop',
            ])
            ->orderBy('goods_received.id', 'asc')
            ->limit($batchSize);

        $this->readScope->receipts($batchBillsQuery, [$warehouseId]);
        /** @var Collection<int, GoodsReceived> $billGrns */
        $billGrns = $batchBillsQuery->get();

        $lastBillId = $billGrns->last()?->id;
        $nextCursor = null;
        if ($billGrns->count() === $batchSize && $lastBillId !== null) {
            $hasMoreQuery = GoodsReceived::query()
                ->where('goods_received.status', 'approved')
                ->where(function (Builder $q): void {
                    $q->where('goods_received.receipt_type', '!=', 'warehouse_advance')
                        ->orWhere(function (Builder $legacy): void {
                            $legacy->whereNull('goods_received.receipt_type')
                                ->whereNotNull('goods_received.purchase_order_id');
                        });
                })
                ->whereDate('goods_received.received_at', $carbonDate)
                ->where('goods_received.id', '>', $lastBillId);

            $this->readScope->receipts($hasMoreQuery, [$warehouseId]);
            if ($hasMoreQuery->exists()) {
                $nextCursor = $lastBillId;
            }
        }

        // 3. Pre-load all confirmed open advances for this warehouse where received_at <= selected date
        // Advances received after selected date are strictly excluded from matching.
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
            ->whereDate('goods_received.received_at', '<=', $carbonDate)
            ->whereDoesntHave('purchaseInvoices')
            ->whereHas('stockBatches', function (Builder $batchQuery): void {
                $batchQuery->where('warehouse_receive_pending', false);
            });

        $this->readScope->receipts($openAdvancesQuery, [$warehouseId]);

        /** @var Collection<int, GoodsReceived> $openAdvances */
        $openAdvances = $openAdvancesQuery
            ->with([
                'items.product.orderUnits',
                'stockBatches',
            ])
            ->orderBy('received_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // 4. Bulk load existing matches for open advances and the current bill batch
        $advanceIds = $openAdvances->pluck('id')->all();
        $billGrnIds = $billGrns->pluck('id')->all();

        $existingAdvanceMatches = $advanceIds !== []
            ? AdvanceReceiveMatch::query()->whereIn('advance_goods_received_id', $advanceIds)->get()
            : collect();

        $existingBillMatches = $billGrnIds !== []
            ? AdvanceReceiveMatch::query()->whereIn('bill_goods_received_id', $billGrnIds)->get()
            : collect();

        // 5. Build in-memory virtual pools for advances
        /** @var array<int, array<int, array<string, mixed>>> $virtualPoolsByProduct */
        $virtualPoolsByProduct = [];
        /** @var array<int, array<string, mixed>> $advanceTracking */
        $advanceTracking = [];
        /** @var array<int, array<string, mixed>> $openAdvancesList */
        $openAdvancesList = [];
        /** @var array<string, array<string, mixed>> $inventoryWithoutBillsMap */
        $inventoryWithoutBillsMap = [];

        foreach ($openAdvances as $advGrn) {
            $grnInitialUnbilledBase = 0.0;
            $availBalances = $this->balanceCalculator->calculateItemAvailableBase($advGrn, null, $existingAdvanceMatches);
            $advReceivedDate = $advGrn->received_at instanceof Carbon
                ? $advGrn->received_at->toDateString()
                : (string) ($advGrn->received_at ?? $carbonDate);

            $advAgeDays = max(0, (int) Carbon::parse($advReceivedDate)->diffInDays(Carbon::parse($carbonDate), false));
            $grnHasOpenBalance = false;
            $grnItemsData = [];

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

                $conv = $this->balanceCalculator->resolveStrictUnitConversion($product, $item->received_unit) ?? 1.0;
                $itemRemainingBase = (float) ($availBalances[$item->id] ?? 0.0);
                $originalQty = (float) $item->received_qty;
                $originalBaseQty = round($originalQty * $conv, 3);
                $totalAlreadyMatchedBase = max(0.0, round($originalBaseQty - $itemRemainingBase, 3));

                if ($itemRemainingBase > 0.0001) {
                    $grnHasOpenBalance = true;
                    $grnInitialUnbilledBase += $itemRemainingBase;

                    $slotIndex = count($virtualPoolsByProduct[$item->product_id] ?? []);
                    $slot = [
                        'advance_goods_received_id' => $advGrn->id,
                        'advance_goods_received_item_id' => $item->id,
                        'grn_number' => $advGrn->grn_number ?? "ADV-{$advGrn->id}",
                        'received_at' => $advReceivedDate,
                        'product_id' => $item->product_id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit' => $item->received_unit ?? $product->unit,
                        'conversion_to_base' => $conv,
                        'received_base_qty' => $originalBaseQty,
                        'already_matched_base_qty' => $totalAlreadyMatchedBase,
                        'initial_available_base_qty' => $itemRemainingBase,
                        'remaining_base_qty' => $itemRemainingBase,
                        'preview_matched_base_qty' => 0.0,
                    ];

                    $virtualPoolsByProduct[$item->product_id][$slotIndex] = $slot;

                    // Group for Inventory Without Bills section
                    $groupKey = "{$warehouseId}_{$item->product_id}_{$item->received_unit}";
                    if (! isset($inventoryWithoutBillsMap[$groupKey])) {
                        $inventoryWithoutBillsMap[$groupKey] = [
                            'warehouse_id' => $warehouseId,
                            'product_id' => $item->product_id,
                            'product_name' => $product->name,
                            'product_sku' => $product->sku,
                            'unit' => $item->received_unit ?? $product->unit,
                            'advance_grn_number' => $advGrn->grn_number ?? "ADV-{$advGrn->id}",
                            'advance_goods_received_id' => $advGrn->id,
                            'received_qty' => 0.0,
                            'matched_qty' => 0.0,
                            'remaining_qty' => 0.0,
                            'age_days' => $advAgeDays,
                            'grns_count' => 0,
                        ];
                    }

                    $inventoryWithoutBillsMap[$groupKey]['received_qty'] = round($inventoryWithoutBillsMap[$groupKey]['received_qty'] + $originalQty, 3);
                    $inventoryWithoutBillsMap[$groupKey]['matched_qty'] = round($inventoryWithoutBillsMap[$groupKey]['matched_qty'] + ($conv > 0 ? $totalAlreadyMatchedBase / $conv : $totalAlreadyMatchedBase), 3);
                    $inventoryWithoutBillsMap[$groupKey]['remaining_qty'] = round($inventoryWithoutBillsMap[$groupKey]['remaining_qty'] + ($conv > 0 ? $itemRemainingBase / $conv : $itemRemainingBase), 3);
                    $inventoryWithoutBillsMap[$groupKey]['age_days'] = max($inventoryWithoutBillsMap[$groupKey]['age_days'], $advAgeDays);
                    $inventoryWithoutBillsMap[$groupKey]['grns_count']++;
                }

                $grnItemsData[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit' => $item->received_unit ?? $product->unit,
                    'received_qty' => $originalQty,
                    'remaining_base_qty' => $itemRemainingBase,
                ];
            }

            if ($grnHasOpenBalance) {
                $advanceTracking[$advGrn->id] = [
                    'grn_number' => $advGrn->grn_number ?? "ADV-{$advGrn->id}",
                    'initial_unbilled_base_qty' => round($grnInitialUnbilledBase, 3),
                    'preview_matched_base_qty' => 0.0,
                ];

                $openAdvancesList[] = [
                    'id' => $advGrn->id,
                    'grn_number' => $advGrn->grn_number ?? "ADV-{$advGrn->id}",
                    'received_at' => $advReceivedDate,
                    'age_days' => $advAgeDays,
                    'unbilled_base_qty' => round($grnInitialUnbilledBase, 3),
                    'items' => $grnItemsData,
                ];
            }
        }

        // 6. Evaluate Bill Matches for the 100 bills
        $readyBills = [];
        $partialBills = [];
        $blockedBills = [];
        $totalMatchedBaseQty = 0.0;

        foreach ($billGrns as $billGrn) {
            $billDateStr = $billGrn->received_at instanceof Carbon
                ? $billGrn->received_at->toDateString()
                : (string) ($billGrn->received_at ?? $carbonDate);

            $supplierName = $billGrn->supplier?->name ?? $billGrn->purchaseOrder?->supplier?->name ?? 'Unknown Supplier';
            $poNumber = $billGrn->purchaseOrder?->po_number ?? 'N/A';

            $billTotalRequiredBase = 0.0;
            $billTotalMatchedBase = 0.0;
            $billTotalRemainingBase = 0.0;
            $evaluatedLines = [];

            foreach ($billGrn->items as $bItem) {
                /** @var Product|null $product */
                $product = $bItem->product;
                if (! $product) {
                    $evaluatedLines[] = [
                        'item_id' => $bItem->id,
                        'product_id' => $bItem->product_id,
                        'product_name' => "Product #{$bItem->product_id}",
                        'product_sku' => '',
                        'unit' => $bItem->received_unit,
                        'quantity' => (float) $bItem->received_qty,
                        'required_base_qty' => 0.0,
                        'already_matched_base_qty' => 0.0,
                        'matched_base_qty' => 0.0,
                        'remaining_unmatched_base_qty' => 0.0,
                        'status' => 'blocked',
                        'reason' => 'product_not_found',
                        'matches' => [],
                    ];

                    continue;
                }

                $alreadyMatchedBase = (float) $existingBillMatches
                    ->where('bill_goods_received_id', $billGrn->id)
                    ->where('bill_goods_received_item_id', $bItem->id)
                    ->sum('base_qty');

                $normLineUnit = ProductUnit::normalizeUnit($bItem->received_unit);
                $normProdUnit = ProductUnit::normalizeUnit($product->unit);

                // Determine strict unit conversion factor
                $conv = null;
                if ($normLineUnit === $normProdUnit || $normLineUnit === '') {
                    $conv = 1.0;
                } else {
                    $conv = $this->balanceCalculator->resolveStrictUnitConversion($product, $bItem->received_unit);
                }

                $receivedBase = round((float) $bItem->received_qty * ($conv ?? 1.0), 3);
                $remainingLineBase = max(0.0, round($receivedBase - $alreadyMatchedBase, 3));

                if ($remainingLineBase <= 0.0001) {
                    // Line is already fully matched
                    $evaluatedLines[] = [
                        'item_id' => $bItem->id,
                        'purchase_order_item_id' => $bItem->purchase_order_item_id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit' => $bItem->received_unit,
                        'quantity' => (float) $bItem->received_qty,
                        'conversion_to_base' => $conv ?? 1.0,
                        'required_base_qty' => $receivedBase,
                        'already_matched_base_qty' => $alreadyMatchedBase,
                        'matched_base_qty' => 0.0,
                        'remaining_unmatched_base_qty' => 0.0,
                        'status' => 'already_cleared',
                        'reason' => 'none',
                        'matches' => [],
                    ];

                    continue;
                }

                $billTotalRequiredBase = round($billTotalRequiredBase + $remainingLineBase, 3);

                // Check unit conversion requirement if units differ
                if ($conv === null) {
                    // Check if there is an advance with the exact same unit
                    $hasExactUnitAdvance = false;
                    if (isset($virtualPoolsByProduct[$product->id])) {
                        foreach ($virtualPoolsByProduct[$product->id] as $vSlot) {
                            if (ProductUnit::normalizeUnit($vSlot['unit']) === $normLineUnit) {
                                $hasExactUnitAdvance = true;
                                break;
                            }
                        }
                    }

                    if ($hasExactUnitAdvance) {
                        $conv = 1.0;
                    } else {
                        // Unit difference without saved conversion -> BLOCK FOR REVIEW
                        $billTotalRemainingBase = round($billTotalRemainingBase + $remainingLineBase, 3);
                        $evaluatedLines[] = [
                            'item_id' => $bItem->id,
                            'purchase_order_item_id' => $bItem->purchase_order_item_id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'product_sku' => $product->sku,
                            'unit' => $bItem->received_unit,
                            'quantity' => (float) $bItem->received_qty,
                            'conversion_to_base' => null,
                            'required_base_qty' => $remainingLineBase,
                            'already_matched_base_qty' => $alreadyMatchedBase,
                            'matched_base_qty' => 0.0,
                            'remaining_unmatched_base_qty' => $remainingLineBase,
                            'status' => 'blocked',
                            'reason' => 'UNIT_DIFFERENCE_REQUIRES_CONVERSION',
                            'matches' => [],
                        ];

                        continue;
                    }
                }

                // Allocate from virtual pool for this product
                $poolExists = isset($virtualPoolsByProduct[$product->id]) && is_array($virtualPoolsByProduct[$product->id]);
                $neededBase = $remainingLineBase;
                $lineMatchedBase = 0.0;
                $lineMatches = [];
                $skippedDueToUnitConversion = false;

                if ($poolExists) {
                    foreach ($virtualPoolsByProduct[$product->id] as &$slot) {
                        if ($neededBase <= 0.0001) {
                            break;
                        }

                        $slotAvail = (float) $slot['remaining_base_qty'];
                        if ($slotAvail <= 0.0001) {
                            continue;
                        }

                        // Verify unit compatibility
                        $normSlotUnit = ProductUnit::normalizeUnit($slot['unit']);
                        if ($normLineUnit !== $normSlotUnit) {
                            $slotConv = $this->balanceCalculator->resolveStrictUnitConversion($product, $slot['unit']);
                            if ($slotConv === null) {
                                $skippedDueToUnitConversion = true;

                                continue;
                            }
                        }

                        $takeBase = min($slotAvail, $neededBase);
                        $slot['remaining_base_qty'] = round($slotAvail - $takeBase, 3);
                        $slot['preview_matched_base_qty'] = round($slot['preview_matched_base_qty'] + $takeBase, 3);
                        $neededBase = round($neededBase - $takeBase, 3);
                        $lineMatchedBase = round($lineMatchedBase + $takeBase, 3);

                        $advGrnId = $slot['advance_goods_received_id'];
                        if (isset($advanceTracking[$advGrnId])) {
                            $advanceTracking[$advGrnId]['preview_matched_base_qty'] = round(
                                $advanceTracking[$advGrnId]['preview_matched_base_qty'] + $takeBase,
                                3
                            );
                        }

                        $lineMatches[] = [
                            'advance_goods_received_id' => $slot['advance_goods_received_id'],
                            'advance_goods_received_item_id' => $slot['advance_goods_received_item_id'],
                            'grn_number' => $slot['grn_number'],
                            'received_at' => $slot['received_at'],
                            'product_id' => $product->id,
                            'matched_base_qty' => round($takeBase, 3),
                            'base_qty' => round($takeBase, 3),
                            'matched_unit' => $bItem->received_unit,
                            'matched_qty' => $conv > 0 ? round($takeBase / $conv, 3) : $takeBase,
                        ];
                    }
                    unset($slot);
                }

                $remUnmatched = max(0.0, round($remainingLineBase - $lineMatchedBase, 3));
                $billTotalMatchedBase = round($billTotalMatchedBase + $lineMatchedBase, 3);
                $billTotalRemainingBase = round($billTotalRemainingBase + $remUnmatched, 3);

                $lineStatus = 'ready';
                $lineReason = 'none';
                if ($lineMatchedBase >= $remainingLineBase - 0.0001) {
                    $lineStatus = 'full_match';
                } elseif ($lineMatchedBase > 0.0001) {
                    $lineStatus = 'partial_match';
                    $lineReason = 'partial_advance_available';
                } else {
                    $lineStatus = 'blocked';
                    if (! $poolExists) {
                        $lineReason = 'NO_ADVANCE';
                    } elseif ($skippedDueToUnitConversion) {
                        $lineReason = 'UNIT_DIFFERENCE_REQUIRES_CONVERSION';
                    } else {
                        $lineReason = 'ADVANCE_EXHAUSTED';
                    }
                }

                $evaluatedLines[] = [
                    'item_id' => $bItem->id,
                    'purchase_order_item_id' => $bItem->purchase_order_item_id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit' => $bItem->received_unit,
                    'quantity' => (float) $bItem->received_qty,
                    'conversion_to_base' => $conv,
                    'required_base_qty' => $remainingLineBase,
                    'already_matched_base_qty' => $alreadyMatchedBase,
                    'matched_base_qty' => $lineMatchedBase,
                    'remaining_unmatched_base_qty' => $remUnmatched,
                    'status' => $lineStatus,
                    'reason' => $lineReason,
                    'matches' => $lineMatches,
                ];
            }

            // Categorize the Bill
            $billData = [
                'goods_received_id' => $billGrn->id,
                'purchase_order_id' => $billGrn->purchase_order_id,
                'grn_number' => $billGrn->grn_number ?? "GRN-{$billGrn->id}",
                'po_number' => $poNumber,
                'bill_date' => $billDateStr,
                'supplier_name' => $supplierName,
                'required_base_qty' => $billTotalRequiredBase,
                'matched_base_qty' => $billTotalMatchedBase,
                'remaining_base_qty' => $billTotalRemainingBase,
                'lines' => $evaluatedLines,
            ];

            if ($billTotalMatchedBase > 0.0001) {
                $totalMatchedBaseQty = round($totalMatchedBaseQty + $billTotalMatchedBase, 3);
                if ($billTotalRemainingBase <= 0.0001) {
                    $billData['match_type'] = 'full_match';
                    $readyBills[] = $billData;
                } else {
                    $billData['match_type'] = 'partial_match';
                    $partialBills[] = $billData;
                }
            } else {
                $billData['match_type'] = 'blocked';
                $hasUnitDiff = collect($evaluatedLines)->contains('reason', 'UNIT_DIFFERENCE_REQUIRES_CONVERSION');
                $hasExhausted = collect($evaluatedLines)->contains('reason', 'ADVANCE_EXHAUSTED');
                if ($hasUnitDiff) {
                    $billData['blocked_reason'] = 'UNIT_DIFFERENCE_REQUIRES_CONVERSION';
                } elseif ($hasExhausted) {
                    $billData['blocked_reason'] = 'ADVANCE_EXHAUSTED';
                } else {
                    $billData['blocked_reason'] = 'NO_ADVANCE';
                }
                $blockedBills[] = $billData;
            }
        }

        // 7. Calculate Advance Clearance Counts
        $fullyClearedCount = 0;
        $partiallyClearedCount = 0;
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

        foreach ($advanceTracking as $advId => $track) {
            $prevMatched = (float) $track['preview_matched_base_qty'];
            $initUnbilled = (float) $track['initial_unbilled_base_qty'];

            if ($prevMatched > 0.0001) {
                if ($prevMatched >= $initUnbilled - 0.0001) {
                    $fullyClearedCount++;
                } else {
                    $partiallyClearedCount++;
                }
            }
        }

        // 8. Canonical Plan Hash (SHA256 of deterministic match instructions)
        $allMatchedBills = array_merge($readyBills, $partialBills);
        $canonicalReadyEntries = array_map(function (array $entry): array {
            return [
                'goods_received_id' => (int) $entry['goods_received_id'],
                'purchase_order_id' => $entry['purchase_order_id'] ? (int) $entry['purchase_order_id'] : null,
                'matched_base_qty' => round((float) $entry['matched_base_qty'], 3),
                'lines' => array_map(function (array $line): array {
                    return [
                        'item_id' => (int) $line['item_id'],
                        'purchase_order_item_id' => $line['purchase_order_item_id'] ? (int) $line['purchase_order_item_id'] : null,
                        'product_id' => (int) $line['product_id'],
                        'required_base_qty' => round((float) $line['required_base_qty'], 3),
                        'matched_base_qty' => round((float) $line['matched_base_qty'], 3),
                        'matches' => array_map(function (array $m): array {
                            return [
                                'advance_goods_received_id' => (int) $m['advance_goods_received_id'],
                                'advance_goods_received_item_id' => (int) $m['advance_goods_received_item_id'],
                                'base_qty' => round((float) $m['base_qty'], 3),
                                'matched_qty' => round((float) $m['matched_qty'], 3),
                            ];
                        }, $line['matches'] ?? []),
                    ];
                }, $entry['lines'] ?? []),
            ];
        }, $allMatchedBills);

        $canonicalPayload = [
            'warehouse_id' => $warehouseId,
            'bill_date' => $carbonDate,
            'cursor' => $cursor,
            'batch_size' => $batchSize,
            'ready_entries' => $canonicalReadyEntries,
        ];
        $planHash = hash('sha256', (string) json_encode($canonicalPayload));

        $summary = [
            'total_bills_on_date' => $totalBillsOnDate,
            'batch_bills_count' => $billGrns->count(),
            'ready_bills' => count($readyBills),
            'partial_bills' => count($partialBills),
            'blocked_bills' => count($blockedBills),
            'advances_fully_cleared' => $fullyClearedCount,
            'advances_partially_cleared' => $partiallyClearedCount,
            'matched_base_qty' => round($totalMatchedBaseQty, 3),
        ];

        return [
            'warehouse_id' => $warehouseId,
            'bill_date' => $carbonDate,
            'cursor' => $cursor,
            'next_cursor' => $nextCursor,
            'batch_size' => $batchSize,
            'generated_at' => now()->toIso8601String(),
            'plan_hash' => $planHash,
            'summary' => $summary,
            'ready_bills' => $readyBills,
            'partial_bills' => $partialBills,
            'blocked_bills' => $blockedBills,
            'open_advances' => $openAdvancesList,
            'inventory_without_bills' => array_values($inventoryWithoutBillsMap),
            'advance_allocations' => $advanceAllocations,
            'warnings' => $warnings,
        ];
    }
}
