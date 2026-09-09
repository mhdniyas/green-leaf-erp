<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Collection;

class AdvanceAvailableBalanceCalculator
{
    /**
     * Calculate item-level available base quantities for a specific advance GRN.
     *
     * @return array<int, float> [item_id => available_base_qty]
     */
    public function calculateItemAvailableBase(GoodsReceived $advanceGrn, ?Collection $productsMap = null, ?Collection $preloadedMatches = null): array
    {
        $items = $advanceGrn->relationLoaded('items') ? $advanceGrn->items : $advanceGrn->items()->get();
        if ($items->isEmpty()) {
            return [];
        }

        $allMatches = $preloadedMatches !== null
            ? $preloadedMatches->where('advance_goods_received_id', $advanceGrn->id)
            : AdvanceReceiveMatch::query()
                ->where('advance_goods_received_id', $advanceGrn->id)
                ->get();

        $result = [];
        $itemsByProduct = $items->groupBy('product_id');

        foreach ($itemsByProduct as $productId => $productItems) {
            $productId = (int) $productId;
            $sortedItems = $productItems->sortBy('id');

            // 1. Calculate received base and deduct item-specific matches
            $itemRemainingBase = [];
            foreach ($sortedItems as $item) {
                /** @var Product|null $product */
                $product = $productsMap?->get($productId) ?? $item->product ?? Product::find($productId);
                $conv = $this->resolveStrictUnitConversion($product, $item->received_unit) ?? 1.0;
                $receivedBase = round((float) $item->received_qty * $conv, 3);

                $itemSpecificMatches = (float) $allMatches
                    ->where('advance_goods_received_item_id', $item->id)
                    ->sum('base_qty');

                $itemRemainingBase[$item->id] = max(0.0, round($receivedBase - $itemSpecificMatches, 3));
            }

            // 2. Find legacy null-item matches for this specific product_id
            $legacyMatches = (float) $allMatches
                ->whereNull('advance_goods_received_item_id')
                ->where('product_id', $productId)
                ->sum('base_qty');

            // 3. Deduct legacy matches sequentially across items of this product in deterministic ID order
            $remainingLegacy = $legacyMatches;
            foreach ($sortedItems as $item) {
                $rem = $itemRemainingBase[$item->id];
                if ($remainingLegacy <= 0.0001) {
                    $result[$item->id] = $rem;

                    continue;
                }

                $deduct = min($remainingLegacy, $rem);
                $result[$item->id] = round($rem - $deduct, 3);
                $remainingLegacy = round($remainingLegacy - $deduct, 3);
            }
        }

        return $result;
    }

    /**
     * Calculate item-level remaining base quantities for an exact bill GRN.
     *
     * @return array<int, float> [item_id => remaining_base_qty]
     */
    public function calculateBillRemainingBase(GoodsReceived $billGrn, ?Collection $productsMap = null): array
    {
        $items = $billGrn->relationLoaded('items') ? $billGrn->items : $billGrn->items()->get();
        if ($items->isEmpty()) {
            return [];
        }

        $allMatches = AdvanceReceiveMatch::query()
            ->where('bill_goods_received_id', $billGrn->id)
            ->get();

        $result = [];
        foreach ($items as $item) {
            /** @var Product|null $product */
            $product = $productsMap?->get((int) $item->product_id) ?? $item->product ?? Product::find($item->product_id);
            $conv = $this->resolveStrictUnitConversion($product, $item->received_unit) ?? 1.0;
            $billBase = round((float) $item->received_qty * $conv, 3);

            $matchedBase = (float) $allMatches
                ->filter(function (AdvanceReceiveMatch $m) use ($item): bool {
                    if ($m->bill_goods_received_item_id !== null) {
                        return (int) $m->bill_goods_received_item_id === (int) $item->id;
                    }
                    if ($item->purchase_order_item_id && $m->purchase_order_item_id) {
                        return (int) $m->purchase_order_item_id === (int) $item->purchase_order_item_id;
                    }

                    return (int) $m->product_id === (int) $item->product_id;
                })
                ->sum('base_qty');

            $result[$item->id] = max(0.0, round($billBase - $matchedBase, 3));
        }

        return $result;
    }

    /**
     * Calculate remaining base quantity for a single bill GRN item.
     */
    public function calculateBillItemRemainingBase(GoodsReceivedItem $billItem, ?Product $product = null): float
    {
        $product = $product ?? $billItem->product ?? Product::find($billItem->product_id);
        $conv = $this->resolveStrictUnitConversion($product, $billItem->received_unit) ?? 1.0;
        $billBase = round((float) $billItem->received_qty * $conv, 3);

        $matchedBase = (float) AdvanceReceiveMatch::query()
            ->where('bill_goods_received_id', $billItem->goods_received_id)
            ->where(function ($query) use ($billItem): void {
                $query->where('bill_goods_received_item_id', $billItem->id)
                    ->orWhere(function ($fallback) use ($billItem): void {
                        $fallback->whereNull('bill_goods_received_item_id')
                            ->when($billItem->purchase_order_item_id, fn ($q) => $q->where('purchase_order_item_id', $billItem->purchase_order_item_id))
                            ->when(! $billItem->purchase_order_item_id, fn ($q) => $q->where('product_id', $billItem->product_id));
                    });
            })
            ->sum('base_qty');

        return max(0.0, round($billBase - $matchedBase, 3));
    }

    /**
     * Resolve strict conversion factor for product unit.
     */
    public function resolveStrictUnitConversion(?Product $product, ?string $unit): ?float
    {
        if (! $product) {
            return null;
        }

        $normalizedUnit = ProductUnit::normalizeUnit($unit ?: $product->unit);
        $normalizedBase = ProductUnit::normalizeUnit($product->unit);

        if ($normalizedUnit === $normalizedBase || $normalizedUnit === '') {
            return 1.0;
        }

        $units = $product->relationLoaded('orderUnits') ? $product->orderUnits : $product->orderUnits()->get();
        $matched = $units->first(function (ProductUnit $pu) use ($normalizedUnit): bool {
            $puUnit = ProductUnit::normalizeUnit($pu->unit);

            return $puUnit === $normalizedUnit;
        });

        if ($matched && $matched->conversion_to_base !== null && (float) $matched->conversion_to_base > 0.0) {
            return (float) $matched->conversion_to_base;
        }

        return null;
    }
}
