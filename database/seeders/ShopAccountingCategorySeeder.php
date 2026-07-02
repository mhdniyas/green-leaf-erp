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
            ['type' => 'income', 'name' => 'Sales Income'],
            ['type' => 'income', 'name' => 'Cash Received'],
            ['type' => 'income', 'name' => 'Owner Top Up'],
            ['type' => 'income', 'name' => 'Other'],
            ['type' => 'expense', 'name' => 'Daily Expense'],
            ['type' => 'expense', 'name' => 'Salary'],
            ['type' => 'expense', 'name' => 'Rent'],
            ['type' => 'expense', 'name' => 'Transport'],
            ['type' => 'expense', 'name' => 'Utilities'],
            ['type' => 'expense', 'name' => 'Maintenance'],
            ['type' => 'expense', 'name' => 'Other'],
        ];

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
