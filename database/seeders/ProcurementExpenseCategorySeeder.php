<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProcurementExpense;
use App\Models\ProcurementExpenseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProcurementExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaultCategories = [
            'vehicle' => [
                'name' => 'Vehicle',
                'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
                'sort_order' => 1,
            ],
            'labour' => [
                'name' => 'Labour',
                'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
                'sort_order' => 2,
            ],
            'food' => [
                'name' => 'Food',
                'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
                'sort_order' => 3,
            ],
            'fuel' => [
                'name' => 'Fuel',
                'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
                'sort_order' => 4,
            ],
            'toll_parking' => [
                'name' => 'Toll/Parking',
                'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
                'sort_order' => 5,
            ],
            'other' => [
                'name' => 'Other',
                'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
                'sort_order' => 6,
            ],
        ];

        foreach ($defaultCategories as $key => $attributes) {
            ProcurementExpenseCategory::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => $attributes['name'],
                    'sub_category_mode' => $attributes['sub_category_mode'],
                    'sort_order' => $attributes['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        if (Schema::hasTable('procurement_expenses')) {
            $existingKeys = ProcurementExpense::query()
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->filter()
                ->all();

            $sortOrder = 10;
            foreach ($existingKeys as $existingKey) {
                $trimmedKey = trim((string) $existingKey);
                if ($trimmedKey === '') {
                    continue;
                }

                ProcurementExpenseCategory::query()->firstOrCreate(
                    ['key' => $trimmedKey],
                    [
                        'name' => Str::headline($trimmedKey),
                        'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
                        'sort_order' => $sortOrder++,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
