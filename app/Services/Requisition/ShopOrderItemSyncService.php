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
use Illuminate\Validation\ValidationException;

class ShopOrderItemSyncService
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
    ) {}

    /**
     * @param  array<string, mixed>  $rawItems
     * @param  array<string, mixed>  $rawUnits
     * @param  array<string, mixed>  $rawMeasures
     * @param  array<string, mixed>  $rawGrades
     * @return array<int, array{product: Product, quantity: float, grade: string, line_key?: string, requested_product_unit_id?: ?int, unit?: string, requested_unit?: string, requested_unit_label?: string, requested_unit_quantity?: float, requested_unit_conversion_to_base?: ?float}>
     */
    public function resolveRequestedProducts(
        array $rawItems,
        array $rawUnits = [],
        array $rawMeasures = [],
        bool $allowZero = false,
        array $rawGrades = [],
    ): array {
        $requestedQuantities = [];
        $lineMeta = [];

        foreach ($rawItems as $lineKey => $quantity) {
            if (! is_string($lineKey) && ! is_int($lineKey)) {
                continue;
            }

            $normalizedLineKey = (string) $lineKey;
            $parts = explode('|', $normalizedLineKey);
            $normalizedSku = (string) ($parts[0] ?? '');
            $embeddedGrade = null;
            $embeddedMeasure = null;

            $allGradeValues = array_map(fn (ProductGrade $g): string => $g->value, ProductGrade::cases());

            if (count($parts) === 2) {
                $possibleGrade = strtoupper(trim((string) $parts[1]));
                if (in_array($possibleGrade, $allGradeValues, true)) {
                    $embeddedGrade = $possibleGrade;
                } else {
                    $embeddedMeasure = $parts[1];
                }
            } elseif (count($parts) >= 3) {
                $possibleGrade = strtoupper(trim((string) $parts[1]));
                if (in_array($possibleGrade, $allGradeValues, true)) {
                    $embeddedGrade = $possibleGrade;
                    $embeddedMeasure = $parts[2];
                } else {
                    $embeddedMeasure = $parts[1];
                }
            }

            $resolvedGrade = filled($rawGrades[$normalizedLineKey] ?? null)
                ? strtoupper(trim((string) $rawGrades[$normalizedLineKey]))
                : ($embeddedGrade ?: ProductGrade::GradeA->value);

            $numericQuantity = (float) $quantity;

            if ($allowZero ? $numericQuantity < 0 : $numericQuantity <= 0) {
                continue;
            }

            $requestedQuantities[$normalizedLineKey] = $numericQuantity;
            $lineMeta[$normalizedLineKey] = [
                'sku' => $normalizedSku,
                'grade' => $resolvedGrade,
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

            $gradeValue = $lineMeta[$lineKey]['grade'] ?? ProductGrade::GradeA->value;
            $resolvedGradeEnum = ProductGrade::tryResolve($gradeValue) ?? ProductGrade::GradeA;

            $requestedUnit = strtolower((string) ($selectedMeasure?->unit ?? $product->unit));
            $requestedUnitLabel = $selectedMeasure?->label ?: strtoupper($requestedUnit);
            $conversionToBase = $selectedMeasure
                ? ($selectedMeasure->conversion_to_base !== null ? (float) $selectedMeasure->conversion_to_base : null)
                : $product->conversionToBaseForUnit($requestedUnit);
            $baseQuantity = $conversionToBase !== null ? round($quantity * $conversionToBase, 2) : $quantity;

            $resolvedItems[] = [
                'product' => $product,
                'line_key' => $lineKey,
                'grade' => $resolvedGradeEnum->value,
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
     * @param  array<int, array{product: Product, quantity: float, grade?: string|ProductGrade}>  $items
     */
    public function syncShopOrderItems(ShopOrder $order, array $items): void
    {
        $existingItems = $order->items()->get()->keyBy(fn (ShopOrderItem $item): string => $this->shopOrderItemKey($item));
        $incomingKeys = [];

        foreach ($items as $item) {
            if ((float) $item['quantity'] <= 0) {
                continue;
            }

            $product = $item['product'];
            $grade = $item['grade'] ?? ProductGrade::GradeA->value;
            $gradeEnum = $grade instanceof ProductGrade ? $grade : (ProductGrade::tryResolve((string) $grade) ?? ProductGrade::GradeA);
            $incomingKey = $this->resolvedOrderItemKey($item);
            $incomingKeys[] = $incomingKey;

            /** @var ShopOrderItem|null $existingItem */
            $existingItem = $existingItems->get($incomingKey);

            $pricePayload = $this->lockedPricePayload($order, $product, (float) $item['quantity'], $gradeEnum);

            if ($existingItem) {
                $existingItem->update([
                    'product_grade' => $gradeEnum->value,
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

            ShopOrderItem::create([
                'shop_order_id' => $order->id,
                'product_id' => $product->id,
                'product_grade' => $gradeEnum->value,
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
    public function lockedPricePayload(
        ShopOrder $order,
        Product $product,
        float $quantity,
        ProductGrade $grade = ProductGrade::GradeA,
    ): array {
        $price = $this->priceBoardService->sellingPriceFor($product, $order->shop, $grade);

        if ($grade !== ProductGrade::GradeA && (float) $price['price'] <= 0.0) {
            throw ValidationException::withMessages([
                'items' => "Selling price is not available for {$product->name} (Grade {$grade->value}).",
            ]);
        }

        return [
            'product_grade' => $grade->value,
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
     * @param  array{product: Product, requested_product_unit_id?: ?int, requested_unit?: string, grade?: string|ProductGrade}  $item
     */
    private function resolvedOrderItemKey(array $item): string
    {
        $product = $item['product'];
        $measureId = $item['requested_product_unit_id'] ?? null;
        $grade = $item['grade'] ?? ProductGrade::GradeA->value;
        $gradeValue = $grade instanceof ProductGrade ? $grade->value : (string) $grade;

        return $product->id.'|'.$gradeValue.'|'.($measureId ?: ($item['requested_unit'] ?? $product->unit));
    }

    private function shopOrderItemKey(ShopOrderItem $item): string
    {
        $gradeValue = (string) ($item->product_grade ?: ProductGrade::GradeA->value);

        return $item->product_id.'|'.$gradeValue.'|'.($item->requested_product_unit_id ?: ($item->requested_unit ?? $item->unit));
    }
}
