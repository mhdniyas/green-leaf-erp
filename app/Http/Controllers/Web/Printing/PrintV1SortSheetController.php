<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Printing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Printing\Concerns\NormalizesPrintingFilters;
use App\Services\Printing\PrintV1SortSheetService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintV1SortSheetController extends Controller
{
    use NormalizesPrintingFilters;

    public function __construct(
        private readonly PrintV1SortSheetService $sortSheetService,
    ) {}

    /**
     * Display the Sort Sheet V1 page with integrated filters and live report preview.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $warehouseId = $request->integer('warehouse_id') ?: null;
        $filterOptions = $this->sortSheetService->filterOptions($warehouseId);
        $presets = $this->sortSheetService->userPresets($request->user(), 'sort-sheet');
        $presetBatches = $this->sortSheetService->userPresetBatches($request->user());

        $filters = $this->extractFilters($request, $this->sortSheetService->defaultOperationalDate());
        $matrixResult = $this->sortSheetService->buildMatrix($filters);

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

        return view('print-v1.sort-sheet.index', [
            'shops' => $filterOptions['shops'],
            'categories' => $filterOptions['categories'],
            'products' => $filterOptions['products'],
            'warehouses' => $filterOptions['warehouses'],
            'priceGroups' => $filterOptions['priceGroups'],
            'matrix' => $matrixResult['matrix'],
            'productMeta' => $matrixResult['productMeta'],
            'filteredShops' => $matrixResult['filteredShops'],
            'candidateShops' => $matrixResult['candidateShops'],
            'skippedEmptyShops' => $matrixResult['skippedEmptyShops'],
            'selectedGrades' => $matrixResult['selectedGrades'],
            'gradeHeading' => $matrixResult['gradeHeading'],
            'date' => $matrixResult['date'],
            'selectedWarehouse' => $matrixResult['selectedWarehouse'],
            'filters' => $matrixResult['filters'],
            'grandTotal' => $matrixResult['grandTotal'],
            'shopTotals' => $matrixResult['shopTotals'],
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
     * Clean printable document view — no sidebars, no buttons, optimized for printing.
     */
    public function print(Request $request): View
    {
        $this->authorizeAccess($request);

        $filters = $this->extractFilters($request, $this->sortSheetService->defaultOperationalDate());
        $matrixResult = $this->sortSheetService->buildMatrix($filters);

        $generatedBy = $request->user()?->name ?? 'Administrator';
        $generatedAt = now()->format('d M Y, h:i A');
        $companyName = 'Green Leaf Distribution';

        return view('print-v1.sort-sheet.print', [
            'matrix' => $matrixResult['matrix'],
            'productMeta' => $matrixResult['productMeta'],
            'filteredShops' => $matrixResult['filteredShops'],
            'candidateShops' => $matrixResult['candidateShops'],
            'skippedEmptyShops' => $matrixResult['skippedEmptyShops'],
            'selectedGrades' => $matrixResult['selectedGrades'],
            'gradeHeading' => $matrixResult['gradeHeading'],
            'date' => $matrixResult['date'],
            'selectedWarehouse' => $matrixResult['selectedWarehouse'],
            'filters' => $matrixResult['filters'],
            'grandTotal' => $matrixResult['grandTotal'],
            'shopTotals' => $matrixResult['shopTotals'],
            'companyName' => $companyName,
            'generatedBy' => $generatedBy,
            'generatedAt' => $generatedAt,
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        if (! $request->user()?->can('sort.sheet.view')) {
            abort(403, 'Unauthorized access to Sort Sheet V1.');
        }
    }
}
