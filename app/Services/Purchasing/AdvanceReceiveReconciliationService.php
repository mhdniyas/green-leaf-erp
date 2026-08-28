<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\User;
use App\Repositories\Inventory\StockBatchRepository;
use App\Repositories\Purchasing\GoodsReceivedRepository;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
     * @return array<string, mixed>
     */
    public function getSuggestionsForOrder(PurchaseOrder $order, ?int $warehouseId = null): array
    {
        $order->loadMissing(['items.product.orderUnits', 'supplier']);
        $targetWarehouseId = $warehouseId ?? $order->destination_shop_id ?? $order->warehouse_id;

        $productItems = [];
        $totalBillBaseQty = 0.0;
        $totalMatchedBaseQty = 0.0;
        $allUnitsCompatible = true;
        $firstUnit = null;

        foreach ($order->items as $item) {
            /** @var Product $product */
            $product = $item->product;
            $itemUnit = $item->unit ?: $product->unit;
            $conversionToBase = (float) ($product->conversionToBaseForUnit($itemUnit) ?? 1.0);
            $billBaseQty = round((float) $item->quantity * $conversionToBase, 3);
            $totalBillBaseQty += $billBaseQty;

            if ($firstUnit === null) {
                $firstUnit = strtolower(trim((string) $itemUnit));
            } elseif ($firstUnit !== strtolower(trim((string) $itemUnit))) {
                $allUnitsCompatible = false;
            }

            // Find all confirmed open/partial Advance GRN items for this product
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
                    'proposed_match_base_qty' => $matchBase,
                    'coverage_percentage' => $billBaseQty > 0 ? round(($matchBase / $billBaseQty) * 100, 1) : 0.0,
                ];

                $lineMatchedBase += $matchBase;
                $remainingNeededBase -= $matchBase;
            }

            $totalMatchedBaseQty += $lineMatchedBase;
            $lineMatchedItemQty = $conversionToBase > 0 ? round($lineMatchedBase / $conversionToBase, 3) : $lineMatchedBase;
            $newReceiveItemQty = max(0.0, round((float) $item->quantity - $lineMatchedItemQty, 3));
            $lineCoveragePct = $billBaseQty > 0 ? round(($lineMatchedBase / $billBaseQty) * 100, 1) : 0.0;

            $productItems[] = [
                'purchase_order_item_id' => $item->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'ordered_qty' => (float) $item->quantity,
                'unit' => $itemUnit,
                'base_qty' => $billBaseQty,
                'total_advance_available_qty' => round(array_sum(array_column($candidates, 'available_qty')), 3),
                'total_proposed_match_qty' => $lineMatchedItemQty,
                'new_receive_qty' => $newReceiveItemQty,
                'coverage_percentage' => $lineCoveragePct,
                'has_advance_available' => ! empty($suggestedMatches),
                'suggested_matches' => $suggestedMatches,
                'all_candidates' => $candidates,
            ];
        }

        $overallCoveragePct = $totalBillBaseQty > 0
            ? round(($totalMatchedBaseQty / $totalBillBaseQty) * 100, 1)
            : 0.0;

        return [
            'purchase_order_id' => $order->id,
            'po_number' => $order->order_number,
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $order->supplier?->name ?? 'Supplier',
            'order_date' => $order->order_date?->toDateString(),
            'total_bill_base_qty' => round($totalBillBaseQty, 3),
            'total_matched_base_qty' => round($totalMatchedBaseQty, 3),
            'overall_coverage_percentage' => $overallCoveragePct,
            'is_overall_percentage_meaningful' => $allUnitsCompatible,
            'items' => $productItems,
        ];
    }

    /**
     * Fetch all open/partial confirmed Advance candidates for a product, sorted FIFO by received_at.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOpenAdvanceCandidatesForProduct(int $productId, ?int $warehouseId = null): array
    {
        $advanceGrns = GoodsReceived::query()
            ->whereNull('purchase_order_id')
            ->where('status', 'approved')
            ->whereHas('stockBatches', fn (Builder $q) => $q->where('warehouse_receive_pending', false)->where('status', '!=', BatchStatus::Closed->value))
            ->when($warehouseId !== null, fn (Builder $q) => $q->where(fn ($wq) => $wq->where('warehouse_id', $warehouseId)->orWhere('destination_shop_id', $warehouseId)))
            ->whereHas('items', fn (Builder $q) => $q->where('product_id', $productId))
            ->with([
                'items' => fn ($q) => $q->where('product_id', $productId),
                'stockBatches' => fn ($q) => $q->where('product_id', $productId)->where('warehouse_receive_pending', false),
            ])
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $candidates = [];

        foreach ($advanceGrns as $grn) {
            foreach ($grn->items as $grnItem) {
                $originalQty = (float) $grnItem->received_qty;

                // Query already matched base quantity for this advance item
                $alreadyMatchedBase = (float) AdvanceReceiveMatch::query()
                    ->where('advance_goods_received_id', $grn->id)
                    ->where('advance_goods_received_item_id', $grnItem->id)
                    ->sum('base_qty');

                $availableQty = max(0.0, round($originalQty - $alreadyMatchedBase, 3));

                // If no remaining balance, advance item is CLEARED
                if ($availableQty <= 0.0001) {
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
                    'already_matched_qty' => $alreadyMatchedBase,
                    'available_qty' => $availableQty,
                    'available_base_qty' => $availableQty,
                    'status' => $alreadyMatchedBase > 0 ? 'partial' : 'open',
                ];
            }
        }

        return $candidates;
    }

    /**
     * Atomically executes Goods Receipt with Advance Reconciliation matches.
     * Prevents over-consumption via row locking and creates StockBatches ONLY for unmatched quantities.
     */
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
                $advanceItem = $advanceGrn->items->firstWhere('product_id', $productId);
                if (! $advanceItem) {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Advance Receive {$advanceGrn->grn_number} does not contain product ID #{$productId}.",
                    ]);
                }

                /** @var Product $product */
                $product = Product::findOrFail($productId);
                $matchUnit = (string) ($match['unit'] ?? $advanceItem->received_unit ?? $product->unit);
                $conversionToBase = (float) ($product->conversionToBaseForUnit($matchUnit) ?? 1.0);
                $matchedQty = (float) ($match['matched_qty'] ?? 0.0);
                $matchedBaseQty = round($matchedQty * $conversionToBase, 3);

                if ($matchedBaseQty <= 0.0001) {
                    continue;
                }

                // Check remaining available balance for this advance item
                $existingMatchedBase = (float) AdvanceReceiveMatch::query()
                    ->where('advance_goods_received_id', $advanceGrn->id)
                    ->where('advance_goods_received_item_id', $advanceItem->id)
                    ->sum('base_qty');

                $alreadyAllocatedInThisRequest = (float) ($allocatedInCurrentTx[$advanceItem->id] ?? 0.0);
                $availableBase = max(0.0, round((float) $advanceItem->received_qty - $existingMatchedBase - $alreadyAllocatedInThisRequest, 3));

                if ($matchedBaseQty > $availableBase + 0.0001) {
                    throw ValidationException::withMessages([
                        'advance_matches' => "Requested match of {$matchedQty} {$matchUnit} exceeds available remaining Advance balance of {$availableBase} base units on {$advanceGrn->grn_number}.",
                    ]);
                }

                $allocatedInCurrentTx[$advanceItem->id] = $alreadyAllocatedInThisRequest + $matchedBaseQty;

                $poItemId = isset($match['purchase_order_item_id']) && $match['purchase_order_item_id'] !== null
                    ? (int) $match['purchase_order_item_id']
                    : null;

                $batch = $advanceGrn->stockBatches->firstWhere('goods_received_item_id', $advanceItem->id)
                    ?? $advanceGrn->stockBatches->first();

                $validatedMatches[] = [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceItem->id,
                    'advance_stock_batch_id' => $batch?->id,
                    'purchase_order_item_id' => $poItemId,
                    'product_id' => $productId,
                    'matched_qty' => $matchedQty,
                    'matched_unit' => $matchUnit,
                    'base_qty' => $matchedBaseQty,
                    'conversion_to_base' => $conversionToBase,
                ];

                $key = $poItemId ? "po:{$poItemId}" : "prod:{$productId}";
                $totalMatchedByBillItem[$key] = ($totalMatchedByBillItem[$key] ?? 0.0) + $matchedBaseQty;
            }

            // 2. Generate GRN for the purchaser bill
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

            // 3. Create Bill GRN Items and calculate unmatched remainder
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

            // 4. Persist AdvanceReceiveMatch records linking Advance to Bill
            foreach ($validatedMatches as $matchRecord) {
                $poItemId = $matchRecord['purchase_order_item_id'];
                $billItem = $poItemId
                    ? collect($createdBillItems)->firstWhere('purchase_order_item_id', $poItemId)
                    : collect($createdBillItems)->firstWhere('product_id', $matchRecord['product_id']);

                AdvanceReceiveMatch::create([
                    'advance_goods_received_id' => $matchRecord['advance_goods_received_id'],
                    'advance_goods_received_item_id' => $matchRecord['advance_goods_received_item_id'],
                    'advance_stock_batch_id' => $matchRecord['advance_stock_batch_id'],
                    'bill_goods_received_id' => $billGrn->id,
                    'bill_goods_received_item_id' => $billItem?->id,
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

            // 5. Create StockBatches ONLY for the unmatched bill quantity
            $date = $billGrn->received_at instanceof Carbon
                ? $billGrn->received_at->format('Y-m-d')
                : Carbon::parse($billGrn->received_at)->format('Y-m-d');

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

                // CRITICAL RULE: If 100% matched from Advance, DO NOT create a StockBatch!
                if ($unmatchedItemQty <= 0.0001) {
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

                // Create StockBatch for ONLY the new unmatched quantity
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
                    'notes' => "Auto-created from GRN: {$billGrn->grn_number} (Unmatched bill portion)",
                ]);
            }

            // 6. Update PO status to Received
            if ($data->purchaseOrderId) {
                /** @var PurchaseOrder|null $po */
                $po = PurchaseOrder::find($data->purchaseOrderId);
                $po?->update([
                    'status' => POStatus::Received,
                ]);
            }

            // 7. Update status on fully cleared Advance receipts
            foreach ($advanceGrns as $advanceGrn) {
                $totalOriginal = (float) $advanceGrn->items->sum('received_qty');
                $totalMatched = (float) AdvanceReceiveMatch::query()
                    ->where('advance_goods_received_id', $advanceGrn->id)
                    ->sum('base_qty');

                if ($totalMatched >= $totalOriginal - 0.0001) {
                    $advanceGrn->update([
                        'bill_status' => 'bill_available',
                        'matched_by' => $userId,
                        'matched_at' => now(),
                    ]);
                }
            }

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
     * Paginated list of pending purchase orders that have confirmed Advance matches available.
     * Bounded query with zero N+1 overhead.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateMatchCandidates(array $filters = [], int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $warehouseId = isset($filters['warehouse_id']) && $filters['warehouse_id'] !== null ? (int) $filters['warehouse_id'] : null;

        // 1. Identify product IDs that currently have confirmed open/partial Advance receipts
        $advanceProductIds = GoodsReceivedItem::query()
            ->whereHas('goodsReceived', fn (Builder $q) => $q->whereNull('purchase_order_id')
                ->where('status', 'approved')
                ->whereHas('stockBatches', fn (Builder $b) => $b->where('warehouse_receive_pending', false)->where('status', '!=', BatchStatus::Closed->value))
                ->when($warehouseId !== null, fn (Builder $wq) => $wq->where(fn ($w) => $w->where('warehouse_id', $warehouseId)->orWhere('destination_shop_id', $warehouseId))))
            ->distinct()
            ->pluck('product_id')
            ->all();

        if (empty($advanceProductIds)) {
            return new LengthAwarePaginator([], 0, $perPage, 1);
        }

        // 2. Query pending Purchase Orders containing those products
        $query = PurchaseOrder::query()
            ->select(['id', 'supplier_id', 'destination_shop_id', 'po_number', 'status', 'order_date', 'created_by', 'created_at', 'updated_at'])
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->where(function (Builder $pending): void {
                $pending->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'))
                    ->orWhere(function ($withoutReceipt): void {
                        $withoutReceipt->whereDoesntHave('goodsReceiveds')->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received']);
                    });
            })
            ->whereHas('items', fn (Builder $itemQuery) => $itemQuery->whereIn('product_id', $advanceProductIds))
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

        // 3. Attach compact suggestions for each order on current page (max 25)
        $paginator->getCollection()->transform(function (PurchaseOrder $order) use ($warehouseId): array {
            $suggestions = $this->getSuggestionsForOrder($order, $warehouseId);
            $receiptState = app(WarehouseReceiptStateResolver::class)->forOrder($order);

            return [
                ...$receiptState,
                'id' => $order->id,
                'purchase_order_id' => $order->id,
                'po_number' => $order->po_number,
                'order_date' => $order->order_date?->toDateString(),
                'supplier_id' => $order->supplier_id,
                'supplier_name' => $order->supplier?->name ?? 'Supplier',
                'destination_shop_name' => $order->destinationShop?->name ?? 'Central Warehouse',
                'item_count' => (int) ($order->items_count ?? $order->items->count()),
                'total_bill_base_qty' => $suggestions['total_bill_base_qty'],
                'total_matched_base_qty' => $suggestions['total_matched_base_qty'],
                'overall_coverage_percentage' => $suggestions['overall_coverage_percentage'],
                'is_overall_percentage_meaningful' => $suggestions['is_overall_percentage_meaningful'],
                'has_advance_match' => $suggestions['overall_coverage_percentage'] > 0 || collect($suggestions['items'])->contains('has_advance_available', true),
                'match_summary_items' => $suggestions['items'],
            ];
        });

        return $paginator;
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
