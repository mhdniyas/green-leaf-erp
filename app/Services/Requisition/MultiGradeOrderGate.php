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
     * Determine whether the feature is explicitly disabled via company settings.
     */
    public function isFeatureExplicitlyDisabled(): bool
    {
        $value = BusinessSetting::query()
            ->where('key', self::SETTING_KEY)
            ->value('value');

        return (string) $value === '0';
    }

    /**
     * Determine whether a specific shop is authorized to order a specific quality grade.
     */
    public function canShopOrderGrade(?Shop $shop, string|ProductGrade $grade): bool
    {
        $gradeValue = $grade instanceof ProductGrade ? $grade->value : strtoupper(trim((string) $grade));

        if (! $shop instanceof Shop) {
            return false;
        }

        return in_array($gradeValue, $this->allowedGradesForShop($shop), true);
    }

    /**
     * Resolve the canonical grade configuration for a shop.
     *
     * @return array{allowed: array<int, string>, default: string, ordered: array<int, string>, multi_grade_ordering_enabled: bool}
     */
    public function resolveGradeConfiguration(?Shop $shop): array
    {
        if (! $shop instanceof Shop) {
            return [
                'allowed' => [ProductGrade::GradeA->value],
                'default' => ProductGrade::GradeA->value,
                'ordered' => [ProductGrade::GradeA->value],
                'multi_grade_ordering_enabled' => false,
            ];
        }

        if ($this->isFeatureExplicitlyDisabled()) {
            $allowed = in_array(ProductGrade::GradeA->value, $shop->allowedProductGrades(), true)
                ? [ProductGrade::GradeA->value]
                : [];
            $default = $allowed[0] ?? ProductGrade::GradeA->value;

            return [
                'allowed' => $allowed,
                'default' => $default,
                'ordered' => $allowed,
                'multi_grade_ordering_enabled' => false,
            ];
        }

        return $shop->resolvedGradeConfiguration();
    }

    /**
     * Get the list of quality grades the given shop can currently order.
     *
     * @return array<int, string>
     */
    public function allowedGradesForShop(?Shop $shop): array
    {
        return $this->resolveGradeConfiguration($shop)['allowed'];
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
        $permittedGrades = $this->allowedGradesForShop($shop);

        foreach ($items as $item) {
            $grade = $item['grade'] ?? $shop->defaultProductGrade();
            $gradeValue = $grade instanceof ProductGrade ? $grade->value : strtoupper(trim((string) $grade));

            if ($gradeValue !== ProductGrade::GradeA->value && $this->isFeatureExplicitlyDisabled()) {
                throw ValidationException::withMessages([
                    'items' => 'Multi-grade ordering add-on is currently disabled.',
                ]);
            }

            if (! in_array($gradeValue, $permittedGrades, true)) {
                throw ValidationException::withMessages([
                    'items' => "Shop {$shop->name} is not permitted to order Grade {$gradeValue}.",
                ]);
            }
        }
    }
}
