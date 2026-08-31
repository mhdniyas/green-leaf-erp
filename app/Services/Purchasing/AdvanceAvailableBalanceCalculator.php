<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
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
    public function calculateItemAvailableBase(GoodsReceived $advanceGrn, ?Collection $productsMap = null): array
    {
        $items = $advanceGrn->relationLoaded('items') ? $advanceGrn->items : $advanceGrn->items()->get();
        if ($items->isEmpty()) {
            return [];
        }

        $allMatches = AdvanceReceiveMatch::query()
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
                $conv = $this->resolveStrictUnitConversion($product, $item->received_unit) ?? 0.0;
                $receivedBase = $conv > 0 ? round((float) $item->received_qty * $conv, 3) : 0.0;

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
     * Resolve strict conversion factor for product unit.
     */
    public function resolveStrictUnitConversion(?Product $product, ?string $unit): ?float
    {
        if (! $product) {
            return null;
        }

        $normalizedUnit = strtolower(trim((string) ($unit ?: $product->unit)));
        $normalizedBase = strtolower(trim((string) $product->unit));

        if ($normalizedUnit === $normalizedBase || $normalizedUnit === '') {
            return 1.0;
        }

        $alias = ProductUnit::UNIT_ALIASES[$normalizedUnit] ?? null;
        if ($alias && $alias === $normalizedBase) {
            return 1.0;
        }

        $units = $product->relationLoaded('orderUnits') ? $product->orderUnits : $product->orderUnits()->get();
        $matched = $units->first(function (ProductUnit $pu) use ($normalizedUnit, $alias): bool {
            $puUnit = strtolower(trim((string) $pu->unit));

            return $puUnit === $normalizedUnit || ($alias && $puUnit === $alias);
        });

        if (! $matched || $matched->conversion_to_base === null || (float) $matched->conversion_to_base <= 0.0) {
            return null;
        }

        return (float) $matched->conversion_to_base;
    }
}
