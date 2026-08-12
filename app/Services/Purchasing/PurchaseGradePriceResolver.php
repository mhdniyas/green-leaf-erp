<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\PurchaseGradePrice;
use Illuminate\Validation\ValidationException;

class PurchaseGradePriceResolver
{
    public function resolve(int $productId, string $businessDate, string $grade, ?float $gradeAFallback = null): float
    {
        $price = PurchaseGradePrice::query()
            ->where('product_id', $productId)
            ->where('grade', $grade)
            ->where('status', 'approved')
            ->whereDate('business_date', '<=', $businessDate)
            ->latest('business_date')
            ->latest('id')
            ->value('purchase_price');

        if ($price !== null && (float) $price > 0) {
            return (float) $price;
        }

        if ($grade === 'A' && $gradeAFallback !== null && $gradeAFallback > 0) {
            return $gradeAFallback;
        }

        throw ValidationException::withMessages([
            'purchase_grade' => "No approved Grade {$grade} purchase price is available for this product and date.",
        ]);
    }
}
