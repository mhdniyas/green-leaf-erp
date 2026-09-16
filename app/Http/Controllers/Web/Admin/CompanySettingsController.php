<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Warehouse\WarehouseSalesAccessService;
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
        'allow_historical_invoice_repricing',
        'default_purchaser_user_id',
        'default_direct_sale_shop_id',
        'auto_load_all_enabled',
        'auto_load_all_time',
        'auto_load_all_next_business_day',
        'auto_load_all_delay_seconds',
        'auto_load_all_allow_manual',
        'shop_attendance_cutoff_time',
    ];

    public function __construct(
        private readonly WarehouseSalesAccessService $warehouseSalesAccessService,
    ) {}

    public function edit(Request $request): View
    {
        $this->authorizeAdmin($request);

        $settings = BusinessSetting::query()
            ->whereIn('key', self::SETTING_KEYS)
            ->pluck('value', 'key');

        $companyDetails = [
            'company_name' => $settings->get('company_name') ?: 'Green Leaf',
            'company_address' => $settings->get('company_address'),
            'company_phone' => $settings->get('company_phone'),
            'company_email' => $settings->get('company_email'),
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

        return view('admin.company-settings.edit', compact(
            'companyDetails',
            'purchaserUsers',
            'directSaleShops',
            'allActiveShops',
            'allActiveUsers',
            'allActiveWarehouses',
            'warehouseSalesConfig',
            'operationalDate',
            'autoLoadAllRuns',
            'businessDayWarehouseSettings'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:120'],
            'allow_historical_invoice_repricing' => ['nullable', 'boolean'],
            'default_purchaser_user_id' => ['required', 'integer', 'exists:users,id', function (string $attribute, mixed $value, \Closure $fail): void {
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
            'warehouse_sales_enabled' => ['nullable', 'boolean'],
            'warehouse_sales_allowed_user_ids' => ['nullable', 'array'],
            'warehouse_sales_allowed_user_ids.*' => ['integer', 'exists:users,id'],
            'warehouse_sales_user_warehouses' => ['nullable', 'array'],
            'business_day_warehouse_settings' => ['nullable', 'array'],
            'purchaser_business_day_warehouse_settings' => ['nullable', 'array'],
        ]);

        foreach (self::SETTING_KEYS as $key) {
            BusinessSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => filled($validated[$key] ?? null) ? $validated[$key] : null],
            );
        }

        // Save Warehouse Sales settings
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

        // Save Purchaser Business Day per-warehouse settings
        $businessDayService = app(PurchaserBusinessDayService::class);
        $pbdSettingsRaw = $request->input('business_day_warehouse_settings', $request->input('purchaser_business_day_warehouse_settings', []));
        $activeWarehouses = Warehouse::query()->active()->get();

        foreach ($activeWarehouses as $wh) {
            $whId = (int) $wh->id;
            $whSettings = is_array($pbdSettingsRaw) && isset($pbdSettingsRaw[$whId]) && is_array($pbdSettingsRaw[$whId])
                ? $pbdSettingsRaw[$whId]
                : [];

            $businessDayService->updateWarehouseSettings($whId, [
                'enabled' => ! empty($whSettings['enabled']),
                'purchasers_can_close' => ! empty($whSettings['purchasers_can_close']),
                'purchasers_can_reopen' => ! empty($whSettings['purchasers_can_reopen']),
                'reopen_requires_reason' => true,
                'allow_close_with_pending' => ! empty($whSettings['allow_close_with_pending']),
                'require_digital_verification' => ! empty($whSettings['require_digital_verification']),
                'admin_override_reopen' => ! empty($whSettings['admin_override_reopen']),
            ]);
        }

        return redirect()
            ->route('admin.company-settings.edit')
            ->with('success', 'Company settings updated successfully.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
