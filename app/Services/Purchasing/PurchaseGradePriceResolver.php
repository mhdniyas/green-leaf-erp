<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\DailyPriceApproval;
use App\Models\PurchaseGradePrice;
use Illuminate\Validation\ValidationException;

class PurchaseGradePriceResolver
{
    public function resolve(int $productId, string $businessDate, string $grade, ?float $gradeAFallback = null): float
    {
        $normalizedGrade = strtoupper(trim($grade));
        $fallbackPrice = ($gradeAFallback !== null && $gradeAFallback > 0) ? $gradeAFallback : null;

        // 1. Check explicit approved PurchaseGradePrice row for the requested grade
        $explicitPrice = PurchaseGradePrice::query()
            ->where('product_id', $productId)
            ->where('grade', $normalizedGrade)
            ->where('status', 'approved')
            ->whereDate('business_date', '<=', $businessDate)
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->value('purchase_price');

        if ($explicitPrice !== null && (float) $explicitPrice > 0) {
            return (float) $explicitPrice;
        }

        // 2. Resolve from approved DailyPriceApproval for the requested grade
        $dailyApprovalPrice = DailyPriceApproval::query()
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->whereDate('business_date', '<=', $businessDate)
            ->when($normalizedGrade === 'A', fn ($q) => $q->where(function ($sub): void {
                $sub->where('grade', 'A')->orWhereNull('grade');
            }))
            ->when($normalizedGrade !== 'A', fn ($q) => $q->where('grade', $normalizedGrade))
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->value('purchase_price');

        if ($dailyApprovalPrice !== null && (float) $dailyApprovalPrice > 0) {
            return (float) $dailyApprovalPrice;
        }

        // 3. Optional fallback price if provided (e.g., entered by purchaser in modal / input)
        if ($fallbackPrice !== null) {
            return $fallbackPrice;
        }

        // 4. For Grade C (and any grade other than B or A), fallback to Grade A ("GRADEONE") approved price
        if ($normalizedGrade !== 'A' && $normalizedGrade !== 'B') {
            $gradeAExplicit = PurchaseGradePrice::query()
                ->where('product_id', $productId)
                ->where('grade', 'A')
                ->where('status', 'approved')
                ->whereDate('business_date', '<=', $businessDate)
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->value('purchase_price');

            if ($gradeAExplicit !== null && (float) $gradeAExplicit > 0) {
                return (float) $gradeAExplicit;
            }

            $gradeADaily = DailyPriceApproval::query()
                ->where('product_id', $productId)
                ->where('status', 'approved')
                ->whereDate('business_date', '<=', $businessDate)
                ->where(function ($sub): void {
                    $sub->where('grade', 'A')->orWhereNull('grade');
                })
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->value('purchase_price');

            if ($gradeADaily !== null && (float) $gradeADaily > 0) {
                return (float) $gradeADaily;
            }
        }

        throw ValidationException::withMessages([
            'purchase_grade' => "No approved Grade {$normalizedGrade} purchase price is available for this product and date.",
        ]);
    }
}
