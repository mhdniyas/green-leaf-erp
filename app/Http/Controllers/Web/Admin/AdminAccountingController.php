<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\GenerateShopAccountingInvoiceRequest;
use App\Http\Requests\Web\Admin\ReviewShopAccountingEntryRequest;
use App\Http\Requests\Web\Admin\StoreShopAccountingCategoryRequest;
use App\Http\Requests\Web\Admin\StoreShopAccountingEntryRequest;
use App\Http\Requests\Web\Admin\UpdateShopAccountingEntryRequest;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingInvoice;
use App\Models\User;
use App\Services\Finance\AdminFinancePillarService;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Finance\ShopAccountingInvoiceService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

class AdminAccountingController extends Controller
{
    public function __construct(
        private readonly AdminFinancePillarService $financePillars,
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
        private readonly ShopAccountingInvoiceService $shopAccountingInvoiceService,
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureAdminAccess($request);

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $finance = $this->financePillars->forPeriod($date, $date);
        $ownedMetrics = $this->ownedShopAccountingService->dashboardMetrics($date);
        $eligibleShops = $this->ownedShopAccountingService->eligibleShops()->take(6);

        return view('admin.accounting.index', compact('date', 'finance', 'ownedMetrics', 'eligibleShops'));
    }

    public function ownedShopsIndex(Request $request): View
    {
        $this->ensureAdminAccess($request);

        $shops = $this->ownedShopAccountingService->eligibleShops();

        return view('admin.accounting.owned_shops.index', compact('shops'));
    }

    public function ownedShopShow(Request $request, Shop $shop): View
    {
        $this->ensureAdminAccess($request);
        $shop = $this->loadEligibleShop($shop);

        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
        $entry = $this->ownedShopAccountingService->entryForDate($shop, $selectedDate);
        $availableCategories = $this->ownedShopAccountingService->availableCategoriesForShop($shop);
        $recentEntries = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->with(['lines.category', 'submittedBy', 'reviewedBy'])
            ->latest('business_date')
            ->limit(12)
            ->get();
        $invoices = ShopAccountingInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['generatedBy', 'splits'])
            ->latest('period_end')
            ->limit(12)
            ->get();

        $incomeTotal = $entry instanceof ShopAccountingEntry
            ? round((float) $entry->lines->where('type', 'income')->sum('amount'), 2)
            : 0.0;
        $expenseTotal = $entry instanceof ShopAccountingEntry
            ? round((float) $entry->lines->where('type', 'expense')->sum('amount'), 2)
            : 0.0;

