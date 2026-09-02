<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\BillReconciliation;
use App\Models\BillReconciliationLine;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Repositories\Inventory\StockBatchRepository;
use App\Repositories\Purchasing\GoodsReceivedRepository;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdvanceReceiveReconciliationService
{
    public function __construct(
        private readonly GoodsReceivedRepository $grnRepository,
        private readonly StockBatchRepository $stockBatchRepository,
        private readonly PriceBoardService $priceBoardService,
        private readonly VendorPriceService $vendorPriceService,
    ) {}

    /**
     * Get deterministic suggested Advance matches for a Purchase Order.
     * Orders candidate advances by physical received_at ASC, id ASC (FIFO).
     *
     * @param  array<string, float>|null  $loadoutsByCohort
     * @return array<string, mixed>
     */
    public function getSuggestionsForOrder(PurchaseOrder $order, ?int $warehouseId = null, ?array $loadoutsByCohort = null, ?array $preloadedCandidatesByProduct = null): array
    {
        $order->loadMissing(['items.product.orderUnits', 'supplier', 'goodsReceiveds.items.product.orderUnits']);
        $targetWarehouseId = $warehouseId ?? $order->destination_shop_id ?? $order->warehouse_id;
        $orderDate = $order->order_date instanceof Carbon ? $order->order_date->toDateString() : (string) ($order->order_date ?? now()->toDateString());

        $pendingGrns = collect();
        foreach ($order->goodsReceiveds as $grn) {
            $facts = app(WarehouseReceiptStateResolver::class)->forReceipt($grn);
            if (($facts['receipt_status'] ?? '') !== 'received') {
                $pendingGrns->push($grn);
            }
        }

        $targetItems = [];
        if ($pendingGrns->isNotEmpty()) {
            foreach ($pendingGrns->sortBy('id') as $pGrn) {
                foreach ($pGrn->items->sortBy('id') as $pItem) {
                    $targetItems[] = [
                        'goods_received_id' => $pGrn->id,
                        'goods_received_item_id' => $pItem->id,
                        'purchase_order_item_id' => $pItem->purchase_order_item_id,
                        'product' => $pItem->product,
                        'qty' => (float) $pItem->received_qty,
                        'unit' => $pItem->received_unit ?: $pItem->product?->unit,
                    ];
                }
            }
        } else {
            foreach ($order->items->sortBy('id') as $poItem) {
                $targetItems[] = [
                    'goods_received_id' => null,
                    'goods_received_item_id' => null,
                    'purchase_order_item_id' => $poItem->id,
                    'product' => $poItem->product,
                    'qty' => (float) $poItem->quantity,
                    'unit' => $poItem->purchase_unit ?: $poItem->unit ?: $poItem->product?->unit,
                ];
            }
        }

        $productItems = [];
        $totalBillBaseQty = 0.0;
        $totalMatchedBaseQty = 0.0;
        $allUnitsCompatible = true;
        $firstUnit = null;

        $exactCount = 0;
        $partialCount = 0;
        $unmatchedCount = 0;

        foreach ($targetItems as $tItem) {
            /** @var Product|null $product */
            $product = $tItem['product'];
            if (! $product) {
                continue;
            }

            $itemUnit = $tItem['unit'] ?: $product->unit;
            $conversionToBase = (float) ($product->conversionToBaseForUnit($itemUnit) ?? 1.0);
            $billBaseQty = round((float) $tItem['qty'] * $conversionToBase, 3);
            $totalBillBaseQty += $billBaseQty;

            if ($firstUnit === null) {
                $firstUnit = strtolower(trim((string) $itemUnit));
            } elseif ($firstUnit !== strtolower(trim((string) $itemUnit))) {
                $allUnitsCompatible = false;
            }

            // Find all confirmed open/partial Advance GRN items for this product
            $candidates = $preloadedCandidatesByProduct !== null ? ($preloadedCandidatesByProduct[$product->id] ?? []) : $this->getOpenAdvanceCandidatesForProduct($product->id, $targetWarehouseId);

            $suggestedMatches = [];
            $remainingNeededBase = $billBaseQty;
            $lineMatchedBase = 0.0;

            foreach ($candidates as $cand) {
                if ($remainingNeededBase <= 0.0001) {
                    break;
                }

                $availableBase = (float) $cand['available_base_qty'];
                if ($availableBase <= 0.0001) {
                    continue;
                }

                $matchBase = min($availableBase, $remainingNeededBase);
                $matchItemQty = $conversionToBase > 0 ? round($matchBase / $conversionToBase, 3) : $matchBase;

                $suggestedMatches[] = [
                    'advance_goods_received_id' => $cand['advance_goods_received_id'],
                    'advance_goods_received_item_id' => $cand['advance_goods_received_item_id'],
                    'advance_stock_batch_id' => $cand['advance_stock_batch_id'],
                    'grn_number' => $cand['grn_number'],
                    'received_at' => $cand['received_at'],
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit' => $itemUnit,
                    'original_qty' => $cand['original_qty'],
                    'already_matched_qty' => $cand['already_matched_qty'],
                    'available_qty' => $cand['available_qty'],
                    'proposed_match_qty' => $matchItemQty,
                    'matched_qty' => $matchItemQty,
                    'proposed_match_base_qty' => $matchBase,
                    'coverage_percentage' => $billBaseQty > 0 ? round(($matchBase / $billBaseQty) * 100, 1) : 0.0,
                ];

                $lineMatchedBase += $matchBase;
                $remainingNeededBase -= $matchBase;
            }

            $totalMatchedBaseQty += $lineMatchedBase;
            $lineMatchedItemQty = $conversionToBase > 0 ? round($lineMatchedBase / $conversionToBase, 3) : $lineMatchedBase;
            $newReceiveItemQty = max(0.0, round((float) $tItem['qty'] - $lineMatchedItemQty, 3));
            $lineCoveragePct = $billBaseQty > 0 ? round(($lineMatchedBase / $billBaseQty) * 100, 1) : 0.0;

            // Determine line match status
            $matchStatus = 'unmatched';
            if ($lineCoveragePct >= 99.99) {
                $matchStatus = 'exact';
                $exactCount++;
            } elseif ($lineCoveragePct > 0.0001) {
                $matchStatus = 'partial';
                $partialCount++;
            } else {
                $unmatchedCount++;
            }

            // Derive Loadout context for this cohort (read-only)
            $cohortKey = "{$product->id}_{$orderDate}";
            $relevantLoadoutQty = $loadoutsByCohort !== null
                ? (float) ($loadoutsByCohort[$cohortKey] ?? 0.0)
                : $this->getLoadedQtyForCohort($product->id, $targetWarehouseId, $orderDate);
            $unbilledLoadoutQty = max(0.0, round($relevantLoadoutQty - $lineMatchedItemQty, 3));

            $productItems[] = [
                'goods_received_id' => $tItem['goods_received_id'],
                'goods_received_item_id' => $tItem['goods_received_item_id'],
                'purchase_order_item_id' => $tItem['purchase_order_item_id'],
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'ordered_qty' => (float) $tItem['qty'],
                'unit' => $itemUnit,
                'base_qty' => $billBaseQty,
                'total_advance_available_qty' => round(array_sum(array_column($candidates, 'available_qty')), 3),
                'total_proposed_match_qty' => $lineMatchedItemQty,
                'new_receive_qty' => $newReceiveItemQty,
                'coverage_percentage' => $lineCoveragePct,
                'match_status' => $matchStatus,
                'relevant_loadout_qty' => $relevantLoadoutQty,
                'unbilled_loadout_qty' => $unbilledLoadoutQty,
                'has_advance_available' => ! empty($suggestedMatches),
                'suggested_matches' => $suggestedMatches,
                'all_candidates' => $candidates,
            ];
        }

        $overallCoveragePct = $totalBillBaseQty > 0
            ? round(($totalMatchedBaseQty / $totalBillBaseQty) * 100, 1)
            : 0.0;

        $reconciliationStatus = 'unmatched';
        $itemTotal = count($order->items);
        if ($itemTotal > 0 && $exactCount === $itemTotal) {
            $reconciliationStatus = 'ready';
        } elseif ($totalMatchedBaseQty > 0.0001) {
            $reconciliationStatus = 'partial';
        }

        return [
            'purchase_order_id' => $order->id,
            'po_number' => $order->order_number ?? $order->po_number,
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $order->supplier?->name ?? 'Supplier',
            'order_date' => $orderDate,
            'total_bill_base_qty' => round($totalBillBaseQty, 3),
            'total_matched_base_qty' => round($totalMatchedBaseQty, 3),
            'overall_coverage_percentage' => $overallCoveragePct,
            'is_overall_percentage_meaningful' => $allUnitsCompatible,
            'has_advance_match' => $totalMatchedBaseQty > 0.0001,
            'reconciliation_status' => $reconciliationStatus,
            'exact_matches_count' => $exactCount,
            'partial_matches_count' => $partialCount,
            'unmatched_count' => $unmatchedCount,
            'items' => $productItems,
        ];
    }

    /**
     * Get deterministic suggested Advance matches for a GoodsReceived note.
     *
     * @return array<string, mixed>
     */
    public function getSuggestionsForGrn(GoodsReceived $grn, ?int $warehouseId = null): array
    {
        $grn->loadMissing(['items.product.orderUnits', 'purchaseOrder.supplier', 'purchaseOrder.items']);

        if ($grn->purchaseOrder instanceof PurchaseOrder) {
            return $this->getSuggestionsForOrder($grn->purchaseOrder, $warehouseId ?? $grn->warehouse_id);
        }

        $targetWarehouseId = $warehouseId ?? $grn->warehouse_id;
        $orderDate = $grn->received_at instanceof Carbon ? $grn->received_at->format('Y-m-d') : (string) ($grn->received_at ?? now()->toDateString());

        $productItems = [];
        $totalBillBaseQty = 0.0;
        $totalMatchedBaseQty = 0.0;
        $allUnitsCompatible = true;
        $firstUnit = null;

        $exactCount = 0;
        $partialCount = 0;
        $unmatchedCount = 0;

        foreach ($grn->items as $item) {
            /** @var Product $product */
            $product = $item->product;
            $itemUnit = $item->received_unit ?: $product->unit;
            $conversionToBase = (float) ($product->conversionToBaseForUnit($itemUnit) ?? 1.0);
            $billBaseQty = round((float) $item->received_qty * $conversionToBase, 3);
            $totalBillBaseQty += $billBaseQty;

            if ($firstUnit === null) {
                $firstUnit = strtolower(trim((string) $itemUnit));
            } elseif ($firstUnit !== strtolower(trim((string) $itemUnit))) {
                $allUnitsCompatible = false;
            }

            $candidates = $this->getOpenAdvanceCandidatesForProduct($product->id, $targetWarehouseId);

            $suggestedMatches = [];
            $remainingNeededBase = $billBaseQty;
            $lineMatchedBase = 0.0;

            foreach ($candidates as $cand) {
                if ($remainingNeededBase <= 0.0001) {
                    break;
                }

                $availableBase = (float) $cand['available_base_qty'];
                if ($availableBase <= 0.0001) {
                    continue;
                }

                $matchBase = min($availableBase, $remainingNeededBase);
                $matchItemQty = $conversionToBase > 0 ? round($matchBase / $conversionToBase, 3) : $matchBase;

                $suggestedMatches[] = [
                    'advance_goods_received_id' => $cand['advance_goods_received_id'],
                    'advance_goods_received_item_id' => $cand['advance_goods_received_item_id'],
                    'advance_stock_batch_id' => $cand['advance_stock_batch_id'],
                    'goods_received_item_id' => $item->id,
                    'grn_number' => $cand['grn_number'],
                    'received_at' => $cand['received_at'],
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'matched_qty' => $matchItemQty,
                    'unit' => $itemUnit,
                    'base_qty' => $matchBase,
                    'available_qty_before' => $cand['available_qty'],
                    'available_qty_after' => round($cand['available_qty'] - $matchItemQty, 3),
                    'available_base_qty_before' => $availableBase,
                    'available_base_qty_after' => round($availableBase - $matchBase, 3),
                ];

                $lineMatchedBase += $matchBase;
                $remainingNeededBase -= $matchBase;
            }

            $totalMatchedBaseQty += $lineMatchedBase;

            $lineMatchedItemQty = $conversionToBase > 0 ? round($lineMatchedBase / $conversionToBase, 3) : $lineMatchedBase;
            $newReceiveBaseQty = max(0.0, round($billBaseQty - $lineMatchedBase, 3));
            $newReceiveItemQty = $conversionToBase > 0 ? round($newReceiveBaseQty / $conversionToBase, 3) : $newReceiveBaseQty;

            $lineCoveragePct = $billBaseQty > 0 ? round(($lineMatchedBase / $billBaseQty) * 100, 1) : 0.0;

            $matchStatus = 'unmatched';
            if ($lineMatchedBase >= $billBaseQty - 0.001) {
                $matchStatus = 'exact';
                $exactCount++;
            } elseif ($lineMatchedBase > 0.0001) {
                $matchStatus = 'partial';
                $partialCount++;
            } else {
                $unmatchedCount++;
            }

            $relevantLoadoutQty = $this->getLoadedQtyForCohort($product->id, $targetWarehouseId, $orderDate);
            $unbilledLoadoutQty = max(0.0, round($relevantLoadoutQty - $billBaseQty, 3));

            $productItems[] = [
                'goods_received_item_id' => $item->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'bill_qty' => (float) $item->received_qty,
                'unit' => $itemUnit,
                'base_qty' => $billBaseQty,
                'total_advance_available_qty' => round(array_sum(array_column($candidates, 'available_qty')), 3),
                'total_proposed_match_qty' => $lineMatchedItemQty,
                'new_receive_qty' => $newReceiveItemQty,
                'coverage_percentage' => $lineCoveragePct,
                'match_status' => $matchStatus,
                'relevant_loadout_qty' => $relevantLoadoutQty,
                'unbilled_loadout_qty' => $unbilledLoadoutQty,
                'has_advance_available' => ! empty($suggestedMatches),
                'suggested_matches' => $suggestedMatches,
                'all_candidates' => $candidates,
            ];
        }

        $overallCoveragePct = $totalBillBaseQty > 0
            ? round(($totalMatchedBaseQty / $totalBillBaseQty) * 100, 1)
            : 0.0;

        $reconciliationStatus = 'unmatched';
        $itemTotal = count($grn->items);
        if ($itemTotal > 0 && $exactCount === $itemTotal) {
            $reconciliationStatus = 'ready';
        } elseif ($totalMatchedBaseQty > 0.0001) {
            $reconciliationStatus = 'partial';
        }

        return [
            'goods_received_id' => $grn->id,
            'grn_number' => $grn->grn_number,
            'supplier_id' => $grn->purchaseOrder?->supplier_id,
            'supplier_name' => $grn->purchaseOrder?->supplier?->name ?? 'Supplier',
            'received_at' => $orderDate,
            'total_bill_base_qty' => round($totalBillBaseQty, 3),
            'total_matched_base_qty' => round($totalMatchedBaseQty, 3),
            'overall_coverage_percentage' => $overallCoveragePct,
            'is_overall_percentage_meaningful' => $allUnitsCompatible,
            'has_advance_match' => $totalMatchedBaseQty > 0.0001,
            'reconciliation_status' => $reconciliationStatus,
            'exact_matches_count' => $exactCount,
            'partial_matches_count' => $partialCount,
            'unmatched_count' => $unmatchedCount,
            'items' => $productItems,
        ];
    }

    /**
     * Get loaded quantity for a cohort (product, warehouse, date).
     * Read-only context query. Zero mutations.
     */
    public function getLoadedQtyForCohort(int $productId, ?int $warehouseId, string $date): float
    {
        return (float) DB::table('shop_order_items')
            ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.shop_order_id')
            ->join('products', 'products.id', '=', 'shop_order_items.product_id')
            ->where('shop_order_items.product_id', $productId)
            ->whereDate('shop_orders.business_date', $date)
            ->whereNull('shop_order_items.deleted_at')
            ->when($warehouseId !== null, fn ($q) => $q->where('products.default_warehouse_id', $warehouseId))
            ->where(function ($q): void {
                $q->where('shop_order_items.sorting_status', 'loaded')
                    ->orWhere('shop_order_items.loaded_qty', '>', 0)
                    ->orWhere('shop_order_items.actual_weight', '>', 0);
            })
            ->sum(DB::raw('COALESCE(shop_order_items.actual_weight, shop_order_items.loaded_qty, 0)'));
    }

    /**
     * Fetch all open/partial confirmed Advance candidates for a product, sorted FIFO by received_at.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOpenAdvanceCandidatesForProduct(int $productId, ?int $warehouseId = null): array
    {
        $advanceGrns = GoodsReceived::query()
            ->openWarehouseAdvance($warehouseId, $productId)
            ->with([
                'items' => fn ($q) => $q->where('product_id', $productId)->with('product.orderUnits'),
                'stockBatches' => fn ($q) => $q->where('product_id', $productId)->where('warehouse_receive_pending', false),
            ])
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $candidates = [];

        foreach ($advanceGrns as $grn) {
            // Unassigned legacy matches for this product on this GRN (advance_goods_received_item_id is NULL)
            $legacyUnassignedMatchedBase = (float) AdvanceReceiveMatch::query()
                ->where('advance_goods_received_id', $grn->id)
                ->where('product_id', $productId)
                ->whereNull('advance_goods_received_item_id')
                ->sum('base_qty');

            foreach ($grn->items as $grnItem) {
                /** @var Product|null $product */
                $product = $grnItem->product;
                $conv = (float) ($product?->conversionToBaseForUnit($grnItem->received_unit) ?? 1.0);
                $originalQty = (float) $grnItem->received_qty;
                $originalBaseQty = $originalQty * $conv;

                // Explicit item-level matches
                $explicitItemMatchedBase = (float) AdvanceReceiveMatch::query()
                    ->where('advance_goods_received_id', $grn->id)
                    ->where('advance_goods_received_item_id', $grnItem->id)
                    ->sum('base_qty');

                $itemRemainingBase = max(0.0, $originalBaseQty - $explicitItemMatchedBase);

                // Absorb from legacy unassigned match pool if any remains
                $legacyApplied = 0.0;
                if ($legacyUnassignedMatchedBase > 0.0001 && $itemRemainingBase > 0.0001) {
                    $legacyApplied = min($itemRemainingBase, $legacyUnassignedMatchedBase);
                    $legacyUnassignedMatchedBase -= $legacyApplied;
                    $itemRemainingBase = max(0.0, $itemRemainingBase - $legacyApplied);
                }

                $totalMatchedBaseForItem = $explicitItemMatchedBase + $legacyApplied;
                $availableBaseQty = round($itemRemainingBase, 3);
                $availableItemQty = $conv > 0 ? round($availableBaseQty / $conv, 3) : $availableBaseQty;

                // If no remaining balance, advance item is CLEARED
                if ($availableBaseQty <= 0.0001) {
                    continue;
                }

                $batch = $grn->stockBatches->firstWhere('goods_received_item_id', $grnItem->id)
                    ?? $grn->stockBatches->first();

                $candidates[] = [
                    'advance_goods_received_id' => $grn->id,
                    'advance_goods_received_item_id' => $grnItem->id,
                    'advance_stock_batch_id' => $batch?->id,
                    'grn_number' => $grn->grn_number,
                    'received_at' => $grn->received_at?->toDateString() ?? $grn->created_at?->toDateString(),
                    'unit' => $grnItem->received_unit ?? 'kg',
                    'original_qty' => $originalQty,
                    'original_base_qty' => round($originalBaseQty, 3),
                    'already_matched_qty' => $conv > 0 ? round($totalMatchedBaseForItem / $conv, 3) : round($totalMatchedBaseForItem, 3),
                    'already_matched_base_qty' => round($totalMatchedBaseForItem, 3),
                    'available_qty' => $availableItemQty,
                    'available_base_qty' => $availableBaseQty,
                    'status' => $totalMatchedBaseForItem > 0.0001 ? 'partial' : 'open',
                ];
            }
        }

        return $candidates;
    }

    public function reconcileAndExecute(GoodsReceivedData $data, int $userId): GoodsReceived
    {
        return DB::transaction(function () use ($data, $userId): GoodsReceived {
            $payloadHash = $data->calculatePayloadHash();

            if (! empty($data->clientSubmissionId)) {
                /** @var GoodsReceived|null $existing */
                $existing = GoodsReceived::query()
                    ->where('client_submission_id', $data->clientSubmissionId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing->fresh([
                        'items.product',
                        'items.purchaseOrderItem',
                        'purchaseOrder',
                        'advanceMatchesAsBill.advanceGoodsReceived',
                        'advanceMatchesAsBill.advanceStockBatch',
                    ]);
                }
            }

            // 1. Lock and validate all referenced Advance receipts
            $requestedMatches = $data->advanceMatches;
            $advanceGrnIds = collect($requestedMatches)->pluck('advance_goods_received_id')->filter()->unique()->all();

            $advanceGrns = GoodsReceived::query()
                ->whereIn('id', $advanceGrnIds)
                ->with(['items', 'stockBatches'])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Map of validated matches by bill item index / PO item id
            $validatedMatches = [];
            $totalMatchedByBillItem = [];

            foreach ($requestedMatches as $match) {
                $advanceGrnId = (int) ($match['advance_goods_received_id'] ?? 0);
                /** @var GoodsReceived|null $advanceGrn */
                $advanceGrn = $advanceGrns->get($advanceGrnId);

                if (! $advanceGrn) {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Referenced Advance Receive #{$advanceGrnId} not found.",
                    ]);
                }

                if ($advanceGrn->status !== 'approved') {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Advance Receive {$advanceGrn->grn_number} is not approved.",
                    ]);
                }

                // Check that the advance has confirmed physical inventory
                $confirmedBatches = $advanceGrn->stockBatches->where('warehouse_receive_pending', false);
                if ($confirmedBatches->isEmpty()) {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Advance Receive {$advanceGrn->grn_number} has not been physically confirmed by warehouse.",
                    ]);
                }

                $productId = (int) ($match['product_id'] ?? 0);
                $advItemId = isset($match['advance_goods_received_item_id']) && $match['advance_goods_received_item_id']
                    ? (int) $match['advance_goods_received_item_id']
                    : null;
                $advanceItem = $advItemId
                    ? $advanceGrn->items->firstWhere('id', $advItemId)
                    : $advanceGrn->items->firstWhere('product_id', $productId);

                if (! $advanceItem || (int) $advanceItem->product_id !== $productId) {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Advance Receive {$advanceGrn->grn_number} does not contain product ID #{$productId}.",
                    ]);
                }

                /** @var Product $product */
                $product = Product::findOrFail($productId);
                $matchUnit = (string) ($match['matched_unit'] ?? $match['unit'] ?? $advanceItem->received_unit ?? $product->unit);
                $conversionToBase = (float) ($match['conversion_to_base'] ?? $product->conversionToBaseForUnit($matchUnit) ?? 1.0);
                $matchedQty = (float) ($match['matched_qty'] ?? 0.0);
                $matchedBaseQty = isset($match['base_qty']) && (float) $match['base_qty'] > 0
                    ? (float) $match['base_qty']
                    : round($matchedQty * $conversionToBase, 3);

                if ($matchedBaseQty <= 0.0001) {
                    continue;
                }

                // Check remaining available balance for this advance item
                $existingMatchedBase = (float) AdvanceReceiveMatch::query()
                    ->where('advance_goods_received_id', $advanceGrn->id)
                    ->where('advance_goods_received_item_id', $advanceItem->id)
                    ->sum('base_qty');

                $alreadyAllocatedInThisBatch = (float) collect($validatedMatches)
                    ->where('advance_goods_received_id', $advanceGrn->id)
                    ->where('advance_goods_received_item_id', $advanceItem->id)
                    ->sum('base_qty');

                $advanceItemUnit = $advanceItem->received_unit ?: $product->unit;
                $advanceItemConv = (float) ($product->conversionToBaseForUnit($advanceItemUnit) ?? 1.0);
                $advanceItemBaseQty = round((float) $advanceItem->received_qty * $advanceItemConv, 3);
                $availableBase = round($advanceItemBaseQty - $existingMatchedBase - $alreadyAllocatedInThisBatch, 3);

                if ($matchedBaseQty > $availableBase + 0.0001) {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Requested match ({$matchedQty} {$matchUnit}) exceeds available advance balance ({$availableBase} base) for {$advanceGrn->grn_number}.",
                    ]);
                }

                $batch = $advanceGrn->stockBatches->firstWhere('goods_received_item_id', $advanceItem->id)
                    ?? $advanceGrn->stockBatches->firstWhere('product_id', $productId)
                    ?? $advanceGrn->stockBatches->first();

                $validatedMatches[] = [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceItem->id,
                    'advance_stock_batch_id' => $batch?->id,
                    'purchase_order_item_id' => $match['purchase_order_item_id'] ?? null,
                    'product_id' => $productId,
                    'matched_qty' => $matchedQty,
                    'matched_unit' => $matchUnit,
                    'base_qty' => $matchedBaseQty,
                    'conversion_to_base' => $conversionToBase,
                ];

                $key = ! empty($match['purchase_order_item_id'])
                    ? "po:{$match['purchase_order_item_id']}"
                    : "prod:{$productId}";

                $totalMatchedByBillItem[$key] = ($totalMatchedByBillItem[$key] ?? 0.0) + $matchedBaseQty;
            }

            // 2. Create Bill GoodsReceived Record
            $grnNumber = $this->grnRepository->generateGrnNumber();
            /** @var GoodsReceived $billGrn */
            $billGrn = $this->grnRepository->create([
                'purchase_order_id' => $data->purchaseOrderId,
                'destination_shop_id' => $data->destinationShopId,
                'warehouse_id' => $data->warehouseId,
                'client_submission_id' => $data->clientSubmissionId,
                'submission_payload_hash' => $payloadHash,
                'grn_number' => $grnNumber,
                'status' => 'approved',
                'bill_status' => 'bill_available',
                'receipt_type' => 'normal_purchase',
                'bill_number' => $data->billNumber,
                'received_by' => $userId,
                'approved_by' => $userId,
                'updated_by' => $userId,
                'matched_by' => $userId,
                'matched_at' => now(),
                'received_at' => $data->receivedAt,
                'approved_at' => now(),
                'transport_cost' => $data->transportCost,
                'labour_cost' => $data->labourCost,
                'notes' => $data->notes,
            ]);

            $totalReceivedQty = array_sum(array_column($data->items, 'received_qty'));
            $createdBillItems = [];

            // 3. Create Bill GRN Items
            foreach ($data->items as $item) {
                /** @var PurchaseOrderItem|null $poItem */
                $poItem = ! empty($item['purchase_order_item_id'])
                    ? PurchaseOrderItem::find($item['purchase_order_item_id'])
                    : null;

                $variance = $poItem ? ($item['received_qty'] - (float) $poItem->quantity) : 0.0;

                /** @var GoodsReceivedItem $grnItem */
                $grnItem = $billGrn->items()->create([
                    'purchase_order_item_id' => $poItem?->id,
                    'product_id' => $item['product_id'],
                    'received_qty' => $item['received_qty'],
                    'received_unit' => $item['received_unit'] ?? 'kg',
                    'variance' => $variance,
                ]);

                $createdBillItems[] = $grnItem;
            }

            // 4. Calculate Reconciliation Quantities and Source Type
            $totalBillBaseQty = 0.0;
            $totalMatchedBaseQty = 0.0;

            foreach ($createdBillItems as $grnItem) {
                /** @var Product $product */
                $product = Product::find($grnItem->product_id);
                $itemUnit = $grnItem->received_unit ?: $product->unit;
                $conv = (float) ($product->conversionToBaseForUnit($itemUnit) ?? 1.0);
                $totalBillBaseQty += round((float) $grnItem->received_qty * $conv, 3);
            }

            foreach ($validatedMatches as $m) {
                $totalMatchedBaseQty += (float) $m['base_qty'];
            }

            $totalNewReceiveBaseQty = max(0.0, round($totalBillBaseQty - $totalMatchedBaseQty, 3));

            $sourceType = 'normal';
            if ($totalMatchedBaseQty > 0.0001 && $totalNewReceiveBaseQty <= 0.0001) {
                $sourceType = 'advance';
            } elseif ($totalMatchedBaseQty > 0.0001 && $totalNewReceiveBaseQty > 0.0001) {
                $sourceType = 'mixed';
            }

            /** @var BillReconciliation $billReconciliation */
            $billReconciliation = BillReconciliation::create([
                'purchase_order_id' => $data->purchaseOrderId,
                'goods_received_id' => $billGrn->id,
                'warehouse_id' => $billGrn->warehouse_id,
                'source_type' => $sourceType,
                'status' => 'confirmed',
                'total_bill_base_qty' => $totalBillBaseQty,
                'total_matched_base_qty' => $totalMatchedBaseQty,
                'total_new_receive_base_qty' => $totalNewReceiveBaseQty,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
                'client_submission_id' => $data->clientSubmissionId,
                'submission_payload_hash' => $payloadHash,
                'notes' => $data->notes,
            ]);

            $date = $billGrn->received_at instanceof Carbon
                ? $billGrn->received_at->format('Y-m-d')
                : Carbon::parse($billGrn->received_at)->format('Y-m-d');

            $createdReconciliationLines = [];

            foreach ($createdBillItems as $grnItem) {
                $poItemId = $grnItem->purchase_order_item_id;
                $key = $poItemId ? "po:{$poItemId}" : "prod:{$grnItem->product_id}";
                $matchedBaseQty = (float) ($totalMatchedByBillItem[$key] ?? 0.0);

                /** @var Product $product */
                $product = Product::find($grnItem->product_id);
                $itemUnit = $grnItem->received_unit ?: $product->unit;
                $conversionToBase = (float) ($product->conversionToBaseForUnit($itemUnit) ?? 1.0);
                $totalBillItemBaseQty = round((float) $grnItem->received_qty * $conversionToBase, 3);
                $matchedItemQty = $conversionToBase > 0 ? round($matchedBaseQty / $conversionToBase, 3) : $matchedBaseQty;

                $unmatchedBaseQty = max(0.0, round($totalBillItemBaseQty - $matchedBaseQty, 3));
                $unmatchedItemQty = $conversionToBase > 0 ? round($unmatchedBaseQty / $conversionToBase, 3) : $unmatchedBaseQty;

                $relevantLoadoutQty = $this->getLoadedQtyForCohort($grnItem->product_id, (int) $billGrn->warehouse_id, $date);
                $unbilledLoadoutQty = max(0.0, round($relevantLoadoutQty - $totalBillItemBaseQty, 3));

                $differenceStatus = 'unmatched';
                if ($matchedBaseQty >= $totalBillItemBaseQty - 0.001) {
                    $differenceStatus = 'matched';
                } elseif ($matchedBaseQty > 0.0001) {
                    $differenceStatus = 'partial';
                }

                /** @var BillReconciliationLine $reconLine */
                $reconLine = $billReconciliation->lines()->create([
                    'purchase_order_item_id' => $poItemId,
                    'product_id' => $grnItem->product_id,
                    'bill_qty' => $grnItem->received_qty,
                    'bill_unit' => $itemUnit,
                    'bill_base_qty' => $totalBillItemBaseQty,
                    'advance_matched_qty' => $matchedItemQty,
                    'advance_matched_unit' => $itemUnit,
                    'advance_matched_base_qty' => $matchedBaseQty,
                    'new_receive_qty' => $unmatchedItemQty,
                    'new_receive_unit' => $itemUnit,
                    'new_receive_base_qty' => $unmatchedBaseQty,
                    'relevant_loadout_qty' => $relevantLoadoutQty,
                    'unbilled_loadout_qty' => $unbilledLoadoutQty,
                    'reconciled_qty' => $grnItem->received_qty,
                    'reconciled_base_qty' => $totalBillItemBaseQty,
                    'difference_status' => $differenceStatus,
                ]);

                $createdReconciliationLines[$key] = $reconLine;
            }

            // 5. Persist AdvanceReceiveMatch records linking Advance to Bill and Reconciliation
            foreach ($validatedMatches as $matchRecord) {
                $poItemId = $matchRecord['purchase_order_item_id'];
                $billItem = $poItemId
                    ? collect($createdBillItems)->firstWhere('purchase_order_item_id', $poItemId)
                    : collect($createdBillItems)->firstWhere('product_id', $matchRecord['product_id']);

                $key = $poItemId ? "po:{$poItemId}" : "prod:{$matchRecord['product_id']}";
                $reconLine = $createdReconciliationLines[$key] ?? null;

                AdvanceReceiveMatch::create([
                    'advance_goods_received_id' => $matchRecord['advance_goods_received_id'],
                    'advance_goods_received_item_id' => $matchRecord['advance_goods_received_item_id'],
                    'advance_stock_batch_id' => $matchRecord['advance_stock_batch_id'],
                    'bill_goods_received_id' => $billGrn->id,
                    'bill_goods_received_item_id' => $billItem?->id,
                    'bill_reconciliation_id' => $billReconciliation->id,
                    'bill_reconciliation_line_id' => $reconLine?->id,
                    'purchase_order_id' => $data->purchaseOrderId,
                    'purchase_order_item_id' => $poItemId,
                    'product_id' => $matchRecord['product_id'],
                    'matched_qty' => $matchRecord['matched_qty'],
                    'matched_unit' => $matchRecord['matched_unit'],
                    'base_qty' => $matchRecord['base_qty'],
                    'conversion_to_base' => $matchRecord['conversion_to_base'],
                    'confirmed_by' => $userId,
                    'confirmed_at' => now(),
                    'client_submission_id' => $data->clientSubmissionId,
                ]);
            }

            // 6. Create StockBatches ONLY for the unmatched bill quantity

            foreach ($createdBillItems as $grnItem) {
                $poItemId = $grnItem->purchase_order_item_id;
                $key = $poItemId ? "po:{$poItemId}" : "prod:{$grnItem->product_id}";
                $matchedBaseQty = (float) ($totalMatchedByBillItem[$key] ?? 0.0);

                /** @var Product $product */
                $product = Product::find($grnItem->product_id);
                $itemUnit = $grnItem->received_unit ?: $product->unit;
                $conversionToBase = (float) ($product->conversionToBaseForUnit($itemUnit) ?? 1.0);
                $totalBillItemBaseQty = round((float) $grnItem->received_qty * $conversionToBase, 3);

                // Unmatched remainder that must be physically received into stock
                $unmatchedBaseQty = max(0.0, round($totalBillItemBaseQty - $matchedBaseQty, 3));
                $unmatchedItemQty = $conversionToBase > 0 ? round($unmatchedBaseQty / $conversionToBase, 3) : $unmatchedBaseQty;

                if ($unmatchedItemQty <= 0.0001 || $data->autoAdvanceClear) {
                    continue;
                }

                // Landed cost allocation for the new quantity
                $allocatedTransport = 0.00;
                $allocatedLabour = 0.00;
                if ($totalReceivedQty > 0) {
                    $allocatedTransport = ($unmatchedItemQty / $totalReceivedQty) * (float) $billGrn->transport_cost;
                    $allocatedLabour = ($unmatchedItemQty / $totalReceivedQty) * (float) $billGrn->labour_cost;
                }

                $costPerKg = $this->calculateWeightedAvgPrice($grnItem->product_id, $date, $grnItem);

                if (($grnItem->grade ?? 'A') === 'A' && $costPerKg > 0) {
                    $this->vendorPriceService->syncPrice($grnItem->product_id, $costPerKg);
                }

                $this->stockBatchRepository->create([
                    'product_id' => $grnItem->product_id,
                    'warehouse_id' => $billGrn->warehouse_id,
                    'goods_received_id' => $billGrn->id,
                    'goods_received_item_id' => $grnItem->id,
                    'purchase_grade' => $grnItem->grade ?? $billGrn->purchase_grade ?? 'A',
                    'grading_mode' => ($grnItem->grade ?? $billGrn->purchase_grade ?? 'A') === 'B' ? 'fixed_purchase_grade' : 'sort_required',
                    'created_by' => $userId,
                    'reference' => $this->stockBatchRepository->generateReference(),
                    'received_at' => $billGrn->received_at,
                    'total_kg' => $unmatchedItemQty,
                    'cost_per_kg' => $costPerKg,
                    'transport_cost' => round($allocatedTransport, 2),
                    'labour_cost' => round($allocatedLabour, 2),
                    'status' => BatchStatus::Pending,
                    'warehouse_receive_pending' => true,
                    'notes' => "Reconciled GRN: {$billGrn->grn_number} (Unmatched: {$unmatchedItemQty} {$itemUnit})",
                ]);
            }

            // 6. Update Advance GRN bill_status if fully consumed
            foreach ($advanceGrns as $advanceGrn) {
                $totalAdvanceReceivedBase = (float) $advanceGrn->items->sum(function (GoodsReceivedItem $it): float {
                    /** @var Product $p */
                    $p = $it->product ?? Product::find($it->product_id);
                    $conv = (float) ($p?->conversionToBaseForUnit($it->received_unit) ?? 1.0);

                    return (float) $it->received_qty * $conv;
                });

                $totalConsumedBase = (float) AdvanceReceiveMatch::query()
                    ->where('advance_goods_received_id', $advanceGrn->id)
                    ->sum('base_qty');

                if ($totalConsumedBase >= $totalAdvanceReceivedBase - 0.001) {
                    $advanceGrn->update(['bill_status' => 'bill_available']);
                }
            }

            // 7. Update PO status and Bill GRN bill_status
            if ($data->autoAdvanceClear && $totalNewReceiveBaseQty > 0.0001) {
                $billGrn->update(['bill_status' => 'bill_pending']);
            }

            if ($data->purchaseOrderId) {
                /** @var PurchaseOrder|null $po */
                $po = PurchaseOrder::with('items.product')->find($data->purchaseOrderId);
                if ($po) {
                    $poOrderedBase = (float) $po->items->sum(function (PurchaseOrderItem $it): float {
                        /** @var Product|null $p */
                        $p = $it->product ?? Product::find($it->product_id);
                        $unit = $it->purchase_unit ?: $it->unit ?: $p?->unit;
                        $conv = (float) ($p?->conversionToBaseForUnit($unit) ?? 1.0);

                        return (float) $it->quantity * $conv;
                    });

                    $poMatchedBase = (float) AdvanceReceiveMatch::query()
                        ->where('purchase_order_id', $po->id)
                        ->sum('base_qty');

                    $poFulfilledBase = round($poMatchedBase + $totalNewReceiveBaseQty, 3);
                    $poRemainingBase = max(0.0, round($poOrderedBase - $poFulfilledBase, 3));

                    if ($poRemainingBase > 0.001) {
                        $po->update(['status' => POStatus::PartiallyReceived]);
                    } else {
                        $po->update(['status' => POStatus::Received]);
                    }
                }
            }

            // 8. Log activity
            activity()
                ->performedOn($billGrn)
                ->causedBy(User::query()->find($userId))
                ->withProperties([
                    'source' => 'advance_receive_reconciliation',
                    'matches_count' => count($validatedMatches),
                ])
                ->log('goods_received.reconciled_with_advance');

            return $billGrn->fresh([
                'items.product',
                'items.purchaseOrderItem',
                'purchaseOrder',
                'advanceMatchesAsBill.advanceGoodsReceived',
                'advanceMatchesAsBill.advanceStockBatch',
            ]);
        });
    }

    /**
     * Reconcile an existing pending GoodsReceived note against open Advance receipts.
     * Reuses the existing bill GRN, adjusts physical stock batches, links matches, and updates reconciliation status.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $advanceMatches
     */
    public function reconcileExistingGrn(
        GoodsReceived $grn,
        array $items,
        array $advanceMatches,
        int $fallbackWarehouseId,
        int $userId,
        bool $autoAdvanceClear = false
    ): GoodsReceived {
        return DB::transaction(function () use ($grn, $items, $advanceMatches, $fallbackWarehouseId, $userId, $autoAdvanceClear): GoodsReceived {
            /** @var GoodsReceived $lockedGrn */
            $lockedGrn = GoodsReceived::query()
                ->whereKey($grn->id)
                ->with(['items.purchaseOrderItem', 'items.product', 'stockBatches', 'purchaseOrder'])
                ->lockForUpdate()
                ->firstOrFail();

            // 1. Lock and validate all referenced Advance receipts
            $advanceGrnIds = collect($advanceMatches)->pluck('advance_goods_received_id')->filter()->unique()->all();
            $advanceGrns = GoodsReceived::query()
                ->whereIn('id', $advanceGrnIds)
                ->with(['items', 'stockBatches'])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $validatedMatches = [];
            $totalMatchedByBillItem = [];

            foreach ($advanceMatches as $match) {
                $advanceGrnId = (int) ($match['advance_goods_received_id'] ?? 0);
                /** @var GoodsReceived|null $advanceGrn */
                $advanceGrn = $advanceGrns->get($advanceGrnId);

                if (! $advanceGrn) {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Referenced Advance Receive #{$advanceGrnId} not found.",
                    ]);
                }

                if ($advanceGrn->status !== 'approved') {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Advance Receive {$advanceGrn->grn_number} is not approved.",
                    ]);
                }

                $confirmedBatches = $advanceGrn->stockBatches->where('warehouse_receive_pending', false);
                if ($confirmedBatches->isEmpty()) {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Advance Receive {$advanceGrn->grn_number} has not been physically confirmed by warehouse.",
                    ]);
                }

                $productId = (int) ($match['product_id'] ?? 0);
                $advItemId = isset($match['advance_goods_received_item_id']) && $match['advance_goods_received_item_id']
                    ? (int) $match['advance_goods_received_item_id']
                    : null;
                $advanceItem = $advItemId
                    ? $advanceGrn->items->firstWhere('id', $advItemId)
                    : $advanceGrn->items->firstWhere('product_id', $productId);

                if (! $advanceItem || (int) $advanceItem->product_id !== $productId) {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Advance Receive {$advanceGrn->grn_number} does not contain product ID #{$productId}.",
                    ]);
                }

                /** @var Product $product */
                $product = Product::findOrFail($productId);
                $matchUnit = (string) ($match['matched_unit'] ?? $match['unit'] ?? $advanceItem->received_unit ?? $product->unit);
                $conversionToBase = (float) ($match['conversion_to_base'] ?? $product->conversionToBaseForUnit($matchUnit) ?? 1.0);
                $matchedQty = (float) ($match['matched_qty'] ?? 0.0);
                $matchedBaseQty = isset($match['base_qty']) && (float) $match['base_qty'] > 0
                    ? (float) $match['base_qty']
                    : round($matchedQty * $conversionToBase, 3);

                if ($matchedBaseQty <= 0.0001) {
                    continue;
                }

                $existingMatchedBase = (float) AdvanceReceiveMatch::query()
                    ->where('advance_goods_received_id', $advanceGrn->id)
                    ->where('advance_goods_received_item_id', $advanceItem->id)
                    ->sum('base_qty');

                $alreadyAllocatedInThisBatch = (float) collect($validatedMatches)
                    ->where('advance_goods_received_id', $advanceGrn->id)
                    ->where('advance_goods_received_item_id', $advanceItem->id)
                    ->sum('base_qty');

                $advanceItemUnit = $advanceItem->received_unit ?: $product->unit;
                $advanceItemConv = (float) ($product->conversionToBaseForUnit($advanceItemUnit) ?? 1.0);
                $advanceItemBaseQty = round((float) $advanceItem->received_qty * $advanceItemConv, 3);
                $availableBase = round($advanceItemBaseQty - $existingMatchedBase - $alreadyAllocatedInThisBatch, 3);

                if ($matchedBaseQty > $availableBase + 0.0001) {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Requested match ({$matchedQty} {$matchUnit}) exceeds available advance balance ({$availableBase} base) for {$advanceGrn->grn_number}.",
                    ]);
                }

                $batch = $advanceGrn->stockBatches->firstWhere('goods_received_item_id', $advanceItem->id)
                    ?? $advanceGrn->stockBatches->firstWhere('product_id', $productId)
                    ?? $advanceGrn->stockBatches->first();

                $validatedMatches[] = [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceItem->id,
                    'advance_stock_batch_id' => $batch?->id,
                    'purchase_order_item_id' => $match['purchase_order_item_id'] ?? null,
                    'goods_received_item_id' => $match['goods_received_item_id'] ?? null,
                    'product_id' => $productId,
                    'matched_qty' => $matchedQty,
                    'matched_unit' => $matchUnit,
                    'base_qty' => $matchedBaseQty,
                    'conversion_to_base' => $conversionToBase,
                ];

                $key = ! empty($match['purchase_order_item_id'])
                    ? "po:{$match['purchase_order_item_id']}"
                    : (! empty($match['goods_received_item_id'])
                        ? "grni:{$match['goods_received_item_id']}"
                        : "prod:{$productId}");

                $totalMatchedByBillItem[$key] = ($totalMatchedByBillItem[$key] ?? 0.0) + $matchedBaseQty;
            }

            // 2. Update GRN items
            foreach ($items as $itemId => $itemData) {
                $item = $lockedGrn->items->firstWhere('id', (int) $itemId) ?? $lockedGrn->items()->findOrFail((int) $itemId);
                $originalPurchasedQty = (float) ($item->purchaseOrderItem?->quantity ?? $item->received_qty);
                $receivedQty = (float) $itemData['received_qty'];
                $variance = $receivedQty - (float) ($item->purchaseOrderItem?->quantity ?? 0.0);

                $item->update([
                    'received_qty' => $receivedQty,
                    'variance' => $variance,
                    'purchased_qty' => $originalPurchasedQty,
                    'discrepancy_type' => $itemData['discrepancy_type'] ?? 'none',
                    'discrepancy_note' => $itemData['discrepancy_note'] ?? null,
                ]);
            }

            $lockedGrn->loadMissing('items.product');

            // 3. Calculate totals & create BillReconciliation
            $totalBillBaseQty = 0.0;
            $totalMatchedBaseQty = 0.0;

            foreach ($lockedGrn->items as $grnItem) {
                /** @var Product $product */
                $product = $grnItem->product;
                $itemUnit = $grnItem->received_unit ?: $product->unit;
                $conv = (float) ($product->conversionToBaseForUnit($itemUnit) ?? 1.0);
                $totalBillBaseQty += round((float) $grnItem->received_qty * $conv, 3);
            }

            foreach ($validatedMatches as $m) {
                $totalMatchedBaseQty += (float) $m['base_qty'];
            }

            $totalNewReceiveBaseQty = max(0.0, round($totalBillBaseQty - $totalMatchedBaseQty, 3));

            $sourceType = 'normal';
            if ($totalMatchedBaseQty > 0.0001 && $totalNewReceiveBaseQty <= 0.0001) {
                $sourceType = 'advance';
            } elseif ($totalMatchedBaseQty > 0.0001 && $totalNewReceiveBaseQty > 0.0001) {
                $sourceType = 'mixed';
            }

            $billReconciliation = BillReconciliation::create([
                'purchase_order_id' => $lockedGrn->purchase_order_id,
                'goods_received_id' => $lockedGrn->id,
                'warehouse_id' => $lockedGrn->warehouse_id ?? $fallbackWarehouseId,
                'source_type' => $sourceType,
                'status' => 'confirmed',
                'total_bill_base_qty' => $totalBillBaseQty,
                'total_matched_base_qty' => $totalMatchedBaseQty,
                'total_new_receive_base_qty' => $totalNewReceiveBaseQty,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            $date = $lockedGrn->received_at instanceof Carbon
                ? $lockedGrn->received_at->format('Y-m-d')
                : Carbon::parse($lockedGrn->received_at)->format('Y-m-d');

            $createdReconciliationLines = [];

            foreach ($lockedGrn->items as $grnItem) {
                $poItemId = $grnItem->purchase_order_item_id;
                $key = $poItemId
                    ? "po:{$poItemId}"
                    : "grni:{$grnItem->id}";
                $matchedBaseQty = (float) ($totalMatchedByBillItem[$key] ?? $totalMatchedByBillItem["prod:{$grnItem->product_id}"] ?? 0.0);

                /** @var Product $product */
                $product = $grnItem->product;
                $itemUnit = $grnItem->received_unit ?: $product->unit;
                $conversionToBase = (float) ($product->conversionToBaseForUnit($itemUnit) ?? 1.0);
                $totalBillItemBaseQty = round((float) $grnItem->received_qty * $conversionToBase, 3);
                $matchedItemQty = $conversionToBase > 0 ? round($matchedBaseQty / $conversionToBase, 3) : $matchedBaseQty;

                $unmatchedBaseQty = max(0.0, round($totalBillItemBaseQty - $matchedBaseQty, 3));
                $unmatchedItemQty = $conversionToBase > 0 ? round($unmatchedBaseQty / $conversionToBase, 3) : $unmatchedBaseQty;

                $relevantLoadoutQty = $this->getLoadedQtyForCohort($grnItem->product_id, (int) ($lockedGrn->warehouse_id ?? $fallbackWarehouseId), $date);
                $unbilledLoadoutQty = max(0.0, round($relevantLoadoutQty - $totalBillItemBaseQty, 3));

                $differenceStatus = 'unmatched';
                if ($matchedBaseQty >= $totalBillItemBaseQty - 0.001) {
                    $differenceStatus = 'matched';
                } elseif ($matchedBaseQty > 0.0001) {
                    $differenceStatus = 'partial';
                }

                $reconLine = $billReconciliation->lines()->create([
                    'purchase_order_item_id' => $poItemId,
                    'product_id' => $grnItem->product_id,
                    'bill_qty' => $grnItem->received_qty,
                    'bill_unit' => $itemUnit,
                    'bill_base_qty' => $totalBillItemBaseQty,
                    'advance_matched_qty' => $matchedItemQty,
                    'advance_matched_unit' => $itemUnit,
                    'advance_matched_base_qty' => $matchedBaseQty,
                    'new_receive_qty' => $unmatchedItemQty,
                    'new_receive_unit' => $itemUnit,
                    'new_receive_base_qty' => $unmatchedBaseQty,
                    'relevant_loadout_qty' => $relevantLoadoutQty,
                    'unbilled_loadout_qty' => $unbilledLoadoutQty,
                    'reconciled_qty' => $grnItem->received_qty,
                    'reconciled_base_qty' => $totalBillItemBaseQty,
                    'difference_status' => $differenceStatus,
                ]);

                $createdReconciliationLines[$key] = $reconLine;
            }

            // 4. Create AdvanceReceiveMatch records
            foreach ($validatedMatches as $matchRecord) {
                $poItemId = $matchRecord['purchase_order_item_id'];
                $grnItemId = $matchRecord['goods_received_item_id'];
                $billItem = $poItemId
                    ? $lockedGrn->items->firstWhere('purchase_order_item_id', $poItemId)
                    : ($grnItemId ? $lockedGrn->items->firstWhere('id', $grnItemId) : $lockedGrn->items->firstWhere('product_id', $matchRecord['product_id']));

                $key = $poItemId ? "po:{$poItemId}" : ($grnItemId ? "grni:{$grnItemId}" : "prod:{$matchRecord['product_id']}");
                $reconLine = $createdReconciliationLines[$key] ?? null;

                AdvanceReceiveMatch::create([
                    'advance_goods_received_id' => $matchRecord['advance_goods_received_id'],
                    'advance_goods_received_item_id' => $matchRecord['advance_goods_received_item_id'],
                    'advance_stock_batch_id' => $matchRecord['advance_stock_batch_id'],
                    'bill_goods_received_id' => $lockedGrn->id,
                    'bill_goods_received_item_id' => $billItem?->id,
                    'bill_reconciliation_id' => $billReconciliation->id,
                    'bill_reconciliation_line_id' => $reconLine?->id,
                    'purchase_order_id' => $lockedGrn->purchase_order_id,
                    'purchase_order_item_id' => $poItemId,
                    'product_id' => $matchRecord['product_id'],
                    'matched_qty' => $matchRecord['matched_qty'],
                    'matched_unit' => $matchRecord['matched_unit'],
                    'base_qty' => $matchRecord['base_qty'],
                    'conversion_to_base' => $matchRecord['conversion_to_base'],
                    'confirmed_by' => $userId,
                    'confirmed_at' => now(),
                ]);
            }

            // 5. StockBatch Handling for Reconciled GRN
            foreach ($lockedGrn->items as $grnItem) {
                $poItemId = $grnItem->purchase_order_item_id;
                $key = $poItemId ? "po:{$poItemId}" : "grni:{$grnItem->id}";
                $matchedBaseQty = (float) ($totalMatchedByBillItem[$key] ?? $totalMatchedByBillItem["prod:{$grnItem->product_id}"] ?? 0.0);

                /** @var Product $product */
                $product = $grnItem->product;
                $itemUnit = $grnItem->received_unit ?: $product->unit;
                $conversionToBase = (float) ($product->conversionToBaseForUnit($itemUnit) ?? 1.0);
                $totalBillItemBaseQty = round((float) $grnItem->received_qty * $conversionToBase, 3);
                $unmatchedBaseQty = max(0.0, round($totalBillItemBaseQty - $matchedBaseQty, 3));
                $unmatchedItemQty = $conversionToBase > 0 ? round($unmatchedBaseQty / $conversionToBase, 3) : $unmatchedBaseQty;

                $itemWarehouseId = (int) ($items[$grnItem->id]['warehouse_id'] ?? $lockedGrn->warehouse_id ?? $fallbackWarehouseId);

                $existingBatch = $lockedGrn->stockBatches->firstWhere('goods_received_item_id', $grnItem->id)
                    ?? $lockedGrn->stockBatches->firstWhere('product_id', $grnItem->product_id);

                if ($unmatchedItemQty <= 0.0001) {
                    // Fully covered by Advance: zero physical new stock, preserve row with 0 kg
                    if ($existingBatch) {
                        $existingBatch->update([
                            'warehouse_id' => $itemWarehouseId,
                            'total_kg' => 0.0,
                            'warehouse_receive_pending' => false,
                            'warehouse_confirmed_at' => now(),
                            'warehouse_confirmed_by' => $userId,
                            'notes' => 'Reconciled 100% from Advance (Stock effect: 0 kg)',
                        ]);
                    }
                } else {
                    // Partial match: only unmatched remainder creates/confirms stock
                    if ($existingBatch) {
                        $existingBatch->update([
                            'warehouse_id' => $itemWarehouseId,
                            'total_kg' => $unmatchedItemQty,
                            'warehouse_receive_pending' => false,
                            'warehouse_confirmed_at' => now(),
                            'warehouse_confirmed_by' => $userId,
                        ]);

                        if ($existingBatch->grading_mode === 'fixed_purchase_grade') {
                            StockMovement::query()->firstOrCreate(
                                [
                                    'batch_id' => $existingBatch->id,
                                    'type' => StockMovementType::In->value,
                                    'grade' => $existingBatch->purchase_grade,
                                ],
                                [
                                    'product_id' => $existingBatch->product_id,
                                    'warehouse_id' => $itemWarehouseId,
                                    'created_by' => $userId,
                                    'quantity' => $unmatchedItemQty,
                                    'cost_per_unit' => $existingBatch->cost_per_kg,
                                    'notes' => "Fixed Grade {$existingBatch->purchase_grade} receipt remainder from {$lockedGrn->grn_number}",
                                ],
                            );

                            $existingBatch->update([
                                'status' => BatchStatus::Sorted,
                                'sorted_at' => now(),
                            ]);
                        }
                    } elseif (! $autoAdvanceClear) {
                        // Create remainder batch if didn't exist
                        $costPerKg = (float) ($grnItem->cost_per_unit ?? 0);
                        $this->stockBatchRepository->create([
                            'product_id' => $grnItem->product_id,
                            'warehouse_id' => $itemWarehouseId,
                            'goods_received_id' => $lockedGrn->id,
                            'goods_received_item_id' => $grnItem->id,
                            'purchase_grade' => $grnItem->grade ?? $lockedGrn->purchase_grade ?? 'A',
                            'grading_mode' => ($grnItem->grade ?? $lockedGrn->purchase_grade ?? 'A') === 'B' ? 'fixed_purchase_grade' : 'sort_required',
                            'created_by' => $userId,
                            'reference' => $this->stockBatchRepository->generateReference(),
                            'received_at' => $lockedGrn->received_at,
                            'total_kg' => $unmatchedItemQty,
                            'cost_per_kg' => $costPerKg,
                            'status' => BatchStatus::Pending,
                            'warehouse_receive_pending' => false,
                            'warehouse_confirmed_at' => now(),
                            'warehouse_confirmed_by' => $userId,
                            'notes' => "Reconciled GRN: {$lockedGrn->grn_number} (Remainder: {$unmatchedItemQty} {$itemUnit})",
                        ]);
                    }
                }
            }

            // 6. Update Advance GRN bill_status if fully consumed
            foreach ($advanceGrns as $advanceGrn) {
                $totalAdvanceReceivedBase = (float) $advanceGrn->items->sum(function (GoodsReceivedItem $it): float {
                    /** @var Product $p */
                    $p = $it->product ?? Product::find($it->product_id);
                    $conv = (float) ($p?->conversionToBaseForUnit($it->received_unit) ?? 1.0);

                    return (float) $it->received_qty * $conv;
                });

                $totalConsumedBase = (float) AdvanceReceiveMatch::query()
                    ->where('advance_goods_received_id', $advanceGrn->id)
                    ->sum('base_qty');

                if ($totalConsumedBase >= $totalAdvanceReceivedBase - 0.001) {
                    $advanceGrn->update(['bill_status' => 'bill_available']);
                }
            }

            // 7. Approve GRN and update PO
            $grnBillStatus = ($autoAdvanceClear && $totalNewReceiveBaseQty > 0.0001) ? 'bill_pending' : 'bill_available';

            $lockedGrn->update([
                'status' => 'approved',
                'bill_status' => $grnBillStatus,
                'approved_by' => $userId,
                'approved_at' => now(),
                'updated_by' => $userId,
                'matched_by' => $userId,
                'matched_at' => now(),
            ]);

            if ($lockedGrn->purchase_order_id) {
                /** @var PurchaseOrder|null $po */
                $po = PurchaseOrder::with('items.product')->find($lockedGrn->purchase_order_id);
                if ($po) {
                    $poOrderedBase = (float) $po->items->sum(function (PurchaseOrderItem $it): float {
                        /** @var Product|null $p */
                        $p = $it->product ?? Product::find($it->product_id);
                        $unit = $it->purchase_unit ?: $it->unit ?: $p?->unit;
                        $conv = (float) ($p?->conversionToBaseForUnit($unit) ?? 1.0);

                        return (float) $it->quantity * $conv;
                    });

                    $poMatchedBase = (float) AdvanceReceiveMatch::query()
                        ->where('purchase_order_id', $po->id)
                        ->sum('base_qty');

                    $poFulfilledBase = round($poMatchedBase + $totalNewReceiveBaseQty, 3);
                    $poRemainingBase = max(0.0, round($poOrderedBase - $poFulfilledBase, 3));

                    if ($poRemainingBase > 0.001) {
                        $po->update(['status' => POStatus::PartiallyReceived]);
                    } else {
                        $po->update(['status' => POStatus::Received]);
                    }
                }
            }

            activity()
                ->performedOn($lockedGrn)
                ->causedBy(User::query()->find($userId))
                ->withProperties([
                    'source' => 'warehouse_pending_advance_reconciliation',
                    'matches_count' => count($validatedMatches),
                ])
                ->log('goods_received.reconciled_with_advance');

            return $lockedGrn->fresh([
                'items.product',
                'items.purchaseOrderItem',
                'purchaseOrder',
                'advanceMatchesAsBill.advanceGoodsReceived',
                'advanceMatchesAsBill.advanceStockBatch',
            ]);
        });
    }

    /**
     * Paginated list of all pending purchase orders requiring warehouse reconciliation.
     * Includes 0% match, partial match, and 100% match records.
     * Bounded query with pre-fetched loadout cohorts and zero N+1 overhead.
     *
     * @param  array<string, mixed>  $filters
     */
    /**
     * Pre-load open advance candidates in bulk for a list of products in memory.
     * Avoids per-product N+1 DB queries during paginated candidate matching.
     *
     * @param  array<int>|null  $productIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function preloadOpenAdvanceCandidates(?int $warehouseId = null, ?array $productIds = null): array
    {
        $advanceGrnsQuery = GoodsReceived::query()
            ->where(function (Builder $typeQuery): void {
                $typeQuery->where('goods_received.receipt_type', 'warehouse_advance')
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('goods_received.receipt_type')
                            ->whereNull('goods_received.purchase_order_id');
                    });
            })
            ->where('goods_received.status', 'approved')
            ->where('goods_received.bill_status', 'bill_pending')
            ->whereDoesntHave('purchaseInvoices');

        if ($warehouseId !== null) {
            app(WarehouseReceiptReadScope::class)->receipts($advanceGrnsQuery, [$warehouseId]);
        }

        $advanceGrns = $advanceGrnsQuery
            ->with([
                'items' => fn ($q) => $q->when($productIds !== null, fn ($pq) => $pq->whereIn('product_id', $productIds))->with('product.orderUnits'),
                'stockBatches' => fn ($q) => $q->when($productIds !== null, fn ($pq) => $pq->whereIn('product_id', $productIds))->where('warehouse_receive_pending', false),
            ])
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $advanceIds = $advanceGrns->pluck('id')->all();
        $allMatches = $advanceIds !== []
            ? AdvanceReceiveMatch::query()->whereIn('advance_goods_received_id', $advanceIds)->get()
            : collect();

        $matchesByAdvanceId = $allMatches->groupBy('advance_goods_received_id');
        $candidatesByProduct = [];

        foreach ($advanceGrns as $grn) {
            $grnMatches = $matchesByAdvanceId->get($grn->id, collect());

            $legacyUnassignedMatchedBase = [];
            foreach ($grnMatches->whereNull('advance_goods_received_item_id') as $m) {
                $legacyUnassignedMatchedBase[$m->product_id] = ($legacyUnassignedMatchedBase[$m->product_id] ?? 0.0) + (float) $m->base_qty;
            }

            foreach ($grn->items as $grnItem) {
                $pId = (int) $grnItem->product_id;
                if ($productIds !== null && ! in_array($pId, $productIds, true)) {
                    continue;
                }

                /** @var Product|null $product */
                $product = $grnItem->relationLoaded('product') ? $grnItem->product : Product::find($grnItem->product_id);
                $conv = (float) ($product?->conversionToBaseForUnit($grnItem->received_unit) ?? 1.0);
                $originalQty = (float) $grnItem->received_qty;
                $originalBaseQty = $originalQty * $conv;

                $explicitItemMatchedBase = (float) $grnMatches
                    ->where('advance_goods_received_item_id', $grnItem->id)
                    ->sum('base_qty');

                $itemRemainingBase = max(0.0, $originalBaseQty - $explicitItemMatchedBase);

                $legacyPool = (float) ($legacyUnassignedMatchedBase[$pId] ?? 0.0);
                $legacyApplied = 0.0;
                if ($legacyPool > 0.0001 && $itemRemainingBase > 0.0001) {
                    $legacyApplied = min($itemRemainingBase, $legacyPool);
                    $legacyUnassignedMatchedBase[$pId] = $legacyPool - $legacyApplied;
                    $itemRemainingBase = max(0.0, $itemRemainingBase - $legacyApplied);
                }

                $totalMatchedBaseForItem = $explicitItemMatchedBase + $legacyApplied;
                $availableBaseQty = round($itemRemainingBase, 3);
                $availableItemQty = $conv > 0 ? round($availableBaseQty / $conv, 3) : $availableBaseQty;

                if ($availableBaseQty <= 0.0001) {
                    continue;
                }

                $batch = $grn->stockBatches->firstWhere('goods_received_item_id', $grnItem->id)
                    ?? $grn->stockBatches->first();

                $candidatesByProduct[$pId][] = [
                    'advance_goods_received_id' => $grn->id,
                    'advance_goods_received_item_id' => $grnItem->id,
                    'advance_stock_batch_id' => $batch?->id,
                    'grn_number' => $grn->grn_number,
                    'received_at' => $grn->received_at?->toDateString() ?? $grn->created_at?->toDateString(),
                    'unit' => $grnItem->received_unit ?? 'kg',
                    'original_qty' => $originalQty,
                    'original_base_qty' => round($originalBaseQty, 3),
                    'already_matched_qty' => $conv > 0 ? round($totalMatchedBaseForItem / $conv, 3) : round($totalMatchedBaseForItem, 3),
                    'already_matched_base_qty' => round($totalMatchedBaseForItem, 3),
                    'available_qty' => $availableItemQty,
                    'available_base_qty' => $availableBaseQty,
                    'status' => $totalMatchedBaseForItem > 0.0001 ? 'partial' : 'open',
                ];
            }
        }

        return $candidatesByProduct;
    }

    public function paginateMatchCandidates(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $date = $filters['date'] ?? null;
        $dateBefore = $filters['date_before'] ?? null;
        $period = $filters['period'] ?? null;
        $warehouseId = isset($filters['warehouse_id']) && $filters['warehouse_id'] !== null ? (int) $filters['warehouse_id'] : null;

        $query = PurchaseOrder::query()
            ->select(['id', 'supplier_id', 'destination_shop_id', 'po_number', 'status', 'order_date', 'created_by', 'created_at', 'updated_at'])
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->where(function (Builder $pending): void {
                $pending->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'))
                    ->orWhere(function ($withoutReceipt): void {
                        $withoutReceipt->whereDoesntHave('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'))
                            ->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received']);
                    });
            })
            ->when($date, fn ($q) => $q->whereDate('order_date', $date))
            ->when($dateBefore, fn ($q) => $q->whereDate('order_date', '<', $dateBefore))
            ->when($period === 'today' && empty($date), fn ($q) => $q->whereDate('order_date', now()->toDateString()))
            ->when($period === 'older' && empty($date) && empty($dateBefore), fn ($q) => $q->whereDate('order_date', '<', now()->toDateString()))
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search): void {
                            $supplierQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->with([
                'supplier:id,name',
                'destinationShop:id,name',
                'items.product.orderUnits',
                'goodsReceiveds' => fn ($receipts) => app(WarehouseReceiptStateResolver::class)->withFacts($receipts->select('goods_received.*'))->withCount('purchaseInvoices'),
            ])
            ->withCount('items')
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        app(WarehouseReceiptReadScope::class)->orders($query, $filters['authorized_warehouse_ids'] ?? ($warehouseId ? [$warehouseId] : null));

        $paginator = $query->paginate($perPage);

        // Pre-fetch loadout cohort totals for current page items (single bulk query)
        $orders = $paginator->getCollection();
        $productIds = $orders->flatMap(fn (PurchaseOrder $o) => $o->items->pluck('product_id'))->unique()->values()->all();
        $dates = $orders->pluck('order_date')->filter()->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)->unique()->values()->all();

        $loadoutsByCohort = [];
        if (! empty($productIds) && ! empty($dates)) {
            $loadouts = DB::table('shop_order_items')
                ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.shop_order_id')
                ->join('products', 'products.id', '=', 'shop_order_items.product_id')
                ->whereIn('shop_order_items.product_id', $productIds)
                ->whereIn(DB::raw('DATE(shop_orders.business_date)'), $dates)
                ->whereNull('shop_order_items.deleted_at')
                ->when($warehouseId !== null, fn ($q) => $q->where('products.default_warehouse_id', $warehouseId))
                ->where(function ($q): void {
                    $q->where('shop_order_items.sorting_status', 'loaded')
                        ->orWhere('shop_order_items.loaded_qty', '>', 0)
                        ->orWhere('shop_order_items.actual_weight', '>', 0);
                })
                ->select([
                    'shop_order_items.product_id',
                    DB::raw('DATE(shop_orders.business_date) as order_date'),
                    DB::raw('SUM(COALESCE(shop_order_items.actual_weight, shop_order_items.loaded_qty, 0)) as total_loaded_qty'),
                ])
                ->groupBy('shop_order_items.product_id', DB::raw('DATE(shop_orders.business_date)'))
                ->get();

            foreach ($loadouts as $row) {
                $loadoutsByCohort["{$row->product_id}_{$row->order_date}"] = (float) $row->total_loaded_qty;
            }
        }

        $candidatesByProduct = ! empty($productIds)
            ? $this->preloadOpenAdvanceCandidates($warehouseId, $productIds)
            : [];

        // Attach suggestion and reconciliation context
        $paginator->getCollection()->transform(function (PurchaseOrder $order) use ($warehouseId, $loadoutsByCohort, $candidatesByProduct): array {
            $suggestions = $this->getSuggestionsForOrder($order, $warehouseId, $loadoutsByCohort, $candidatesByProduct);
            $receiptState = app(WarehouseReceiptStateResolver::class)->forOrder($order);

            return [
                ...$receiptState,
                'id' => $order->id,
                'purchase_order_id' => $order->id,
                'po_number' => $order->po_number,
                'order_date' => $order->order_date?->toDateString(),
                'business_date' => $order->order_date?->toDateString(),
                'supplier_id' => $order->supplier_id,
                'supplier_name' => $order->supplier?->name ?? 'Supplier',
                'destination_shop_name' => $order->destinationShop?->name ?? 'Central Warehouse',
                'warehouse_id' => $warehouseId ?? $order->destination_shop_id ?? $order->warehouse_id,
                'item_count' => (int) ($order->items_count ?? $order->items->count()),
                'total_bill_base_qty' => $suggestions['total_bill_base_qty'],
                'total_matched_base_qty' => $suggestions['total_matched_base_qty'],
                'overall_coverage_percentage' => $suggestions['overall_coverage_percentage'],
                'is_overall_percentage_meaningful' => $suggestions['is_overall_percentage_meaningful'],
                'has_advance_match' => $suggestions['has_advance_match'],
                'reconciliation_status' => $suggestions['reconciliation_status'],
                'exact_matches_count' => $suggestions['exact_matches_count'],
                'partial_matches_count' => $suggestions['partial_matches_count'],
                'unmatched_count' => $suggestions['unmatched_count'],
                'goods_received_numbers' => $order->goodsReceiveds->pluck('grn_number')->filter()->values()->all(),
                'match_summary_items' => $suggestions['items'],
            ];
        });

        return $paginator;
    }

    public function paginateUnitDifferences(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $warehouseId = isset($filters['warehouse_id']) && $filters['warehouse_id'] !== null ? (int) $filters['warehouse_id'] : null;

        $query = PurchaseOrder::query()
            ->select(['id', 'supplier_id', 'destination_shop_id', 'po_number', 'status', 'order_date', 'created_by', 'created_at', 'updated_at'])
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->where(function (Builder $pending): void {
                $pending->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'))
                    ->orWhere(function ($withoutReceipt): void {
                        $withoutReceipt->whereDoesntHave('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'))
                            ->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received']);
                    });
            })
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search): void {
                            $supplierQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.product', function (Builder $pq) use ($search): void {
                            $pq->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%");
                        });
                });
            })
            ->with([
                'supplier:id,name',
                'destinationShop:id,name',
                'items.product.orderUnits',
                'goodsReceiveds' => fn ($receipts) => app(WarehouseReceiptStateResolver::class)->withFacts($receipts->select('goods_received.*')),
            ])
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        app(WarehouseReceiptReadScope::class)->orders($query, $filters['authorized_warehouse_ids'] ?? ($warehouseId ? [$warehouseId] : null));

        $allOrders = $query->get();
        $productIds = $allOrders->flatMap(fn (PurchaseOrder $o) => $o->items->pluck('product_id'))->unique()->values()->all();

        $candidatesByProduct = ! empty($productIds)
            ? $this->preloadOpenAdvanceCandidates($warehouseId, $productIds)
            : [];

        $diffRows = collect();

        foreach ($allOrders as $order) {
            $orderWhId = $warehouseId ?? $order->destination_shop_id ?? $order->warehouse_id;

            $pendingGrns = collect();
            foreach ($order->goodsReceiveds as $grn) {
                $facts = app(WarehouseReceiptStateResolver::class)->forReceipt($grn);
                if (($facts['receipt_status'] ?? '') !== 'received') {
                    $pendingGrns->push($grn);
                }
            }

            $targetItems = [];
            if ($pendingGrns->isNotEmpty()) {
                foreach ($pendingGrns->sortBy('id') as $pGrn) {
                    foreach ($pGrn->items->sortBy('id') as $pItem) {
                        $targetItems[] = [
                            'source_type' => 'goods_received_item',
                            'goods_received_id' => $pGrn->id,
                            'goods_received_item_id' => $pItem->id,
                            'purchase_order_item_id' => $pItem->purchase_order_item_id,
                            'product' => $pItem->product,
                            'qty' => (float) $pItem->received_qty,
                            'unit' => $pItem->received_unit ?: $pItem->product?->unit,
                        ];
                    }
                }
            } else {
                foreach ($order->items->sortBy('id') as $poItem) {
                    $targetItems[] = [
                        'source_type' => 'purchase_order_item',
                        'goods_received_id' => null,
                        'goods_received_item_id' => null,
                        'purchase_order_item_id' => $poItem->id,
                        'product' => $poItem->product,
                        'qty' => (float) $poItem->quantity,
                        'unit' => $poItem->purchase_unit ?: $poItem->unit ?: $poItem->product?->unit,
                    ];
                }
            }

            foreach ($targetItems as $tItem) {
                $product = $tItem['product'];
                if (! $product) {
                    continue;
                }

                $billUnit = $tItem['unit'] ?: $product->unit;
                $normBillUnit = ProductUnit::normalizeUnit($billUnit);
                $normBaseUnit = ProductUnit::normalizeUnit($product->unit);

                $alreadyMatchedQty = (float) AdvanceReceiveMatch::query()
                    ->where('purchase_order_id', $order->id)
                    ->get()
                    ->filter(function ($m) use ($tItem): bool {
                        if ($tItem['goods_received_item_id'] !== null) {
                            return $m->bill_goods_received_id === $tItem['goods_received_id']
                                && $m->bill_goods_received_item_id === $tItem['goods_received_item_id'];
                        }

                        return $m->purchase_order_item_id === $tItem['purchase_order_item_id'];
                    })
                    ->sum('matched_qty');

                $remainingBillQty = max(0.0, round((float) $tItem['qty'] - $alreadyMatchedQty, 3));
                if ($remainingBillQty <= 0.0001) {
                    continue;
                }

                $candidates = $candidatesByProduct[$product->id] ?? [];
                if (empty($candidates)) {
                    continue;
                }

                $strictConv = app(AdvanceAvailableBalanceCalculator::class)->resolveStrictUnitConversion($product, $billUnit);
                $hasUnitMismatch = false;
                $candidateUnits = [];
                $totalAdvAvailQty = 0.0;

                foreach ($candidates as $cand) {
                    $totalAdvAvailQty += (float) $cand['available_qty'];
                    $candUnit = (string) ($cand['unit'] ?? $product->unit);
                    $candidateUnits[] = $candUnit;
                    $normCandUnit = ProductUnit::normalizeUnit($candUnit);

                    if ($normBillUnit !== $normCandUnit && $strictConv === null) {
                        $hasUnitMismatch = true;
                    }
                }

                if ($hasUnitMismatch || $strictConv === null) {
                    $diffRows->push([
                        'purchase_order_id' => $order->id,
                        'goods_received_id' => $tItem['goods_received_id'],
                        'goods_received_item_id' => $tItem['goods_received_item_id'],
                        'purchase_order_item_id' => $tItem['purchase_order_item_id'],
                        'po_number' => $order->po_number,
                        'order_date' => $order->order_date?->toDateString(),
                        'supplier_name' => $order->supplier?->name ?? 'Vendor',
                        'destination_shop_name' => $order->destinationShop?->name ?? 'Warehouse',
                        'warehouse_id' => $orderWhId,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'product_base_unit' => $product->unit,
                        'bill_qty' => (float) $tItem['qty'],
                        'bill_unit' => $billUnit,
                        'available_advance_qty' => round($totalAdvAvailQty, 3),
                        'advance_units' => array_values(array_unique($candidateUnits)),
                        'already_matched_qty' => round($alreadyMatchedQty, 3),
                        'remaining_bill_qty' => $remainingBillQty,
                        'reason' => "Unit mismatch: Bill in {$billUnit}, Advance in ".implode('/', array_unique($candidateUnits)).' (No conversion configured)',
                        'candidates' => $candidates,
                    ]);
                }
            }
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $sliced = $diffRows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $sliced,
            $diffRows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()]
        );
    }

    public function countUnitDifferences(array $filters = []): int
    {
        return $this->paginateUnitDifferences($filters, 1)->total();
    }

    /**
     * Safely resolve a unit difference line on a purchase order.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function resolveUnitDifference(array $payload, int $userId): array
    {
        return DB::transaction(function () use ($payload, $userId): array {
            $purchaseOrderId = (int) $payload['purchase_order_id'];
            $purchaseOrderItemId = (int) $payload['purchase_order_item_id'];
            $advanceGoodsReceivedId = (int) $payload['advance_goods_received_id'];
            $advanceGoodsReceivedItemId = isset($payload['advance_goods_received_item_id']) && $payload['advance_goods_received_item_id'] !== null
                ? (int) $payload['advance_goods_received_item_id']
                : null;
            $matchedQty = (float) $payload['matched_qty'];
            $conversionFactor = isset($payload['conversion_factor']) && (float) $payload['conversion_factor'] > 0
                ? (float) $payload['conversion_factor']
                : 1.0;
            $notes = (string) ($payload['notes'] ?? 'Manual unit difference resolution');
            $clientSubmissionId = (string) ($payload['client_submission_id'] ?? (string) Str::uuid());

            if (! empty($clientSubmissionId)) {
                $existing = AdvanceReceiveMatch::query()
                    ->where('client_submission_id', $clientSubmissionId)
                    ->first();
                if ($existing !== null) {
                    return [
                        'status' => 'success',
                        'message' => 'Unit difference already resolved (idempotent).',
                        'match_id' => $existing->id,
                        'remaining_bill_qty' => 0.0,
                        'po_fully_cleared' => false,
                        'advance_fully_cleared' => false,
                    ];
                }
            }

            // 1. Lock and validate PO
            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()
                ->whereKey($purchaseOrderId)
                ->with(['items.product', 'goodsReceiveds'])
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($po->status, ['draft', 'cancelled', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => "Purchase Order #{$po->po_number} is in status {$po->status} and cannot be reconciled.",
                ]);
            }

            /** @var PurchaseOrderItem $poItem */
            $poItem = $po->items->firstWhere('id', $purchaseOrderItemId);
            if (! $poItem) {
                throw ValidationException::withMessages([
                    'purchase_order_item_id' => "Purchase Order Item #{$purchaseOrderItemId} does not belong to PO #{$po->po_number}.",
                ]);
            }

            // 1b. Find existing pending bill GRN early so we can use it for remaining-qty checks.
            //     The authoritative bill quantity is the bill GRN item's received_qty, NOT the
            //     PO ordered quantity.  Fall back to PO qty only when no bill GRN exists yet
            //     (create-bill-grn path) so the over-clear guard still works in that case.
            $existingBillGrn = $po->goodsReceiveds()
                ->where(function (Builder $q): void {
                    $q->where('receipt_type', 'normal_purchase')
                        ->orWhereNull('receipt_type');
                })
                ->first();

            $existingBillItem = null;
            if ($existingBillGrn) {
                $existingBillItem = $existingBillGrn->items()->firstWhere('purchase_order_item_id', $poItem->id)
                    ?? $existingBillGrn->items()->firstWhere('product_id', $poItem->product_id);
            }

            // Authoritative bill qty: bill GRN item received_qty when available, else PO ordered qty.
            $authoritativeBillQty = $existingBillItem
                ? (float) $existingBillItem->received_qty
                : (float) $poItem->quantity;

            // Check remaining unbilled qty against the authoritative bill quantity
            $alreadyMatchedBaseQtyForItem = (float) AdvanceReceiveMatch::query()
                ->where('purchase_order_id', $po->id)
                ->where('purchase_order_item_id', $poItem->id)
                ->sum('base_qty');

            $billItemConv = app(AdvanceAvailableBalanceCalculator::class)->resolveStrictUnitConversion($poItem->product, $poItem->purchase_unit ?: $poItem->product?->unit) ?? 1.0;
            $effectiveConv = $conversionFactor > 0 ? $conversionFactor : $billItemConv;
            $authoritativeBillBaseQty = round($authoritativeBillQty * $effectiveConv, 3);
            $remainingBillBaseQty = max(0.0, round($authoritativeBillBaseQty - $alreadyMatchedBaseQtyForItem, 3));

            // matched_qty is in the bill unit; convert to base for the guard
            $requestedMatchBaseQty = round($matchedQty * $conversionFactor, 3);
            if ($requestedMatchBaseQty > $remainingBillBaseQty + 0.0001) {
                throw ValidationException::withMessages([
                    'matched_qty' => "Requested match ({$matchedQty} = {$requestedMatchBaseQty} base) exceeds remaining unbilled quantity ({$remainingBillBaseQty} base).",
                ]);
            }

            $remainingBillQty = $effectiveConv > 0
                ? round($remainingBillBaseQty / $effectiveConv, 3)
                : $remainingBillBaseQty;

            // 2. Lock and validate Advance GRN
            /** @var GoodsReceived $advGrn */
            $advGrn = GoodsReceived::query()
                ->whereKey($advanceGoodsReceivedId)
                ->with(['items', 'stockBatches'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($advGrn->status !== 'approved') {
                throw ValidationException::withMessages([
                    'advance_goods_received_id' => "Advance Receive {$advGrn->grn_number} is not approved.",
                ]);
            }

            $advItem = $advanceGoodsReceivedItemId
                ? $advGrn->items->firstWhere('id', $advanceGoodsReceivedItemId)
                : $advGrn->items->firstWhere('product_id', $poItem->product_id);

            if (! $advItem || (int) $advItem->product_id !== (int) $poItem->product_id) {
                throw ValidationException::withMessages([
                    'advance_goods_received_id' => "Advance Receive {$advGrn->grn_number} does not contain product ID #{$poItem->product_id}.",
                ]);
            }

            $product = $poItem->product;
            $matchedBaseQty = round($matchedQty * $conversionFactor, 3);

            // Check advance item available balance
            $calc = app(AdvanceAvailableBalanceCalculator::class);
            $advBalances = $calc->calculateItemAvailableBase($advGrn);
            $availableBase = (float) ($advBalances[$advItem->id] ?? 0.0);

            if ($matchedBaseQty > $availableBase + 0.0001) {
                throw ValidationException::withMessages([
                    'matched_qty' => "Requested match ({$matchedQty} = {$matchedBaseQty} base) exceeds available advance balance ({$availableBase} base) for {$advGrn->grn_number}.",
                ]);
            }

            // 3. Find or Create Bill GoodsReceived for this PO.
            //    Re-use the early-loaded bill GRN/item from the remaining-qty check above.
            $billGrn = $existingBillGrn;
            $billItem = $existingBillItem;

            if (! $billGrn) {
                $billGrn = GoodsReceived::create([
                    'public_uuid' => (string) Str::uuid(),
                    'warehouse_id' => $po->destination_shop_id ?? $advGrn->warehouse_id,
                    'destination_shop_id' => $po->destination_shop_id,
                    'purchase_order_id' => $po->id,
                    'grn_number' => 'GRN-PO-'.str_replace('PO-', '', $po->po_number),
                    'status' => 'approved',
                    'bill_status' => 'bill_pending',
                    'receipt_type' => 'normal_purchase',
                    'received_by' => $userId,
                    'received_at' => now(),
                    'approved_at' => now(),
                    'approved_by' => $userId,
                    'notes' => $notes,
                ]);

                // When creating a new bill GRN in the PO-only path, the bill qty is the PO
                // ordered quantity (this is the "create-bill-grn" path where PO qty is correct).
                $billItem = GoodsReceivedItem::create([
                    'goods_received_id' => $billGrn->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'received_qty' => (float) $poItem->quantity,
                    'received_unit' => $poItem->purchase_unit ?: $product->unit,
                    'variance' => 0.0,
                ]);
            }

            $batch = $advGrn->stockBatches->firstWhere('goods_received_item_id', $advItem->id)
                ?? $advGrn->stockBatches->first();

            // 4. Create AdvanceReceiveMatch
            $matchRecord = AdvanceReceiveMatch::create([
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'advance_stock_batch_id' => $batch?->id,
                'bill_goods_received_id' => $billGrn->id,
                'bill_goods_received_item_id' => $billItem?->id,
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $poItem->product_id,
                'matched_qty' => $matchedQty,
                'matched_unit' => $poItem->purchase_unit ?: $product->unit,
                'base_qty' => $matchedBaseQty,
                'conversion_to_base' => $conversionFactor,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
                'client_submission_id' => $clientSubmissionId,
                'notes' => $notes,
            ]);

            // 5. Update Advance GRN bill_status if fully drained
            $postBalances = $calc->calculateItemAvailableBase($advGrn->fresh(['items']));
            if (array_sum($postBalances) <= 0.0001) {
                $advGrn->update([
                    'bill_status' => 'bill_available',
                    'matched_at' => now(),
                ]);
            }

            // 6. Check if PO is fully reconciled.
            //    Compare matched base qty per bill GRN item against the bill item's received_qty
            //    (not the PO ordered quantity) to determine completion.
            $allPoItemsCompleted = true;
            $freshBillGrn = $billGrn->fresh(['items.product.orderUnits']);
            foreach ($po->items as $item) {
                // Find the corresponding bill GRN item for this PO item
                $correspondingBillItem = $freshBillGrn?->items
                    ? ($freshBillGrn->items->firstWhere('purchase_order_item_id', $item->id)
                        ?? $freshBillGrn->items->firstWhere('product_id', $item->product_id))
                    : null;

                // Authoritative qty: bill GRN item qty when available, else PO item qty
                $authQty = $correspondingBillItem
                    ? (float) $correspondingBillItem->received_qty
                    : (float) $item->quantity;

                $itemConv = app(AdvanceAvailableBalanceCalculator::class)->resolveStrictUnitConversion($item->product, $item->purchase_unit ?: $item->product?->unit) ?? 1.0;
                $effItemConv = ($item->id === $poItem->id && $conversionFactor > 0) ? $conversionFactor : $itemConv;
                $authBaseQty = round($authQty * $effItemConv, 3);

                $itemMatchedBase = (float) AdvanceReceiveMatch::query()
                    ->where('purchase_order_id', $po->id)
                    ->where('purchase_order_item_id', $item->id)
                    ->sum('base_qty');

                if ($itemMatchedBase < $authBaseQty - 0.0001) {
                    $allPoItemsCompleted = false;
                    break;
                }
            }

            if ($allPoItemsCompleted) {
                $po->update(['status' => POStatus::Received]);
                $billGrn->update(['bill_status' => 'bill_available']);
            }

            activity()
                ->performedOn($po)
                ->causedBy(User::find($userId))
                ->withProperties([
                    'action' => 'unit_difference_resolved',
                    'purchase_order_id' => $po->id,
                    'purchase_order_item_id' => $poItem->id,
                    'advance_goods_received_id' => $advGrn->id,
                    'matched_qty' => $matchedQty,
                    'matched_unit' => $poItem->purchase_unit ?: $product->unit,
                    'conversion_factor' => $conversionFactor,
                    'matched_base_qty' => $matchedBaseQty,
                ])
                ->log('purchasing.unit_difference_resolved');

            $newRemainingBillQty = $effectiveConv > 0
                ? max(0.0, round(($remainingBillBaseQty - $requestedMatchBaseQty) / $effectiveConv, 3))
                : max(0.0, round($remainingBillBaseQty - $requestedMatchBaseQty, 3));

            return [
                'status' => 'success',
                'message' => 'Unit difference resolved successfully.',
                'match_id' => $matchRecord->id,
                'remaining_bill_qty' => $newRemainingBillQty,
                'po_fully_cleared' => $allPoItemsCompleted,
                'advance_fully_cleared' => array_sum($postBalances) <= 0.0001,
            ];
        });
    }

    private function calculateWeightedAvgPrice(int $productId, string $date, GoodsReceivedItem $currentItem): float
    {
        $poItem = $currentItem->purchaseOrderItem;
        if ($poItem && (float) $poItem->unit_price > 0) {
            return (float) $poItem->unit_price;
        }

        $latestPoItem = PurchaseOrderItem::where('product_id', $productId)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereDate('order_date', '<=', $date)->where('status', '!=', 'cancelled'))
            ->latest('id')
            ->first();

        if ($latestPoItem && (float) $latestPoItem->unit_price > 0) {
            return (float) $latestPoItem->unit_price;
        }

        return 0.00;
    }
}
