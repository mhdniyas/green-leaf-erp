<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\SaveCashbookSettlementRequest;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\PaymentsSettings\PaymentsAdvancedSettingsService;
use App\Services\Cashbook\PaymentsSettings\PaymentsAllocationSettingsService;
use App\Services\Cashbook\PaymentsSettings\PaymentsCompanyCollectionsService;
use App\Services\Cashbook\PaymentsSettings\PaymentsCompanyToShopService;
use App\Services\Cashbook\PaymentsSettings\PaymentsPettySettingsService;
use App\Services\Cashbook\PaymentsSettings\PaymentsSettingsOverviewService;
use App\Services\Cashbook\PaymentsSettings\PaymentsSettlementSettingsService;
use App\Services\Cashbook\PaymentsSettings\PaymentsShopToCompanyService;
use App\Services\Cashbook\PaymentsSettings\ShopPaymentsReportConfigService;
use App\Services\Cashbook\ShopSettlementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CashbookSettlementController extends Controller
{
    public function __construct(
        private readonly CashbookShopSyncService $shopSync,
        private readonly ShopSettlementService $settlements,
        private readonly ?PaymentsSettingsOverviewService $overviewService = null,
        private readonly ?PaymentsCompanyCollectionsService $companyCollectionsService = null,
        private readonly ?PaymentsShopToCompanyService $shopToCompanyService = null,
        private readonly ?PaymentsCompanyToShopService $companyToShopService = null,
        private readonly ?PaymentsPettySettingsService $pettyService = null,
        private readonly ?PaymentsSettlementSettingsService $settlementSettingsService = null,
        private readonly ?PaymentsAllocationSettingsService $allocationSettingsService = null,
        private readonly ?ShopPaymentsReportConfigService $reportConfigService = null,
        private readonly ?PaymentsAdvancedSettingsService $advancedService = null,
    ) {}

    public function index(Request $request, string $shop): View
    {
        return $this->page($request, $shop);
    }

    public function create(Request $request, string $shop): View
    {
        return $this->page($request, $shop, true);
    }

    public function edit(Request $request, string $shop, string $settlement): View
    {
        return $this->page($request, $shop, true, $settlement);
    }

    private function page(Request $request, string $shop, bool $editing = false, ?string $settlement = null): View
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);
        $relations = $this->settlements->settlements((int) $currentShop->shop_id);
        $relation = $settlement === null ? null : $relations->firstWhere('public_uuid', $settlement);
        abort_if($settlement !== null && $relation === null, 404);
        $settings = $currentShop->entrySettings()
            ->where('enabled', true)
            ->with(['entryType', 'headerGroup'])
            ->orderBy('display_order')
            ->get();
        $company = config('greenleaf');

        $allRelations = ShopCashbookRelation::with(['shop', 'items.setting.entryType'])
            ->where('enabled', true)
            ->get();

        $importableSettlements = $allRelations->map(function (ShopCashbookRelation $r) use ($settings, $currentShop): array {
            $isSameShop = (int) $r->shop_id === (int) $currentShop->shop_id;
            $items = $r->items->map(function ($item) use ($settings, $isSameShop): ?array {
                $settingId = $isSameShop
                    ? (string) $item->shop_ledger_entry_setting_id
                    : (string) ($settings->firstWhere('entry_type_id', $item->setting?->entry_type_id)?->id ?? '');

                if ($settingId === '') {
                    return null;
                }

                return [
                    'setting_id' => $settingId,
                    'role' => $item->role ?? 'add',
                ];
            })->filter()->values()->all();

            return [
                'id' => $r->id,
                'name' => $r->name,
                'shop_name' => $r->shop?->name ?? 'Default',
                'is_same_shop' => $isSameShop,
                'items' => $items,
            ];
        })->filter(fn (array $s): bool => ! empty($s['items']))->values()->all();

        $headerGroups = ShopLedgerHeaderGroup::where('shop_id', $currentShop->shop_id)
            ->where('enabled', true)
            ->with('allowedProducts:id,name,sku')
            ->orderBy('display_order')
            ->get();

        return view('admin.cashbook.settings.settlements.'.($editing ? 'form' : 'index'), compact('shops', 'currentShop', 'relations', 'relation', 'settings', 'headerGroups', 'company', 'importableSettlements'));
    }

    public function store(SaveCashbookSettlementRequest $request, string $shop): RedirectResponse
    {
        $this->settlements->save($request->profile(), $request->validated());

        return redirect()->route('admin.cashbook.settings.shop.settlements.index', $shop)->with('success', 'Settlement created. Its result is now available in the summary.');
    }

    public function update(SaveCashbookSettlementRequest $request, string $shop, string $settlement): RedirectResponse
    {
        $profile = $request->profile();
        $relation = ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('public_uuid', $settlement)->firstOrFail();
        $this->settlements->save($profile, $request->validated(), $relation);

        return redirect()->route('admin.cashbook.settings.shop.settlements.index', $shop)->with('success', 'Settlement updated.');
    }

    public function destroy(Request $request, string $shop, string $settlement): RedirectResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);

        $relation = ShopCashbookRelation::where('shop_id', $currentShop->shop_id)
            ->where('public_uuid', $settlement)
            ->firstOrFail();

        if (ShopCashbookRelationItem::where('source_settlement_id', $relation->id)->exists()) {
            return redirect()->route('admin.cashbook.settings.shop.settlements.index', $shop)
                ->with('error', "Settlement '{$relation->name}' cannot be deleted because another settlement uses it.");
        }

        $name = $relation->name;
        activity('cashbook_settlement')->performedOn($relation)->withProperties([
            'shop_id' => $currentShop->shop_id,
            'before' => $relation->only(['name', 'enabled', 'is_company_payable', 'is_net_balance']) + ['items' => $relation->items()->get()->toArray()],
        ])->log('Settlement deleted');
        $relation->delete();

        return redirect()->route('admin.cashbook.settings.shop.settlements.index', $shop)
            ->with('success', "Settlement '{$name}' deleted.");
    }

    public function copy(Request $request, string $shop, string $settlement): RedirectResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);

        $relation = ShopCashbookRelation::where('shop_id', $currentShop->shop_id)->where('public_uuid', $settlement)->firstOrFail();

        $validated = $request->validate([
            'target_shop_ids' => ['required', 'array', 'min:1'],
            'target_shop_ids.*' => ['required', 'integer', 'exists:shops,id'],
        ]);

        $copiedCount = 0;
        foreach ($validated['target_shop_ids'] as $targetShopId) {
            if ((int) $targetShopId === (int) $currentShop->shop_id) {
                continue;
            }

            $targetProfile = $shops->firstWhere('shop_id', (int) $targetShopId);
            if ($targetProfile) {
                $this->settlements->copyToShop($relation, $targetProfile);
                $copiedCount++;
            }
        }

        return redirect()->route('admin.cashbook.settings.shop.settlements.index', $shop)
            ->with('success', "Settlement '{$relation->name}' successfully copied to {$copiedCount} shop(s).");
    }

    public function reorder(Request $request, string $shop): JsonResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string'],
        ]);

        $this->settlements->reorder($currentShop, $validated['order']);

        return response()->json([
            'success' => true,
            'message' => 'Settlement order saved.',
        ]);
    }

    public function setNetBalance(Request $request, string $shop, string $settlement): JsonResponse|RedirectResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);

        $relation = ShopCashbookRelation::where('shop_id', $currentShop->shop_id)->where('public_uuid', $settlement)->firstOrFail();
        $this->settlements->setNetBalance($currentShop, $relation);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Settlement '{$relation->name}' is now marked as Net Balance.",
                'settlement_uuid' => $relation->public_uuid,
            ]);
        }

        return redirect()->route('admin.cashbook.settings.shop.settlements.index', $shop)
            ->with('success', "Settlement '{$relation->name}' is now marked as Net Balance.");
    }

    public function setDefaultPaymentPayable(Request $request, string $shop, string $settlement): JsonResponse|RedirectResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);

        $relation = ShopCashbookRelation::where('shop_id', $currentShop->shop_id)->where('public_uuid', $settlement)->firstOrFail();
        $this->settlements->setDefaultPaymentPayable($currentShop, $relation);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Settlement '{$relation->name}' is now Default Payment Payable.",
                'settlement_uuid' => $relation->public_uuid,
            ]);
        }

        return redirect()->route('admin.cashbook.settings.shop.settlements.index', $shop)
            ->with('success', "Settlement '{$relation->name}' is now Default Payment Payable.");
    }

    public function setDefaultPaymentPaid(Request $request, string $shop, string $settlement): JsonResponse|RedirectResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);

        $relation = ShopCashbookRelation::where('shop_id', $currentShop->shop_id)->where('public_uuid', $settlement)->firstOrFail();
        $this->settlements->setDefaultPaymentPaid($currentShop, $relation);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Settlement '{$relation->name}' is now Default Payment Paid.",
                'settlement_uuid' => $relation->public_uuid,
            ]);
        }

        return redirect()->route('admin.cashbook.settings.shop.settlements.index', $shop)
            ->with('success', "Settlement '{$relation->name}' is now Default Payment Paid.");
    }

    public function paymentsIndex(Request $request, string $shop): View
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);

        $shopModel = Shop::query()->findOrFail($currentShop->shop_id);
        $shopKey = $currentShop->slug ?: $currentShop->shop_id;
        $month = (string) $request->input('month', now()->format('Y-m'));
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            $month = now()->format('Y-m');
            $monthDate = now()->startOfMonth();
        }
        $startDate = $monthDate->copy()->startOfMonth()->toDateString();
        $endDate = $monthDate->copy()->endOfMonth()->toDateString();

        $overviewService = $this->overviewService ?? app(PaymentsSettingsOverviewService::class);
        $companyCollectionsService = $this->companyCollectionsService ?? app(PaymentsCompanyCollectionsService::class);
        $shopToCompanyService = $this->shopToCompanyService ?? app(PaymentsShopToCompanyService::class);
        $companyToShopService = $this->companyToShopService ?? app(PaymentsCompanyToShopService::class);
        $pettyService = $this->pettyService ?? app(PaymentsPettySettingsService::class);
        $settlementSettingsService = $this->settlementSettingsService ?? app(PaymentsSettlementSettingsService::class);
        $allocationSettingsService = $this->allocationSettingsService ?? app(PaymentsAllocationSettingsService::class);
        $reportConfigService = $this->reportConfigService ?? app(ShopPaymentsReportConfigService::class);
        $advancedService = $this->advancedService ?? app(PaymentsAdvancedSettingsService::class);

        $overviewData = $overviewService->getOverviewData($shopModel, $startDate, $endDate);
        $collectionsData = $companyCollectionsService->getViewModel($shopModel, $startDate, $endDate);
        $shopToCompanyData = $shopToCompanyService->getViewModel($shopModel, $startDate, $endDate);
        $companyToShopData = $companyToShopService->getViewModel($shopModel, $startDate, $endDate);
        $pettyData = $pettyService->getViewModel($shopModel, $startDate, $endDate);
        $settlementData = $settlementSettingsService->getViewModel($shopModel, $startDate, $endDate);
        $allocationData = $allocationSettingsService->getViewModel($shopModel, $startDate, $endDate);
        $reportHeadingsData = $reportConfigService->calculateReport($shopModel, $startDate, $endDate, $month);
        $advancedData = $advancedService->getViewModel($shopModel);

        $relations = $settlementData['relations'];
        $entrySettings = $currentShop->entrySettings()
            ->where('enabled', true)
            ->with(['entryType', 'companyAccount', 'headerGroup'])
            ->orderBy('display_order')
            ->get();
        $expenseEntrySettings = $entrySettings->filter(fn ($setting): bool => (bool) $setting->include_in_expense || $setting->entryType?->category === 'expense')->values();

        $paymentConfig = $currentShop->getPaymentConfiguration();
        $paymentConfig['expense_allocation'] = $this->settlements->expenseAllocationConfiguration($currentShop);

        return view('admin.cashbook.settings.payments.index', [
            'shops' => $shops,
            'currentShop' => $currentShop,
            'shopModel' => $shopModel,
            'shopKey' => $shopKey,
            'month' => $month,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'relations' => $relations,
            'entrySettings' => $entrySettings,
            'expenseEntrySettings' => $expenseEntrySettings,
            'paymentConfig' => $paymentConfig,
            'overviewData' => $overviewData,
            'collectionsData' => $collectionsData,
            'shopToCompanyData' => $shopToCompanyData,
            'companyToShopData' => $companyToShopData,
            'pettyData' => $pettyData,
            'settlementData' => $settlementData,
            'allocationData' => $allocationData,
            'reportHeadingsData' => $reportHeadingsData,
            'advancedData' => $advancedData,
        ]);
    }

    public function saveCompanyCollections(Request $request, string $shop): JsonResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);
        $shopModel = Shop::query()->findOrFail($currentShop->shop_id);

        $service = $this->companyCollectionsService ?? app(PaymentsCompanyCollectionsService::class);
        $service->saveMappings($shopModel, (array) $request->input('mappings', []), (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Company collections configuration saved successfully.',
        ]);
    }

    public function saveShopToCompany(Request $request, string $shop): JsonResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);
        $shopModel = Shop::query()->findOrFail($currentShop->shop_id);

        $validated = $request->validate([
            'allowed_methods' => ['nullable', 'array'],
            'allowed_methods.*' => ['string', 'in:cash,online_upi,cheque,bank_transfer,other'],
            'default_account_id' => ['nullable', 'integer'],
            'paid_relation_id' => ['nullable', 'integer'],
        ]);

        $service = $this->shopToCompanyService ?? app(PaymentsShopToCompanyService::class);
        $service->saveSettings($shopModel, $validated, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Shop to Company payment settings saved successfully.',
        ]);
    }

    public function savePetty(Request $request, string $shop): JsonResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);
        $shopModel = Shop::query()->findOrFail($currentShop->shop_id);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'allow_company_to_petty' => ['required', 'boolean'],
            'shop_owner_view_petty' => ['required', 'boolean'],
            'allow_expenses_from_petty' => ['required', 'boolean'],
        ]);

        $service = $this->pettyService ?? app(PaymentsPettySettingsService::class);
        $service->saveSettings($shopModel, $validated, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Petty cash settings saved successfully.',
        ]);
    }

    public function saveSettlement(Request $request, string $shop): JsonResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);
        $shopModel = Shop::query()->findOrFail($currentShop->shop_id);

        $validated = $request->validate([
            'relation_id' => ['required', 'integer'],
            'items' => ['present', 'array'],
            'items.*.type' => ['required', 'in:header,category,settlement'],
            'items.*.id' => ['required', 'integer'],
            'items.*.role' => ['required', 'in:add,subtract'],
        ]);

        $service = $this->settlementSettingsService ?? app(PaymentsSettlementSettingsService::class);
        $service->saveRelationItems($shopModel, (int) $validated['relation_id'], (array) $validated['items'], (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Settlement relation items saved successfully.',
        ]);
    }

    public function saveAllocation(Request $request, string $shop): JsonResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);
        $shopModel = Shop::query()->findOrFail($currentShop->shop_id);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'auto_allocate' => ['required', 'boolean'],
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer'],
            'default_category_id' => ['nullable', 'integer'],
            'payable_relation_id' => ['nullable', 'integer'],
            'paid_relation_id' => ['nullable', 'integer'],
        ]);

        $service = $this->allocationSettingsService ?? app(PaymentsAllocationSettingsService::class);
        $service->saveSettings($shopModel, $validated, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment allocation settings saved successfully.',
        ]);
    }

    public function saveReportHeadings(Request $request, string $shop): JsonResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);
        $shopModel = Shop::query()->findOrFail($currentShop->shop_id);

        $month = (string) $request->input('month', now()->format('Y-m'));
        $headings = (array) $request->input('headings', []);

        $service = $this->reportConfigService ?? app(ShopPaymentsReportConfigService::class);
        $saved = $service->saveConfigurationForMonth((int) $shopModel->id, $month, $headings, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => "Monthly Sales Report headings saved for month {$month}.",
            'headings' => $saved,
        ]);
    }

    public function saveAdvanced(Request $request, string $shop): JsonResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);
        $shopModel = Shop::query()->findOrFail($currentShop->shop_id);

        $service = $this->advancedService ?? app(PaymentsAdvancedSettingsService::class);
        $service->saveSettings($shopModel, (array) $request->input('settings', []), (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Advanced technical settings saved successfully.',
        ]);
    }

    public function savePaymentsConfiguration(Request $request, string $shop): JsonResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);

        $validated = $request->validate([
            'payment_settlement_id' => ['nullable', 'integer'],
            'payable.source' => ['nullable', 'in:categories,settlement'],
            'payable.category_ids' => ['nullable', 'array'],
            'payable.category_ids.*' => ['integer'],
            'payable.settlement_id' => ['nullable'],
            'sales_collections.source' => ['nullable', 'in:categories,settlement'],
            'sales_collections.direct_category_ids' => ['nullable', 'array'],
            'sales_collections.direct_category_ids.*' => ['integer'],
            'sales_collections.cash_category_ids' => ['nullable', 'array'],
            'sales_collections.cash_category_ids.*' => ['integer'],
            'sales_collections.settlement_id' => ['nullable'],
            'direct_to_company.source' => ['nullable', 'in:categories,settlement'],
            'direct_to_company.category_ids' => ['nullable', 'array'],
            'direct_to_company.category_ids.*' => ['integer'],
            'direct_to_company.settlement_id' => ['nullable'],
            'paid.source' => ['nullable', 'in:categories,settlement'],
            'paid.category_ids' => ['nullable', 'array'],
            'paid.category_ids.*' => ['integer'],
            'paid.settlement_id' => ['nullable'],
            'expense_allocation' => ['sometimes', 'array:enabled,auto_allocate,category_ids,default_category_id'],
            'expense_allocation.enabled' => ['required_with:expense_allocation', 'boolean'],
            'expense_allocation.auto_allocate' => ['required_with:expense_allocation', 'boolean'],
            'expense_allocation.category_ids' => ['required_with:expense_allocation', 'array'],
            'expense_allocation.category_ids.*' => [
                'integer',
                Rule::exists('shop_ledger_entry_settings', 'id')->where(fn ($query) => $query
                    ->where('shop_id', $currentShop->shop_id)
                    ->where('enabled', true)),
            ],
            'expense_allocation.default_category_id' => [
                'nullable',
                'integer',
                Rule::exists('shop_ledger_entry_settings', 'id')->where(fn ($query) => $query
                    ->where('shop_id', $currentShop->shop_id)
                    ->where('enabled', true)),
            ],
            'petty' => ['sometimes', 'array:enabled,allow_company_to_petty,shop_owner_view_petty,allow_expenses_from_petty'],
            'petty.enabled' => ['required_with:petty', 'boolean'],
            'petty.allow_company_to_petty' => ['required_with:petty', 'boolean'],
            'petty.shop_owner_view_petty' => ['required_with:petty', 'boolean'],
            'petty.allow_expenses_from_petty' => ['required_with:petty', 'boolean'],
        ]);

        $allowedExpenseIds = $currentShop->entrySettings()
            ->where('enabled', true)
            ->where(function ($query): void {
                $query->where('include_in_expense', true)
                    ->orWhereHas('entryType', fn ($typeQuery) => $typeQuery->where('category', 'expense'));
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $submittedExpenseIds = array_map('intval', (array) ($validated['expense_allocation']['category_ids'] ?? []));

        if (array_diff($submittedExpenseIds, $allowedExpenseIds) !== []) {
            throw ValidationException::withMessages([
                'expense_allocation.category_ids' => 'Only enabled expense categories belonging to this shop may be allocated.',
            ]);
        }

        if (! empty($validated['expense_allocation']['default_category_id'])
            && ! in_array((int) $validated['expense_allocation']['default_category_id'], array_map('intval', $validated['expense_allocation']['category_ids']), true)) {
            throw ValidationException::withMessages([
                'expense_allocation.default_category_id' => 'The default expense category must also be selected for allocation.',
            ]);
        }

        if (! isset($validated['direct_to_company']) && isset($validated['paid'])) {
            $validated['direct_to_company'] = $validated['paid'];
        } elseif (! isset($validated['paid']) && isset($validated['direct_to_company'])) {
            $validated['paid'] = $validated['direct_to_company'];
        }

        $this->settlements->savePaymentConfiguration($currentShop, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Payments configuration saved successfully.',
            'configuration' => $currentShop->fresh()->getPaymentConfiguration(),
        ]);
    }
}
