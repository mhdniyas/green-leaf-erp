<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseProductFilter;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseProductFilterController extends Controller
{
    public function __construct(private readonly CashbookShopSyncService $shopSyncService) {}

    public function index(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $filters = PurchaseProductFilter::query()
            ->withCount('products')
            ->with('createdBy')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.cashbook.finance.purchase.product-filters.index', array_merge(
            $this->purchaseLayoutData(),
            compact('filters')
        ));
    }

    public function create(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $categories = Category::query()
            ->with(['products' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        $uncategorizedProducts = Product::query()
            ->whereNull('category_id')
            ->orderBy('name')
            ->get();

        return view('admin.cashbook.finance.purchase.product-filters.create', array_merge(
            $this->purchaseLayoutData(),
            compact('categories', 'uncategorizedProducts')
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ], [
            'product_ids.required' => 'A product filter must contain at least one product.',
            'product_ids.min' => 'A product filter must contain at least one product.',
        ]);

        DB::transaction(function () use ($validated, $request): PurchaseProductFilter {
            $filter = PurchaseProductFilter::query()->create([
                'name' => trim($validated['name']),
                'created_by' => $request->user()?->id,
            ]);

            $uniqueProductIds = array_values(array_unique(array_map('intval', $validated['product_ids'])));
            $filter->products()->sync($uniqueProductIds);

            return $filter;
        });

        return redirect()
            ->route('admin.cashbook.finance.purchase.product-filters.index')
            ->with('success', 'Product filter created successfully.');
    }

    public function edit(Request $request, PurchaseProductFilter $productFilter): View
    {
        $this->ensureMainAdmin($request);

        $selectedProductIds = $productFilter->getProductIds();

        $categories = Category::query()
            ->with(['products' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        $uncategorizedProducts = Product::query()
            ->whereNull('category_id')
            ->orderBy('name')
            ->get();

        return view('admin.cashbook.finance.purchase.product-filters.edit', array_merge(
            $this->purchaseLayoutData(),
            compact('productFilter', 'selectedProductIds', 'categories', 'uncategorizedProducts')
        ));
    }

    public function update(Request $request, PurchaseProductFilter $productFilter): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ], [
            'product_ids.required' => 'A product filter must contain at least one product.',
            'product_ids.min' => 'A product filter must contain at least one product.',
        ]);

        DB::transaction(function () use ($productFilter, $validated): void {
            $productFilter->update([
                'name' => trim($validated['name']),
            ]);

            $uniqueProductIds = array_values(array_unique(array_map('intval', $validated['product_ids'])));
            $productFilter->products()->sync($uniqueProductIds);
        });

        return redirect()
            ->route('admin.cashbook.finance.purchase.product-filters.index')
            ->with('success', 'Product filter updated successfully.');
    }

    public function destroy(Request $request, PurchaseProductFilter $productFilter): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        DB::transaction(function () use ($productFilter): void {
            $productFilter->delete();
        });

        return redirect()
            ->route('admin.cashbook.finance.purchase.product-filters.index')
            ->with('success', 'Product filter deleted successfully.');
    }

    private function ensureMainAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        if (
            $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('accounts')
            || $user->hasRole('accountant')
            || $user->hasRole('account')
            || $user->hasRole('manager')
            || (property_exists($user, 'is_admin') && $user->is_admin)
            || $user->hasAnyPermission([
                'accounting.report.view',
                'accounting.dashboard.view',
                'accounting.ledger.view',
                'finance.dashboard.view',
            ])
        ) {
            return;
        }

        abort(403);
    }

    /** @return array<string, mixed> */
    private function purchaseLayoutData(): array
    {
        $shops = $this->shopSyncService->syncAndGetProfiles();

        return [
            'shops' => $shops,
            'companyAccounts' => CompanyAccount::where('enabled', true)->orderBy('name')->get(),
            'company' => config('greenleaf'),
            'currentShop' => $shops->first(),
            'productFilters' => PurchaseProductFilter::query()->orderBy('name')->get(['id', 'uuid', 'name']),
        ];
    }
}
