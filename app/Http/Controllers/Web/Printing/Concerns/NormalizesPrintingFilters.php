<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Printing\Concerns;

use App\Models\SortSheetPreset;
use Illuminate\Http\Request;

trait NormalizesPrintingFilters
{
    /**
     * Extract normalized filter parameters from the HTTP request, applying preset if requested.
     * READ-ONLY: Never modifies preset records.
     *
     * @return array<string, mixed>
     */
    protected function extractFilters(Request $request, string $defaultDate): array
    {
        $rawShopIds = $request->input('shop_ids', $request->input('print_shop_ids', []));
        if (is_string($rawShopIds)) {
            $rawShopIds = explode(',', $rawShopIds);
        }
        $shopIds = collect((array) $rawShopIds)
            ->filter(fn ($v) => $v !== null && $v !== '' && is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $categoryIds = collect((array) $request->input('category_ids', []))
            ->when($request->filled('category_id'), fn ($col) => $col->push($request->input('category_id')))
            ->filter(fn ($v) => $v !== null && $v !== '' && is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $productIds = collect((array) $request->input('product_ids', []))
            ->when($request->filled('product_id'), fn ($col) => $col->push($request->input('product_id')))
            ->filter(fn ($v) => $v !== null && $v !== '' && is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $warehouseId = $request->integer('warehouse_id') ?: null;
        $priceGroupId = $request->integer('price_group_id') ?: null;

        // Apply preset if requested (only fills omitted filters)
        $selectedPresetUuid = null;
        $presetIdentifier = $request->input('preset_id', $request->input('preset'));
        if (! empty($presetIdentifier)) {
            $user = $request->user();
            $preset = SortSheetPreset::query()
                ->where(function ($q) use ($user) {
                    if ($user) {
                        $q->where('user_id', $user->id)->orWhereNull('user_id');
                    } else {
                        $q->whereNull('user_id');
                    }
                })
                ->where(function ($q) use ($presetIdentifier) {
                    $q->where('uuid', $presetIdentifier);
                    if (is_numeric($presetIdentifier)) {
                        $q->orWhere('id', (int) $presetIdentifier);
                    }
                })
                ->first();

            if ($preset) {
                $selectedPresetUuid = $preset->uuid;
                if ($warehouseId === null && $preset->warehouse_id) {
                    $warehouseId = (int) $preset->warehouse_id;
                }
                if ($priceGroupId === null && $preset->price_group_id) {
                    $priceGroupId = (int) $preset->price_group_id;
                }
                if (empty($shopIds) && ! $request->has('shop_id') && $preset->shop_id) {
                    $shopIds = [(int) $preset->shop_id];
                }
                if (empty($categoryIds) && ! empty($preset->category_ids)) {
                    $categoryIds = collect((array) $preset->category_ids)
                        ->filter(fn ($v) => $v !== null && $v !== '' && is_numeric($v))
                        ->map(fn ($v) => (int) $v)
                        ->values()
                        ->all();
                }
                if (empty($productIds) && ! empty($preset->product_ids)) {
                    $productIds = collect((array) $preset->product_ids)
                        ->filter(fn ($v) => $v !== null && $v !== '' && is_numeric($v))
                        ->map(fn ($v) => (int) $v)
                        ->values()
                        ->all();
                }
            }
        }

        $showCategoryTitles = $this->resolveBooleanOption(
            $request,
            ['show_category_titles', 'show_category_headers'],
            default: true,
            treatOmittedAsFalseWhenFormSubmitted: true
        );
        $codeSort = $this->resolveBooleanOption(
            $request,
            ['code_sort', 'sku_sort'],
            default: true,
            treatOmittedAsFalseWhenFormSubmitted: false
        );
        $showCode = $this->resolveBooleanOption(
            $request,
            ['show_code', 'show_sku'],
            default: true,
            treatOmittedAsFalseWhenFormSubmitted: false
        );
        $skipEmptyShops = $this->resolveBooleanOption(
            $request,
            ['skip_empty_shops'],
            default: true,
            treatOmittedAsFalseWhenFormSubmitted: false
        );

        $filters = [
            'date' => $request->input('date', $defaultDate),
            'grades' => $request->input('grades'),
            'category_ids' => $categoryIds,
            'product_ids' => $productIds,
            'shop_ids' => $shopIds,
            'shop_id' => $request->input('shop_id'),
            'warehouse_id' => $warehouseId,
            'price_group_id' => $priceGroupId,
            'code_sort' => $codeSort,
            'sku_sort' => $codeSort,
            'show_code' => $showCode,
            'show_category_titles' => $showCategoryTitles,
            'show_category_headers' => $showCategoryTitles,
            'skip_empty_shops' => $skipEmptyShops,
            'preset_id' => $selectedPresetUuid,
            'selected_preset' => $selectedPresetUuid,
        ];

        if ($request->has('grades_filter')) {
            $filters['grades_filter'] = 1;
        }

        return $filters;
    }

    /**
     * Resolve boolean option from request, supporting aliases, boolean strings, and form omission rules.
     *
     * @param  array<int, string>  $keys
     */
    protected function resolveBooleanOption(
        Request $request,
        array $keys,
        bool $default = true,
        bool $treatOmittedAsFalseWhenFormSubmitted = false
    ): bool {
        foreach ($keys as $key) {
            if ($request->has($key)) {
                $val = $request->input($key);
                if (is_bool($val)) {
                    return $val;
                }
                if ($val === '0' || $val === 0 || $val === 'false' || $val === 'off' || $val === 'no') {
                    return false;
                }
                if ($val === '1' || $val === 1 || $val === 'true' || $val === 'on' || $val === 'yes') {
                    return true;
                }

                return (bool) filter_var($val, FILTER_VALIDATE_BOOLEAN);
            }
        }

        if ($treatOmittedAsFalseWhenFormSubmitted && $request->hasAny(['grades_filter', 'filter_applied', 'apply_filters'])) {
            return false;
        }

        return $default;
    }
}
