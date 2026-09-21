<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

final class ReportHeadingDictionary
{
    // Sales
    public const FRUITS_SALE = 'fruits_sale';

    public const VEGETABLES_SALE = 'vegetables_sale';

    public const STATIONERY_SALE = 'stationery_sale';

    public const OTHER_SALE = 'other_sale';

    // Product expenses
    public const FRUITS_EXPENSE = 'fruits_expense';

    public const VEGETABLES_EXPENSE = 'vegetables_expense';

    public const STATIONERY_EXPENSE = 'stationery_expense';

    public const OTHER_PRODUCT_EXPENSE = 'other_product_expense';

    // Operating expenses
    public const SALARY = 'salary';

    public const RENT = 'rent';

    public const VEHICLE_FUEL = 'vehicle_fuel';

    public const FOOD_MESS = 'food_mess';

    public const OTHER_EXPENSE = 'other_expense';

    // Excluded
    public const IGNORE = 'ignore';

    /**
     * @return array<string, array{group: string, code: string, label: string}>
     */
    public static function definitions(): array
    {
        return [
            self::FRUITS_SALE => ['group' => 'Sales', 'code' => self::FRUITS_SALE, 'label' => 'Fruits Sale'],
            self::VEGETABLES_SALE => ['group' => 'Sales', 'code' => self::VEGETABLES_SALE, 'label' => 'Vegetables Sale'],
            self::STATIONERY_SALE => ['group' => 'Sales', 'code' => self::STATIONERY_SALE, 'label' => 'Stationery Sale'],
            self::OTHER_SALE => ['group' => 'Sales', 'code' => self::OTHER_SALE, 'label' => 'Other Sale'],

            self::FRUITS_EXPENSE => ['group' => 'Product Expense', 'code' => self::FRUITS_EXPENSE, 'label' => 'Fruits Expense'],
            self::VEGETABLES_EXPENSE => ['group' => 'Product Expense', 'code' => self::VEGETABLES_EXPENSE, 'label' => 'Vegetables Expense'],
            self::STATIONERY_EXPENSE => ['group' => 'Product Expense', 'code' => self::STATIONERY_EXPENSE, 'label' => 'Stationery Expense'],
            self::OTHER_PRODUCT_EXPENSE => ['group' => 'Product Expense', 'code' => self::OTHER_PRODUCT_EXPENSE, 'label' => 'Other Product Expense'],

            self::SALARY => ['group' => 'Operating Expense', 'code' => self::SALARY, 'label' => 'Salary'],
            self::RENT => ['group' => 'Operating Expense', 'code' => self::RENT, 'label' => 'Rent'],
            self::VEHICLE_FUEL => ['group' => 'Operating Expense', 'code' => self::VEHICLE_FUEL, 'label' => 'Vehicle/Fuel'],
            self::FOOD_MESS => ['group' => 'Operating Expense', 'code' => self::FOOD_MESS, 'label' => 'Food/Mess'],
            self::OTHER_EXPENSE => ['group' => 'Operating Expense', 'code' => self::OTHER_EXPENSE, 'label' => 'Others'],

            self::IGNORE => ['group' => 'Exclusion', 'code' => self::IGNORE, 'label' => 'Exclude from Final Report'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_map(fn (array $def) => $def['label'], self::definitions());
    }

    public static function getLabel(string $code): string
    {
        return self::definitions()[$code]['label'] ?? str($code)->replace('_', ' ')->title()->toString();
    }

    public static function isValid(string $code): bool
    {
        return isset(self::definitions()[$code]);
    }

    public static function isOperatingExpense(string $code): bool
    {
        return in_array($code, [self::SALARY, self::RENT, self::VEHICLE_FUEL, self::FOOD_MESS, self::OTHER_EXPENSE], true);
    }

    public static function isProductExpense(string $code): bool
    {
        return in_array($code, [self::FRUITS_EXPENSE, self::VEGETABLES_EXPENSE, self::STATIONERY_EXPENSE, self::OTHER_PRODUCT_EXPENSE], true);
    }

    public static function isSale(string $code): bool
    {
        return in_array($code, [self::FRUITS_SALE, self::VEGETABLES_SALE, self::STATIONERY_SALE, self::OTHER_SALE], true);
    }
}
