<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\BusinessSetting;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\DailyProductPriceRevision;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopPriceGroup;
use Illuminate\Support\Collection;

class GradeSellingPriceService
{
    public const DEFAULT_GRADE_B_PERCENTAGE = 90.0;

    public const DEFAULT_GRADE_C_PERCENTAGE = 80.0;

    public function globalPercentage(string $grade): float
    {
        $normalizedGrade = strtoupper(trim($grade));

        if ($normalizedGrade === 'A') {
            return 100.0;
        }

        if ($normalizedGrade === 'B') {
            $val = BusinessSetting::query()->where('key', 'grade_b_default_percentage')->value('value');

            return $val !== null && is_numeric($val) ? (float) $val : self::DEFAULT_GRADE_B_PERCENTAGE;
        }

        if ($normalizedGrade === 'C') {
            $val = BusinessSetting::query()->where('key', 'grade_c_default_percentage')->value('value');

            return $val !== null && is_numeric($val) ? (float) $val : self::DEFAULT_GRADE_C_PERCENTAGE;
        }

        return 100.0;
    }

    public function productPercentage(int $productId, string $grade): float
    {
        $normalizedGrade = strtoupper(trim($grade));
        if ($normalizedGrade === 'A') {
            return 100.0;
        }

        $allCustom = $this->loadAllProductGradePercentages();
        if (isset($allCustom[$productId][$normalizedGrade]) && is_numeric($allCustom[$productId][$normalizedGrade])) {
            return (float) $allCustom[$productId][$normalizedGrade];
        }

        return $this->globalPercentage($normalizedGrade);
    }

    public function calculateDefaultPrice(?float $sellingPriceA, int $productId, string $grade): ?float
    {
        if ($sellingPriceA === null || $sellingPriceA <= 0.0) {
            return null;
        }

        $normalizedGrade = strtoupper(trim($grade));
        if ($normalizedGrade === 'A') {
            return round($sellingPriceA, 2);
        }

        $percentage = $this->productPercentage($productId, $normalizedGrade);

        return round($sellingPriceA * ($percentage / 100.0), 2);
    }

