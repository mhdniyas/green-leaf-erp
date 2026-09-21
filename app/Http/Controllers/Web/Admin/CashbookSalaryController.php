<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Cashbook\SalaryHrTransactionType;
use App\Http\Controllers\Controller;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\ShopSalaryBridgeService;
use App\Support\ShopOwner\ActiveShopResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class CashbookSalaryController extends Controller
{
    public function __construct(
        private readonly CashbookShopSyncService $shopSync,
        private readonly ShopSalaryBridgeService $salaryBridgeService,
        private readonly ActiveShopResolver $activeShopResolver,
    ) {}

    public function rootIndex(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $shops = $this->shopSync->syncAndGetProfiles();
        $firstProfile = $shops->first();

        $shopKey = $firstProfile?->slug ?: ($firstProfile?->shop_id ?? '1');

        return redirect()->route('admin.cashbook.settings.shop.salary.index', ['shop' => $shopKey]);
    }

    public function index(Request $request, string $shop): View
    {
        $shopModel = $this->resolveAuthorizedShop($request, $shop, forEdit: false);
        $currentShopProfile = $this->resolveShopProfile($shopModel);
        $shops = $this->shopSync->syncAndGetProfiles();
        $shopKey = $currentShopProfile?->slug ?: (string) $shopModel->id;

        $user = $request->user();
        $canEdit = $this->canEditSalarySettings($user);

        // Pure read: get stored settings or in-memory unsaved defaults (does NOT write to DB)
        $salarySettings = $this->salaryBridgeService->getSettingsForShop($shopModel);
        $treeItems = $this->salaryBridgeService->getTreeViewModel($salarySettings);

        // Load existing active entry settings for this shop
        $entrySettings = ShopLedgerEntrySetting::query()
            ->with(['entryType', 'headerGroup'])
            ->where('shop_id', $shopModel->id)
            ->where('enabled', true)
            ->orderBy('display_order')
            ->get();

        // Load canonical active settlements for this shop
        $settlements = ShopCashbookRelation::query()
            ->where('shop_id', $shopModel->id)
            ->where('enabled', true)
            ->orderBy('is_company_payable', 'desc')
            ->orderBy('name')
            ->get();

        // Load recent audit trail logs for this shop's salary settings
        $recentAudits = Activity::query()
            ->where('log_name', 'cashbook_salary_settings')
            ->where('properties->shop_id', $shopModel->id)
            ->with('causer')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.cashbook.settings.salary.index', [
            'shop' => $shopModel,
            'currentShop' => $currentShopProfile ?? (object) [
                'shop_id' => $shopModel->id,
                'name' => $shopModel->name,
                'code' => $shopModel->code,
                'slug' => $shopModel->slug ?? (string) $shopModel->id,
            ],
            'shops' => $shops,
            'shopKey' => $shopKey,
            'salarySettings' => $salarySettings,
            'treeItems' => $treeItems,
            'entrySettings' => $entrySettings,
            'settlements' => $settlements,
            'canEdit' => $canEdit,
            'transactionTypes' => SalaryHrTransactionType::cases(),
            'recentAudits' => $recentAudits,
        ]);
    }

    public function update(Request $request, string $shop): RedirectResponse
    {
        $shopModel = $this->resolveAuthorizedShop($request, $shop, forEdit: true);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($this->canEditSalarySettings($user), 403, 'Unauthorized to edit salary settings.');

        $validated = $request->validate([
            'types' => ['required', 'array'],
            'types.*.shop_ledger_entry_setting_id' => ['nullable', 'integer', 'exists:shop_ledger_entry_settings,id'],
            'types.*.allowed_payment_modes' => ['nullable', 'array'],
            'types.*.allowed_payment_modes.*' => ['string', 'in:sales_cash,petty,company_payable'],
            'types.*.default_payment_mode' => ['required', 'string', 'in:sales_cash,petty,company_payable'],
            'types.*.company_payable_settlement_id' => ['nullable', 'integer', 'exists:shop_cashbook_relations,id'],
            'types.*.is_enabled' => ['nullable', 'boolean'],
        ]);

        $this->salaryBridgeService->updateSettingsForShop($shopModel, $validated['types'], $user);

        return redirect()->back()->with('success', "Salary bridge configuration for {$shopModel->name} updated successfully.");
    }

    public function resetToDefault(Request $request, string $shop): RedirectResponse
    {
        $shopModel = $this->resolveAuthorizedShop($request, $shop, forEdit: true);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($this->canEditSalarySettings($user), 403, 'Unauthorized to reset salary settings.');

        $this->salaryBridgeService->resetToDefault($shopModel, $user);

        return redirect()->route('admin.cashbook.settings.shop.salary.index', $shop)
            ->with('success', "Salary settings for {$shopModel->name} have been reset to the system default (Sales Cash, Company Payable, Petty — default: Sales Cash). Category mappings and settlement relations were preserved.");
    }

    private function canViewSalarySettings(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('hr_manager')
            || $user->can('hr.payroll.view')
            || $user->can('accounting.ledger.view')
            || $user->can('cashbook.monthly-report.settings.view')
            || $user->can('admin.settings.view');
    }

    private function canEditSalarySettings(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return true;
        }

        return $user->can('admin.settings.update')
            || $user->can('cashbook.monthly-report.settings.manage');
    }

    private function resolveAuthorizedShop(Request $request, string|int $shop, bool $forEdit = false): Shop
    {
        $profiles = $this->shopSync->syncAndGetProfiles();

        $profile = null;
        if (is_numeric($shop)) {
            $profile = $profiles->first(fn (ShopLedgerProfile $p): bool => (int) $p->shop_id === (int) $shop);
        }

        if (! $profile) {
            $profile = $profiles->first(fn (ShopLedgerProfile $p): bool => in_array((string) $shop, [(string) $p->shop_id, (string) $p->slug, (string) $p->uuid, (string) $p->code], true));
        }

        $shopModel = null;
        if ($profile) {
            $shopModel = Shop::query()->find($profile->shop_id);
        }

        if (! $shopModel) {
            $shopModel = is_numeric($shop)
                ? Shop::query()->find((int) $shop)
                : Shop::query()->where('code', (string) $shop)->orWhere('public_uuid', (string) $shop)->first();
        }

        abort_unless($shopModel instanceof Shop, 404, 'Shop not found.');

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($forEdit) {
            abort_unless($this->canEditSalarySettings($user), 403, 'Unauthorized to edit salary configuration.');
        } else {
            abort_unless($this->canViewSalarySettings($user), 403, 'Unauthorized to view salary configuration.');
        }

        return $shopModel;
    }

    private function resolveShopProfile(Shop $shop): ?ShopLedgerProfile
    {
        $profiles = $this->shopSync->syncAndGetProfiles();

        return $profiles->first(fn (ShopLedgerProfile $p): bool => (int) $p->shop_id === (int) $shop->id);
    }
}
