<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\BusinessSetting;

class InventorySortingSettingsService
{
    private const string SORT_ALL_AS_GRADE_A_KEY = 'inventory_sort_all_as_grade_a';

    public function sortAllAsGradeA(): bool
    {
        return filter_var(
            BusinessSetting::query()->where('key', self::SORT_ALL_AS_GRADE_A_KEY)->value('value') ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function updateSortAllAsGradeA(bool $enabled): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => self::SORT_ALL_AS_GRADE_A_KEY],
            ['value' => $enabled ? '1' : '0'],
        );
    }
}
