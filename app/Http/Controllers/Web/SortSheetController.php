<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exports\SortSheetExport;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Database\Eloquent\Collection;
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

        $shops = Shop::where('status', 'active')->orderBy('name')->get();
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $priceGroups = ShopPriceGroup::orderBy('name')->get();

        return view('sort-sheet.index', compact('shops', 'categories', 'priceGroups'));
    }

    /**
     * Generate the sort sheet matrix from approved shop orders.
     */
    public function generate(Request $request): View
    {
        $this->authorizeAccess($request);

        $date = $request->input('date', app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());
        $shopId = $request->input('shop_id');
        $categoryId = $request->input('category_id');
        $priceGroupId = $request->input('price_group_id');

        $shops = Shop::where('status', 'active')->orderBy('name')->get();
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $priceGroups = ShopPriceGroup::orderBy('name')->get();

        // Apply shop-level filters
        $shopQuery = Shop::where('status', 'active');
        if ($shopId) {
            $shopQuery->where('id', $shopId);
        }
        if ($priceGroupId) {
            $shopQuery->where('shop_price_group_id', $priceGroupId);
        }
        $filteredShops = $shopQuery->orderBy('warehouse_tag')->get();

        // Load approved orders for the date
        $ordersQuery = ShopOrder::whereDate('business_date', $date)
            ->where('state', 'approved')
            ->whereIn('shop_id', $filteredShops->pluck('id'))
            ->with(['items.product.category', 'shop']);

        $orders = $ordersQuery->get();

        if ($orders->isEmpty()) {
            return view('sort-sheet.index', compact('shops', 'categories', 'priceGroups'))
                ->with('noOrders', true)
                ->with('filters', compact('date', 'shopId', 'categoryId', 'priceGroupId'));
        }

        // Build the pivot matrix: [product_id => [shop_id => approved_qty]]
        $matrix = [];    // [product_id => [shop_id => qty]]
        $productMeta = []; // [product_id => ['name', 'unit', 'category_id']]

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product) {
                    continue;
                }

                // Apply product category filter
                if ($categoryId && $product->category_id != $categoryId) {
                    continue;
                }

                $pid = $product->id;
                $sid = $order->shop_id;

                if (! isset($matrix[$pid])) {
                    $matrix[$pid] = [];
                    $productMeta[$pid] = [
                        'name' => $product->name,
                        'unit' => $product->unit,
                        'category_id' => $product->category_id,
                        'category_name' => $product->category?->name ?? '—',
                    ];
                }

                $matrix[$pid][$sid] = ($matrix[$pid][$sid] ?? 0) + (float) $item->approved_qty;
            }
        }

        // Sort products alphabetically by name
        uasort($matrix, fn ($a, $b): int => strcmp(
            $productMeta[array_key_first((array) $a) ?? 0]['name'] ?? '',
            $productMeta[array_key_first((array) $b) ?? 0]['name'] ?? '',
        ));
        uksort($matrix, fn ($a, $b): int => strcmp($productMeta[$a]['name'] ?? '', $productMeta[$b]['name'] ?? ''));

        return view('sort-sheet.index', compact(
            'shops',
            'categories',
            'priceGroups',
            'filteredShops',
            'matrix',
            'productMeta',
            'date',
        ))->with('filters', compact('date', 'shopId', 'categoryId', 'priceGroupId'));
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

        [$filteredShops, $matrix, $productMeta, $date] = $this->buildMatrixData($request);

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
        ));
    }

    /**
     * Print view — no sidebar, no buttons.
     */
    public function print(Request $request): View
    {
        $this->authorizeAccess($request);

        [$filteredShops, $matrix, $productMeta, $date] = $this->buildMatrixData($request);

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

    /**
     * Build the pivot matrix from request parameters — shared by export methods.
     *
     * @return array{0: Collection<int, Shop>, 1: array<int, array<int, float>>, 2: array<int, array<string, mixed>>, 3: string}
     */
    private function buildMatrixData(Request $request): array
    {
        $date = $request->input('date', app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());
        $shopId = $request->input('shop_id');
        $categoryId = $request->input('category_id');
        $priceGroupId = $request->input('price_group_id');

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
                if ($categoryId && $product->category_id != $categoryId) {
                    continue;
                }

                $pid = $product->id;
                $sid = $order->shop_id;

                if (! isset($matrix[$pid])) {
                    $matrix[$pid] = [];
                    $productMeta[$pid] = [
                        'name' => $product->name,
                        'unit' => $product->unit,
                        'category_id' => $product->category_id,
                        'category_name' => $product->category?->name ?? '—',
                    ];
                }

                $matrix[$pid][$sid] = ($matrix[$pid][$sid] ?? 0) + (float) $item->approved_qty;
            }
        }

        uksort($matrix, fn ($a, $b): int => strcmp($productMeta[$a]['name'] ?? '', $productMeta[$b]['name'] ?? ''));

        return [$filteredShops, $matrix, $productMeta, $date];
    }
}
