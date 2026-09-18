<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Purchasing\PurchaserAllotmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProductPurchaserAllotmentController extends Controller
{
    public function __construct(
        private readonly PurchaserAllotmentService $allotmentService,
        private readonly CashbookShopSyncService $shopSyncService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        $categoryId = $request->filled('category_id') ? $request->integer('category_id') : null;
        $purchaserId = $request->filled('purchaser_id') ? $request->integer('purchaser_id') : null;

        $products = $this->allotmentService->getOverviewPaginated($search, $categoryId, $purchaserId);

        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $purchasers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['purchase', 'purchaser', 'admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.cashbook.finance.purchase.product_allotments.index', array_merge(
            $this->purchaseLayoutData(),
            compact('products', 'categories', 'purchasers', 'search', 'categoryId', 'purchaserId')
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $productIds = $request->has('product_ids')
            ? (array) $request->input('product_ids')
            : [(int) $request->input('product_id')];

        $validated = $request->validate([
            'purchaser_user_id' => ['required', 'integer', 'exists:users,id'],
            'effective_from' => ['required', 'date'],
        ]);

        $this->allotmentService->bulkAssignPurchaser(
            $productIds,
            (int) $validated['purchaser_user_id'],
            (string) $validated['effective_from'],
            $request->user()?->id
        );

        return redirect()
            ->route('admin.cashbook.finance.purchase.product-allotments.index', $request->only(['search', 'category_id', 'purchaser_id']))
            ->with('success', 'Purchaser allotment saved successfully.');
    }

    public function history(Request $request, Product $product): View
    {
        $this->ensureMainAdmin($request);

        $history = $this->allotmentService->getAllotmentHistory($product->id);
        $product->load(['category', 'currentPurchaserAllotment.purchaser']);

        return view('admin.cashbook.finance.purchase.product_allotments.history', array_merge(
            $this->purchaseLayoutData(),
            compact('product', 'history')
        ));
    }

    private function ensureMainAdmin(Request $request): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (
            $user->hasRole('admin')
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
        ];
    }
}