        return view('admin.accounting.owned_shops.show', [
            'shop' => $shop->loadMissing(['ownerships.user', 'users']),
            'selectedDate' => $selectedDate,
            'entry' => $entry,
            'availableCategories' => $availableCategories,
            'globalCategories' => $availableCategories->whereNull('shop_id')->values(),
            'shopCategories' => $availableCategories->where('shop_id', $shop->id)->values(),
            'recentEntries' => $recentEntries,
            'invoices' => $invoices,
            'ownershipTotal' => $this->ownedShopAccountingService->ownershipPercentageTotal($shop),
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'netAmount' => round($incomeTotal - $expenseTotal, 2),
        ]);
    }

    public function storeOwnerships(Request $request, Shop $shop): RedirectResponse
    {
        $this->ensureAdminAccess($request);
        $shop = $this->loadEligibleShop($shop);

        $request->merge([
            'ownerships' => collect($request->input('ownerships', []))
                ->filter(function ($ownership): bool {
                    if (! is_array($ownership)) {
                        return false;
                    }

                    return filled($ownership['owner_name'] ?? null)
                        || filled($ownership['ownership_percent'] ?? null)
                        || filled($ownership['role_label'] ?? null)
                        || filled($ownership['user_id'] ?? null);
                })
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'ownerships' => ['required', 'array', 'min:1'],
            'ownerships.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'ownerships.*.owner_name' => ['required', 'string', 'max:255'],
            'ownerships.*.ownership_percent' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'ownerships.*.role_label' => ['nullable', 'string', 'max:100'],
        ]);

        $ownershipTotal = round((float) collect($validated['ownerships'])->sum('ownership_percent'), 2);
        if (abs($ownershipTotal - 100.00) > 0.01) {
            return back()->withErrors(['ownerships' => 'Ownership percentages must total exactly 100%.'])->withInput();
        }

        $this->ownedShopAccountingService->replaceOwnerships($shop, $validated['ownerships']);

        return redirect()->route('admin.accounting.owned-shops.show', $shop)
            ->with('success', 'Shop ownership shares updated.');
    }

    public function storeCategory(StoreShopAccountingCategoryRequest $request, Shop $shop): RedirectResponse
    {
        $this->ensureAdminAccess($request);
        $shop = $this->loadEligibleShop($shop);

        $validated = $request->validated();
        $targetShopId = $validated['scope'] === 'global' ? null : $shop->id;

        $exists = ShopAccountingCategory::query()
            ->where('type', $validated['type'])
            ->where('name', trim($validated['name']))
            ->when(
                $targetShopId === null,
                fn ($query) => $query->whereNull('shop_id'),
                fn ($query) => $query->where('shop_id', $targetShopId)
            )
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'A category with this name already exists for the selected scope.'])->withInput();
        }

        ShopAccountingCategory::query()->create([
            'shop_id' => $targetShopId,
            'type' => $validated['type'],
            'name' => trim($validated['name']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.accounting.owned-shops.show', $shop)
            ->with('success', 'Accounting category created.');
    }

    public function storeEntry(StoreShopAccountingEntryRequest $request, Shop $shop): RedirectResponse
    {
        $this->ensureAdminAccess($request);
        $shop = $this->loadEligibleShop($shop);

        $entry = $this->ownedShopAccountingService->saveEntry($shop, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop,
            'date' => $entry->business_date->toDateString(),
        ])->with('success', 'Daily accounting entry saved.');
    }

    public function updateEntry(UpdateShopAccountingEntryRequest $request, Shop $shop, ShopAccountingEntry $entry): RedirectResponse
    {
        $this->ensureAdminAccess($request);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($entry->shop_id === $shop->id, 404);

        $entry = $this->ownedShopAccountingService->saveEntry($shop, $request->validated(), (int) $request->user()->id, $entry);

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop,
            'date' => $entry->business_date->toDateString(),
        ])->with('success', 'Daily accounting entry updated.');
    }

    public function reviewEntry(ReviewShopAccountingEntryRequest $request, Shop $shop, ShopAccountingEntry $entry): RedirectResponse
    {
        $this->ensureAdminAccess($request);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($entry->shop_id === $shop->id, 404);

        $entry = $this->ownedShopAccountingService->reviewEntry(
            $entry,
            $request->validated('decision'),
            (int) $request->user()->id,
            $request->validated('admin_note'),
        );

        $message = $entry->status === 'approved'
            ? 'Daily accounting approved.'
            : 'Daily accounting sent back for recheck.';

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop,
            'date' => $entry->business_date->toDateString(),
        ])->with($entry->status === 'approved' ? 'success' : 'warning', $message);
    }

    public function storeInvoice(GenerateShopAccountingInvoiceRequest $request, Shop $shop): RedirectResponse
    {
        $this->ensureAdminAccess($request);
        $shop = $this->loadEligibleShop($shop);

        try {
            $invoice = $this->shopAccountingInvoiceService->generate(
                shop: $shop,
                periodStart: Carbon::parse($request->validated('period_start')),
                periodEnd: Carbon::parse($request->validated('period_end')),
                userId: (int) $request->user()->id,
                notes: $request->validated('notes'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['invoice' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.accounting.owned-shops.invoices.show', [
            'shop' => $shop,
            'invoice' => $invoice,
        ])->with('success', 'Settlement invoice generated successfully.');
    }

    public function showInvoice(Request $request, Shop $shop, ShopAccountingInvoice $invoice): View
    {
        $this->ensureAdminAccess($request);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($invoice->shop_id === $shop->id, 404);

        return view('admin.accounting.owned_shops.invoice', [
            'shop' => $shop,
            'invoice' => $invoice->load(['generatedBy', 'approvedBy', 'splits.ownership']),
        ]);
    }

    public function generateDailyWorkflowInvoices(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess($request);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $this->shopInvoiceService->generateForBusinessDate($validated['date'], (int) $request->user()->id);

        return redirect()->route('admin.accounting.index', ['date' => $validated['date']])
            ->with('success', 'Daily shop invoices generated for the selected date.');
    }

    public function purchasersIndex(Request $request): View
    {
        $this->ensureAdminAccess($request);

        $purchasers = User::whereHas('roles', fn ($query) => $query->where('name', 'purchaser'))
            ->withSum(['purchaserCredits as total_in' => fn ($q) => $q->where('type', 'in')], 'amount')
            ->withSum(['purchaserCredits as total_out' => fn ($q) => $q->where('type', 'out')], 'amount')
            ->orderBy('name')
            ->get();

        return view('admin.accounting.purchasers.index', compact('purchasers'));
    }

    public function purchaserShow(Request $request, User $user): View
    {
        $this->ensureAdminAccess($request);
        abort_unless($user->hasRole('purchaser'), 404);

        $credits = PurchaserCredit::query()
            ->where('purchaser_id', $user->id)
            ->with(['purchaseInvoice', 'creator'])
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();

        $totalIn = (float) $credits->where('type', 'in')->sum('amount');
        $totalOut = (float) $credits->where('type', 'out')->sum('amount');
        $balance = $totalIn - $totalOut;

        return view('admin.accounting.purchasers.show', compact('user', 'credits', 'totalIn', 'totalOut', 'balance'));
    }

    public function storePurchaserCredit(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdminAccess($request);
        abort_unless($user->hasRole('purchaser'), 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'business_date' => ['required', 'date'],
        ]);

        PurchaserCredit::create([
            'purchaser_id' => $user->id,
            'type' => 'in',
            'amount' => (float) $validated['amount'],
            'description' => $validated['description'] ?: 'Cash / Credit from Green Leaf',
            'created_by' => auth()->id(),
            'business_date' => $validated['business_date'],
        ]);

        return redirect()->route('admin.accounting.purchasers.show', $user)
            ->with('success', 'Credit added successfully.');
    }

    private function ensureAdminAccess(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole('admin')
            || $request->user()?->can('admin.user.view')
            || $request->user()?->can('admin.daily-progress.view')
            || $request->user()?->can('admin.activity-log.view'),
            403,
            'Unauthorized access to admin accounting.'
        );
    }

    private function loadEligibleShop(Shop $shop): Shop
    {
        abort_unless($this->ownedShopAccountingService->isEligibleShop($shop), 404, 'This shop is not enabled for owned-shop accounting.');

        return $shop;
    }
}
