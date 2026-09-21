<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Shop;
use App\Models\ShopSupplier;
use App\Services\Cashbook\CategoryExplanationService;
use App\Services\Cashbook\ShopCategoryConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CashbookCategoryController extends Controller
{
    public function __construct(
        private readonly ShopCategoryConfigurationService $configService,
        private readonly CategoryExplanationService $explanationService
    ) {}

    /**
     * List all categories with intelligent non-misleading shop summaries.
     */
    public function index(Request $request): View
    {
        $query = LedgerEntryType::query()->with([
            'settings' => fn ($q) => $q->where('enabled', true)->with(['headerGroup', 'companyAccount', 'definedShopSuppliers', 'vendorSettlementRelation']),
        ])->orderBy('display_order')->orderBy('name');

        if ($request->filled('search')) {
            $search = strtolower(trim((string) $request->input('search')));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(code) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('category')) {
            $query->where('category', (string) $request->input('category'));
        }

        $categories = $query->get();
        $allShops = Shop::query()->orderBy('name')->get();

        // Calculate aggregated shop summaries per category
        $categorySummaries = $categories->mapWithKeys(function (LedgerEntryType $cat) use ($allShops): array {
            $enabledSettings = $cat->settings;
            $shopCount = $enabledSettings->count();

            // 1. Header Summary
            $headerNames = $enabledSettings->map(fn ($s) => $s->headerGroup?->name)->filter()->unique();
            $headerSummary = match ($headerNames->count()) {
                0 => 'None',
                1 => $headerNames->first(),
                default => 'Varies',
            };

            // 2. Settlement Summary
            $settlementTypes = $enabledSettings->map(function ($s) {
                $item = ShopCashbookRelationItem::where('shop_ledger_entry_setting_id', $s->id)->first();
                if ($item && $item->relation) {
                    return $item->relation->name;
                }

                return $s->vendorSettlementRelation?->name;
            })->filter()->unique();

            $settlementSummary = match ($settlementTypes->count()) {
                0 => 'None',
                1 => $settlementTypes->first(),
                default => 'Varies',
            };

            // 3. Company Relation Summary
            $companyAccounts = $enabledSettings->map(fn ($s) => $s->companyAccount?->name ?: $s->companyAccount?->bank_name)->filter()->unique();
            $companySummary = match ($companyAccounts->count()) {
                0 => 'None',
                1 => $companyAccounts->first(),
                default => $companyAccounts->count().' Shops',
            };

            // 4. Vendor Summary
            $vendorPurchases = $enabledSettings->filter(fn ($s) => $s->is_vendor_purchase);
            $vendorSummary = match ($vendorPurchases->count()) {
                0 => 'None',
                $allShops->count() => 'All Shops',
                default => $vendorPurchases->count().' Shops',
            };

            return [
                $cat->id => [
                    'shop_count' => $shopCount,
                    'total_shops' => $allShops->count(),
                    'header' => $headerSummary,
                    'settlement' => $settlementSummary,
                    'company' => $companySummary,
                    'vendor' => $vendorSummary,
                ],
            ];
        });

        return view('admin.cashbook.categories.index', compact('categories', 'categorySummaries', 'allShops'));
    }

    /**
     * Category Creation Form.
     */
    public function create(): View
    {
        $shops = Shop::query()->orderBy('name')->get();

        return view('admin.cashbook.categories.create', compact('shops'));
    }

    /**
     * Store new Category (LedgerEntryType) and assign to initial shops.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:income,expense,transfer'],
            'active' => ['nullable', 'boolean'],
            'shop_ids' => ['nullable', 'array'],
            'shop_ids.*' => ['integer', 'exists:shops,id'],
        ]);

        $code = Str::slug($validated['name'], '_');
        $originalCode = $code;
        $counter = 1;
        while (LedgerEntryType::where('code', $code)->exists()) {
            $code = $originalCode.'_'.$counter;
            $counter++;
        }

        $maxOrder = (int) LedgerEntryType::max('display_order') ?? 0;

        $entryType = LedgerEntryType::create([
            'code' => $code,
            'name' => trim($validated['name']),
            'category' => strtolower($validated['category']),
            'system_type' => 'custom',
            'active' => (bool) ($validated['active'] ?? true),
            'display_order' => $maxOrder + 1,
        ]);

        if (! empty($validated['shop_ids'])) {
            $this->configService->assignShops($entryType, $validated['shop_ids']);
        }

        return redirect()->route('admin.cashbook.categories.show', $entryType->code)
            ->with('success', "Category '{$entryType->name}' created successfully.");
    }

    /**
     * Category Details / Unified Edit Page.
     */
    public function show(string|int $category): View
    {
        $resolvedCategory = $this->resolveCategory($category);

        $entryType = LedgerEntryType::with([
            'settings' => fn ($q) => $q->with(['shop', 'headerGroup', 'companyAccount', 'vendorSettlementRelation', 'definedShopSuppliers']),
        ])->findOrFail($resolvedCategory->id);

        $allShops = Shop::query()->orderBy('name')->get();
        $companyAccounts = CompanyAccount::query()->orderBy('name')->get();

        // Build shop card configurations with shop-isolated data
        $shopConfigs = $allShops->map(function (Shop $shop) use ($entryType): array {
            $setting = $entryType->settings->firstWhere('shop_id', $shop->id);
            if (! $setting) {
                // Fetch or create disabled record for display
                $setting = ShopLedgerEntrySetting::where('shop_id', $shop->id)
                    ->where('entry_type_id', $entryType->id)
                    ->first();
            }

            // Headers isolated strictly to this shop
            $shopHeaders = ShopLedgerHeaderGroup::where('shop_id', $shop->id)
                ->orderBy('display_order')
                ->get();

            // Settlement relations isolated strictly to this shop
            $shopSettlements = ShopCashbookRelation::where('shop_id', $shop->id)
                ->where('enabled', true)
                ->orderBy('display_order')
                ->get();

            // Shop suppliers isolated strictly to this shop
            $shopSuppliers = ShopSupplier::with('supplier')
                ->where('shop_id', $shop->id)
                ->get()
                ->sortBy(fn (ShopSupplier $ss): string => strtolower((string) ($ss->supplier?->name ?? '')))
                ->values();

            $settlementItem = null;
            $hasConflict = false;
            $explanation = '';

            if ($setting) {
                $settlementItem = ShopCashbookRelationItem::where('shop_ledger_entry_setting_id', $setting->id)->first();
                $explanation = $this->explanationService->generateSummary($setting);

                // Detect conflicts between settlement_behavior and explicit relation item role
                if ($setting->settlement_behavior && $settlementItem) {
                    $behaviorRole = strtolower((string) $setting->settlement_behavior) === 'decrease' ? 'subtract' : 'add';
                    if ($behaviorRole !== strtolower((string) $settlementItem->role)) {
                        $hasConflict = true;
                    }
                }
            }

            return [
                'shop' => $shop,
                'setting' => $setting,
                'headers' => $shopHeaders,
                'settlements' => $shopSettlements,
                'suppliers' => $shopSuppliers,
                'settlement_item' => $settlementItem,
                'has_conflict' => $hasConflict,
                'explanation' => $explanation,
            ];
        });

        $assignedShopIds = $entryType->settings->where('enabled', true)->pluck('shop_id')->all();

        return view('admin.cashbook.categories.show', compact('entryType', 'allShops', 'companyAccounts', 'shopConfigs', 'assignedShopIds'));
    }

    /**
     * Update Global Category Identity (name & active status).
     */
    public function updateGlobal(Request $request, string|int $category): RedirectResponse
    {
        $entryType = $this->resolveCategory($category);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        $entryType->update([
            'name' => trim($validated['name']),
            'active' => (bool) ($validated['active'] ?? false),
        ]);

        return redirect()->back()->with('success', 'Global category details updated.');
    }

    /**
     * Assign or unassign category to shops.
     */
    public function assignShops(Request $request, string|int $category): RedirectResponse
    {
        $entryType = $this->resolveCategory($category);
        $validated = $request->validate([
            'shop_ids' => ['nullable', 'array'],
            'shop_ids.*' => ['integer', 'exists:shops,id'],
        ]);

        $this->configService->assignShops($entryType, $validated['shop_ids'] ?? []);

        return redirect()->back()->with('success', 'Shop assignments updated.');
    }

    /**
     * Update Shop Basic & Header Configuration.
     */
    public function updateShopBasicHeader(Request $request, string|int $category, int $shop): RedirectResponse
    {
        $setting = $this->getOrCreateShopSetting($category, $shop);
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'header_group_id' => ['nullable', 'integer', 'exists:shop_ledger_header_groups,id'],
        ]);

        $this->configService->updateBasicAndHeader($setting, $validated);

        return redirect()->back()->with('success', "Basic & Header settings updated for {$setting->shop?->name}.");
    }

    /**
     * Update Shop Settlement Configuration.
     */
    public function updateShopSettlement(Request $request, string|int $category, int $shop): RedirectResponse
    {
        $setting = $this->getOrCreateShopSetting($category, $shop);
        $validated = $request->validate([
            'settlement_behavior' => ['nullable', 'string', 'in:increase,decrease,none'],
            'relation_id' => ['nullable', 'integer', 'exists:shop_cashbook_relations,id'],
            'role' => ['nullable', 'string', 'in:add,subtract'],
        ]);

        $this->configService->updateSettlement($setting, $validated);

        return redirect()->back()->with('success', "Settlement settings updated for {$setting->shop?->name}.");
    }

    /**
     * Update Shop Company Relation Configuration.
     */
    public function updateShopCompanyRelation(Request $request, string|int $category, int $shop): RedirectResponse
    {
        $setting = $this->getOrCreateShopSetting($category, $shop);
        $validated = $request->validate([
            'company_account_id' => ['nullable', 'integer', 'exists:cashbook_company_accounts,id'],
            'default_funding_source' => ['required', 'string', 'in:sales,petty,company,none'],
        ]);

        $this->configService->updateCompanyRelation($setting, $validated);

        return redirect()->back()->with('success', "Company relation updated for {$setting->shop?->name}.");
    }

    /**
     * Update Shop Vendor Relation Configuration.
     */
    public function updateShopVendorRelation(Request $request, string|int $category, int $shop): RedirectResponse
    {
        $setting = $this->getOrCreateShopSetting($category, $shop);
        $validated = $request->validate([
            'is_vendor_purchase' => ['nullable', 'boolean'],
            'vendor_purchase_payment_type' => ['nullable', 'string', 'in:cash,credit'],
            'vendor_access_mode' => ['nullable', 'string', 'in:all,pinned'],
            'mirror_to_cashbook' => ['nullable', 'boolean'],
            'pinned_supplier_ids' => ['nullable', 'array'],
            'pinned_supplier_ids.*' => ['integer', 'exists:shop_suppliers,id'],
        ]);

        $this->configService->updateVendorRelation($setting, $validated);

        return redirect()->back()->with('success', "Vendor relation updated for {$setting->shop?->name}.");
    }

    /**
     * Update Shop Reports & Accounting Flags.
     */
    public function updateShopReports(Request $request, string|int $category, int $shop): RedirectResponse
    {
        $setting = $this->getOrCreateShopSetting($category, $shop);
        $validated = $request->validate([
            'include_in_sales' => ['nullable', 'boolean'],
            'include_in_income' => ['nullable', 'boolean'],
            'include_in_expense' => ['nullable', 'boolean'],
            'include_in_pl' => ['nullable', 'boolean'],
            'include_in_payable' => ['nullable', 'boolean'],
            'payable_direction' => ['nullable', 'string', 'in:vendor_payable,company_payable'],
            'sales_report_bucket' => ['nullable', 'string', 'in:default,sales,rent,purchase,other_expense,ignore'],
        ]);

        $this->configService->updateAccountingFlags($setting, $validated);

        return redirect()->back()->with('success', "Report & accounting flags updated for {$setting->shop?->name}.");
    }

    /**
     * Update Shop Advanced Settings.
     */
    public function updateShopAdvanced(Request $request, string|int $category, int $shop): RedirectResponse
    {
        $setting = $this->getOrCreateShopSetting($category, $shop);
        $validated = $request->validate([
            'note_enabled' => ['nullable', 'boolean'],
            'edit_policy' => ['nullable', 'string', 'in:past_days_allowed,today_only'],
            'petty_behavior' => ['nullable', 'string', 'in:increase,decrease,none'],
            'company_pending_behavior' => ['nullable', 'string', 'in:increase,decrease,none'],
            'generates_secondary_entry' => ['nullable', 'boolean'],
            'secondary_entry_type_id' => ['nullable', 'integer', 'exists:ledger_entry_types,id'],
            'secondary_amount_mode' => ['nullable', 'string', 'in:same_amount,percentage'],
            'secondary_amount_value' => ['nullable', 'numeric'],
            'mirror_to_cashbook' => ['nullable', 'boolean'],
        ]);

        $this->configService->updateAdvancedSettings($setting, $validated);

        return redirect()->back()->with('success', "Advanced settings updated for {$setting->shop?->name}.");
    }

    private function getOrCreateShopSetting(string|int $categoryId, int $shopId): ShopLedgerEntrySetting
    {
        $entryType = $this->resolveCategory($categoryId);
        $shop = Shop::findOrFail($shopId);

        $setting = ShopLedgerEntrySetting::where('shop_id', $shop->id)
            ->where('entry_type_id', $entryType->id)
            ->first();

        if (! $setting) {
            $setting = ShopLedgerEntrySetting::create([
                'shop_id' => $shop->id,
                'entry_type_id' => $entryType->id,
                'display_name' => null,
                'enabled' => true,
                'effective_from' => now()->toDateString(),
                'default_funding_source' => 'sales',
                'include_in_pl' => true,
                'header_display_order' => 0,
                'display_order' => 0,
                'mirror_to_cashbook' => true,
            ]);
        }

        return $setting;
    }

    private function resolveCategory(string|int $category): LedgerEntryType
    {
        return LedgerEntryType::where('code', (string) $category)
            ->orWhere('id', is_numeric($category) ? (int) $category : 0)
            ->firstOrFail();
    }
}
