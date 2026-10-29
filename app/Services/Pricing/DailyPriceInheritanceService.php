<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\Inventory\ProductGrade;
use App\Models\DailyPriceApproval;
use App\Models\Product;

class DailyPriceInheritanceService
{
    /**
     * Determine if a given price is a valid positive selling price candidate.
     */
    public static function isValidPrice(?float $price): bool
    {
        return $price !== null && (float) $price > 0.0;
    }

    /**
     * Resolve a valid positive previous price candidate for a specified column.
     * Returns null if previous approval is missing or its column price is null, zero, or negative.
     */
    public static function previousPrice(?DailyPriceApproval $previousApproval, string $field): ?float
    {
        if (! $previousApproval instanceof DailyPriceApproval) {
            return null;
        }

        $raw = $previousApproval->{$field};
        if ($raw === null) {
            return null;
        }

        $val = round((float) $raw, 2);

        return $val > 0.0 ? $val : null;
    }

    /**
     * Apply Category A price update and propagate fallback prices to Category B and C
     * only when they do not already possess an explicitly configured positive price.
     */
    public static function applyCategoryAUpdate(
        DailyPriceApproval $approval,
        ?float $newPriceA,
        ?DailyPriceApproval $previousApproval = null,
    ): void {
        $approval->price_a = $newPriceA;

        // Propagate to price_b if not already set to a positive value
        if (! self::isValidPrice((float) $approval->price_b)) {
            $prevB = self::previousPrice($previousApproval, 'price_b');
            $approval->price_b = $prevB ?? $newPriceA;
        }

        // Propagate to price_c if not already set to a positive value
        if (! self::isValidPrice((float) $approval->price_c)) {
            $prevC = self::previousPrice($previousApproval, 'price_c');
            $approval->price_c = $prevC ?? $newPriceA;
        }
    }

    /**
     * Apply a price update for a given category ('a', 'b', or 'c') to an approval,
     * maintaining independent tier columns and applying consistent fallback rules.
     */
    public static function applyCategoryPrice(
        DailyPriceApproval $approval,
        string $category,
        ?float $newPrice,
        ?DailyPriceApproval $previousApproval = null,
        ?Product $product = null,
    ): void {
        $cat = strtolower(trim($category));

        if ($cat === 'a') {
            self::applyCategoryAUpdate($approval, $newPrice, $previousApproval);
        } elseif ($cat === 'b') {
            $approval->price_b = $newPrice;

            // If Category A or C are unpriced on a new approval, provide valid fallbacks
            if (! self::isValidPrice((float) $approval->price_a)) {
                $gradeVal = $approval->grade instanceof ProductGrade ? $approval->grade->value : (string) ($approval->grade ?? 'A');
                $approval->price_a = self::previousPrice($previousApproval, 'price_a')
                    ?? ($gradeVal === 'A' && $product && (float) $product->base_price > 0 ? (float) $product->base_price : $newPrice);
            }
            if (! self::isValidPrice((float) $approval->price_c)) {
                $approval->price_c = self::previousPrice($previousApproval, 'price_c')
                    ?? (self::isValidPrice((float) $approval->price_a) ? (float) $approval->price_a : $newPrice);
            }
        } elseif ($cat === 'c') {
            $approval->price_c = $newPrice;

            // If Category A or B are unpriced on a new approval, provide valid fallbacks
            if (! self::isValidPrice((float) $approval->price_a)) {
                $gradeVal = $approval->grade instanceof ProductGrade ? $approval->grade->value : (string) ($approval->grade ?? 'A');
                $approval->price_a = self::previousPrice($previousApproval, 'price_a')
                    ?? ($gradeVal === 'A' && $product && (float) $product->base_price > 0 ? (float) $product->base_price : $newPrice);
            }
            if (! self::isValidPrice((float) $approval->price_b)) {
                $approval->price_b = self::previousPrice($previousApproval, 'price_b')
                    ?? (self::isValidPrice((float) $approval->price_a) ? (float) $approval->price_a : $newPrice);
            }
        }
    }
}
