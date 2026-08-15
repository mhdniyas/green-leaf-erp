<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
    ];

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
        ];

        $purchaserUsers = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['purchaser', 'admin']))
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

        $operationalDate = app(\App\Services\Purchasing\PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        return view('admin.company-settings.edit', compact('companyDetails', 'purchaserUsers', 'directSaleShops', 'allActiveShops', 'operationalDate'));
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
            'default_purchaser_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'default_direct_sale_shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'auto_load_all_enabled' => ['nullable', 'boolean'],
            'auto_load_all_time' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'auto_load_all_next_business_day' => ['nullable', 'boolean'],
            'auto_load_all_delay_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'auto_load_all_allow_manual' => ['nullable', 'boolean'],
        ]);

        foreach (self::SETTING_KEYS as $key) {
            BusinessSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => filled($validated[$key] ?? null) ? $validated[$key] : null],
            );
        }

        return redirect()
            ->route('admin.company-settings.edit')
            ->with('success', 'Company bill details updated.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
