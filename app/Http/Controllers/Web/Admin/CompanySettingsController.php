<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\ProcurementExpenseCategory;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Warehouse\WarehouseSalesAccessService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class CompanySettingsController extends Controller
{
    private const array SETTING_KEYS = [
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'daily_price_cutoff_time',
        'allow_historical_invoice_repricing',
        'default_purchaser_user_id',
        'default_direct_sale_shop_id',
        'auto_load_all_enabled',
        'auto_load_all_time',
        'auto_load_all_next_business_day',
        'auto_load_all_delay_seconds',
        'auto_load_all_allow_manual',
        'shop_attendance_cutoff_time',
        'purchaser_business_day_verification_enabled',
        'purchaser_business_day_require_daily_submission',
        'enable_multi_grade_ordering',
        'grade_b_default_percentage',
        'grade_c_default_percentage',
        'selling_price_default_percentage',
    ];

    public function __construct(
        private readonly WarehouseSalesAccessService $warehouseSalesAccessService,
    ) {}

    public function edit(Request $request): View|RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($request->input('tab') === 'selling-price') {
            return redirect()->route('admin.selling-price.index');
        }

        $settings = BusinessSetting::query()
            ->whereIn('key', self::SETTING_KEYS)
            ->pluck('value', 'key');

        $rawCutoff = (string) ($settings->get('daily_price_cutoff_time') ?: PurchaserBusinessDayService::DEFAULT_DAILY_PRICE_CUTOFF_TIME);
        $normalizedCutoff = strlen($rawCutoff) === 5 ? "{$rawCutoff}:00" : $rawCutoff;
        try {
            $parsedCutoff = Carbon::createFromFormat('H:i:s', $normalizedCutoff);
            $dailyCutoffInput = $parsedCutoff->format('H:i');
            $dailyCutoffLabel = $parsedCutoff->format('g:i A');
        } catch (\Throwable) {
            $dailyCutoffInput = '09:00';
            $dailyCutoffLabel = '9:00 AM';
        }

        $companyDetails = [
            'company_name' => $settings->get('company_name') ?: 'Green Leaf',
            'company_address' => $settings->get('company_address'),
            'company_phone' => $settings->get('company_phone'),
            'company_email' => $settings->get('company_email'),
            'daily_price_cutoff_time' => $dailyCutoffInput,
            'daily_price_cutoff_time_label' => $dailyCutoffLabel,
            'allow_historical_invoice_repricing' => filter_var($settings->get('allow_historical_invoice_repricing') ?? false, FILTER_VALIDATE_BOOLEAN),
            'default_purchaser_user_id' => ($settings->get('default_purchaser_user_id') !== null && $settings->get('default_purchaser_user_id') !== '')
                ? (int) $settings->get('default_purchaser_user_id')
                : null,
            'default_direct_sale_shop_id' => ($settings->get('default_direct_sale_shop_id') !== null && $settings->get('default_direct_sale_shop_id') !== '')
                ? (int) $settings->get('default_direct_sale_shop_id')
                : null,
            'auto_load_all_enabled' => filter_var($settings->get('auto_load_all_enabled') ?? false, FILTER_VALIDATE_BOOLEAN),
            'auto_load_all_time' => $settings->get('auto_load_all_time') ?: '00:15',
            'auto_load_all_next_business_day' => filter_var($settings->get('auto_load_all_next_business_day') ?? false, FILTER_VALIDATE_BOOLEAN),
            'auto_load_all_delay_seconds' => (int) ($settings->get('auto_load_all_delay_seconds') ?: 3),
            'auto_load_all_allow_manual' => filter_var($settings->get('auto_load_all_allow_manual') ?? true, FILTER_VALIDATE_BOOLEAN),
            'shop_attendance_cutoff_time' => $settings->get('shop_attendance_cutoff_time') ?: '10:00',
            'enable_multi_grade_ordering' => filter_var($settings->get('enable_multi_grade_ordering') ?? false, FILTER_VALIDATE_BOOLEAN),
            'grade_b_default_percentage' => (float) ($settings->get('grade_b_default_percentage') ?: 90),
            'grade_c_default_percentage' => (float) ($settings->get('grade_c_default_percentage') ?: 80),
            'selling_price_default_percentage' => (function () use ($settings): float {
                $rawPercentage = $settings->get('selling_price_default_percentage');

                return ($rawPercentage !== null && is_numeric($rawPercentage) && (float) $rawPercentage >= 0)
                    ? (float) $rawPercentage
                    : 25.0;
            })(),
        ];

        $purchaserBusinessDayGlobalSettings = [
            'verification_enabled' => filter_var($settings->get('purchaser_business_day_verification_enabled') ?? true, FILTER_VALIDATE_BOOLEAN),
            'require_daily_submission' => filter_var($settings->get('purchaser_business_day_require_daily_submission') ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        $purchaserUsers = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'purchaser'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $directSaleShops = Shop::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'warehouse_tag']);

        $allActiveShops = Shop::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'warehouse_tag']);

        $allActiveUsers = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $allActiveWarehouses = Warehouse::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $warehouseSalesConfig = [
            'enabled' => $this->warehouseSalesAccessService->isFeatureEnabled(),
            'allowed_user_ids' => $this->warehouseSalesAccessService->allowedUserIds(),
            'user_warehouses' => $this->warehouseSalesAccessService->userWarehousesMap(),
        ];

        $operationalDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        $autoLoadAllRuns = Activity::query()
            ->where('log_name', 'auto_load_all')
            ->with('causer:id,name')
            ->latest()
            ->limit(8)
            ->get();

        $businessDayWarehouseSettings = app(PurchaserBusinessDayService::class)->getAllWarehouseSettings();

        $operatingExpenseCategories = ProcurementExpenseCategory::query()
            ->withCount(['subCategories', 'expenses'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedCategoryId = (int) $request->input('category_id', $operatingExpenseCategories->first()?->id ?? 0);
        $selectedExpenseCategory = $operatingExpenseCategories->firstWhere('id', $selectedCategoryId) ?? $operatingExpenseCategories->first();
        $selectedExpenseLocations = $selectedExpenseCategory
            ? $selectedExpenseCategory->subCategories()
                ->withCount('expenses')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
            : collect();

        $tab = (string) $request->input('tab', '');
        $subtab = (string) $request->input('subtab', '');

        if ($tab === 'operating-expenses') {
            $activeTab = 'purchaser';
            $activeSubtab = 'operating-expenses';
        } elseif ($tab === 'purchaser-business-day') {
            $activeTab = 'purchaser';
            $activeSubtab = 'operations';
        } else {
            $activeTab = in_array($tab, ['company', 'purchaser', 'operations', 'warehouse-sales', 'auto-load', 'history'], true)
                ? $tab
                : 'company';
            $activeSubtab = in_array($subtab, ['daily-prices', 'operations', 'operating-expenses'], true)
                ? $subtab
                : 'daily-prices';
        }

        return view('admin.company-settings.edit', compact(
            'companyDetails',
            'purchaserBusinessDayGlobalSettings',
            'purchaserUsers',
            'directSaleShops',
            'allActiveShops',
            'allActiveUsers',
            'allActiveWarehouses',
            'warehouseSalesConfig',
            'operationalDate',
            'autoLoadAllRuns',
            'businessDayWarehouseSettings',
            'operatingExpenseCategories',
            'selectedExpenseCategory',
            'selectedExpenseLocations',
            'activeTab',
            'activeSubtab',
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'company_name' => ['sometimes', 'required', 'string', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:120'],
            'daily_price_cutoff_time' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'allow_historical_invoice_repricing' => ['nullable', 'boolean'],
            'default_purchaser_user_id' => ['sometimes', 'required', 'integer', 'exists:users,id', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! User::query()->whereKey($value)->whereHas('roles', fn ($query) => $query->where('name', 'purchaser'))->exists()) {
                    $fail('The Company Default Purchaser must have the purchaser role.');
                }
            }],
            'default_direct_sale_shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'auto_load_all_enabled' => ['nullable', 'boolean'],
            'auto_load_all_time' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'auto_load_all_next_business_day' => ['nullable', 'boolean'],
            'auto_load_all_delay_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'auto_load_all_allow_manual' => ['nullable', 'boolean'],
            'shop_attendance_cutoff_time' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'purchaser_business_day_verification_enabled' => ['nullable', 'boolean'],
            'purchaser_business_day_require_daily_submission' => ['nullable', 'boolean'],
            'enable_multi_grade_ordering' => ['nullable', 'boolean'],
            'grade_b_default_percentage' => ['nullable', 'numeric', 'min:1', 'max:200'],
            'grade_c_default_percentage' => ['nullable', 'numeric', 'min:1', 'max:200'],
            'selling_price_default_percentage' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'active_tab' => ['nullable', 'string', 'in:company,purchaser,operations,purchaser-business-day,warehouse-sales,auto-load,operating-expenses,history,selling-price'],
            'active_subtab' => ['nullable', 'string', 'in:daily-prices,operations,operating-expenses'],
            'warehouse_sales_enabled' => ['nullable', 'boolean'],
            'warehouse_sales_allowed_user_ids' => ['nullable', 'array'],
            'warehouse_sales_allowed_user_ids.*' => ['integer', 'exists:users,id'],
            'warehouse_sales_user_warehouses' => ['nullable', 'array'],
            'business_day_warehouse_settings' => ['nullable', 'array'],
            'purchaser_business_day_warehouse_settings' => ['nullable', 'array'],
        ]);

        foreach (self::SETTING_KEYS as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $value = filled($validated[$key] ?? null) ? $validated[$key] : null;

            if ($key === 'enable_multi_grade_ordering') {
                $value = $request->boolean('enable_multi_grade_ordering') ? '1' : '0';
            } elseif (in_array($key, [
                'allow_historical_invoice_repricing',
                'auto_load_all_enabled',
                'auto_load_all_next_business_day',
                'auto_load_all_delay_seconds',
                'auto_load_all_allow_manual',
                'purchaser_business_day_verification_enabled',
                'purchaser_business_day_require_daily_submission',
            ], true)) {
                if ($key === 'auto_load_all_delay_seconds') {
                    $value = (int) ($validated['auto_load_all_delay_seconds'] ?? 3);
                } else {
                    $value = $request->boolean($key) ? '1' : '0';
                }
            } elseif ($key === 'daily_price_cutoff_time' && $value !== null) {
                $value = strlen((string) $value) === 5 ? "{$value}:00" : (string) $value;
            }

            BusinessSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }

        // Save Warehouse Sales settings
        if ($request->has('warehouse_sales_enabled') || $request->has('warehouse_sales_allowed_user_ids')) {
            $enabled = (bool) ($validated['warehouse_sales_enabled'] ?? false);
            $allowedUserIds = array_values(array_map('intval', $validated['warehouse_sales_allowed_user_ids'] ?? []));
            $userWarehousesRaw = $validated['warehouse_sales_user_warehouses'] ?? [];
            $userWarehouses = [];

            foreach ($allowedUserIds as $uId) {
                if (isset($userWarehousesRaw[$uId]) && is_array($userWarehousesRaw[$uId])) {
                    $userWarehouses[$uId] = array_values(array_map('intval', $userWarehousesRaw[$uId]));
                } else {
                    $userWarehouses[$uId] = [];
                }
            }

            $this->warehouseSalesAccessService->updateSettings([
                'enabled' => $enabled,
                'allowed_user_ids' => $allowedUserIds,
                'user_warehouses' => $userWarehouses,
            ]);
        }

        // Save Purchaser Business Day settings
        if ($request->has('business_day_warehouse_settings') || $request->has('purchaser_business_day_warehouse_settings')) {
            $businessDayService = app(PurchaserBusinessDayService::class);
            $pbdSettingsRaw = $request->input('business_day_warehouse_settings', $request->input('purchaser_business_day_warehouse_settings', []));
            $activeWarehouses = Warehouse::query()->active()->get();

            $isGlobalEnabled = filter_var($request->input('purchaser_business_day_verification_enabled', true), FILTER_VALIDATE_BOOLEAN);
            $isGlobalRequireSubmission = filter_var($request->input('purchaser_business_day_require_daily_submission', true), FILTER_VALIDATE_BOOLEAN);

            foreach ($activeWarehouses as $wh) {
                $whId = (int) $wh->id;
                $whSettings = is_array($pbdSettingsRaw) && isset($pbdSettingsRaw[$whId]) && is_array($pbdSettingsRaw[$whId])
                    ? $pbdSettingsRaw[$whId]
                    : [];

                $businessDayService->updateWarehouseSettings($whId, [
                    'enabled' => isset($whSettings['enabled']) ? ! empty($whSettings['enabled']) : $isGlobalEnabled,
                    'require_daily_submission' => isset($whSettings['require_daily_submission']) ? ! empty($whSettings['require_daily_submission']) : $isGlobalRequireSubmission,
                    'purchasers_can_close' => isset($whSettings['purchasers_can_close']) ? ! empty($whSettings['purchasers_can_close']) : true,
                    'purchasers_can_reopen' => isset($whSettings['purchasers_can_reopen']) ? ! empty($whSettings['purchasers_can_reopen']) : false,
                    'reopen_requires_reason' => true,
                    'allow_close_with_pending' => isset($whSettings['allow_close_with_pending']) ? ! empty($whSettings['allow_close_with_pending']) : true,
                    'require_digital_verification' => isset($whSettings['require_digital_verification']) ? ! empty($whSettings['require_digital_verification']) : false,
                    'admin_override_reopen' => isset($whSettings['admin_override_reopen']) ? ! empty($whSettings['admin_override_reopen']) : false,
                ]);
            }
        }

        $activeTab = $request->input('active_tab');
        $activeSubtab = $request->input('active_subtab');

        if ($activeTab === 'selling-price') {
            return redirect()
                ->route('admin.selling-price.index')
                ->with('success', 'Selling price settings updated successfully.');
        }

        if ($activeTab === 'operating-expenses') {
            $activeTab = 'purchaser';
            $activeSubtab = 'operating-expenses';
        } elseif ($activeTab === 'purchaser-business-day') {
            $activeTab = 'purchaser';
            $activeSubtab = 'operations';
        }

        $redirectParams = [];
        if ($activeTab) {
            $redirectParams['tab'] = $activeTab;
        }
        if ($activeSubtab && $activeTab === 'purchaser') {
            $redirectParams['subtab'] = $activeSubtab;
        }

        return redirect()
            ->route('admin.company-settings.edit', $redirectParams)
            ->with('success', 'Company settings updated successfully.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
