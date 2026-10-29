<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Printing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Printing\StorePrintV1PresetRequest;
use App\Http\Requests\Printing\UpdatePrintV1SectionSettingsRequest;
use App\Http\Requests\Printing\UpdatePrintV1ShopSettingsRequest;
use App\Models\Category;
use App\Models\Printing\PrintV1Preset;
use App\Models\Printing\PrintV1PresetBatch;
use App\Models\Printing\PrintV1Setting;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopPriceGroup;
use App\Models\SortSheetPreset;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintV1SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $userId = $request->user()?->id;

        $sortSettings = PrintV1Setting::getForSection('sort-sheet', $userId);
        $selectionSettings = PrintV1Setting::getForSection('selection', $userId);
        $gridSettings = PrintV1Setting::getForSection('segregation-grid', $userId);
        $portraitSettings = PrintV1Setting::getForSection('shop-wise-portrait', $userId);
        $shopSettings = PrintV1Setting::getForSection('shops', $userId);

        $presets = PrintV1Preset::query()
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $batches = PrintV1PresetBatch::query()
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get();

        $shops = Shop::orderByRaw("COALESCE(NULLIF(warehouse_tag, ''), 'ZZZ') ASC")->orderBy('name')->get();
        $categories = Category::active()->orderBy('name')->get();
        $priceGroups = ShopPriceGroup::orderBy('name')->get();
        $products = Product::active()
            ->with(['category:id,name'])
            ->orderBy('name')
            ->get();
        $warehouses = Warehouse::orderBy('name')->get();

        $categoryPickerOptions = $categories->map(fn ($cat) => [
            'id' => (int) $cat->id,
            'name' => $cat->name,
        ])->values()->all();

        $productPickerOptions = $products->map(fn ($prod) => [
            'id' => (int) $prod->id,
            'sku' => (string) ($prod->sku ?? ''),
            'name' => $prod->name,
            'category_id' => (int) ($prod->category_id ?? 0),
            'category_name' => $prod->category?->name ?? 'Uncategorized',
            'default_warehouse_id' => $prod->default_warehouse_id ? (int) $prod->default_warehouse_id : null,
        ])->values()->all();

        // Check legacy presets availability for optional copy
        $legacyPresetCount = SortSheetPreset::count();

        $activeTab = $request->input('tab', 'sort-sheet');

        return view('print-v1.settings.index', compact(
            'sortSettings',
            'selectionSettings',
            'gridSettings',
            'portraitSettings',
            'shopSettings',
            'presets',
            'batches',
            'shops',
            'categories',
            'priceGroups',
            'products',
            'warehouses',
            'legacyPresetCount',
            'activeTab',
            'categoryPickerOptions',
            'productPickerOptions'
        ));
    }

    public function updateSection(UpdatePrintV1SectionSettingsRequest $request, string $section): RedirectResponse
    {
        if (! in_array($section, ['sort-sheet', 'selection', 'segregation-grid', 'shop-wise-portrait'], true)) {
            abort(404, 'Invalid print section.');
        }

        $validated = $request->validated();

        if ($request->has('filter_scope_present') || $request->has('category_ids')) {
            $validated['category_ids'] = array_map('intval', (array) $request->input('category_ids', []));
        }
        if ($request->has('filter_scope_present') || $request->has('product_ids')) {
            $validated['product_ids'] = array_map('intval', (array) $request->input('product_ids', []));
        }
        if ($request->has('grades')) {
            $validated['grades'] = array_values(array_filter((array) $request->input('grades', [])));
        }

        if ($request->has('warehouse_id')) {
            $validated['warehouse_id'] = ! empty($request->input('warehouse_id'))
                ? (int) $request->input('warehouse_id')
                : null;
        }
        if ($request->has('price_group_id')) {
            $validated['price_group_id'] = ! empty($request->input('price_group_id'))
                ? (int) $request->input('price_group_id')
                : null;
        }

        // Boolean settings: evaluate exact submitted booleans (handling both checked=1 and unchecked=0)
        if ($request->has('skip_empty_shops')) {
            $validated['skip_empty_shops'] = $request->boolean('skip_empty_shops');
        }

        if ($section === 'sort-sheet') {
            if ($request->has('code_sort')) {
                $validated['code_sort'] = $request->boolean('code_sort');
            }
            if ($request->has('show_category_titles')) {
                $validated['show_category_titles'] = $request->boolean('show_category_titles');
            }
            if ($request->has('separate_category_pages')) {
                $validated['separate_category_pages'] = $request->boolean('separate_category_pages');
            }
            if ($request->has('show_code')) {
                $validated['show_code'] = $request->boolean('show_code');
            }
        } elseif (in_array($section, ['selection', 'segregation-grid'], true)) {
            if ($request->has('items_per_page')) {
                $validated['items_per_page'] = (int) $request->input('items_per_page');
            }
        } elseif ($section === 'shop-wise-portrait') {
            if ($request->has('orientation')) {
                $validated['orientation'] = in_array($request->input('orientation'), ['portrait', 'landscape'], true)
                    ? $request->input('orientation')
                    : 'portrait';
            }
        }

        // Check if user wants global system default or user-specific override
        $scopeGlobal = $request->boolean('save_as_system_default', false) && ($request->user()?->hasRole('admin') || $request->user()?->isMainAdmin());
        $targetUserId = $scopeGlobal ? null : $request->user()?->id;

        PrintV1Setting::setForSection($section, $validated, $targetUserId);

        return redirect()->route('print.settings.index', ['tab' => $section])
            ->with('success', "Settings for '".ucwords(str_replace('-', ' ', $section))."' updated successfully!");
    }

    public function updateShops(UpdatePrintV1ShopSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $scopeGlobal = $request->boolean('save_as_system_default', false) && ($request->user()?->hasRole('admin') || $request->user()?->isMainAdmin());
        $targetUserId = $scopeGlobal ? null : $request->user()?->id;

        $shopPayload = [
            'included_inactive_shop_ids' => array_map('intval', (array) ($request->input('included_inactive_shop_ids', []))),
            'excluded_active_shop_ids' => array_map('intval', (array) ($request->input('excluded_active_shop_ids', []))),
        ];

        if ($request->has('default_skip_empty_shops')) {
            $shopPayload['default_skip_empty_shops'] = $request->boolean('default_skip_empty_shops');
        }

        PrintV1Setting::setForSection('shops', $shopPayload, $targetUserId);

        return redirect()->route('print.settings.index', ['tab' => 'shops'])
            ->with('success', 'Shop Printing Configuration saved successfully!');
    }

    public function storePreset(StorePrintV1PresetRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $settings = (array) ($validated['settings'] ?? []);
        if (isset($settings['category_ids'])) {
            $settings['category_ids'] = array_map('intval', (array) $settings['category_ids']);
        }
        if (isset($settings['product_ids'])) {
            $settings['product_ids'] = array_map('intval', (array) $settings['product_ids']);
        }

        PrintV1Preset::create([
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'section' => $validated['section'],
            'settings' => $settings,
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ]);

        return redirect()->route('print.settings.index', ['tab' => 'presets'])
            ->with('success', "Preset '{$validated['name']}' created successfully!");
    }

    public function updatePreset(StorePrintV1PresetRequest $request, PrintV1Preset $preset): RedirectResponse
    {
        $validated = $request->validated();
        $settings = (array) ($validated['settings'] ?? []);
        if (isset($settings['category_ids'])) {
            $settings['category_ids'] = array_map('intval', (array) $settings['category_ids']);
        }
        if (isset($settings['product_ids'])) {
            $settings['product_ids'] = array_map('intval', (array) $settings['product_ids']);
        }

        $preset->update([
            'name' => $validated['name'],
            'section' => $validated['section'],
            'settings' => $settings,
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ]);

        return redirect()->route('print.settings.index', ['tab' => 'presets'])
            ->with('success', "Preset '{$preset->name}' updated successfully!");
    }

    public function destroyPreset(Request $request, PrintV1Preset $preset): RedirectResponse
    {
        $this->authorizeAccess($request);

        $name = $preset->name;
        $preset->delete();

        return redirect()->route('print.settings.index', ['tab' => 'presets'])
            ->with('success', "Preset '{$name}' deleted successfully!");
    }

    public function reorderPresets(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $orderUuids = (array) $request->input('preset_ids', []);
        foreach ($orderUuids as $index => $uuid) {
            PrintV1Preset::where('uuid', $uuid)
                ->orWhere('id', $uuid)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function storePresetBatch(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'section' => 'nullable|string|in:sort-sheet,selection,segregation-grid,shop-wise-portrait',
            'preset_ids' => 'required|array|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        PrintV1PresetBatch::create([
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'section' => $validated['section'] ?? 'sort-sheet',
            'preset_ids' => $validated['preset_ids'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('print.settings.index', ['tab' => 'batches'])
            ->with('success', "Preset Batch '{$validated['name']}' created successfully!");
    }

    public function destroyPresetBatch(Request $request, PrintV1PresetBatch $batch): RedirectResponse
    {
        $this->authorizeAccess($request);

        $name = $batch->name;
        $batch->delete();

        return redirect()->route('print.settings.index', ['tab' => 'batches'])
            ->with('success', "Preset Batch '{$name}' deleted successfully!");
    }

    /**
     * Copy legacy presets into independent V1 records (safe read-only import).
     */
    public function copyLegacyPresets(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $legacyPresets = SortSheetPreset::all();
        $importedCount = 0;

        foreach ($legacyPresets as $legacy) {
            $mappedSection = match ($legacy->surface) {
                'segregation' => 'selection',
                'grid' => 'segregation-grid',
                'portrait' => 'shop-wise-portrait',
                default => 'sort-sheet',
            };

            PrintV1Preset::create([
                'user_id' => $request->user()?->id,
                'name' => $legacy->name.' (Imported)',
                'section' => $mappedSection,
                'settings' => [
                    'warehouse_id' => $legacy->warehouse_id,
                    'price_group_id' => $legacy->price_group_id,
                    'shop_id' => $legacy->shop_id,
                    'category_ids' => $legacy->category_ids ?? [],
                    'product_ids' => $legacy->product_ids ?? [],
                    'grades' => ['A', 'B', 'C', 'U'],
                    'skip_empty_shops' => true,
                    'separate_category_pages' => (bool) $legacy->separate_category_pages,
                    'page_break_category_ids' => $legacy->page_break_category_ids ?? [],
                ],
                'sort_order' => (int) $legacy->sort_order,
                'is_default' => false,
            ]);

            $importedCount++;
        }

        return redirect()->route('print.settings.index', ['tab' => 'presets'])
            ->with('success', "Successfully imported {$importedCount} legacy presets as independent V1 records!");
    }

    private function authorizeAccess(Request $request): void
    {
        if (! $request->user()?->can('sort.sheet.view')) {
            abort(403, 'Unauthorized access to Printing V1 settings.');
        }
    }
}
