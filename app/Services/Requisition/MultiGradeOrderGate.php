<?php

declare(strict_types=1);

namespace App\Services\Requisition;

use App\Enums\Inventory\ProductGrade;
use App\Models\BusinessSetting;
use App\Models\Shop;
use Illuminate\Validation\ValidationException;

class MultiGradeOrderGate
{
    public const SETTING_KEY = 'enable_multi_grade_ordering';

    /**
     * Determine whether the multi-grade ordering add-on is globally activated.
     */
    public function isFeatureEnabled(): bool
    {
        $value = BusinessSetting::query()
            ->where('key', self::SETTING_KEY)
            ->value('value');

        return (string) ($value ?? '0') === '1';
    }

    /**
     * Determine whether a specific shop is authorized to order a specific quality grade.
     * Grade A is always allowed regardless of feature flag or shop settings.
     */
    public function canShopOrderGrade(?Shop $shop, string|ProductGrade $grade): bool
    {
        $gradeValue = $grade instanceof ProductGrade ? $grade->value : strtoupper(trim((string) $grade));

        if ($gradeValue === ProductGrade::GradeA->value) {
            return true;
        }

        if (! $this->isFeatureEnabled()) {
            return false;
        }

        if (! $shop instanceof Shop) {
            return false;
        }

        return $shop->canOrderGrade($gradeValue);
    }

    /**
     * Get the list of quality grades the given shop can currently order.
     * If the feature is disabled globally, returns only Grade A.
     *
     * @return array<int, string>
     */
    public function allowedGradesForShop(?Shop $shop): array
    {
        if (! $this->isFeatureEnabled() || ! $shop instanceof Shop) {
            return [ProductGrade::GradeA->value];
        }

        return $shop->allowedProductGrades();
    }

    /**
     * Validate an array of resolved items against the shop's permitted quality grades.
     *
     * @param  array<int, array{grade?: string|ProductGrade|null, product?: mixed}>  $items
     *
     * @throws ValidationException
     */
    public function validateOrderItems(Shop $shop, array $items): void
    {
        foreach ($items as $item) {
            $grade = $item['grade'] ?? ProductGrade::GradeA->value;
            $gradeValue = $grade instanceof ProductGrade ? $grade->value : strtoupper(trim((string) $grade));

            if ($gradeValue === ProductGrade::GradeA->value) {
                continue;
            }

            if (! $this->isFeatureEnabled()) {
                throw ValidationException::withMessages([
                    'items' => 'Multi-grade ordering add-on is currently disabled.',
                ]);
            }

            if (! $shop->canOrderGrade($gradeValue)) {
                throw ValidationException::withMessages([
                    'items' => "Shop {$shop->name} is not permitted to order Grade {$gradeValue}.",
                ]);
            }
        }
    }
}
