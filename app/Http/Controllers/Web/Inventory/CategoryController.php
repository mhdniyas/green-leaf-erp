<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\DTOs\Inventory\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\StoreCategoryRequest;
use App\Http\Requests\Web\Inventory\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Inventory\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $service,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(
            $request->user()?->can('inventory.category.view') ||
            $request->user()?->can('inventory.product.view') ||
            $request->user()?->hasRole('purchaser') ||
            $request->user()?->hasRole('admin'),
            403
        );

        $status = in_array($request->string('status')->toString(), ['active', 'inactive'], true)
            ? $request->string('status')->toString()
            : null;

        $categories = $this->service->paginateAdmin(
            perPage: 20,
            search: $request->string('search')->toString() ?: null,
            status: $status,
        );

        return view('inventory.categories.index', compact('categories'));
    }

    public function exportPdf(Request $request): View
    {
        abort_unless(
            $request->user()?->can('inventory.category.view') ||
            $request->user()?->can('inventory.product.view') ||
            $request->user()?->hasRole('purchaser') ||
            $request->user()?->hasRole('admin'),
            403
        );

        $rawCategoryIds = $request->input('category_ids');
        $categoryIds = [];

        if (is_array($rawCategoryIds)) {
            $categoryIds = array_map('intval', array_filter($rawCategoryIds));
        } elseif (is_string($rawCategoryIds) && trim($rawCategoryIds) !== '') {
            $categoryIds = array_map('intval', array_filter(explode(',', $rawCategoryIds)));
        }

        $search = $request->string('search')->toString() ?: null;
        $status = in_array($request->string('status')->toString(), ['active', 'inactive'], true)
            ? $request->string('status')->toString()
            : null;

        $query = Category::query();

        if (!empty($categoryIds)) {
            $query->whereIn('id', $categoryIds);
        } else {
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }
            if ($status) {
                $query->where('is_active', $status === 'active');
            }
        }

        $categories = $query->with(['products' => function ($q) {
            $q->orderBy('name');
        }])->orderBy('name')->get();

        $totalProducts = $categories->sum(fn ($c) => $c->products->count());

        return view('inventory.categories.pdf', compact('categories', 'totalProducts'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('inventory.category.create'), 403);

        return view('inventory.categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->service->create(CategoryData::fromRequest($request));

        return redirect()->route('inventory.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(Request $request, Category $category): View
    {
        abort_unless($request->user()?->can('inventory.category.update'), 403);

        return view('inventory.categories.edit', compact('category'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->service->update($category, CategoryData::fromRequest($request));

        return redirect()->route('inventory.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin') || $request->user()?->can('inventory.category.update'), 403);

        if ($category->products()->exists()) {
            return redirect()->route('inventory.categories.index')
                ->with('error', 'Cannot delete category that contains products.');
        }

        $this->service->delete($category);

        return redirect()->route('inventory.categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    public function products(Request $request, Category $category): View
    {
        abort_unless($request->user()?->can('inventory.category.update'), 403);

        $products = \App\Models\Product::query()->orderBy('name')->get();

        return view('inventory.categories.products', compact('category', 'products'));
    }

    public function updateProducts(Request $request, Category $category): RedirectResponse
    {
        abort_unless($request->user()?->can('inventory.category.update'), 403);

        $productIds = $request->input('product_ids', []);
        if (!is_array($productIds)) {
            $productIds = [];
        }
        $productIds = array_map('intval', $productIds);

        if (!empty($productIds)) {
            \App\Models\Product::query()
                ->whereIn('id', $productIds)
                ->update(['category_id' => $category->id]);
        }

        return redirect()->route('inventory.categories.index')
            ->with('success', 'Products assigned to category successfully.');
    }
}
