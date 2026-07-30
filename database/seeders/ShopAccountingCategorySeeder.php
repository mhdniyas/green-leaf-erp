<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use Illuminate\Database\Seeder;

class ShopAccountingCategorySeeder extends Seeder
{
    public function run(): void
    {
        $globalCategories = [
            ['type' => 'income', 'name' => 'Sales', 'cash_effect' => true, 'purpose' => 'sales_cash'],
            ['type' => 'income', 'name' => 'UPI Transactions / Paytm / GPay / PhonePe', 'cash_effect' => false, 'purpose' => 'sales_non_cash'],
            ['type' => 'income', 'name' => 'Cash', 'cash_effect' => true, 'purpose' => 'sales_cash'],
            ['type' => 'income', 'name' => 'Card', 'cash_effect' => false, 'purpose' => 'sales_non_cash'],
            ['type' => 'income', 'name' => 'Other', 'cash_effect' => true, 'purpose' => 'custom'],
            ['type' => 'income', 'name' => 'Cash Purchase', 'cash_effect' => true, 'purpose' => 'custom'],

            ['type' => 'expense', 'name' => 'Rent', 'cash_effect' => true, 'purpose' => 'custom'],
            ['type' => 'expense', 'name' => 'Cash Purchase', 'cash_effect' => true, 'purpose' => 'custom'],
            ['type' => 'expense', 'name' => 'Room', 'cash_effect' => true, 'purpose' => 'custom'],
            ['type' => 'expense', 'name' => 'Shop Deductions', 'cash_effect' => true, 'purpose' => 'custom'],
            ['type' => 'expense', 'name' => 'Vehicle', 'cash_effect' => true, 'purpose' => 'custom'],
            ['type' => 'expense', 'name' => 'Salary', 'cash_effect' => true, 'purpose' => 'staff_salary'],
            ['type' => 'expense', 'name' => 'Other', 'cash_effect' => true, 'purpose' => 'custom'],
        ];
        $shopCategories = [
            'AV_BAZARO' => [
                ['type' => 'expense', 'name' => 'Akash'],
            ],
            'AV_LULU_VARTHUR' => [
                ['type' => 'expense', 'name' => 'Tomato'],
            ],
            'AV_GRANDCITY' => [
                ['type' => 'income', 'name' => 'SM Delivery'],
            ],
            'AV_CASIO' => [
                ['type' => 'expense', 'name' => 'To Casio'],
            ],
            'AV_SANA_JP' => [
                ['type' => 'income', 'name' => 'Rent'],
            ],
            'AV_JINDAL_CITY' => [
                ['type' => 'expense', 'name' => 'S/M P'],
                ['type' => 'expense', 'name' => 'Onion'],
                ['type' => 'expense', 'name' => 'Flower'],
                ['type' => 'expense', 'name' => 'Banana'],
                ['type' => 'expense', 'name' => 'Room / Kuri'],
            ],
        ];

        $this->syncCategories(null, $globalCategories);

        $shopsByCode = Shop::query()
            ->whereIn('code', array_keys($shopCategories))
            ->get()
            ->keyBy('code');

        foreach ($shopCategories as $shopCode => $categories) {
            $shop = $shopsByCode->get($shopCode);

            if (! $shop instanceof Shop) {
                continue;
            }

            $this->syncCategories($shop, $categories);
        }
    }

    /**
     * @param  array<int, array{type:string, name:string, cash_effect?:bool, purpose?:string}>  $categories
     */
    private function syncCategories(?Shop $shop, array $categories): void
    {
        $desiredKeys = collect($categories)
            ->map(fn (array $category): string => $category['type'].'|'.$category['name'])
            ->all();
        $query = ShopAccountingCategory::query()
            ->when(
                $shop instanceof Shop,
                fn ($query) => $query->where('shop_id', $shop->id),
                fn ($query) => $query->whereNull('shop_id'),
            );

        (clone $query)
            ->withCount('entryLines')
            ->get()
            ->each(function (ShopAccountingCategory $category) use ($desiredKeys): void {
                $key = $category->type.'|'.$category->name;

                if (in_array($key, $desiredKeys, true)) {
                    return;
                }

                if ((int) $category->entry_lines_count > 0) {
                    $category->update(['is_active' => false]);

                    return;
                }

                $category->delete();
            });

        foreach ($categories as $category) {
            ShopAccountingCategory::query()->updateOrCreate(
                [
                    'shop_id' => $shop?->id,
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
