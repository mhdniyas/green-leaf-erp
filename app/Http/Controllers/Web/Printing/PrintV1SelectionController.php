<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Printing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Printing\Concerns\NormalizesPrintingFilters;
use App\Services\Printing\PrintV1SelectionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintV1SelectionController extends Controller
{
    use NormalizesPrintingFilters;

    public function __construct(
        private readonly PrintV1SelectionService $selectionService,
    ) {}

    /**
     * Display the Selection V1 page with integrated filters and shop-by-shop report preview.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $warehouseId = $request->integer('warehouse_id') ?: null;
        $filterOptions = $this->selectionService->filterOptions($warehouseId);
        $presets = $this->selectionService->userPresets($request->user());
        $presetBatches = $this->selectionService->userPresetBatches($request->user());

        $filters = $this->extractFilters($request, $this->selectionService->defaultOperationalDate());
        $selectionData = $this->selectionService->buildSelectionData($filters);

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

        return view('print-v1.selection.index', [
            'shops' => $filterOptions['shops'],
            'categories' => $filterOptions['categories'],
            'products' => $filterOptions['products'],
            'warehouses' => $filterOptions['warehouses'],
            'priceGroups' => $filterOptions['priceGroups'],
            'shopSections' => $selectionData['shopSections'],
            'shopPages' => $selectionData['shopPages'],
            'totalShopsWithOrders' => $selectionData['totalShopsWithOrders'],
            'totalItemsAllShops' => $selectionData['totalItemsAllShops'],
            'totalQtyAllShops' => $selectionData['totalQtyAllShops'],
            'formattedTotalQtyAllShops' => $selectionData['formattedTotalQtyAllShops'],
            'filteredShops' => $selectionData['filteredShops'],
            'candidateShops' => $selectionData['candidateShops'],
            'skippedEmptyShops' => $selectionData['skippedEmptyShops'],
            'selectedGrades' => $selectionData['selectedGrades'],
            'gradeHeading' => $selectionData['gradeHeading'],
            'date' => $selectionData['date'],
            'selectedWarehouse' => $selectionData['selectedWarehouse'],
            'filters' => $selectionData['filters'],
            'hasData' => $selectionData['hasData'],
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
     * Clean printable document view — shop-by-shop A4 portrait list, 36 rows per page.
     */
    public function print(Request $request): View
    {
        $this->authorizeAccess($request);

        $filters = $this->extractFilters($request, $this->selectionService->defaultOperationalDate());
        $selectionData = $this->selectionService->buildSelectionData($filters);

        $generatedBy = $request->user()?->name ?? 'Administrator';
        $generatedAt = now()->format('d M Y, h:i A');
        $companyName = 'Green Leaf Distribution';

        return view('print-v1.selection.print', [
            'shopSections' => $selectionData['shopSections'],
            'shopPages' => $selectionData['shopPages'],
            'totalShopsWithOrders' => $selectionData['totalShopsWithOrders'],
            'totalItemsAllShops' => $selectionData['totalItemsAllShops'],
            'totalQtyAllShops' => $selectionData['totalQtyAllShops'],
            'formattedTotalQtyAllShops' => $selectionData['formattedTotalQtyAllShops'],
            'filteredShops' => $selectionData['filteredShops'],
            'candidateShops' => $selectionData['candidateShops'],
            'skippedEmptyShops' => $selectionData['skippedEmptyShops'],
            'selectedGrades' => $selectionData['selectedGrades'],
            'gradeHeading' => $selectionData['gradeHeading'],
            'date' => $selectionData['date'],
            'selectedWarehouse' => $selectionData['selectedWarehouse'],
            'filters' => $selectionData['filters'],
            'hasData' => $selectionData['hasData'],
            'companyName' => $companyName,
            'generatedBy' => $generatedBy,
            'generatedAt' => $generatedAt,
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        if (! $request->user()?->can('sort.sheet.view')) {
            abort(403, 'Unauthorized access to Selection V1.');
        }
    }
}
