<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\ImportProductMeasuresJsonRequest;
use App\Http\Requests\Web\Inventory\StoreProductRequest;
use App\Http\Requests\Web\Inventory\UpdateProductMeasuresBulkRequest;
use App\Http\Requests\Web\Inventory\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\CategoryRepository;
use App\Services\Inventory\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $service,
        private readonly CategoryRepository $categories,
    ) {}

    public function index(Request $request): View
    {
        $status = in_array($request->string('status')->toString(), ['active', 'inactive'], true)
            ? $request->string('status')->toString()
            : null;
        $unit = $request->string('unit')->toString() ?: null;
        $products = $this->service->paginate(
            perPage: 20,
            categoryId: $request->integer('category_id') ?: null,
            search: $request->string('search')->toString() ?: null,
            status: $status,
            unit: $unit,
        );

        $categories = $this->categories->findAllActive();
        $warehouseReceivers = collect();
        if ($request->user()?->hasRole('admin')) {
            $warehouseReceivers = User::query()
                ->role('warehouse_receiver')
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        $deletedCount = $request->user()?->hasRole('admin')
            ? Product::onlyTrashed()->count()
            : 0;

        return view('inventory.products.index', compact('products', 'categories', 'warehouseReceivers', 'deletedCount'));
    }

    public function trash(Request $request): View
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $products = Product::onlyTrashed()
            ->with(['category', 'orderUnits'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('inventory.products.trash', compact('products'));
    }

    public function restore(Request $request, string $product): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $deletedProduct = Product::onlyTrashed()
            ->where('public_uuid', $product)
            ->orWhere('sku', $product)
            ->firstOrFail();

        $deletedProduct->restore();
        $deletedProduct->update(['is_active' => true]);

        activity()
            ->causedBy($request->user())
            ->performedOn($deletedProduct)
            ->log('Product restored');

        return redirect()
            ->route('inventory.products.trash')
            ->with('success', "{$deletedProduct->name} restored successfully.");
    }

    public function forceDelete(Request $request, string $product): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $deletedProduct = Product::onlyTrashed()
            ->where('public_uuid', $product)
            ->orWhere('sku', $product)
            ->firstOrFail();

        $hasHistory =
            $deletedProduct->stockBatches()->exists()
            || $deletedProduct->stockMovements()->exists()
            || $deletedProduct->dailyPrices()->exists()
            || $deletedProduct->wastageEntries()->exists();

        if ($hasHistory) {
            return back()->with(
                'error',
                'This product has transaction history and cannot be permanently deleted.'
            );
        }

        $deletedProduct->orderUnits()->delete();
        $deletedProduct->forceDelete();

        return redirect()
            ->route('inventory.products.trash')
            ->with('success', 'Product permanently deleted.');
    }

    public function create(Request $request): View
    {
        $categories = $this->categories->findAllActive();
        $warehouses = Warehouse::active()->orderBy('name')->get();
        $deletedCount = $request->user()?->hasRole('admin')
            ? Product::onlyTrashed()->count()
            : 0;

        return view('inventory.products.create', compact('categories', 'warehouses', 'deletedCount'));
    }

    public function bulkMeasures(Request $request): View
    {
        abort_unless($request->user()?->can('inventory.product.update'), 403);

        $products = $this->filteredBulkMeasureProducts($request);

        $units = ProductUnit::AVAILABLE_UNITS;
        $categories = $this->categories->findAllActive();

        return view('inventory.products.bulk-measures', compact('products', 'units', 'categories'));
    }

    public function receiverIndex(Request $request): View
    {
        abort_unless($request->user()?->hasRole('warehouse_receiver') || $request->user()?->can('warehouse.receive.view'), 403);

        $status = in_array($request->string('status')->toString(), ['active', 'inactive'], true)
            ? $request->string('status')->toString()
            : null;

        $products = $this->service->paginate(
            perPage: 20,
            categoryId: $request->integer('category_id') ?: null,
            search: $request->string('search')->toString() ?: null,
            status: $status,
        );
        $categories = $this->categories->findAllActive();

        return view('warehouse-receiver.products', compact('products', 'categories'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->service->create(ProductData::fromRequest($request));

        return redirect()->route('inventory.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): View
    {
        $product->load('orderUnits');
        $categories = $this->categories->findAllActive();
        $warehouses = Warehouse::active()->orderBy('name')->get();

        return view('inventory.products.edit', compact('product', 'categories', 'warehouses'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->service->update($product, ProductData::fromRequest($request));

        return redirect()->route('inventory.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function updateBulkMeasures(UpdateProductMeasuresBulkRequest $request): JsonResponse|RedirectResponse
    {
        $updated = $this->service->bulkUpdateMeasures($request->validatedProducts());
        $message = "{$updated} product measures updated.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'updated' => $updated,
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('inventory.products.measures.bulk', $request->only(['search', 'category_id', 'status', 'base_unit', 'measure_status']))
            ->with('success', $message);
    }

    public function exportBulkMeasures(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('inventory.product.update'), 403);

        $products = $this->filteredBulkMeasureProducts($request);
        $payload = [
            'format' => 'green-leaf-product-measures.v1',
            'exported_at' => now()->toIso8601String(),
            'filters' => $request->only(['search', 'category_id', 'status', 'base_unit', 'measure_status']),
            'products' => $products->map(fn (Product $product): array => [
                'public_uuid' => $product->public_uuid,
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category?->name,
                'is_active' => (bool) $product->is_active,
                'base_unit' => ProductUnit::normalizeUnit($product->unit),
                'measures' => $this->exportedMeasureRows($product),
            ])->values()->all(),
        ];

        $filename = 'green-leaf-product-measures-'.now()->format('Y-m-d-His').'.json';

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function importBulkMeasures(ImportProductMeasuresJsonRequest $request): RedirectResponse
    {
        $updated = $this->service->bulkUpdateMeasures($request->importedProducts());

        return redirect()
            ->route('inventory.products.measures.bulk', $request->only(['search', 'category_id', 'status', 'base_unit', 'measure_status']))
            ->with('success', "{$updated} product measures imported.");
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $products = $this->filteredExportProducts($request);
        $filename = 'green-leaf-products-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($products): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Category', 'Code', 'Product Name', 'Base Unit', 'Order Units', 'Status', 'Base Price']);

            $products->each(function (Product $product) use ($handle): void {
                fputcsv($handle, [
                    $product->category?->name ?? 'Uncategorized',
                    $product->sku,
                    $product->name,
                    strtoupper((string) $product->unit),
                    $this->orderUnitLabels($product)->join(' / '),
                    $product->is_active ? 'Active' : 'Inactive',
                    number_format((float) $product->base_price, 2, '.', ''),
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportPdf(Request $request): View
    {
        $products = $this->filteredExportProducts($request);
        $groupedProducts = $this->categoryGroupedProducts($products);
        $filters = $this->exportFilters($request);

        return view('inventory.products.pdf', compact('groupedProducts', 'products', 'filters'));
    }

    public function exportWhatsApp(Request $request): RedirectResponse
    {
        $products = $this->filteredExportProducts($request);
        $message = $this->buildWhatsAppProductText($products);

        return redirect()->away('https://api.whatsapp.com/send?text='.rawurlencode($message));
    }

    public function updateStatus(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->hasRole('admin') ||
            $user?->hasRole('purchaser') ||
            $user?->hasRole('purchase') ||
            $user?->can('inventory.product.status.update'),
            403
        );

        if ($request->has('show_in_purchaser_order')) {
            $validated = $request->validate([
                'show_in_purchaser_order' => ['required', 'boolean'],
            ]);

            $product->update([
                'show_in_purchaser_order' => (bool) $validated['show_in_purchaser_order'],
            ]);

            return redirect()
                ->back()
                ->with('success', "{$product->name} purchase visibility updated.");
        }

        abort_unless($user?->hasRole('admin') || $user?->can('inventory.product.status.update'), 403);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $this->service->updateStatus($product, (bool) $validated['is_active'], $request->user());

        return redirect()
            ->back()
            ->with('success', "{$product->name} marked ".($validated['is_active'] ? 'active.' : 'inactive.'));
    }

    public function updateStatusPermissions(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $permission = Permission::findOrCreate('inventory.product.status.update', 'web');
        $selectedUserIds = collect($validated['user_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique();

        User::query()
            ->role('warehouse_receiver')
            ->get()
            ->each(function (User $user) use ($permission, $selectedUserIds): void {
                if ($selectedUserIds->contains((int) $user->id)) {
                    $user->givePermissionTo($permission);

                    return;
                }

                $user->revokePermissionTo($permission);
            });

        return redirect()
            ->route('inventory.products.index', $request->only(['search', 'category_id', 'status']))
            ->with('success', 'Warehouse receiver product status permissions updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->service->delete($product);

        return redirect()->route('inventory.products.index')
            ->with('success', 'Product deleted.');
    }

    /**
     * @return Collection<int, Product>
     */
    private function filteredExportProducts(Request $request): Collection
    {
        $status = in_array($request->string('status')->toString(), ['active', 'inactive'], true)
            ? $request->string('status')->toString()
            : null;
        $unit = $request->string('unit')->toString() ?: null;

        return Product::query()
            ->with(['category', 'orderUnits'])
            ->when($request->integer('category_id') > 0, fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($unit, fn ($query) => $query->where('unit', $unit))
            ->when($request->string('search')->toString() !== '', function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('unit', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('is_active')
            ->ordered()
            ->get()
            ->sortBy([
                fn (Product $product): string => $product->category?->name ?? 'Uncategorized',
                fn (Product $product): string => $product->sku_sort_value,
                fn (Product $product): string => $product->name,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<string, Collection<int, Product>>
     */
    private function categoryGroupedProducts(Collection $products): Collection
    {
        return $products
            ->groupBy(fn (Product $product): string => $product->category?->name ?? 'Uncategorized')
            ->sortKeys();
    }

    /**
     * @return Collection<int, string>
     */
    private function orderUnitLabels(Product $product): Collection
    {
        if ($product->orderUnits->isEmpty()) {
            return collect([strtoupper((string) $product->unit)]);
        }

        return $product->orderUnits
            ->where('is_orderable', true)
            ->map(fn (ProductUnit $unit): string => (string) ($unit->label ?: strtoupper((string) $unit->unit)))
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private function exportFilters(Request $request): array
    {
        return collect($request->only(['search', 'category_id', 'status', 'unit']))
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => (string) $value)
            ->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function buildWhatsAppProductText(Collection $products): string
    {
        $lines = [
            'Green Leaf Product Catalog',
            'Generated: '.now()->format('d M Y, h:i A'),
            'Total products: '.$products->count(),
            '',
        ];

        $this->categoryGroupedProducts($products)->each(function (Collection $categoryProducts, string $categoryName) use (&$lines): void {
            $lines[] = '*'.$categoryName.'*';

            $categoryProducts->values()->each(function (Product $product, int $index) use (&$lines): void {
                $lines[] = ($index + 1).'. '.$product->sku.' - '.$product->name.' ('.strtoupper((string) $product->unit).')';
            });

            $lines[] = '';
        });

        return trim(implode("\n", $lines));
    }

    private function filteredBulkMeasureProducts(Request $request)
    {
        $status = in_array($request->string('status')->toString(), ['active', 'inactive'], true)
            ? $request->string('status')->toString()
            : null;
        $requestedBaseUnit = ProductUnit::normalizeUnit($request->string('base_unit')->toString());
        $baseUnit = in_array($requestedBaseUnit, ProductUnit::AVAILABLE_UNITS, true)
            ? $requestedBaseUnit
            : null;
        $measureStatus = in_array($request->string('measure_status')->toString(), ['missing_box', 'missing_piece', 'has_multiple'], true)
            ? $request->string('measure_status')->toString()
            : null;

        return Product::query()
            ->with(['category', 'orderUnits'])
            ->when($request->integer('category_id') > 0, fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($baseUnit, fn ($query) => $query->whereIn('unit', ProductUnit::databaseUnitsFor($baseUnit)))
            ->when($request->string('search')->toString() !== '', function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('is_active')
            ->ordered()
            ->get()
            ->when($measureStatus === 'missing_box', fn ($products) => $products->filter(fn (Product $product): bool => ! $product->orderUnits->contains('unit', 'box')))
            ->when($measureStatus === 'missing_piece', fn ($products) => $products->filter(fn (Product $product): bool => ! $product->orderUnits->contains('unit', 'piece')))
            ->when($measureStatus === 'has_multiple', fn ($products) => $products->filter(fn (Product $product): bool => $product->orderUnits->where('is_orderable', true)->count() > 1))
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportedMeasureRows(Product $product): array
    {
        if ($product->orderUnits->isEmpty()) {
            $baseUnit = ProductUnit::normalizeUnit($product->unit);

            return [[
                'unit' => $baseUnit,
                'label' => strtoupper($baseUnit),
                'conversion_to_base' => 1.0,
                'is_base' => true,
                'is_orderable' => true,
                'sort_order' => 0,
            ]];
        }

        return $product->orderUnits->map(fn (ProductUnit $unit): array => [
            'unit' => ProductUnit::normalizeUnit($unit->unit),
            'label' => $unit->label,
            'conversion_to_base' => $unit->conversion_to_base !== null ? (float) $unit->conversion_to_base : null,
            'is_base' => (bool) $unit->is_base,
            'is_orderable' => (bool) $unit->is_orderable,
            'sort_order' => (int) $unit->sort_order,
        ])->values()->all();
    }
}
