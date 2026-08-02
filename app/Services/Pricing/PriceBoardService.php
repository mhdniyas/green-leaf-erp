<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\BusinessSetting;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\DailyProductPriceRevision;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\ProductWholesalePrice;
use App\Models\Shop;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PriceBoardService
{
    private const AUTO_APPROVE_SAME_PURCHASE_PRICE_KEY = 'auto_approve_same_daily_purchase_price';

    /**
     * @return array<int, ProductGrade>
     */
    public function sellableGrades(): array
    {
        return [ProductGrade::GradeA, ProductGrade::GradeB, ProductGrade::GradeC];
    }

    /**
     * @return Collection<int, ShopPriceGroup>
     */
    public function ensureDefaultPriceGroups(): Collection
    {
        foreach (['A' => 10, 'B' => 12, 'C' => 15] as $name => $margin) {
            ShopPriceGroup::query()->firstOrCreate(
                ['name' => $name],
                [
                    'default_margin_percent' => $margin,
                    'is_active' => true,
                ]
            );
        }

        return ShopPriceGroup::query()
            ->withCount('shops')
            ->orderBy('name')
            ->get();
    }

    public function autoApproveSamePurchasePrice(): bool
    {
        return filter_var(
            BusinessSetting::query()
                ->where('key', self::AUTO_APPROVE_SAME_PURCHASE_PRICE_KEY)
                ->value('value') ?? true,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function updateAutoApproveSamePurchasePrice(bool $enabled): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => self::AUTO_APPROVE_SAME_PURCHASE_PRICE_KEY],
            ['value' => $enabled ? '1' : '0'],
        );
    }

    public function defaultGroup(): ShopPriceGroup
    {
        return ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 10, 'is_active' => true]
        );
    }

    public function groupForShop(?Shop $shop): ShopPriceGroup
    {
        if ($shop?->priceGroup?->is_active) {
            return $shop->priceGroup;
        }

        return $this->defaultGroup();
    }

    /**
     * @return array{price: float, group: ShopPriceGroup, source: string, grade: string}
     */
    public function sellingPriceFor(Product $product, ?Shop $shop, ProductGrade $grade = ProductGrade::GradeA): array
    {
        $group = $this->groupForShop($shop);
        $price = DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->where('shop_price_group_id', $group->id)
            ->where('grade', $grade->value)
            ->first();

        if (! $price) {
            $price = $this->createMarginPrice($product, $group, $grade, null);
        }

        return [
            'price' => (float) $price->selling_price,
            'group' => $group,
            'source' => (string) $price->price_source,
            'grade' => $grade->value,
        ];
    }

    public function ensureSellingPricesForGroup(ShopPriceGroup $group, ?int $userId = null): void
    {
        Product::query()
            ->active()
            ->select(['id', 'base_price'])
            ->chunkById(200, function (Collection $products) use ($group, $userId): void {
                foreach ($products as $product) {
                    foreach ($this->sellableGrades() as $grade) {
                        DailyProductPrice::query()->firstOrCreate(
                            [
                                'product_id' => $product->id,
                                'shop_price_group_id' => $group->id,
                                'grade' => $grade->value,
                            ],
                            $this->marginPricePayload($product, $group, $grade, $userId)
                        );
                    }
                }
            });
    }

    /**
     * @param  array<int|string, array<string, array<string, mixed>>>  $prices
     */
    public function updateGroupSellingPrices(ShopPriceGroup $group, array $prices, int $userId, ?string $reason = null): void
    {
        DB::transaction(function () use ($group, $prices, $userId, $reason): void {
            $products = Product::query()
                ->whereIn('id', array_map('intval', array_keys($prices)))
                ->get()
                ->keyBy('id');

            foreach ($prices as $productId => $gradeRows) {
                /** @var Product|null $product */
                $product = $products->get((int) $productId);

                if (! $product) {
                    continue;
                }

                foreach ($this->sellableGrades() as $grade) {
                    $row = $gradeRows[$grade->value] ?? [];
                    $manualOverride = ($row['mode'] ?? 'margin') === 'manual';
                    $marginPercent = $manualOverride ? null : (float) ($row['margin_percent'] ?? $group->default_margin_percent);
                    $sellingPrice = $manualOverride
                        ? (float) ($row['selling_price'] ?? 0)
                        : $this->calculateMarginSellingPrice($product, $grade, $marginPercent);

                    $price = DailyProductPrice::query()->firstOrNew([
                        'product_id' => $product->id,
                        'shop_price_group_id' => $group->id,
                        'grade' => $grade->value,
                    ]);

                    $oldPrice = $price->exists ? (float) $price->selling_price : null;
                    $oldMargin = $price->exists && $price->margin_percent !== null ? (float) $price->margin_percent : null;

                    $price->fill([
                        'selling_price' => round($sellingPrice, 2),
                        'price_source' => $manualOverride ? 'manual' : 'margin',
                        'margin_percent' => $marginPercent,
                        'manual_override' => $manualOverride,
                        'override_reason' => $reason,
                        'changed_by' => $userId,
                    ]);
                    $price->save();

                    if ($oldPrice === null || abs($oldPrice - (float) $price->selling_price) > 0.0001 || $oldMargin !== $marginPercent) {
                        DailyProductPriceRevision::create([
                            'daily_product_price_id' => $price->id,
                            'product_id' => $product->id,
                            'shop_price_group_id' => $group->id,
                            'grade' => $grade->value,
                            'old_price' => $oldPrice,
                            'new_price' => $price->selling_price,
                            'old_margin_percent' => $oldMargin,
                            'new_margin_percent' => $marginPercent,
                            'change_type' => $manualOverride ? 'manual' : 'margin',
                            'reason' => $reason,
                            'changed_by' => $userId,
                            'changed_at' => now(),
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Update final product prices across shop price groups such as Own / A, Own / B, Own / C.
     *
     * @param  array<int|string, array<int|string, mixed>>  $prices
     */
    public function updateShopCategoryPrices(array $prices, int $userId, ?string $reason = null): void
    {
        DB::transaction(function () use ($prices, $userId, $reason): void {
            $products = Product::query()
                ->whereIn('id', array_map('intval', array_keys($prices)))
                ->get()
                ->keyBy('id');

            foreach ($prices as $productId => $groupPrices) {
                /** @var Product|null $product */
                $product = $products->get((int) $productId);

                if (! $product) {
                    continue;
                }

                foreach ($groupPrices as $groupId => $sellingPrice) {
                    if ($sellingPrice === null || $sellingPrice === '') {
                        continue;
                    }

                    /** @var ShopPriceGroup|null $group */
                    $group = ShopPriceGroup::query()->find((int) $groupId);

                    if (! $group) {
                        continue;
                    }

                    $grade = ProductGrade::GradeA;
                    $price = DailyProductPrice::query()->firstOrNew([
                        'product_id' => $product->id,
                        'shop_price_group_id' => $group->id,
                        'grade' => $grade->value,
                    ]);

                    $oldPrice = $price->exists ? (float) $price->selling_price : null;
                    $newPrice = round((float) $sellingPrice, 2);

                    $price->fill([
                        'selling_price' => $newPrice,
                        'price_source' => 'manual',
                        'margin_percent' => null,
                        'manual_override' => true,
                        'override_reason' => $reason,
                        'changed_by' => $userId,
                    ]);
                    $price->save();

                    if ($oldPrice === null || abs($oldPrice - $newPrice) > 0.0001) {
                        DailyProductPriceRevision::create([
                            'daily_product_price_id' => $price->id,
                            'product_id' => $product->id,
                            'shop_price_group_id' => $group->id,
                            'grade' => $grade->value,
                            'old_price' => $oldPrice,
                            'new_price' => $newPrice,
                            'old_margin_percent' => null,
                            'new_margin_percent' => null,
                            'change_type' => 'manual',
                            'reason' => $reason,
                            'changed_by' => $userId,
                            'changed_at' => now(),
                        ]);
                    }
                }
            }
        });
    }

    /**
     * @param  iterable<int>  $productIds
     */
    public function refreshWholesalePricesForProducts(iterable $productIds, string $sourceType, ?string $sourceReference = null): void
    {
        foreach (array_unique(array_map('intval', is_array($productIds) ? $productIds : iterator_to_array($productIds))) as $productId) {
            $product = Product::query()->find($productId);

            if (! $product) {
                continue;
            }

            foreach ($this->sellableGrades() as $grade) {
                $costData = $this->calculateCurrentCost($product, $grade);

                ProductWholesalePrice::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'grade' => $grade->value,
                    ],
                    [
                        'weighted_average_cost' => $costData['weighted_average_cost'],
                        'wholesale_price' => $costData['weighted_average_cost'],
                        'sellable_quantity' => $costData['sellable_quantity'],
                        'total_cost' => $costData['total_cost'],
                        'source_type' => $sourceType,
                        'source_reference' => $sourceReference,
                        'calculated_at' => now(),
                    ]
                );
            }

            if ($sourceType === 'grn') {
                $this->createOrUpdatePendingApproval($product);
            } else {
                $this->refreshMarginSellingPrices($product);
            }
        }
    }

    /**
     * Create or update a pending DailyPriceApproval for the active business date based on current GRN cost.
     */
    public function createOrUpdatePendingApproval(Product $product): void
    {
        $gradeACost = (float) (ProductWholesalePrice::query()
            ->where('product_id', $product->id)
            ->where('grade', ProductGrade::GradeA->value)
            ->value('wholesale_price') ?? $product->base_price);

        $marginA = (float) (ShopPriceGroup::where('name', 'A')->value('default_margin_percent') ?? 10);
        $marginB = (float) (ShopPriceGroup::where('name', 'B')->value('default_margin_percent') ?? 12);
        $marginC = (float) (ShopPriceGroup::where('name', 'C')->value('default_margin_percent') ?? 15);

        $businessDate = app(PurchaserBusinessDayService::class)->currentCalendarDate();

        DailyPriceApproval::updateOrCreate(
            [
                'product_id' => $product->id,
                'business_date' => $businessDate,
            ],
            [
                'purchase_price' => $gradeACost,
                'price_unit' => $product->unit ?: 'kg',
                'price_a' => round($gradeACost * (1 + $marginA / 100), 2),
                'price_b' => round($gradeACost * (1 + $marginB / 100), 2),
                'price_c' => round($gradeACost * (1 + $marginC / 100), 2),
                'status' => 'pending',
            ]
        );
    }

    /**
     * @return Collection<int, DailyPriceApproval>
     */
    public function ensurePendingApprovalsForPurchaseDate(string $purchaseDate, bool $includeAllProducts = false): Collection
    {
        $priceGroups = $this->ensureDefaultPriceGroups()->whereIn('name', ['A', 'B', 'C'])->values();

        $grns = GoodsReceived::query()
            ->whereIn('status', ['pending_approval', 'approved'])
            ->whereDate('received_at', $purchaseDate)
            ->with(['items.product', 'items.purchaseOrderItem'])
            ->get();

        $products = [];

        foreach ($grns as $grn) {
            foreach ($grn->items as $item) {
                $product = $item->product;

                if (! $product) {
                    continue;
                }

                $productId = (int) $product->id;
                $qty = (float) $item->received_qty;
                $unitPrice = (float) ($item->purchaseOrderItem?->costPerKgForReceivedQuantity($qty) ?? $item->purchaseOrderItem?->unit_price ?? 0);

                if (! isset($products[$productId])) {
                    $products[$productId] = [
                        'product_id' => $productId,
                        'base_price' => (float) $product->base_price,
                        'unit' => (string) $product->unit,
                        'total_qty' => 0.0,
                        'weighted_sum' => 0.0,
                    ];
                }

                $products[$productId]['total_qty'] += $qty;
                $products[$productId]['weighted_sum'] += $qty * $unitPrice;
            }
        }

        if ($includeAllProducts) {
            Product::query()
                ->ordered()
                ->get(['id', 'base_price', 'unit'])
                ->each(function (Product $product) use (&$products): void {
                    $productId = (int) $product->id;

                    if (isset($products[$productId])) {
                        return;
                    }

                    $products[$productId] = [
                        'product_id' => $productId,
                        'base_price' => (float) $product->base_price,
                        'unit' => (string) $product->unit,
                        'total_qty' => 0.0,
                        'weighted_sum' => 0.0,
                    ];
                });
        }

        if ($products === []) {
            return collect();
        }

        $businessDate = Carbon::parse($purchaseDate)->toDateString();
        $marginA = (float) ($priceGroups->firstWhere('name', 'A')?->default_margin_percent ?? 10);
        $marginB = (float) ($priceGroups->firstWhere('name', 'B')?->default_margin_percent ?? 12);
        $marginC = (float) ($priceGroups->firstWhere('name', 'C')?->default_margin_percent ?? 15);
        $autoApproveSamePurchasePrice = $this->autoApproveSamePurchasePrice();
        $previousApprovals = DailyPriceApproval::query()
            ->whereIn('product_id', array_map('intval', array_keys($products)))
            ->whereDate('business_date', '<', $businessDate)
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->orderBy('business_date')
            ->get()
            ->keyBy('product_id');

        foreach ($products as $product) {
            $previousApproval = $previousApprovals->get((int) $product['product_id']);
            $purchasePrice = $product['total_qty'] > 0
                ? round($product['weighted_sum'] / $product['total_qty'], 4)
                : round((float) ($previousApproval?->purchase_price ?? $product['base_price']), 4);
            $comparisonPurchasePrice = $previousApproval
                ? (float) $previousApproval->purchase_price
                : ((float) $product['base_price'] > 0 ? (float) $product['base_price'] : null);
            $movementStatus = $this->movementStatusForPurchasePrice($purchasePrice, $comparisonPurchasePrice);

            $approval = DailyPriceApproval::query()
                ->where('product_id', $product['product_id'])
                ->whereDate('business_date', $businessDate)
                ->first() ?? new DailyPriceApproval([
                    'product_id' => $product['product_id'],
                    'business_date' => $businessDate,
                ]);

            if ($approval->exists && $approval->status === 'approved') {
                if ($this->hasValidApprovedSellingPrices($approval)) {
                    continue;
                }

                $approval->forceFill([
                    'status' => 'pending',
                    'approved_by' => null,
                    'approved_at' => null,
                ]);
            }

            $samePriceAutoApproved = $movementStatus === 'same' && $autoApproveSamePurchasePrice && $previousApproval;

            $approval->fill([
                'purchase_price' => $purchasePrice,
                'price_unit' => $approval->price_unit ?: (string) ($previousApproval?->price_unit ?: ($product['unit'] ?? 'kg')),
                'price_a' => $samePriceAutoApproved && $previousApproval ? $previousApproval->price_a : ((float) $approval->price_a > 0 ? $approval->price_a : round($purchasePrice * (1 + $marginA / 100), 2)),
                'price_b' => $samePriceAutoApproved && $previousApproval ? $previousApproval->price_b : ((float) $approval->price_b > 0 ? $approval->price_b : round($purchasePrice * (1 + $marginB / 100), 2)),
                'price_c' => $samePriceAutoApproved && $previousApproval ? $previousApproval->price_c : ((float) $approval->price_c > 0 ? $approval->price_c : round($purchasePrice * (1 + $marginC / 100), 2)),
                'status' => $samePriceAutoApproved ? 'approved' : ($approval->exists ? $approval->status : 'pending'),
                'approved_by' => $samePriceAutoApproved ? null : $approval->approved_by,
                'approved_at' => $samePriceAutoApproved ? ($approval->approved_at ?? now()) : $approval->approved_at,
            ]);
            $approval->save();
        }

        return DailyPriceApproval::query()
            ->with('product.orderUnits')
            ->whereDate('business_date', $businessDate)
            ->whereIn('product_id', array_map('intval', array_keys($products)))
            ->get()
            ->each(function (DailyPriceApproval $approval) use ($products): void {
                $product = $products[(int) $approval->product_id] ?? null;

                $approval->setAttribute('purchased_today', (float) ($product['total_qty'] ?? 0) > 0);
                $approval->setAttribute('purchase_quantity', (float) ($product['total_qty'] ?? 0));

                $this->appendMovementMetadata($approval);
            });
    }

    public function ensureProductApprovalForPurchaseDate(Product $product, string $purchaseDate): DailyPriceApproval
    {
        $priceGroups = $this->ensureDefaultPriceGroups()->whereIn('name', ['A', 'B', 'C'])->values();
        $businessDate = Carbon::parse($purchaseDate)->toDateString();
        $previousApproval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '<', $businessDate)
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->orderByDesc('business_date')
            ->first();

        $purchasePrice = round((float) ($previousApproval?->purchase_price ?? $product->base_price), 4);
        $marginA = (float) ($priceGroups->firstWhere('name', 'A')?->default_margin_percent ?? 10);
        $marginB = (float) ($priceGroups->firstWhere('name', 'B')?->default_margin_percent ?? 12);
        $marginC = (float) ($priceGroups->firstWhere('name', 'C')?->default_margin_percent ?? 15);

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', $businessDate)
            ->first() ?? new DailyPriceApproval([
                'product_id' => $product->id,
                'business_date' => $businessDate,
            ]);

        if (! $approval->exists || ! $this->hasValidApprovedSellingPrices($approval)) {
            $approval->fill([
                'purchase_price' => $purchasePrice,
                'price_unit' => $approval->price_unit ?: (string) ($previousApproval?->price_unit ?: $product->unit ?: 'kg'),
                'price_a' => (float) $approval->price_a > 0 ? $approval->price_a : round($purchasePrice * (1 + $marginA / 100), 2),
                'price_b' => (float) $approval->price_b > 0 ? $approval->price_b : round($purchasePrice * (1 + $marginB / 100), 2),
                'price_c' => (float) $approval->price_c > 0 ? $approval->price_c : round($purchasePrice * (1 + $marginC / 100), 2),
                'status' => $approval->exists ? $approval->status : 'pending',
            ]);
            $approval->save();
        }

        $approval->setAttribute('purchased_today', false);
        $approval->setAttribute('purchase_quantity', 0.0);

        return $this->appendMovementMetadata($approval);
    }

    public function appendMovementMetadata(DailyPriceApproval $approval): DailyPriceApproval
    {
        $approval->loadMissing('product');

        $previousApproval = DailyPriceApproval::query()
            ->where('product_id', $approval->product_id)
            ->whereDate('business_date', '<', $approval->business_date)
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->orderByDesc('business_date')
            ->first();

        $approval->comparison_purchase_price = $previousApproval
            ? (float) $previousApproval->purchase_price
            : ($approval->product && (float) $approval->product->base_price > 0 ? (float) $approval->product->base_price : null);
        $approval->movement_status = $this->movementStatusForPurchasePrice((float) $approval->purchase_price, $approval->comparison_purchase_price);

        return $approval;
    }

    private function movementStatusForPurchasePrice(float $purchasePrice, ?float $comparisonPurchasePrice): string
    {
        if ($comparisonPurchasePrice === null) {
            return 'changed';
        }

        if (abs($purchasePrice - $comparisonPurchasePrice) <= 0.0001) {
            return 'same';
        }

        return $purchasePrice > $comparisonPurchasePrice ? 'up' : 'down';
    }

    private function hasValidApprovedSellingPrices(DailyPriceApproval $approval): bool
    {
        return (float) $approval->price_a > 0
            && (float) $approval->price_b > 0
            && (float) $approval->price_c > 0;
    }

    /**
     * @return array{weighted_average_cost: float, sellable_quantity: float, total_cost: float}
     */
    public function calculateCurrentCost(Product $product, ProductGrade $grade): array
    {
        $movementCost = $this->calculateCostFromSortedMovements($product, $grade);
        if ($movementCost['sellable_quantity'] > 0) {
            return $movementCost;
        }

        $batches = StockBatch::query()
            ->where('product_id', $product->id)
            ->where('status', '!=', BatchStatus::Closed->value)
            ->whereNull('deleted_at')
            ->with('wastageEntries')
            ->get();

        $sellableQuantity = 0.0;
        $totalCost = 0.0;

        foreach ($batches as $batch) {
            $batchQuantity = (float) $batch->total_kg;
            if ($batchQuantity <= 0) {
                continue;
            }

            $wastageQuantity = (float) $batch->wastageEntries->sum('quantity');
            $sellableBatchQuantity = max(0.0, $batchQuantity - $wastageQuantity);

            if ($sellableBatchQuantity <= 0) {
                continue;
            }

            $deductedQuantity = $this->deductedMovementQuantity((int) $batch->id, $grade);
            $remainingQuantity = max(0.0, $sellableBatchQuantity - $deductedQuantity);
            $batchUnitCost = (float) $batch->total_landed_cost / $sellableBatchQuantity;

            $sellableQuantity += $remainingQuantity;
            $totalCost += $remainingQuantity * $batchUnitCost;
        }

        if ($sellableQuantity <= 0) {
            $fallback = (float) $product->base_price;

            return [
                'weighted_average_cost' => $fallback,
                'sellable_quantity' => 0.0,
                'total_cost' => 0.0,
            ];
        }

        return [
            'weighted_average_cost' => round($totalCost / $sellableQuantity, 4),
            'sellable_quantity' => round($sellableQuantity, 3),
            'total_cost' => round($totalCost, 4),
        ];
    }

    public function refreshMarginSellingPrices(Product $product): void
    {
        DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->where('manual_override', false)
            ->with('shopPriceGroup')
            ->get()
            ->each(function (DailyProductPrice $price) use ($product): void {
                if (! $price->shopPriceGroup) {
                    return;
                }

                $grade = $price->grade instanceof ProductGrade ? $price->grade : ProductGrade::from((string) $price->grade);
                $margin = $price->margin_percent !== null ? (float) $price->margin_percent : (float) $price->shopPriceGroup->default_margin_percent;
                $newPrice = $this->calculateMarginSellingPrice($product, $grade, $margin);
                $oldPrice = (float) $price->selling_price;

                if (abs($oldPrice - $newPrice) <= 0.0001) {
                    return;
                }

                $price->update([
                    'selling_price' => round($newPrice, 2),
                    'price_source' => 'margin',
                    'margin_percent' => $margin,
                    'changed_by' => null,
                ]);

                DailyProductPriceRevision::create([
                    'daily_product_price_id' => $price->id,
                    'product_id' => $product->id,
                    'shop_price_group_id' => $price->shop_price_group_id,
                    'grade' => $grade->value,
                    'old_price' => $oldPrice,
                    'new_price' => $price->selling_price,
                    'old_margin_percent' => $margin,
                    'new_margin_percent' => $margin,
                    'change_type' => 'auto',
                    'reason' => 'Wholesale cost changed.',
                    'changed_at' => now(),
                ]);
            });
    }

    private function createMarginPrice(Product $product, ShopPriceGroup $group, ProductGrade $grade, ?int $userId): DailyProductPrice
    {
        return DailyProductPrice::create(array_merge(
            [
                'product_id' => $product->id,
                'shop_price_group_id' => $group->id,
                'grade' => $grade->value,
            ],
            $this->marginPricePayload($product, $group, $grade, $userId)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function marginPricePayload(Product $product, ShopPriceGroup $group, ProductGrade $grade, ?int $userId): array
    {
        $margin = (float) $group->default_margin_percent;

        return [
            'selling_price' => $this->calculateMarginSellingPrice($product, $grade, $margin),
            'price_source' => 'margin',
            'margin_percent' => $margin,
            'manual_override' => false,
            'changed_by' => $userId,
        ];
    }

    private function calculateMarginSellingPrice(Product $product, ProductGrade $grade, float $marginPercent): float
    {
        $wholesale = ProductWholesalePrice::query()
            ->where('product_id', $product->id)
            ->where('grade', $grade->value)
            ->value('wholesale_price');

        $base = $wholesale !== null ? (float) $wholesale : (float) $product->base_price;
        $gradeMultiplier = match ($grade) {
            ProductGrade::GradeA => 1.00,
            ProductGrade::GradeB => 0.90,
            ProductGrade::GradeC => 0.80,
            default => 1.00,
        };

        return round(($base * $gradeMultiplier) * (1 + ($marginPercent / 100)), 2);
    }

    /**
     * @return array{weighted_average_cost: float, sellable_quantity: float, total_cost: float}
     */
    private function calculateCostFromSortedMovements(Product $product, ProductGrade $grade): array
    {
        $lots = StockMovement::query()
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_movements.batch_id')
            ->where('stock_movements.product_id', $product->id)
            ->where('stock_movements.grade', $grade->value)
            ->whereNull('stock_batches.deleted_at')
            ->selectRaw(
                'stock_movements.batch_id, MAX(stock_movements.cost_per_unit) as cost_per_unit, '.
                'SUM(CASE '.
                'WHEN stock_movements.type IN (?, ?) THEN stock_movements.quantity '.
                'WHEN stock_movements.type IN (?, ?, ?) THEN -stock_movements.quantity '.
                'ELSE 0 END) as available_quantity',
                [
                    StockMovementType::In->value,
                    StockMovementType::SaleReversal->value,
                    StockMovementType::Out->value,
                    StockMovementType::Wastage->value,
                    StockMovementType::Sale->value,
                ]
            )
            ->groupBy('stock_movements.batch_id')
            ->toBase()
            ->get();

        $sellableQuantity = 0.0;
        $totalCost = 0.0;

        foreach ($lots as $lot) {
            $quantity = max(0.0, (float) $lot->available_quantity);

            if ($quantity <= 0) {
                continue;
            }

            $sellableQuantity += $quantity;
            $totalCost += $quantity * (float) $lot->cost_per_unit;
        }

        if ($sellableQuantity <= 0) {
            return [
                'weighted_average_cost' => 0.0,
                'sellable_quantity' => 0.0,
                'total_cost' => 0.0,
            ];
        }

        return [
            'weighted_average_cost' => round($totalCost / $sellableQuantity, 4),
            'sellable_quantity' => round($sellableQuantity, 3),
            'total_cost' => round($totalCost, 4),
        ];
    }

    private function deductedMovementQuantity(int $batchId, ProductGrade $grade): float
    {
        return (float) StockMovement::query()
            ->where('batch_id', $batchId)
            ->where('grade', $grade->value)
            ->whereIn('type', [
                StockMovementType::Out->value,
                StockMovementType::Wastage->value,
                StockMovementType::Sale->value,
            ])
            ->sum('quantity');
    }
}
