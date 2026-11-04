<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\SellingPriceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SellingPriceCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $categories = SellingPriceCategory::query()
            ->withCount('products')
            ->with(['creator:id,name', 'updater:id,name'])
            ->orderBy('name')
            ->get();

        $rawPercentage = BusinessSetting::query()
            ->where('key', 'selling_price_default_percentage')
            ->value('value');

        $sellingPriceDefaultPercentage = ($rawPercentage !== null && is_numeric($rawPercentage) && (float) $rawPercentage >= 0)
            ? (float) $rawPercentage
            : SellingPriceManagementController::DEFAULT_PERCENTAGE;

        $unassignedProductsCount = Product::query()
            ->where('is_active', true)
            ->whereNull('selling_price_category_id')
            ->count();

        $totalActiveProductsCount = Product::query()
            ->where('is_active', true)
            ->count();

        return view('admin.selling-price.categories', [
            'categories' => $categories,
            'sellingPriceDefaultPercentage' => $sellingPriceDefaultPercentage,
            'unassignedProductsCount' => $unassignedProductsCount,
            'totalActiveProductsCount' => $totalActiveProductsCount,
            'activeTab' => 'categories',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:selling_price_categories,name'],
            'markup_percentage' => ['required', 'numeric', 'min:0', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        SellingPriceCategory::query()->create([
            'name' => trim($validated['name']),
            'markup_percentage' => (float) $validated['markup_percentage'],
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.selling-price.categories.index')
            ->with('success', "Selling price category \"{$validated['name']}\" created successfully.");
    }

    public function update(Request $request, SellingPriceCategory $category): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('selling_price_categories', 'name')->ignore($category->id)],
            'markup_percentage' => ['required', 'numeric', 'min:0', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => trim($validated['name']),
            'markup_percentage' => (float) $validated['markup_percentage'],
            'is_active' => $request->boolean('is_active', $category->is_active),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.selling-price.categories.index')
            ->with('success', "Selling price category \"{$category->name}\" updated successfully.");
    }

    public function toggleStatus(Request $request, SellingPriceCategory $category): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $newStatus = ! $category->is_active;
        $category->update([
            'is_active' => $newStatus,
            'updated_by' => $request->user()->id,
        ]);

        $statusLabel = $newStatus ? 'activated' : 'deactivated';

        return redirect()
            ->route('admin.selling-price.categories.index')
            ->with('success', "Selling price category \"{$category->name}\" was {$statusLabel}.");
    }

    public function destroy(Request $request, SellingPriceCategory $category): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($category->products()->exists()) {
            return redirect()
                ->route('admin.selling-price.categories.index')
                ->with('error', "Cannot delete category \"{$category->name}\" because it still has assigned products. Please remove or reassign them first.");
        }

        $categoryName = $category->name;
        $category->delete();

        return redirect()
            ->route('admin.selling-price.categories.index')
            ->with('success', "Selling price category \"{$categoryName}\" deleted successfully.");
    }

    public function assignedProducts(Request $request, SellingPriceCategory $category): JsonResponse
    {
        $this->authorizeAdmin($request);

        $products = $category->products()
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'category_id', 'selling_price_category_id', 'is_active'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit ?: 'kg',
                'category_name' => $p->category?->name ?? 'General',
                'is_active' => (bool) $p->is_active,
            ]);

        return response()->json([
            'status' => 'success',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'markup_percentage' => (float) $category->markup_percentage,
                'is_active' => (bool) $category->is_active,
            ],
            'products' => $products,
        ]);
    }

    public function searchProducts(Request $request, SellingPriceCategory $category): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = trim((string) $request->input('q', ''));

        $productsQuery = Product::query()
            ->where('is_active', true)
            ->with(['category:id,name', 'sellingPriceCategory:id,name']);

        if ($query !== '') {
            $productsQuery->where(function ($sub) use ($query) {
                $sub->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%");
                if (is_numeric($query)) {
                    $sub->orWhere('id', (int) $query);
                }
            });
        }

        $products = $productsQuery
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'name', 'sku', 'unit', 'category_id', 'selling_price_category_id'])
            ->map(function (Product $p) use ($category) {
                $isCurrent = $p->selling_price_category_id === $category->id;
                $isOther = ! empty($p->selling_price_category_id) && ! $isCurrent;

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'unit' => $p->unit ?: 'kg',
                    'category_name' => $p->category?->name ?? 'General',
                    'current_category_id' => $p->selling_price_category_id,
                    'current_category_name' => $p->sellingPriceCategory?->name,
                    'is_assigned_to_current' => $isCurrent,
                    'is_assigned_to_other' => $isOther,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $products,
        ]);
    }

    public function assignProducts(Request $request, SellingPriceCategory $category): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'confirm_reassign' => ['nullable', 'boolean'],
        ]);

        $productIds = array_values(array_unique(array_map('intval', $validated['product_ids'])));
        $confirmReassign = $request->boolean('confirm_reassign');

        // Check for conflicts: products already assigned to a DIFFERENT category
        $conflicts = Product::query()
            ->whereIn('id', $productIds)
            ->whereNotNull('selling_price_category_id')
            ->where('selling_price_category_id', '!=', $category->id)
            ->with('sellingPriceCategory:id,name')
            ->get(['id', 'name', 'sku', 'selling_price_category_id']);

        if ($conflicts->isNotEmpty() && ! $confirmReassign) {
            $conflictDetails = $conflicts->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'current_category' => $p->sellingPriceCategory?->name ?? 'Another Category',
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'requires_confirmation',
                    'message' => 'Some selected products are already assigned to other categories.',
                    'conflicts' => $conflictDetails,
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', 'Some selected products are already assigned to other categories. Please confirm reassignment.')
                ->with('conflicts', $conflictDetails);
        }

        DB::transaction(function () use ($productIds, $category): void {
            Product::query()
                ->whereIn('id', $productIds)
                ->update(['selling_price_category_id' => $category->id]);
        });

        $count = count($productIds);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Successfully assigned {$count} product(s) to {$category->name}.",
                'assigned_count' => $count,
            ]);
        }

        return redirect()
            ->route('admin.selling-price.categories.index')
            ->with('success', "Successfully assigned {$count} product(s) to {$category->name}.");
    }

    public function removeProduct(Request $request, SellingPriceCategory $category): JsonResponse|RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $productId = (int) $validated['product_id'];

        $updated = DB::transaction(function () use ($productId, $category): int {
            return Product::query()
                ->where('id', $productId)
                ->where('selling_price_category_id', $category->id)
                ->update(['selling_price_category_id' => null]);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $updated > 0 ? 'Product removed from category.' : 'Product was not in this category.',
                'removed' => $updated > 0,
            ]);
        }

        return redirect()
            ->route('admin.selling-price.categories.index')
            ->with('success', 'Product removed from category.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
