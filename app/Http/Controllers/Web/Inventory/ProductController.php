<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\StoreProductRequest;
use App\Http\Requests\Web\Inventory\UpdateProductRequest;
use App\Models\Product;
use App\Models\Warehouse;
use App\Repositories\Inventory\CategoryRepository;
use App\Services\Inventory\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $service,
        private readonly CategoryRepository $categories,
    ) {}

    public function index(Request $request): View
    {
        $products = $this->service->paginate(
            perPage: 20,
            categoryId: $request->integer('category_id') ?: null,
            search: $request->string('search')->toString() ?: null,
        );

        $categories = $this->categories->findAllActive();

        return view('inventory.products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        $categories = $this->categories->findAllActive();
        $warehouses = Warehouse::active()->orderBy('name')->get();

        return view('inventory.products.create', compact('categories', 'warehouses'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->service->create(ProductData::fromRequest($request));

        return redirect()->route('inventory.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): View
    {
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

    public function destroy(Product $product): RedirectResponse
    {
        $this->service->delete($product);

        return redirect()->route('inventory.products.index')
            ->with('success', 'Product deleted.');
    }
}