    /**
     * Batch resolve Grade A, B, and C pricing details for a collection of products in Group A scope.
     *
     * @param  array<int>  $productIds
     * @param  Collection<int, DailyPriceApproval>  $todayApprovalsA  Keyed by product_id
     * @return array<int, array{
     *     calc_price_b: ?float,
     *     selling_price_b: ?float,
     *     is_manual_b: bool,
     *     percentage_b: float,
     *     calc_price_c: ?float,
     *     selling_price_c: ?float,
     *     is_manual_c: bool,
     *     percentage_c: float
     * }>
     */
    public function batchResolveGradePrices(
        array $productIds,
        string $businessDate,
        Collection $todayApprovalsA,
    ): array {
        if (empty($productIds)) {
            return [];
        }

        $groupA = ShopPriceGroup::query()->where('name', 'A')->first();
        $groupAId = $groupA?->id;

        // Query today's Grade B & C approvals in a single query
        $todayApprovalsBC = DailyPriceApproval::query()
            ->with('updatedBy:id,name')
            ->whereIn('product_id', $productIds)
            ->whereIn('grade', ['B', 'C'])
            ->whereDate('business_date', $businessDate)
            ->get()
            ->groupBy(fn (DailyPriceApproval $item) => $item->product_id.'_'.$item->grade->value);

        // Query active DailyProductPrice records for Group A in a single query
        $dailyProductPrices = $groupAId ? DailyProductPrice::query()
            ->with('changedBy:id,name')
            ->whereIn('product_id', $productIds)
            ->where('shop_price_group_id', $groupAId)
            ->whereIn('grade', ['B', 'C'])
            ->get()
            ->groupBy(fn (DailyProductPrice $item) => $item->product_id.'_'.$item->grade->value) : collect();

        $resolved = [];

        foreach ($productIds as $productId) {
            $todayAppA = $todayApprovalsA->get($productId);
            $sellingPriceA = $todayAppA?->price_a !== null && (float) $todayAppA->price_a > 0
                ? (float) $todayAppA->price_a
                : null;

            // Grade B
            $pctB = $this->productPercentage($productId, 'B');
            $calcB = $this->calculateDefaultPrice($sellingPriceA, $productId, 'B');

            $dppB = $dailyProductPrices->get($productId.'_B')?->first();
            $appB = $todayApprovalsBC->get($productId.'_B')?->first();

            $isManualB = (bool) ($dppB?->manual_override ?? false);
            if ($isManualB) {
                $sellingPriceB = $dppB?->selling_price !== null && (float) $dppB->selling_price > 0
                    ? (float) $dppB->selling_price
                    : ($appB?->price_a !== null && (float) $appB->price_a > 0 ? (float) $appB->price_a : $calcB);
            } else {
                $sellingPriceB = $calcB;
            }

            // Grade C
            $pctC = $this->productPercentage($productId, 'C');
            $calcC = $this->calculateDefaultPrice($sellingPriceA, $productId, 'C');

            $dppC = $dailyProductPrices->get($productId.'_C')?->first();
            $appC = $todayApprovalsBC->get($productId.'_C')?->first();

            $isManualC = (bool) ($dppC?->manual_override ?? false);
            if ($isManualC) {
                $sellingPriceC = $dppC?->selling_price !== null && (float) $dppC->selling_price > 0
                    ? (float) $dppC->selling_price
                    : ($appC?->price_a !== null && (float) $appC->price_a > 0 ? (float) $appC->price_a : $calcC);
            } else {
                $sellingPriceC = $calcC;
            }

            $updatedByB = $isManualB ? ($appB?->updatedBy?->name ?? $dppB?->changedBy?->name) : null;
            $updatedByC = $isManualC ? ($appC?->updatedBy?->name ?? $dppC?->changedBy?->name) : null;

            $resolved[$productId] = [
                'calc_price_b' => $calcB,
                'selling_price_b' => $sellingPriceB,
                'is_manual_b' => $isManualB,
                'percentage_b' => $pctB,
                'updated_by_b' => $updatedByB,
                'calc_price_c' => $calcC,
                'selling_price_c' => $sellingPriceC,
                'is_manual_c' => $isManualC,
                'percentage_c' => $pctC,
                'updated_by_c' => $updatedByC,
            ];
        }

        return $resolved;
    }

    /**
     * Save a manual price override for Grade B or C in Customer Group A scope.
     */
    public function saveManualGradePrice(
        Product $product,
        string $grade,
        float $price,
        string $businessDate,
        int $userId,
    ): void {
        $normalizedGrade = strtoupper(trim($grade));
        if (! in_array($normalizedGrade, ['B', 'C'], true)) {
            return;
        }

        $groupA = ShopPriceGroup::query()->where('name', 'A')->first();
        if (! $groupA) {
            return;
        }

        $roundedPrice = round($price, 2);

        // 1. Update DailyProductPrice
        $activePrice = DailyProductPrice::firstOrNew([
            'product_id' => $product->id,
            'shop_price_group_id' => $groupA->id,
            'grade' => $normalizedGrade,
        ]);

        $oldPrice = $activePrice->exists ? (float) $activePrice->selling_price : null;

        $activePrice->fill([
            'selling_price' => $roundedPrice,
            'price_source' => 'manual',
            'margin_percent' => null,
            'manual_override' => true,
            'override_reason' => "Purchaser manual override (Grade {$normalizedGrade})",
            'changed_by' => $userId,
        ]);
        $activePrice->save();

        if ($oldPrice === null || abs($oldPrice - $roundedPrice) > 0.0001) {
            DailyProductPriceRevision::create([
                'daily_product_price_id' => $activePrice->id,
                'product_id' => $product->id,
                'shop_price_group_id' => $groupA->id,
                'grade' => $normalizedGrade,
                'old_price' => $oldPrice,
                'new_price' => $roundedPrice,
                'change_type' => 'manual',
                'reason' => "Purchaser manual override (Grade {$normalizedGrade})",
                'changed_by' => $userId,
                'changed_at' => now(),
            ]);
        }

        // 2. Update or create DailyPriceApproval for this grade
        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', $normalizedGrade)
            ->whereDate('business_date', $businessDate)
            ->first();

        if (! $approval) {
            $prevApproval = DailyPriceApproval::query()
                ->where('product_id', $product->id)
                ->where('grade', $normalizedGrade)
                ->whereDate('business_date', '<', $businessDate)
                ->orderByDesc('business_date')
                ->first();

            $cost = (float) ($prevApproval?->purchase_price ?? ($product->vendor_price > 0 ? $product->vendor_price : $product->base_price));

            $approval = new DailyPriceApproval([
                'product_id' => $product->id,
                'business_date' => $businessDate,
                'grade' => $normalizedGrade,
                'purchase_price' => $cost,
                'price_unit' => ProductUnit::normalizeUnit((string) ($product->unit ?: 'kg')),
                'price_a' => $roundedPrice,
                'price_b' => DailyPriceInheritanceService::previousPrice($prevApproval, 'price_b') ?? $roundedPrice,
                'price_c' => DailyPriceInheritanceService::previousPrice($prevApproval, 'price_c') ?? $roundedPrice,
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'updated_by' => $userId,
            ]);
        } else {
            $prevApproval = DailyPriceApproval::query()
                ->where('product_id', $product->id)
                ->where('grade', $normalizedGrade)
                ->whereDate('business_date', '<', $businessDate)
                ->orderByDesc('business_date')
                ->first();

            DailyPriceInheritanceService::applyCategoryAUpdate($approval, $roundedPrice, $prevApproval);
            $approval->status = 'approved';
            $approval->approved_by = $userId;
            $approval->approved_at = now();
            $approval->updated_by = $userId;
        }

        $approval->save();
    }

