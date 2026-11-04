<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\BusinessSetting;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\ProductSellingPrice;
use App\Models\Shop;
use App\Models\ShopProductSellingPrice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SellingPriceResolverService
{
    public const float DEFAULT_GLOBAL_PERCENTAGE = 25.0;

    /**
     * Resolve the effective selling price rule and calculated retail price for a product.
     *
     * Precedence:
     * 1. Shop-specific product markup percentage (when shop is provided and override exists)
     * 2. Product fixed retail price (Mode C)
     * 3. Product custom percentage (Mode B)
     * 4. Active assigned Selling Price Category percentage (Mode A)
     * 5. Global Default Percentage (Mode A Fallback)
     *
     * @return array{
     *     pricing_mode: string,
     *     source: string,
     *     source_label: string,
     *     effective_percentage: ?float,
     *     fixed_price: ?float,
     *     supply_price: ?float,
     *     retail_price: ?float,
     *     is_override: bool,
     *     is_shop_override: bool,
     *     is_fixed: bool,
     *     warning: ?string,
     *     override_record: ?ShopProductSellingPrice,
     *     general_rule: array<string, mixed>
     * }
     */
    public function resolve(
        Product $product,
        ?float $supplyPrice = null,
        ?Shop $shop = null,
        ?float $globalPercentage = null
    ): array {
        $globalPercentage = $globalPercentage ?? $this->globalDefaultPercentage();
        $generalRule = $this->resolveGeneral($product, $supplyPrice, $globalPercentage);

        // 1. Shop-specific product markup percentage
        if ($shop) {
            $shopOverride = $this->getShopOverride($product, $shop);

            if ($shopOverride && $shopOverride->markup_percentage !== null) {
                $percentage = (float) $shopOverride->markup_percentage;
                $retailPrice = $supplyPrice !== null
                    ? round($supplyPrice * (1 + ($percentage / 100)), 2)
                    : null;

                return [
                    'pricing_mode' => 'shop_override',
                    'source' => 'shop_override',
                    'source_label' => "Shop Custom ({$percentage}%)",
                    'effective_percentage' => $percentage,
                    'fixed_price' => null,
                    'supply_price' => $supplyPrice,
                    'retail_price' => $retailPrice,
                    'is_override' => true,
                    'is_shop_override' => true,
                    'is_fixed' => false,
                    'warning' => null,
                    'override_record' => $shopOverride,
                    'general_rule' => $generalRule,
                ];
            }
        }

        // Tiers 2-5 inherited from general rule
        return array_merge($generalRule, [
            'is_shop_override' => false,
            'override_record' => null,
            'general_rule' => $generalRule,
        ]);
    }

    /**
     * Resolve general product rule (Tiers 2-5) without shop-level override.
     *
     * @return array{
     *     pricing_mode: string,
     *     source: string,
     *     source_label: string,
     *     effective_percentage: ?float,
     *     fixed_price: ?float,
     *     supply_price: ?float,
     *     retail_price: ?float,
     *     is_override: bool,
     *     is_fixed: bool,
     *     warning: ?string
     * }
     */
    public function resolveGeneral(
        Product $product,
        ?float $supplyPrice = null,
        ?float $globalPercentage = null
    ): array {
        $globalPercentage = $globalPercentage ?? $this->globalDefaultPercentage();
        $config = $product->relationLoaded('sellingPriceConfig')
            ? $product->sellingPriceConfig
            : $product->sellingPriceConfig()->first();

        $category = $product->relationLoaded('sellingPriceCategory')
            ? $product->sellingPriceCategory
            : $product->sellingPriceCategory()->first();

        // 2. Product Fixed Retail Price (Mode C)
        if ($config && $config->pricing_mode === ProductSellingPrice::MODE_FIXED_PRICE && $config->fixed_price !== null) {
            $fixedPrice = round((float) $config->fixed_price, 2);
            $warning = null;
            if ($supplyPrice !== null && $fixedPrice < $supplyPrice) {
                $warning = sprintf(
                    'Fixed retail price (₹%s) is below the supply price (₹%s).',
                    number_format($fixedPrice, 2),
                    number_format($supplyPrice, 2)
                );
            }

            return [
                'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
                'source' => 'product_fixed',
                'source_label' => 'Fixed Price Override',
                'effective_percentage' => null,
                'fixed_price' => $fixedPrice,
                'supply_price' => $supplyPrice,
                'retail_price' => $fixedPrice,
                'is_override' => true,
                'is_fixed' => true,
                'warning' => $warning,
            ];
        }

        // 3. Product Custom Percentage (Mode B)
        if ($config && $config->pricing_mode === ProductSellingPrice::MODE_CUSTOM_PERCENTAGE && $config->markup_percentage !== null) {
            $percentage = (float) $config->markup_percentage;
            $retailPrice = $supplyPrice !== null
                ? round($supplyPrice * (1 + ($percentage / 100)), 2)
                : null;

            return [
                'pricing_mode' => ProductSellingPrice::MODE_CUSTOM_PERCENTAGE,
                'source' => 'product_custom',
                'source_label' => "Product Custom ({$percentage}%)",
                'effective_percentage' => $percentage,
                'fixed_price' => null,
                'supply_price' => $supplyPrice,
                'retail_price' => $retailPrice,
                'is_override' => true,
                'is_fixed' => false,
                'warning' => null,
            ];
        }

        // 4. Active assigned Selling Price Category (Mode A - Category)
        if ($category && $category->is_active) {
            $percentage = (float) $category->markup_percentage;
            $retailPrice = $supplyPrice !== null
                ? round($supplyPrice * (1 + ($percentage / 100)), 2)
                : null;

            return [
                'pricing_mode' => ProductSellingPrice::MODE_INHERITED,
                'source' => 'category',
                'source_label' => "Category: {$category->name} ({$percentage}%)",
                'effective_percentage' => $percentage,
                'fixed_price' => null,
                'supply_price' => $supplyPrice,
                'retail_price' => $retailPrice,
                'is_override' => false,
                'is_fixed' => false,
                'warning' => null,
            ];
        }

        // 5. Global Default Percentage (Mode A - Fallback)
        $retailPrice = $supplyPrice !== null
            ? round($supplyPrice * (1 + ($globalPercentage / 100)), 2)
            : null;

        $fallbackReason = ($category && ! $category->is_active)
            ? "Global Default ({$globalPercentage}% - Category Inactive)"
            : "Global Default ({$globalPercentage}%)";

        return [
            'pricing_mode' => ProductSellingPrice::MODE_INHERITED,
            'source' => 'global_default',
            'source_label' => $fallbackReason,
            'effective_percentage' => $globalPercentage,
            'fixed_price' => null,
            'supply_price' => $supplyPrice,
            'retail_price' => $retailPrice,
            'is_override' => false,
            'is_fixed' => false,
            'warning' => null,
        ];
    }

    /**
     * Resolve the supply price for a product under an optional shop and date context.
     * Aligns with ShopOwnerController's candidate price evaluation (max among eligible sources).
     */
    public function resolveSupplyPrice(Product $product, ?Shop $shop = null, ?string $date = null): ?float
    {
        if (! $shop) {
            return null;
        }

        $targetDate = $date ? Carbon::parse($date)->toDateString() : today()->toDateString();
        $group = $shop->relationLoaded('priceGroup') ? $shop->priceGroup : $shop->priceGroup()->first();
        $groupName = strtoupper(trim((string) ($group?->name ?? 'A')));
        if (! in_array($groupName, ['A', 'B', 'C'], true)) {
            $groupName = 'A';
        }

        $priceKey = 'price_'.strtolower($groupName);
        $candidatePrices = [];

        // 1. Daily Price Approval for target date
        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', $targetDate)
            ->where('status', 'approved')
            ->first();

        if ($approval && (float) ($approval->$priceKey ?? 0) > 0) {
            $candidatePrices[] = (float) $approval->$priceKey;
        }

        // 2. Latest previous Daily Price Approval
        $previousApproval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '<', $targetDate)
            ->where('status', 'approved')
            ->orderByDesc('business_date')
            ->first();

        if ($previousApproval && (float) ($previousApproval->$priceKey ?? 0) > 0) {
            $candidatePrices[] = (float) $previousApproval->$priceKey;
        }

        // 3. DailyProductPrice by shop price group
        if ($group) {
            $groupPrice = DailyProductPrice::query()
                ->where('product_id', $product->id)
                ->where('shop_price_group_id', $group->id)
                ->where('grade', 'A')
                ->value('selling_price');

            if ($groupPrice !== null && (float) $groupPrice > 0) {
                $candidatePrices[] = (float) $groupPrice;
            }
        }

        // 4. Product base price as last resort if positive
        if ((float) ($product->base_price ?? 0) > 0) {
            $candidatePrices[] = (float) $product->base_price;
        }

        return $candidatePrices !== [] ? max($candidatePrices) : null;
    }

    /**
     * Batch resolve supply prices for multiple products under a shop context without N+1 queries.
     * Aligns with ShopOwnerController's candidate price evaluation (max among eligible sources).
     *
     * @param  iterable<Product>  $products
     * @return array<int, ?float> Map of product_id => supply_price
     */
    public function batchResolveSupplyPrices(iterable $products, ?Shop $shop = null, ?string $date = null): array
    {
        $prices = [];
        $productList = $products instanceof Collection ? $products : collect($products);

        if ($productList->isEmpty()) {
            return [];
        }

        foreach ($productList as $product) {
            $prices[$product->id] = null;
        }

        if (! $shop) {
            return $prices;
        }

        $targetDate = $date ? Carbon::parse($date)->toDateString() : today()->toDateString();
        $group = $shop->relationLoaded('priceGroup') ? $shop->priceGroup : $shop->priceGroup()->first();
        $groupName = strtoupper(trim((string) ($group?->name ?? 'A')));
        if (! in_array($groupName, ['A', 'B', 'C'], true)) {
            $groupName = 'A';
        }

        $priceKey = 'price_'.strtolower($groupName);
        $productIds = $productList->pluck('id')->all();

        /** @var array<int, array<int, float>> $candidates */
        $candidates = [];
        foreach ($productIds as $id) {
            $candidates[$id] = [];
        }

        // 1. Target date approvals
        $currentApprovals = DailyPriceApproval::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('business_date', $targetDate)
            ->where('status', 'approved')
            ->get()
            ->keyBy('product_id');

        foreach ($productIds as $id) {
            $app = $currentApprovals->get($id);
            if ($app && (float) ($app->$priceKey ?? 0) > 0) {
                $candidates[$id][] = (float) $app->$priceKey;
            }
        }

        // 2. Previous approvals
        $previousApprovals = DailyPriceApproval::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('business_date', '<', $targetDate)
            ->where('status', 'approved')
            ->orderByDesc('business_date')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->first());

        foreach ($productIds as $id) {
            $prev = $previousApprovals->get($id);
            if ($prev && (float) ($prev->$priceKey ?? 0) > 0) {
                $candidates[$id][] = (float) $prev->$priceKey;
            }
        }

        // 3. DailyProductPrice by shop price group
        if ($group) {
            $groupPrices = DailyProductPrice::query()
                ->whereIn('product_id', $productIds)
                ->where('shop_price_group_id', $group->id)
                ->where('grade', 'A')
                ->pluck('selling_price', 'product_id');

            foreach ($productIds as $id) {
                $gp = $groupPrices->get($id);
                if ($gp !== null && (float) $gp > 0) {
                    $candidates[$id][] = (float) $gp;
                }
            }
        }

        // 4. Product base price
        $productMap = $productList->keyBy('id');
        foreach ($productIds as $id) {
            $p = $productMap->get($id);
            if ($p && (float) ($p->base_price ?? 0) > 0) {
                $candidates[$id][] = (float) $p->base_price;
            }
        }

        foreach ($productIds as $id) {
            $prices[$id] = $candidates[$id] !== [] ? max($candidates[$id]) : null;
        }

        return $prices;
    }

    public function globalDefaultPercentage(): float
    {
        $raw = BusinessSetting::query()
            ->where('key', 'selling_price_default_percentage')
            ->value('value');

        return ($raw !== null && is_numeric($raw) && (float) $raw >= 0)
            ? (float) $raw
            : self::DEFAULT_GLOBAL_PERCENTAGE;
    }

    private function getShopOverride(Product $product, Shop $shop): ?ShopProductSellingPrice
    {
        if ($product->relationLoaded('shopSellingPrices')) {
            return $product->shopSellingPrices->firstWhere('shop_id', $shop->id);
        }

        return ShopProductSellingPrice::query()
            ->where('shop_id', $shop->id)
            ->where('product_id', $product->id)
            ->first();
    }
}
