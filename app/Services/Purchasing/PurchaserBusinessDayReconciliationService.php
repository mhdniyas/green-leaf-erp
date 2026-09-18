<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PurchaserBusinessDayReconciliationService
{
    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly DailyInventoryComparisonService $comparisonService,
        private readonly PurchaserAllotmentService $allotmentService,
    ) {}

    /**
     * Calculate purchaser business day reconciliation for physical warehouse receipts vs bills.
     * Read-only calculation. Does NOT mutate database records or inventory.
     *
     * @return array{
     *     purchaser_id: int,
     *     purchaser_name: string,
     *     business_date: string,
     *     warehouse_id: int|null,
     *     total_received_qty: float,
     *     total_billed_qty: float,
     *     total_pending_qty: float,
     *     coverage_percentage: float,
     *     is_fully_covered: bool,
     *     products: array<int, array<string, mixed>>,
     *     unassigned_products: array<int, array<string, mixed>>
     * }
     */
    public function calculateReconciliation(
        int $purchaserId,
        string|\DateTimeInterface $businessDate,
        ?int $warehouseId = null
    ): array {
        $dateStr = is_string($businessDate) ? $businessDate : $businessDate->format('Y-m-d');
        $purchaser = User::query()->find($purchaserId);
        $purchaserName = $purchaser?->name ?? "Purchaser #{$purchaserId}";

        // 1. Fetch all physical goods received items for the target business date & optional warehouse
        $allReceiptItems = GoodsReceivedItem::query()
            ->whereHas('goodsReceived', function (Builder $q) use ($dateStr, $warehouseId): void {
                $q->where('status', '!=', 'cancelled')
                    ->where(function (Builder $dq) use ($dateStr): void {
                        $dq->whereHas('businessDay', fn (Builder $bdq) => $bdq->whereDate('business_date', $dateStr))
                            ->orWhere(function (Builder $sub) use ($dateStr): void {
                                $sub->whereNull('business_day_id')
                                    ->whereDate('received_at', $dateStr);
                            });
                    })
                    ->when($warehouseId !== null && $warehouseId > 0, fn (Builder $wq) => $wq->where('warehouse_id', $warehouseId));
            })
            ->with([
                'product',
                'goodsReceived.purchaserCart',
                'goodsReceived.purchaseOrder',
                'purchaseOrderItem',
            ])
            ->get();

        // 2. Classify items into Target Purchaser vs Unassigned vs Other Purchasers using canonical priority
        $targetPurchaserItems = collect();
        $unassignedItems = collect();

        foreach ($allReceiptItems as $item) {
            $resolvedPurchaserId = $this->resolvePurchaserForReceiptItem($item, $dateStr);

            if ($resolvedPurchaserId === $purchaserId) {
                $targetPurchaserItems->push($item);
            } elseif ($resolvedPurchaserId === null) {
                $unassignedItems->push($item);
            }
        }

        // 3. Build product-level reconciliation rows for target purchaser
        $productRows = $this->buildProductBreakdown($targetPurchaserItems, $purchaserId, $dateStr);

        // 4. Build product-level reconciliation rows for unassigned items
        $unassignedRows = $this->buildProductBreakdown($unassignedItems, null, $dateStr);

        $units = $productRows->pluck('unit')->unique();
        $hasMixedUnits = $units->count() > 1;

        $totalReceivedQty = round((float) $productRows->sum('received_qty'), 3);
        $totalBilledQty = round((float) $productRows->sum('billed_qty'), 3);
        $totalPendingQty = round((float) $productRows->sum('pending_qty'), 3);
        $totalExcessQty = round((float) $productRows->sum('excess_qty'), 3);

        $coveragePercentage = $totalReceivedQty > 0.0001
            ? round(min(100.0, ($totalBilledQty / $totalReceivedQty) * 100.0), 2)
            : 100.0;

        return [
            'purchaser_id' => $purchaserId,
            'purchaser_name' => $purchaserName,
            'business_date' => $dateStr,
            'warehouse_id' => $warehouseId,
            'total_received_qty' => $totalReceivedQty,
            'total_billed_qty' => $totalBilledQty,
            'total_pending_qty' => $totalPendingQty,
            'total_excess_qty' => $totalExcessQty,
            'coverage_percentage' => $coveragePercentage,
            'is_fully_covered' => $totalPendingQty <= 0.0001,
            'has_mixed_units' => $hasMixedUnits,
            'products' => $productRows->values()->all(),
            'unassigned_products' => $unassignedRows->values()->all(),
        ];
    }

    /**
     * Resolve purchaser responsibility using canonical priority:
     * 1. Direct transaction ownership (cart / purchase order)
     * 2. ProductPurchaserAllotment active on the receipt business date
     * 3. Legacy / Unassigned (null)
     */
    public function resolvePurchaserForReceiptItem(GoodsReceivedItem $item, string $receiptBusinessDate): ?int
    {
        $grn = $item->relationLoaded('goodsReceived') ? $item->goodsReceived : $item->goodsReceived()->with(['purchaserCart', 'purchaseOrder'])->first();

        // Priority 1: Direct historical transaction ownership
        if ($grn?->purchaser_cart_id) {
            $cart = $grn->relationLoaded('purchaserCart') ? $grn->purchaserCart : $grn->purchaserCart()->first();
            if ($cart?->user_id) {
                return (int) $cart->user_id;
            }
        }

        if ($grn?->purchase_order_id) {
            $po = $grn->relationLoaded('purchaseOrder') ? $grn->purchaseOrder : $grn->purchaseOrder()->first();
            if ($po) {
                $poPurchaserId = $po->purchaser_id ?? $po->created_by;
                if ($poPurchaserId) {
                    return (int) $poPurchaserId;
                }
            }
        }

        // Priority 2: ProductPurchaserAllotment active on RECEIPT BUSINESS DATE
        if ($item->product_id) {
            $allotmentPurchaser = $this->allotmentService->getPurchaserForProductOnDate($item->product_id, $receiptBusinessDate);
            if ($allotmentPurchaser !== null) {
                return (int) $allotmentPurchaser->id;
            }
        }

        // Priority 3: No direct ownership or historical allotment -> Unassigned
        return null;
    }

    /**
     * Build product breakdown rows comparing received qty vs billed qty.
     *
     * @param  Collection<int, GoodsReceivedItem>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function buildProductBreakdown(Collection $items, ?int $purchaserId, string $businessDate): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        $allItemIds = $items->pluck('id')->all();

        // Preload matches for all advance receipt items in this batch up to the present moment
        $matchesByAdvItemId = AdvanceReceiveMatch::query()
            ->whereIn('advance_goods_received_item_id', $allItemIds)
            ->get()
            ->groupBy('advance_goods_received_item_id');

        $groupedByProduct = $items->groupBy('product_id');
        $rows = collect();

        foreach ($groupedByProduct as $productId => $prodItems) {
            /** @var GoodsReceivedItem $firstItem */
            $firstItem = $prodItems->first();
            /** @var Product|null $product */
            $product = $firstItem->product;
            $productName = $product?->name ?? "Product #{$productId}";
            $sku = $product?->sku ?? '';
            $baseUnit = ProductUnit::normalizeUnit($product?->unit ?? 'kg');

            $receivedQty = 0.0;
            $billedQty = 0.0;
            $unitMismatch = false;
            $detailItems = [];

            foreach ($prodItems as $item) {
                $itemUnit = ProductUnit::normalizeUnit($item->received_unit ?: $baseUnit);
                if ($itemUnit !== $baseUnit) {
                    $unitMismatch = true;
                }

                $itemReceivedQty = (float) $item->received_qty;
                $receivedQty += $itemReceivedQty;

                $grn = $item->goodsReceived;
                $isAdvance = ($grn?->receipt_type === 'warehouse_advance');

                if (! $isAdvance) {
                    // Direct bill receipt: Received qty is already fully billed
                    $itemBilledQty = $itemReceivedQty;
                } else {
                    // Advance receipt: Calculate billed qty from matches up to present moment
                    $itemMatches = $matchesByAdvItemId->get($item->id) ?? collect();
                    $itemBilledQty = round((float) $itemMatches->sum(function (AdvanceReceiveMatch $m): float {
                        return (float) ($m->base_qty > 0 ? $m->base_qty : $m->matched_qty);
                    }), 3);
                }

                $billedQty += $itemBilledQty;

                $detailItems[] = [
                    'item_id' => $item->id,
                    'grn_id' => $item->goods_received_id,
                    'grn_number' => $grn?->grn_number ?? 'GRN-'.$item->goods_received_id,
                    'receipt_type' => $grn?->receipt_type ?? 'direct_bill',
                    'received_qty' => $itemReceivedQty,
                    'billed_qty' => min($itemReceivedQty, $itemBilledQty),
                    'unit' => $itemUnit,
                ];
            }

            $receivedQty = round($receivedQty, 3);
            $billedQty = round($billedQty, 3);
            $pendingQty = round(max(0.0, $receivedQty - $billedQty), 3);
            $excessQty = round(max(0.0, $billedQty - $receivedQty), 3);

            $coveragePercentage = $receivedQty > 0.0001
                ? round(min(100.0, ($billedQty / $receivedQty) * 100.0), 2)
                : 100.0;

            $rows->push([
                'product_id' => $productId,
                'product_name' => $productName,
                'sku' => $sku,
                'unit' => $baseUnit,
                'received_qty' => $receivedQty,
                'billed_qty' => $billedQty,
                'pending_qty' => $pendingQty,
                'excess_qty' => $excessQty,
                'coverage_percentage' => $coveragePercentage,
                'is_fully_covered' => $pendingQty <= 0.0001,
                'unit_mismatch' => $unitMismatch,
                'items' => $detailItems,
            ]);
        }

        return $rows->sortBy('product_name')->values();
    }
}
