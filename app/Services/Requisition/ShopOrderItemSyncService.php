<?php

declare(strict_types=1);

namespace App\Services\Requisition;

use App\Enums\Inventory\ProductGrade;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Support\Collection;

class ShopOrderItemSyncService
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
    ) {}

    /**
     * @param  array<string, mixed>  $rawItems
     * @param  array<string, mixed>  $rawUnits
     * @param  array<string, mixed>  $rawMeasures
     * @return array<int, array{product: Product, quantity: float, line_key?: string, requested_product_unit_id?: ?int, unit?: string, requested_unit?: string, requested_unit_label?: string, requested_unit_quantity?: float, requested_unit_conversion_to_base?: ?float}>
     */
    public function resolveRequestedProducts(array $rawItems, array $rawUnits = [], array $rawMeasures = []): array
    {
        $requestedQuantities = [];
        $lineMeta = [];

        foreach ($rawItems as $lineKey => $quantity) {
            if (! is_string($lineKey) && ! is_int($lineKey)) {
                continue;
            }

            $normalizedLineKey = (string) $lineKey;
            [$normalizedSku, $embeddedMeasure] = array_pad(explode('|', $normalizedLineKey, 2), 2, null);
            $normalizedSku = (string) $normalizedSku;
            $numericQuantity = (float) $quantity;

            if ($numericQuantity <= 0) {
                continue;
            }

            $requestedQuantities[$normalizedLineKey] = $numericQuantity;
            $lineMeta[$normalizedLineKey] = [
                'sku' => $normalizedSku,
                'measure' => filled($rawMeasures[$normalizedLineKey] ?? null)
                    ? (string) $rawMeasures[$normalizedLineKey]
                    : ($embeddedMeasure ?: null),
            ];
        }

        if ($requestedQuantities === []) {
            return [];
        }

        $productsBySku = Product::query()
            ->with('orderUnits')
            ->whereIn('sku', collect($lineMeta)->pluck('sku')->unique()->all())
            ->get()
            ->keyBy('sku');

        $resolvedItems = [];

        foreach ($requestedQuantities as $lineKey => $quantity) {
            $sku = $lineMeta[$lineKey]['sku'];
            /** @var Product|null $product */
            $product = $productsBySku->get($sku);

            if (! $product) {
                continue;
            }

            $selectedMeasure = $this->selectedProductUnitForLine(
                product: $product,
                measureUuid: $lineMeta[$lineKey]['measure'],
                requestedUnit: strtolower(trim((string) ($rawUnits[$lineKey] ?? $rawUnits[$sku] ?? $product->unit))),
            );

            $requestedUnit = strtolower((string) ($selectedMeasure?->unit ?? $product->unit));
            $requestedUnitLabel = $selectedMeasure?->label ?: strtoupper($requestedUnit);
            $conversionToBase = $selectedMeasure
                ? ($selectedMeasure->conversion_to_base !== null ? (float) $selectedMeasure->conversion_to_base : null)
                : $product->conversionToBaseForUnit($requestedUnit);
            $baseQuantity = $conversionToBase !== null ? round($quantity * $conversionToBase, 2) : $quantity;

            $resolvedItems[] = [
                'product' => $product,
                'line_key' => $lineKey,
                'requested_product_unit_id' => $selectedMeasure?->id,
                'quantity' => $baseQuantity,
                'unit' => $conversionToBase !== null ? $product->unit : $requestedUnit,
                'requested_unit' => $requestedUnit,
                'requested_unit_label' => $requestedUnitLabel,
                'requested_unit_quantity' => $quantity,
                'requested_unit_conversion_to_base' => $conversionToBase,
            ];
        }

        return collect($resolvedItems)
            ->groupBy(fn (array $item): string => $this->resolvedOrderItemKey($item))
            ->map(function (Collection $items): array {
                $first = $items->first();
                $first['quantity'] = round((float) $items->sum('quantity'), 2);
                $first['requested_unit_quantity'] = round((float) $items->sum('requested_unit_quantity'), 2);

                return $first;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{product: Product, quantity: float}>  $items
     */
    public function syncShopOrderItems(ShopOrder $order, array $items): void
    {
        $existingItems = $order->items()->get()->keyBy(fn (ShopOrderItem $item): string => $this->shopOrderItemKey($item));
        $incomingKeys = [];

        foreach ($items as $item) {
            $product = $item['product'];
            $incomingKey = $this->resolvedOrderItemKey($item);
            $incomingKeys[] = $incomingKey;

            /** @var ShopOrderItem|null $existingItem */
            $existingItem = $existingItems->get($incomingKey);

            if ($existingItem) {
                $pricePayload = $this->lockedPricePayload($order, $product, (float) $item['quantity']);
                $existingItem->update([
                    'requested_qty' => $item['quantity'],
                    'unit' => $item['unit'] ?? $product->unit,
                    'requested_product_unit_id' => $item['requested_product_unit_id'] ?? null,
                    'requested_unit' => $item['requested_unit'] ?? $product->unit,
                    'requested_unit_label' => $item['requested_unit_label'] ?? strtoupper((string) ($item['requested_unit'] ?? $product->unit)),
                    'requested_unit_quantity' => $item['requested_unit_quantity'] ?? $item['quantity'],
                    'requested_unit_conversion_to_base' => $item['requested_unit_conversion_to_base'] ?? null,
                    ...$pricePayload,
                ]);

                continue;
            }

            $pricePayload = $this->lockedPricePayload($order, $product, (float) $item['quantity']);
            ShopOrderItem::create([
                'shop_order_id' => $order->id,
                'product_id' => $product->id,
                'requested_qty' => $item['quantity'],
                'unit' => $item['unit'] ?? $product->unit,
                'requested_product_unit_id' => $item['requested_product_unit_id'] ?? null,
                'requested_unit' => $item['requested_unit'] ?? $product->unit,
                'requested_unit_label' => $item['requested_unit_label'] ?? strtoupper((string) ($item['requested_unit'] ?? $product->unit)),
                'requested_unit_quantity' => $item['requested_unit_quantity'] ?? $item['quantity'],
                'requested_unit_conversion_to_base' => $item['requested_unit_conversion_to_base'] ?? null,
                ...$pricePayload,
            ]);
        }

        $itemsToDelete = $order->items()->get()
            ->reject(fn (ShopOrderItem $item): bool => in_array($this->shopOrderItemKey($item), $incomingKeys, true))
            ->pluck('id')
            ->all();

        if ($itemsToDelete !== []) {
            $order->items()->whereIn('id', $itemsToDelete)->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function lockedPricePayload(ShopOrder $order, Product $product, float $quantity): array
    {
        $price = $this->priceBoardService->sellingPriceFor($product, $order->shop, ProductGrade::GradeA);

        return [
            'product_grade' => ProductGrade::GradeA->value,
            'locked_price_group_id' => $price['group']->id,
            'locked_selling_price' => $price['price'],
            'locked_price_source' => $price['source'],
            'line_total' => round($quantity * $price['price'], 2),
        ];
    }

    private function selectedProductUnitForLine(Product $product, ?string $measureUuid, string $requestedUnit): ?ProductUnit
    {
        $orderableUnits = $product->orderUnits->where('is_orderable', true);

        if (filled($measureUuid)) {
            /** @var ProductUnit|null $selected */
            $selected = $orderableUnits->firstWhere('public_uuid', $measureUuid);
            if ($selected) {
                return $selected;
            }
        }

        /** @var ProductUnit|null $selected */
        $selected = $orderableUnits->first(fn (ProductUnit $unit): bool => strtolower((string) $unit->unit) === $requestedUnit);

        return $selected;
    }

    /**
     * @param  array{product: Product, requested_product_unit_id?: ?int, requested_unit?: string}  $item
     */
    private function resolvedOrderItemKey(array $item): string
    {
        $product = $item['product'];
        $measureId = $item['requested_product_unit_id'] ?? null;

        return $product->id.'|'.($measureId ?: ($item['requested_unit'] ?? $product->unit));
    }

    private function shopOrderItemKey(ShopOrderItem $item): string
    {
        return $item->product_id.'|'.($item->requested_product_unit_id ?: ($item->requested_unit ?? $item->unit));
    }
}
