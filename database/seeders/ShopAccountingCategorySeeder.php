<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShopAccountingCategory;
use Illuminate\Database\Seeder;

class ShopAccountingCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['type' => 'income', 'name' => 'Sales Income - Cash', 'cash_effect' => true, 'purpose' => 'sales_cash'],
            ['type' => 'income', 'name' => 'Sales Income - GPay', 'cash_effect' => false, 'purpose' => 'sales_non_cash'],
            ['type' => 'income', 'name' => 'Sales Income - Paytm', 'cash_effect' => false, 'purpose' => 'sales_non_cash'],
            ['type' => 'income', 'name' => 'Sales Income - PhonePe', 'cash_effect' => false, 'purpose' => 'sales_non_cash'],
            ['type' => 'income', 'name' => 'Sales Income - Online', 'cash_effect' => false, 'purpose' => 'sales_non_cash'],
            ['type' => 'income', 'name' => 'Shop Cash Credit', 'cash_effect' => true, 'purpose' => 'shop_cash_credit'],
            ['type' => 'income', 'name' => 'Credit Collection', 'cash_effect' => true],
            ['type' => 'income', 'name' => 'Supplier Refund', 'cash_effect' => true],
            ['type' => 'income', 'name' => 'Commission Income', 'cash_effect' => true],
            ['type' => 'income', 'name' => 'Scrap / Empty Box Sale', 'cash_effect' => true],
            ['type' => 'income', 'name' => 'Rent Income', 'cash_effect' => true],
            ['type' => 'income', 'name' => 'Other Income', 'cash_effect' => true],
            ['type' => 'expense', 'name' => 'Warehouse Delivery Invoice'],
            ['type' => 'expense', 'name' => 'Cash Purchase'],
            ['type' => 'expense', 'name' => 'Shop Rent'],
            ['type' => 'expense', 'name' => 'Electricity Bill'],
            ['type' => 'expense', 'name' => 'Water Bill'],
            ['type' => 'expense', 'name' => 'Internet / Phone Bill'],
            ['type' => 'expense', 'name' => 'Staff Salary', 'purpose' => 'staff_salary'],
            ['type' => 'expense', 'name' => 'Staff Salary Advance', 'purpose' => 'staff_advance'],
            ['type' => 'expense', 'name' => 'Transport / Delivery'],
            ['type' => 'expense', 'name' => 'Fuel Expense'],
            ['type' => 'expense', 'name' => 'Packaging / Carry Bags'],
            ['type' => 'expense', 'name' => 'Maintenance / Repair'],
            ['type' => 'expense', 'name' => 'Cleaning Expense'],
            ['type' => 'expense', 'name' => 'Bank Charges'],
            ['type' => 'expense', 'name' => 'Tax / License'],
            ['type' => 'expense', 'name' => 'Tea / Food Expense'],
            ['type' => 'expense', 'name' => 'Cash Sent To Company', 'purpose' => 'cash_sent_company'],
            ['type' => 'expense', 'name' => 'Other Expense'],
        ];

        ShopAccountingCategory::query()
            ->whereNull('shop_id')
            ->whereNotIn('name', collect($categories)->pluck('name')->all())
            ->delete();

        foreach ($categories as $category) {
            ShopAccountingCategory::query()->updateOrCreate(
                [
                    'shop_id' => null,
                    'type' => $category['type'],
                    'name' => $category['name'],
                ],
                [
                    'cash_effect' => (bool) ($category['cash_effect'] ?? true),
                    'purpose' => (string) ($category['purpose'] ?? 'custom'),
                    'is_active' => true,
                ],
            );
        }
    }
}
