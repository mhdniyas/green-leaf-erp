<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('admin.user.view');

        $tab = $request->query('tab', 'warehouses');
        if (! in_array($tab, ['warehouses', 'unallocated'], true)) {
            $tab = 'warehouses';
        }

        $warehouses = Warehouse::withCount('products')->orderBy('name')->get();
        $unallocatedCount = Product::whereNull('default_warehouse_id')->count();
        $categories = Category::active()->orderBy('name')->get();
        $activeWarehouses = Warehouse::active()->orderBy('name')->get();

        $unallocatedProducts = null;
        if ($tab === 'unallocated') {
            $query = Product::whereNull('default_warehouse_id')
                ->with('category:id,name');

            if ($request->filled('search')) {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->integer('category_id'));
            }

            if ($request->filled('status')) {
                $status = $request->string('status')->toString();
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            $unallocatedProducts = $query->ordered()->paginate(25)->withQueryString();
        }

        return view('admin.warehouses.index', compact(
            'warehouses',
            'unallocatedCount',
            'unallocatedProducts',
            'tab',
            'categories',
            'activeWarehouses'
        ));
    }

    public function allocateProduct(Request $request): RedirectResponse
    {
        Gate::authorize('admin.user.view');

        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        $product->update(['default_warehouse_id' => $warehouse->id]);

        return back()->with('success', "Product '{$product->name}' allocated to {$warehouse->name} successfully.");
    }

    public function bulkAllocate(Request $request): RedirectResponse
    {
        Gate::authorize('admin.user.view');

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
        ]);

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
        $count = Product::whereIn('id', $validated['product_ids'])
            ->update(['default_warehouse_id' => $warehouse->id]);

        return back()->with('success', "{$count} products allocated to {$warehouse->name} successfully.");
    }

    public function create(): View
    {
        Gate::authorize('admin.user.view');

        $categories = Category::active()->orderBy('name')->get();
        $products = Product::active()
            ->with('category:id,name')
            ->ordered()
            ->get(['id', 'name', 'sku', 'unit', 'category_id', 'default_warehouse_id', 'is_active']);

        return view('admin.warehouses.create', compact('categories', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('admin.user.view');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:warehouses,code'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')],
            'product_ids' => ['array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
        ]);

        $warehouse = Warehouse::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => $request->boolean('is_active', true),
        ]);
        $warehouse->categories()->sync($validated['category_ids'] ?? []);
        $this->syncDefaultProducts($warehouse, $validated['product_ids'] ?? []);

        return redirect()->route('admin.warehouses.index')
            ->with('success', 'Warehouse created successfully.');
    }

    public function edit(Warehouse $warehouse): View
    {
        Gate::authorize('admin.user.view');

        $warehouse->load('categories:id');
        $categories = Category::active()->orderBy('name')->get();
        $products = Product::active()
            ->with('category:id,name')
            ->ordered()
            ->get(['id', 'name', 'sku', 'unit', 'category_id', 'default_warehouse_id', 'is_active']);
        $defaultProducts = $products->where('default_warehouse_id', $warehouse->id)->values();

        return view('admin.warehouses.edit', compact('warehouse', 'categories', 'products', 'defaultProducts'));
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('admin.user.view');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', "unique:warehouses,code,{$warehouse->id}"],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')],
            'product_ids' => ['array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
        ]);

        $warehouse->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => $request->boolean('is_active', true),
        ]);
        $warehouse->categories()->sync($validated['category_ids'] ?? []);
        $this->syncDefaultProducts($warehouse, $validated['product_ids'] ?? []);

        return redirect()->route('admin.warehouses.edit', $warehouse)
            ->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('admin.user.view');

        $warehouse->delete();

        return redirect()->route('admin.warehouses.index')
            ->with('success', 'Warehouse deleted successfully.');
    }

    /**
     * @param  array<int, int|string>  $productIds
     */
    private function syncDefaultProducts(Warehouse $warehouse, array $productIds): void
    {
        $productIds = collect($productIds)
            ->filter(fn ($id): bool => $id !== null && $id !== '')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        Product::where('default_warehouse_id', $warehouse->id)
            ->when(! empty($productIds), fn ($query) => $query->whereNotIn('id', $productIds))
            ->update(['default_warehouse_id' => null]);

        if (! empty($productIds)) {
            Product::whereIn('id', $productIds)->update(['default_warehouse_id' => $warehouse->id]);
        }
    }
}
