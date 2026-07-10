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
            ['type' => 'income', 'name' => 'Sales Income - Cash'],
            ['type' => 'income', 'name' => 'Sales Income - GPay'],
            ['type' => 'income', 'name' => 'Sales Income - Paytm'],
            ['type' => 'income', 'name' => 'Sales Income - PhonePe'],
            ['type' => 'income', 'name' => 'Sales Income - Online'],
            ['type' => 'income', 'name' => 'Credit Collection'],
            ['type' => 'income', 'name' => 'Supplier Refund'],
            ['type' => 'income', 'name' => 'Commission Income'],
            ['type' => 'income', 'name' => 'Scrap / Empty Box Sale'],
            ['type' => 'income', 'name' => 'Rent Income'],
            ['type' => 'income', 'name' => 'Other Income'],
            ['type' => 'expense', 'name' => 'Warehouse Delivery Invoice'],
            ['type' => 'expense', 'name' => 'Cash Purchase'],
            ['type' => 'expense', 'name' => 'Shop Rent'],
            ['type' => 'expense', 'name' => 'Electricity Bill'],
            ['type' => 'expense', 'name' => 'Water Bill'],
            ['type' => 'expense', 'name' => 'Internet / Phone Bill'],
            ['type' => 'expense', 'name' => 'Staff Salary'],
            ['type' => 'expense', 'name' => 'Transport / Delivery'],
            ['type' => 'expense', 'name' => 'Fuel Expense'],
            ['type' => 'expense', 'name' => 'Packaging / Carry Bags'],
            ['type' => 'expense', 'name' => 'Maintenance / Repair'],
            ['type' => 'expense', 'name' => 'Cleaning Expense'],
            ['type' => 'expense', 'name' => 'Bank Charges'],
            ['type' => 'expense', 'name' => 'Tax / License'],
            ['type' => 'expense', 'name' => 'Tea / Food Expense'],
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
                ['is_active' => true],
            );
        }
    }
}
