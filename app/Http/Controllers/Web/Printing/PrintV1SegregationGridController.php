<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Printing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Printing\Concerns\NormalizesPrintingFilters;
use App\Services\Printing\PrintV1SegregationGridService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintV1SegregationGridController extends Controller
{
    use NormalizesPrintingFilters;

    public function __construct(
        private readonly PrintV1SegregationGridService $gridService,
    ) {}

    /**
     * Display the Segregation Grid V1 page with integrated filters and matrix preview.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $warehouseId = $request->integer('warehouse_id') ?: null;
        $filterOptions = $this->gridService->filterOptions($warehouseId);
        $presets = $this->gridService->userPresets($request->user());
        $presetBatches = $this->gridService->userPresetBatches($request->user());

        $filters = $this->extractFilters($request, $this->gridService->defaultOperationalDate());
        $gridData = $this->gridService->buildGridData($filters);

        $categoryPickerOptions = $filterOptions['categories']->map(fn ($cat) => [
            'id' => (string) $cat->id,
            'name' => $cat->name,
            'warehouse_ids' => $cat->warehouses->pluck('id')->map(fn ($id) => (string) $id)->values(),
        ])->values();

        $productPickerOptions = $filterOptions['products']->map(fn ($prod) => [
            'id' => (string) $prod->id,
            'sku' => (string) ($prod->sku ?? ''),
            'name' => $prod->name,
            'category_id' => (string) ($prod->category_id ?? ''),
            'category_name' => $prod->category?->name ?? 'Uncategorized',
            'default_warehouse_id' => $prod->default_warehouse_id ? (string) $prod->default_warehouse_id : '',
        ])->values();

        $shopPickerOptions = $filterOptions['shops']->map(fn ($shop) => [
            'id' => (string) $shop->id,
            'name' => $shop->name,
            'warehouse_tag' => (string) ($shop->warehouse_tag ?? ''),
        ])->values();

        $warehousePickerOptions = $filterOptions['warehouses']->map(fn ($wh) => [
            'id' => (string) $wh->id,
            'name' => $wh->name,
            'code' => $wh->code,
        ])->values();

        $priceGroupPickerOptions = $filterOptions['priceGroups']->map(fn ($pg) => [
            'id' => (string) $pg->id,
            'name' => $pg->name,
        ])->values();

        return view('print-v1.segregation-grid.index', [
            'shops' => $filterOptions['shops'],
            'categories' => $filterOptions['categories'],
            'products' => $filterOptions['products'],
            'warehouses' => $filterOptions['warehouses'],
            'priceGroups' => $filterOptions['priceGroups'],
            'gridPages' => $gridData['gridPages'],
            'matrix' => $gridData['matrix'],
            'productMeta' => $gridData['productMeta'],
            'filteredShops' => $gridData['filteredShops'],
            'candidateShops' => $gridData['candidateShops'],
            'skippedEmptyShops' => $gridData['skippedEmptyShops'],
            'selectedGrades' => $gridData['selectedGrades'],
            'gradeHeading' => $gridData['gradeHeading'],
            'date' => $gridData['date'],
            'selectedWarehouse' => $gridData['selectedWarehouse'],
            'filters' => $gridData['filters'],
            'grandTotal' => $gridData['grandTotal'],
            'shopTotals' => $gridData['shopTotals'],
            'shopWidth' => $gridData['shopWidth'],
            'hasData' => $gridData['hasData'],
            'categoryPickerOptions' => $categoryPickerOptions,
            'productPickerOptions' => $productPickerOptions,
            'shopPickerOptions' => $shopPickerOptions,
            'warehousePickerOptions' => $warehousePickerOptions,
            'priceGroupPickerOptions' => $priceGroupPickerOptions,
            'presets' => $presets,
            'presetBatches' => $presetBatches,
        ]);
    }

    /**
     * Clean printable document view — Segregation Grid landscape layout, 9 rows per page.
     */
    public function print(Request $request): View
    {
        $this->authorizeAccess($request);

        $filters = $this->extractFilters($request, $this->gridService->defaultOperationalDate());
        $gridData = $this->gridService->buildGridData($filters);

        $companyName = 'Green Leaf Distribution';

        return view('print-v1.segregation-grid.print', [
            'gridPages' => $gridData['gridPages'],
            'matrix' => $gridData['matrix'],
            'productMeta' => $gridData['productMeta'],
            'filteredShops' => $gridData['filteredShops'],
            'candidateShops' => $gridData['candidateShops'],
            'skippedEmptyShops' => $gridData['skippedEmptyShops'],
            'selectedGrades' => $gridData['selectedGrades'],
            'gradeHeading' => $gridData['gradeHeading'],
            'date' => $gridData['date'],
            'selectedWarehouse' => $gridData['selectedWarehouse'],
            'filters' => $gridData['filters'],
            'grandTotal' => $gridData['grandTotal'],
            'shopTotals' => $gridData['shopTotals'],
            'shopWidth' => $gridData['shopWidth'],
            'hasData' => $gridData['hasData'],
            'companyName' => $companyName,
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        if (! $request->user()?->can('sort.sheet.view')) {
            abort(403, 'Unauthorized access to Segregation Grid V1.');
        }
    }
}
