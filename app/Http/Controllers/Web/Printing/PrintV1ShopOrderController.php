<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Printing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Printing\Concerns\NormalizesPrintingFilters;
use App\Services\Printing\PrintV1ShopOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintV1ShopOrderController extends Controller
{
    use NormalizesPrintingFilters;

    public function __construct(
        private readonly PrintV1ShopOrderService $shopOrderService,
    ) {}

    /**
     * Backward-compatibility redirect: /print/shop-wise-portrait -> /print/shop-order
     */
    public function legacyRedirect(Request $request): RedirectResponse
    {
        return redirect()->route('print.shop-order', $request->query(), 301);
    }

    /**
     * Display the Shop Order V1 page with integrated filters and shop-by-shop preview.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $warehouseId = $request->integer('warehouse_id') ?: null;
        $filterOptions = $this->shopOrderService->filterOptions($warehouseId);
        $presets = $this->shopOrderService->userPresets($request->user());
        $presetBatches = $this->shopOrderService->userPresetBatches($request->user());

        $filters = $this->extractFilters($request, $this->shopOrderService->defaultOperationalDate());
        $shopOrderData = $this->shopOrderService->buildShopOrderData($filters);

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

        return view('print-v1.shop-order.index', [
            'shops' => $filterOptions['shops'],
            'categories' => $filterOptions['categories'],
            'products' => $filterOptions['products'],
            'warehouses' => $filterOptions['warehouses'],
            'priceGroups' => $filterOptions['priceGroups'],
            'shopSections' => $shopOrderData['shopSections'],
            'totalShopsWithOrders' => $shopOrderData['totalShopsWithOrders'],
            'totalItemsAllShops' => $shopOrderData['totalItemsAllShops'],
            'totalQtyAllShops' => $shopOrderData['totalQtyAllShops'],
            'formattedTotalQtyAllShops' => $shopOrderData['formattedTotalQtyAllShops'],
            'filteredShops' => $shopOrderData['filteredShops'],
            'candidateShops' => $shopOrderData['candidateShops'],
            'skippedEmptyShops' => $shopOrderData['skippedEmptyShops'],
            'selectedGrades' => $shopOrderData['selectedGrades'],
            'gradeHeading' => $shopOrderData['gradeHeading'],
            'date' => $shopOrderData['date'],
            'selectedWarehouse' => $shopOrderData['selectedWarehouse'],
            'filters' => $shopOrderData['filters'],
            'hasData' => $shopOrderData['hasData'],
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
     * Clean printable document view — Shop Order A4 portrait format, 1 shop per page.
     */
    public function print(Request $request): View
    {
        $this->authorizeAccess($request);

        $filters = $this->extractFilters($request, $this->shopOrderService->defaultOperationalDate());
        $shopOrderData = $this->shopOrderService->buildShopOrderData($filters);

        $companyName = 'Green Leaf Distribution';

        return view('print-v1.shop-order.print', [
            'shopSections' => $shopOrderData['shopSections'],
            'totalShopsWithOrders' => $shopOrderData['totalShopsWithOrders'],
            'totalItemsAllShops' => $shopOrderData['totalItemsAllShops'],
            'totalQtyAllShops' => $shopOrderData['totalQtyAllShops'],
            'formattedTotalQtyAllShops' => $shopOrderData['formattedTotalQtyAllShops'],
            'filteredShops' => $shopOrderData['filteredShops'],
            'candidateShops' => $shopOrderData['candidateShops'],
            'skippedEmptyShops' => $shopOrderData['skippedEmptyShops'],
            'selectedGrades' => $shopOrderData['selectedGrades'],
            'gradeHeading' => $shopOrderData['gradeHeading'],
            'date' => $shopOrderData['date'],
            'selectedWarehouse' => $shopOrderData['selectedWarehouse'],
            'filters' => $shopOrderData['filters'],
            'hasData' => $shopOrderData['hasData'],
            'companyName' => $companyName,
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        if (! $request->user()?->can('sort.sheet.view')) {
            abort(403, 'Unauthorized access to Shop Order V1.');
        }
    }
}
