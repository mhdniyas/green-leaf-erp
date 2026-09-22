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

        if ($normalizedGrade === 'A') {
            // 1. Check explicit approved PurchaseGradePrice row for Grade A
            $explicitPrice = PurchaseGradePrice::query()
                ->where('product_id', $productId)
                ->where('grade', 'A')
                ->where('status', 'approved')
                ->whereDate('business_date', '<=', $businessDate)
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->value('purchase_price');

            if ($explicitPrice !== null && (float) $explicitPrice > 0) {
                return (float) $explicitPrice;
            }

            // 2. Resolve from approved DailyPriceApproval for Grade A
            $dailyApprovalPrice = DailyPriceApproval::query()
                ->where('product_id', $productId)
                ->where('status', 'approved')
                ->whereDate('business_date', '<=', $businessDate)
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->value('purchase_price');

            if ($dailyApprovalPrice !== null && (float) $dailyApprovalPrice > 0) {
                return (float) $dailyApprovalPrice;
            }

            // 3. Optional fallback for Grade A if provided
            if ($gradeAFallback !== null && $gradeAFallback > 0) {
                return $gradeAFallback;
            }

            throw ValidationException::withMessages([
                'purchase_grade' => 'No approved Grade A purchase price is available for this product and date.',
            ]);
        }

        // Grade B/C/D - Resolve ONLY from PurchaseGradePrice for the exact requested grade
        $price = PurchaseGradePrice::query()
            ->where('product_id', $productId)
            ->where('grade', $normalizedGrade)
            ->where('status', 'approved')
            ->whereDate('business_date', '<=', $businessDate)
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->value('purchase_price');

        if ($price !== null && (float) $price > 0) {
            return (float) $price;
        }

        throw ValidationException::withMessages([
            'purchase_grade' => "No approved Grade {$normalizedGrade} purchase price is available for this product and date.",
        ]);
    }
}
