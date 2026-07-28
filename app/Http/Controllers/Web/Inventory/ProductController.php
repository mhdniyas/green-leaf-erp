<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\StoreProductRequest;
use App\Http\Requests\Web\Inventory\UpdateProductMeasuresBulkRequest;
use App\Http\Requests\Web\Inventory\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\CategoryRepository;
use App\Services\Inventory\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

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
        $products = $this->service->paginate(
            perPage: 20,
            categoryId: $request->integer('category_id') ?: null,
            search: $request->string('search')->toString() ?: null,
            status: $status,
        );

        $categories = $this->categories->findAllActive();
        $warehouseReceivers = collect();
        if ($request->user()?->hasRole('admin')) {
            $warehouseReceivers = User::query()
                ->role('warehouse_receiver')
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        return view('inventory.products.index', compact('products', 'categories', 'warehouseReceivers'));
    }

    public function create(): View
    {
        $categories = $this->categories->findAllActive();
        $warehouses = Warehouse::active()->orderBy('name')->get();

        return view('inventory.products.create', compact('categories', 'warehouses'));
    }

    public function bulkMeasures(Request $request): View
    {
        abort_unless($request->user()?->can('inventory.product.update'), 403);

        $status = in_array($request->string('status')->toString(), ['active', 'inactive'], true)
            ? $request->string('status')->toString()
            : null;
        $baseUnit = in_array($request->string('base_unit')->toString(), ProductUnit::AVAILABLE_UNITS, true)
            ? $request->string('base_unit')->toString()
            : null;
        $measureStatus = in_array($request->string('measure_status')->toString(), ['missing_box', 'missing_piece', 'has_multiple'], true)
            ? $request->string('measure_status')->toString()
            : null;

        $products = Product::query()
            ->with(['category', 'orderUnits'])
            ->when($request->integer('category_id') > 0, fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($baseUnit, fn ($query) => $query->where('unit', $baseUnit))
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

    public function updateBulkMeasures(UpdateProductMeasuresBulkRequest $request): RedirectResponse
    {
        $updated = $this->service->bulkUpdateMeasures($request->validatedProducts());

        return redirect()
            ->route('inventory.products.measures.bulk', $request->only(['search', 'category_id', 'status', 'base_unit', 'measure_status']))
            ->with('success', "{$updated} product measures updated.");
    }

    public function updateStatus(Request $request, Product $product): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin') || $request->user()?->can('inventory.product.status.update'), 403);

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
}
