<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exports\SortSheetExport;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Models\Warehouse;
use App\Models\SortSheetPreset;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SortSheetController extends Controller
{
    /**
     * Display the Sort Sheet index page with filters.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        [$shops, $categories, $priceGroups, $products, $warehouses] = $this->filterOptions($request);
        $presets = $this->userPresets($request);
        $presetBatches = $this->userPresetBatches($request);
        $surface = 'sort-sheet';
        $defaultDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        return view('sort-sheet.index', compact('shops', 'categories', 'priceGroups', 'products', 'warehouses', 'presets', 'presetBatches', 'surface', 'defaultDate'));
    }

    public function segregationIndex(Request $request): View
    {
        $this->authorizeAccess($request);

        [$shops, $categories, $priceGroups, $products, $warehouses] = $this->filterOptions($request, orderedOnly: true);
        $presets = $this->userPresets($request);
        $presetBatches = $this->userPresetBatches($request);
        $surface = 'segregation';
        $defaultDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        return view('sort-sheet.index', compact('shops', 'categories', 'priceGroups', 'products', 'warehouses', 'presets', 'presetBatches', 'surface', 'defaultDate'));
    }

    public function portraitIndex(Request $request): View
    {
        $this->authorizeAccess($request);

        [$shops, $categories, $priceGroups, $products, $warehouses] = $this->filterOptions($request, orderedOnly: true);
        $presets = $this->userPresets($request);
        $presetBatches = $this->userPresetBatches($request);
        $surface = 'portrait';
        $defaultDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        return view('sort-sheet.index', compact('shops', 'categories', 'priceGroups', 'products', 'warehouses', 'presets', 'presetBatches', 'surface', 'defaultDate'));
    }

    public function wideIndex(Request $request): View
    {
        $this->authorizeAccess($request);

        [$shops, $categories, $priceGroups, $products, $warehouses] = $this->filterOptions($request, orderedOnly: true);
        $presets = $this->userPresets($request);
        $presetBatches = $this->userPresetBatches($request);
        $surface = 'wide';
        $defaultDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        return view('sort-sheet.index', compact('shops', 'categories', 'priceGroups', 'products', 'warehouses', 'presets', 'presetBatches', 'surface', 'defaultDate'));
    }

    public function gridIndex(Request $request): View
    {
        $this->authorizeAccess($request);

        [$shops, $categories, $priceGroups, $products, $warehouses] = $this->filterOptions($request, orderedOnly: true);
        $presets = $this->userPresets($request);
        $presetBatches = $this->userPresetBatches($request);
        $surface = 'grid';
        $defaultDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        return view('sort-sheet.index', compact('shops', 'categories', 'priceGroups', 'products', 'warehouses', 'presets', 'presetBatches', 'surface', 'defaultDate'));
    }

    /**
     * Generate the sort sheet matrix from approved shop orders.
     */
    public function generate(Request $request): View
    {
        $this->authorizeAccess($request);

        [$shops, $categories, $priceGroups, $products, $warehouses] = $this->filterOptions($request, orderedOnly: $request->routeIs('segregation.*'));
        [$filteredShops, $matrix, $productMeta, $date, $selectedWarehouse] = $this->buildMatrixData($request);
        $presets = $this->userPresets($request);
        $presetBatches = $this->userPresetBatches($request);
        $filters = $this->filtersFromRequest($request);
        $surface = match (true) {
            $request->routeIs('*.shop-wise-portrait*') => 'portrait',
            $request->routeIs('*.shop-wise-wide*') => 'wide',
            $request->routeIs('*.grid*') => 'grid',
            $request->routeIs('segregation.*') => 'segregation',
            default => 'sort-sheet',
        };

        if (empty($matrix)) {
            return view('sort-sheet.index', compact('shops', 'categories', 'priceGroups', 'products', 'warehouses', 'presets', 'presetBatches', 'surface', 'selectedWarehouse'))
                ->with('noOrders', true)
                ->with('filters', $filters);
        }

        $sortSheetShareUrl = 'https://api.whatsapp.com/send?text='.rawurlencode(
            $this->buildSortSheetShareText($matrix, $productMeta, $date),
        );

        return view('sort-sheet.index', compact(
            'shops',
            'categories',
            'priceGroups',
            'products',
            'warehouses',
            'presets',
            'presetBatches',
            'filteredShops',
            'matrix',
            'productMeta',
            'date',
            'surface',
            'selectedWarehouse',
            'sortSheetShareUrl',
        ))->with('filters', $filters);
    }

    public function storePreset(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'surface' => 'nullable|string|in:sort-sheet,segregation,portrait,wide,grid',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'price_group_id' => 'nullable|integer|exists:shop_price_groups,id',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
            'separate_category_pages' => 'nullable|boolean',
            'page_break_category_ids' => 'nullable|array',
            'page_break_category_ids.*' => 'integer|exists:categories,id',
        ]);

        SortSheetPreset::create([
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'surface' => $validated['surface'] ?? 'sort-sheet',
            'warehouse_id' => $validated['warehouse_id'] ?? null,
            'price_group_id' => $validated['price_group_id'] ?? null,
            'shop_id' => $validated['shop_id'] ?? null,
            'category_ids' => $validated['category_ids'] ?? [],
            'product_ids' => $validated['product_ids'] ?? [],
            'separate_category_pages' => (bool) ($validated['separate_category_pages'] ?? false),
            'page_break_category_ids' => $validated['page_break_category_ids'] ?? [],
        ]);

        return redirect()->back()->with('success', "Custom order preset '{$validated['name']}' saved successfully!");
    }

    public function destroyPreset(Request $request, SortSheetPreset $preset): RedirectResponse
    {
        $this->authorizeAccess($request);

        $preset->delete();

        return redirect()->back()->with('success', 'Custom order preset deleted successfully.');
    }

    public function reorderPresets(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAccess($request);

        $orderIds = (array) $request->input('preset_ids', []);
        foreach ($orderIds as $index => $uuid) {
            SortSheetPreset::where('uuid', $uuid)
                ->orWhere('id', $uuid)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function movePresetUp(Request $request, SortSheetPreset $preset): RedirectResponse
    {
        $this->authorizeAccess($request);

        $presets = $this->userPresets($request);
        $index = $presets->search(fn ($p) => $p->id === $preset->id);

        if ($index !== false && $index > 0) {
            $prevPreset = $presets->get($index - 1);
            $temp = $preset->sort_order;
            $preset->sort_order = $prevPreset->sort_order === $temp ? $index - 1 : $prevPreset->sort_order;
            $prevPreset->sort_order = $temp === $prevPreset->sort_order ? $index : $temp;

            if ($preset->sort_order >= $prevPreset->sort_order) {
                $prevPreset->sort_order = $index;
                $preset->sort_order = $index - 1;
            }

            $preset->save();
            $prevPreset->save();
        }

        return redirect()->back()->with('success', "Preset '{$preset->name}' moved up.");
    }

    public function movePresetDown(Request $request, SortSheetPreset $preset): RedirectResponse
    {
        $this->authorizeAccess($request);

        $presets = $this->userPresets($request);
        $index = $presets->search(fn ($p) => $p->id === $preset->id);

        if ($index !== false && $index < $presets->count() - 1) {
            $nextPreset = $presets->get($index + 1);
            $temp = $preset->sort_order;
            $preset->sort_order = $nextPreset->sort_order === $temp ? $index + 1 : $nextPreset->sort_order;
            $nextPreset->sort_order = $temp === $nextPreset->sort_order ? $index : $temp;

            if ($preset->sort_order <= $nextPreset->sort_order) {
                $nextPreset->sort_order = $index;
                $preset->sort_order = $index + 1;
            }

            $preset->save();
            $nextPreset->save();
        }

        return redirect()->back()->with('success', "Preset '{$preset->name}' moved down.");
    }

    private function userPresets(Request $request): Collection
    {
        return SortSheetPreset::query()
            ->where(function ($query) use ($request) {
                $query->where('user_id', $request->user()?->id)
                    ->orWhereNull('user_id');
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    private function userPresetBatches(Request $request): Collection
    {
        return \App\Models\SortSheetPresetBatch::query()
            ->where(function ($query) use ($request) {
                $query->where('user_id', $request->user()?->id)
                    ->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get();
    }

    public function presetsIndex(Request $request): View
    {
        $this->authorizeAccess($request);

        [$shops, $categories, $priceGroups, $products, $warehouses] = $this->filterOptions($request);
        $presets = $this->userPresets($request);
        $presetBatches = $this->userPresetBatches($request);

        return view('sort-sheet.presets.index', compact('shops', 'categories', 'priceGroups', 'products', 'warehouses', 'presets', 'presetBatches'));
    }

    public function editPreset(Request $request, SortSheetPreset $preset): View
    {
        $this->authorizeAccess($request);

        [$shops, $categories, $priceGroups, $products, $warehouses] = $this->filterOptions($request);

        return view('sort-sheet.presets.edit', compact('preset', 'shops', 'categories', 'priceGroups', 'products', 'warehouses'));
    }

    public function updatePreset(Request $request, SortSheetPreset $preset): RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'surface' => 'nullable|string|in:sort-sheet,segregation,portrait,wide,grid',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'price_group_id' => 'nullable|integer|exists:shop_price_groups,id',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
            'separate_category_pages' => 'nullable|boolean',
            'page_break_category_ids' => 'nullable|array',
            'page_break_category_ids.*' => 'integer|exists:categories,id',
        ]);

        $preset->update([
            'name' => $validated['name'],
            'surface' => $validated['surface'] ?? 'sort-sheet',
            'warehouse_id' => $validated['warehouse_id'] ?? null,
            'price_group_id' => $validated['price_group_id'] ?? null,
            'shop_id' => $validated['shop_id'] ?? null,
            'category_ids' => $validated['category_ids'] ?? [],
            'product_ids' => $validated['product_ids'] ?? [],
            'separate_category_pages' => (bool) ($validated['separate_category_pages'] ?? false),
            'page_break_category_ids' => $validated['page_break_category_ids'] ?? [],
        ]);

        return redirect()->route('sort-sheet.presets.index')->with('success', "Preset '{$preset->name}' updated successfully!");
    }

    public function segregationGenerate(Request $request): View
    {
        return $this->generate($request);
    }

    public function portraitGenerate(Request $request): View
    {
        return $this->generate($request);
    }

    public function wideGenerate(Request $request): View
    {
        return $this->generate($request);
    }

    public function gridGenerate(Request $request): View
    {
        return $this->generate($request);
    }

    /**
     * Export the sort sheet as an Excel file.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorizeExport($request);

        [$filteredShops, $matrix, $productMeta, $date] = $this->buildMatrixData($request);

        $filename = "sort-sheet-{$date}.xlsx";
        $export = new SortSheetExport($matrix, $productMeta, $filteredShops, $date);

        return Excel::download($export, $filename);
    }

    /**
     * Export the sort sheet as a print-friendly PDF view (browser print).
     */
    public function exportPdf(Request $request): View
    {
        $this->authorizeExport($request);

        [$filteredShops, $matrix, $productMeta, $date, $selectedWarehouse] = $this->buildMatrixData($request);

        $generatedBy = $request->user()->name;
        $generatedAt = now()->format('d M Y, h:i A');
        $companyName = 'Green Leaf Distribution';

        return view('sort-sheet.print', compact(
            'filteredShops',
            'matrix',
            'productMeta',
            'date',
            'generatedBy',
            'generatedAt',
            'companyName',
            'selectedWarehouse',
        ));
    }

    public function storePresetBatch(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'preset_ids' => 'required|array|min:1',
        ]);

        \App\Models\SortSheetPresetBatch::create([
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'preset_ids' => $validated['preset_ids'],
        ]);

        return redirect()->route('sort-sheet.presets.index')->with('success', "Preset Batch '{$validated['name']}' saved successfully!");
    }

    public function destroyPresetBatch(Request $request, \App\Models\SortSheetPresetBatch $batch): RedirectResponse
    {
        $this->authorizeAccess($request);

        $name = $batch->name;
        $batch->delete();

        return redirect()->route('sort-sheet.presets.index')->with('success', "Preset Batch '{$name}' deleted successfully!");
    }

    /**
     * Batch print multiple selected presets in order.
     */
    public function batchPrintPresets(Request $request): View
    {
        $this->authorizeExport($request);

        $presetIds = (array) $request->input('preset_ids', []);
        if (empty($presetIds) && $request->filled('batch_id')) {
            $batch = \App\Models\SortSheetPresetBatch::where('uuid', $request->input('batch_id'))
                ->orWhere('id', $request->input('batch_id'))
                ->first();
            if ($batch) {
                $presetIds = $batch->preset_ids ?? [];
            }
        }

        $date = $request->input('date', app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());

        $userPresets = $this->userPresets($request);
        $selectedPresets = collect($presetIds)
            ->map(fn ($id) => $userPresets->first(fn ($p) => $p->uuid === $id || (string) $p->id === (string) $id))
            ->filter();

        $batchData = [];
        foreach ($selectedPresets as $preset) {
            $subReqParams = array_filter([
                'date' => $date,
                'warehouse_id' => $preset->warehouse_id,
                'price_group_id' => $preset->price_group_id,
                'shop_id' => $preset->shop_id,
                'separate_category_pages' => $preset->separate_category_pages ? 1 : null,
            ]);
            if (! empty($preset->category_ids)) {
                $subReqParams['category_ids'] = $preset->category_ids;
            }
            if (! empty($preset->product_ids)) {
                $subReqParams['product_ids'] = $preset->product_ids;
            }
            if (! empty($preset->page_break_category_ids)) {
                $subReqParams['page_break_category_ids'] = $preset->page_break_category_ids;
            }

            $subReq = Request::create(route('sort-sheet.generate'), 'GET', $subReqParams);
            $subReq->setUserResolver(fn () => $request->user());

            [$filteredShops, $matrix, $productMeta, $reqDate, $selectedWarehouse] = $this->buildMatrixData($subReq);

            $batchData[] = [
                'preset' => $preset,
                'filteredShops' => $filteredShops,
                'matrix' => $matrix,
                'productMeta' => $productMeta,
                'selectedWarehouse' => $selectedWarehouse,
            ];
        }

        $generatedBy = $request->user()->name;
        $generatedAt = now()->format('d M Y, h:i A');
        $companyName = 'Green Leaf Distribution';

        return view('sort-sheet.presets.batch-print', compact(
            'batchData',
            'date',
            'generatedBy',
            'generatedAt',
            'companyName'
        ));
    }

    public function segregationPdf(Request $request): View
    {
        $this->authorizeExport($request);

        [$filteredShops, $matrix, $productMeta, $date, $selectedWarehouse] = $this->buildMatrixData($request);

        $companyName = 'Green Leaf Distribution';

        return view('sort-sheet.segregation-print', compact(
            'filteredShops',
            'matrix',
            'productMeta',
            'date',
            'companyName',
            'selectedWarehouse',
        ));
    }

    public function segregationMatrixPrint(Request $request): View
    {
        $this->authorizeExport($request);

        [$filteredShops, $matrix, $productMeta, $date, $selectedWarehouse] = $this->buildMatrixData($request);

        $companyName = 'Green Leaf Distribution';
        $orientation = $request->input('orientation') === 'portrait' ? 'portrait' : 'landscape';

        return view('sort-sheet.segregation-matrix-print', compact(
            'filteredShops',
            'matrix',
            'productMeta',
            'date',
            'companyName',
            'selectedWarehouse',
            'orientation',
        ));
    }

    public function segregationGridPrint(Request $request): View
    {
        $this->authorizeExport($request);

        [$filteredShops, $matrix, $productMeta, $date, $selectedWarehouse] = $this->buildMatrixData($request);

        $companyName = 'Green Leaf Distribution';

        return view('sort-sheet.segregation-grid-print', compact(
            'filteredShops',
            'matrix',
            'productMeta',
            'date',
            'companyName',
            'selectedWarehouse',
        ));
    }

    /**
     * Print view — no sidebar, no buttons.
     */
    public function print(Request $request): View
    {
        $this->authorizeAccess($request);

        [$filteredShops, $matrix, $productMeta, $date, $selectedWarehouse] = $this->buildMatrixData($request);

        $generatedBy = $request->user()->name;
        $generatedAt = now()->format('d M Y, h:i A');
        $companyName = 'Green Leaf Distribution';

        return view('sort-sheet.print', compact(
            'filteredShops',
            'matrix',
            'productMeta',
            'date',
            'generatedBy',
            'generatedAt',
            'companyName',
            'selectedWarehouse',
        ));
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ────────────────────────────────────────────────────────────────────────────

    private function authorizeAccess(Request $request): void
    {
        if (! $request->user()?->can('sort.sheet.view')) {
            abort(403, 'Unauthorized. Sort Sheet access requires Admin, Purchase Manager, or Warehouse role.');
        }
    }

    private function authorizeExport(Request $request): void
    {
        if (! $request->user()?->can('sort.sheet.export')) {
            abort(403, 'Unauthorized. Sort Sheet export is not available for your role.');
        }
    }

    private function filterOptions(Request $request, bool $orderedOnly = false): array
    {
        $shops = Shop::where('status', 'active')->orderBy('name')->get();
        $priceGroups = ShopPriceGroup::orderBy('name')->get();
        $warehouses = Warehouse::active()->with('categories:id')->orderBy('name')->get(['id', 'name', 'code']);

        if ($orderedOnly) {
            [$categories, $products] = $this->orderedProductOptions($request);

            return [$shops, $categories, $priceGroups, $products, $warehouses];
        }

        $warehouseCategoryIds = $this->warehouseCategoryIds($request);
        $categoryQuery = Category::where('is_active', true)->with('warehouses:id');
        if ($warehouseCategoryIds !== null) {
            $categoryQuery->whereIn('id', $warehouseCategoryIds);
        }
        $productQuery = Product::active()->with('category.warehouses:id')->ordered();
        if ($warehouseCategoryIds !== null) {
            $productQuery->whereIn('category_id', $warehouseCategoryIds);
        }

        return [
            $shops,
            $categoryQuery->orderBy('name')->get(),
            $priceGroups,
            $productQuery->get(['id', 'name', 'sku', 'category_id']),
            $warehouses,
        ];
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, Category>, 1: \Illuminate\Support\Collection<int, Product>}
     */
    private function orderedProductOptions(Request $request): array
    {
        $filters = $this->filtersFromRequest($request);
        $warehouseCategoryIds = $this->warehouseCategoryIds($request);

        $shopQuery = Shop::where('status', 'active');
        if ($filters['shopId']) {
            $shopQuery->where('id', $filters['shopId']);
        }
        if ($filters['priceGroupId']) {
            $shopQuery->where('shop_price_group_id', $filters['priceGroupId']);
        }

        $orders = ShopOrder::whereDate('business_date', $filters['date'])
            ->where('state', 'approved')
            ->whereIn('shop_id', $shopQuery->pluck('id'))
            ->with(['items.product.category.warehouses:id'])
            ->get();

        $products = $orders
            ->flatMap->items
            ->filter(fn ($item): bool => (float) $item->approved_qty > 0 && $item->product !== null)
            ->map(fn ($item): Product => $item->product)
            ->when($warehouseCategoryIds !== null, fn ($products) => $products->filter(
                fn (Product $product): bool => in_array((int) $product->category_id, $warehouseCategoryIds, true),
            ))
            ->unique('id')
            ->sort(function (Product $a, Product $b): int {
                return strcmp(Product::sortableSku((string) $a->sku), Product::sortableSku((string) $b->sku))
                    ?: strcmp($a->name, $b->name);
            })
            ->values();

        $categories = $products
            ->map(fn (Product $product): ?Category => $product->category)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return [$categories, $products];
    }

    /**
     * Build the pivot matrix from request parameters — shared by export methods.
     *
     * @return array{0: Collection<int, Shop>, 1: array<int, array<int, float>>, 2: array<int, array<string, mixed>>, 3: string, 4: Warehouse|null}
     */
    private function buildMatrixData(Request $request): array
    {
        $filters = $this->filtersFromRequest($request);
        $date = $filters['date'];
        $shopId = $filters['shopId'];
        $categoryIds = $filters['categoryIds'];
        $productIds = $filters['productIds'];
        $priceGroupId = $filters['priceGroupId'];
        $warehouseCategoryIds = $this->warehouseCategoryIds($request);
        $selectedWarehouse = $filters['warehouseId']
            ? Warehouse::find($filters['warehouseId'])
            : null;

        $shopQuery = Shop::where('status', 'active');
        if ($shopId) {
            $shopQuery->where('id', $shopId);
        }
        if ($priceGroupId) {
            $shopQuery->where('shop_price_group_id', $priceGroupId);
        }
        $filteredShops = $shopQuery->orderBy('warehouse_tag')->get();

        $orders = ShopOrder::whereDate('business_date', $date)
            ->where('state', 'approved')
            ->whereIn('shop_id', $filteredShops->pluck('id'))
            ->with(['items.product.category'])
            ->get();

        $matrix = [];
        $productMeta = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product) {
                    continue;
                }
                if (! empty($categoryIds) && ! in_array((int) $product->category_id, $categoryIds, true)) {
                    continue;
                }
                if ($warehouseCategoryIds !== null && ! in_array((int) $product->category_id, $warehouseCategoryIds, true)) {
                    continue;
                }
                if (! empty($productIds) && ! in_array((int) $product->id, $productIds, true)) {
                    continue;
                }

                $pid = $product->id;
                $sid = $order->shop_id;

                if (! isset($matrix[$pid])) {
                    $matrix[$pid] = [];
                    $productMeta[$pid] = [
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'unit' => $product->unit,
                        'category_id' => $product->category_id,
                        'category_name' => $product->category?->name ?? '—',
                    ];
                }

                $matrix[$pid][$sid] = ($matrix[$pid][$sid] ?? 0) + (float) $item->approved_qty;
            }
        }

        $this->sortMatrixByCategoryOrderAndItemCode($matrix, $productMeta, $categoryIds);

        return [$filteredShops, $matrix, $productMeta, $date, $selectedWarehouse];
    }

    /**
     * @return array{date: string, shopId: string|null, categoryIds: array<int, int>, productIds: array<int, int>, priceGroupId: string|null, warehouseId: int|null}
     */
    private function filtersFromRequest(Request $request): array
    {
        $categoryIds = collect($request->input('category_ids', []))
            ->when($request->filled('category_id'), fn ($values) => $values->push($request->input('category_id')))
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        $productIds = collect($request->input('product_ids', []))
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        return [
            'date' => $request->input('date', app(PurchaserBusinessDayService::class)->operationalDate()->toDateString()),
            'shopId' => $request->input('shop_id'),
            'categoryIds' => $categoryIds,
            'productIds' => $productIds,
            'priceGroupId' => $request->input('price_group_id'),
            'warehouseId' => $request->integer('warehouse_id') ?: null,
            'separateCategoryPages' => (bool) $request->boolean('separate_category_pages', false),
        ];
    }

    /**
     * @return array<int, int>|null
     */
    private function warehouseCategoryIds(Request $request): ?array
    {
        $warehouseId = $request->integer('warehouse_id');
        if (! $warehouseId) {
            return null;
        }

        $warehouse = Warehouse::find($warehouseId);
        if (! $warehouse) {
            return [];
        }

        return $warehouse
            ->categories()
            ->pluck('categories.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, array<int, float>>  $matrix
     * @param  array<int, array<string, mixed>>  $productMeta
     * @param  array<int, int>  $categoryIds
     */
    private function sortMatrixByCategoryOrderAndItemCode(array &$matrix, array $productMeta, array $categoryIds = []): void
    {
        $categoryOrderMap = array_flip($categoryIds);

        uksort($matrix, function (int $a, int $b) use ($productMeta, $categoryOrderMap): int {
            $catA = (int) ($productMeta[$a]['category_id'] ?? 0);
            $catB = (int) ($productMeta[$b]['category_id'] ?? 0);

            $orderA = $categoryOrderMap[$catA] ?? PHP_INT_MAX;
            $orderB = $categoryOrderMap[$catB] ?? PHP_INT_MAX;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strcmp((string) ($productMeta[$a]['category_name'] ?? ''), (string) ($productMeta[$b]['category_name'] ?? ''))
                ?: strcmp(Product::sortableSku((string) ($productMeta[$a]['sku'] ?? '')), Product::sortableSku((string) ($productMeta[$b]['sku'] ?? '')))
                ?: strcmp((string) ($productMeta[$a]['name'] ?? ''), (string) ($productMeta[$b]['name'] ?? ''));
        });
    }

    /**
     * @param  array<int, array<int, float>>  $matrix
     * @param  array<int, array<string, mixed>>  $productMeta
     */
    private function buildSortSheetShareText(array $matrix, array $productMeta, string $date): string
    {
        $lines = [
            '*Sort Sheet Summary*',
            \Carbon\Carbon::parse($date)->format('d M Y'),
            '---',
            '',
        ];

        foreach ($matrix as $productId => $shopQtys) {
            $meta = $productMeta[$productId] ?? [];
            $lines[] = '*'.($meta['name'] ?? 'Product').'*';
            $lines[] = 'Total '.$this->formatShareQuantity(array_sum($shopQtys), (string) ($meta['unit'] ?? ''));
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    private function formatShareQuantity(float $quantity, string $unit): string
    {
        $formatted = $quantity == (int) $quantity
            ? (string) (int) $quantity
            : rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');

        return trim($formatted.' '.$unit);
    }

}
