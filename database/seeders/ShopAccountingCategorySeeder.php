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
            ['type' => 'income', 'name' => 'Loan Given', 'cash_effect' => true, 'purpose' => 'shop_cash_credit'],
            ['type' => 'expense', 'name' => 'Staff Salary', 'cash_effect' => true, 'purpose' => 'staff_salary'],
            ['type' => 'expense', 'name' => 'Staff Salary Advance', 'cash_effect' => true, 'purpose' => 'staff_advance'],
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