    /**
     * Reset Grade B or C to default auto-calculated price for Group A scope.
     */
    public function resetGradeToDefault(
        Product $product,
        string $grade,
        string $businessDate,
        int $userId,
        ?float $currentPriceA = null,
    ): ?float {
        $normalizedGrade = strtoupper(trim($grade));
        if (! in_array($normalizedGrade, ['B', 'C'], true)) {
            return null;
        }

        $groupA = ShopPriceGroup::query()->where('name', 'A')->first();
        if (! $groupA) {
            return null;
        }

        if ($currentPriceA === null) {
            $approvalA = DailyPriceApproval::query()
                ->where('product_id', $product->id)
                ->where('grade', 'A')
                ->whereDate('business_date', $businessDate)
                ->first();
            $currentPriceA = $approvalA?->price_a !== null && (float) $approvalA->price_a > 0
                ? (float) $approvalA->price_a
                : null;
        }

        $defaultPrice = $this->calculateDefaultPrice($currentPriceA, $product->id, $normalizedGrade);

        // Update DailyProductPrice to manual_override = false
        $activePrice = DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->where('shop_price_group_id', $groupA->id)
            ->where('grade', $normalizedGrade)
            ->first();

        if ($activePrice) {
            $activePrice->fill([
                'selling_price' => $defaultPrice ?? 0,
                'price_source' => 'calculated',
                'manual_override' => false,
                'override_reason' => "Reset to default calculated price (Grade {$normalizedGrade})",
                'changed_by' => $userId,
            ]);
            $activePrice->save();
        }

        // Update DailyPriceApproval if present
        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', $normalizedGrade)
            ->whereDate('business_date', $businessDate)
            ->first();

        if ($approval) {
            if ($defaultPrice !== null && $defaultPrice > 0) {
                $prevApproval = DailyPriceApproval::query()
                    ->where('product_id', $product->id)
                    ->where('grade', $normalizedGrade)
                    ->whereDate('business_date', '<', $businessDate)
                    ->orderByDesc('business_date')
                    ->first();

                DailyPriceInheritanceService::applyCategoryAUpdate($approval, $defaultPrice, $prevApproval);
                $approval->updated_by = $userId;
                $approval->save();
            } else {
                $approval->delete();
            }
        }

        return $defaultPrice;
    }

    /**
     * @return array<int, array<string, float>>
     */
    private function loadAllProductGradePercentages(): array
    {
        $raw = BusinessSetting::query()->where('key', 'product_grade_percentages')->value('value');
        if (! $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
