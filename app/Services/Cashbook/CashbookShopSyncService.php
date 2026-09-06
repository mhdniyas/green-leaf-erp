<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\LedgerClient;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopConfigPreset;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Client;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopStaffPayment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Connects the Cashbook domain directly to Green Leaf ERP's owned shops.
 *
 * Ensures that any shop in Green Leaf ERP with accounting enabled (accounting_mode='owned'
 * or having a client_id) automatically has a corresponding ShopLedgerProfile and default
 * entry settings without requiring manual database seeding.
 */
class CashbookShopSyncService
{
    private const DEFAULT_EFFECTIVE_FROM = '2026-01-01';

    public function __construct(
        private readonly InvoiceCashbookProjectionService $invoiceProjectionService,
        private readonly StaffPaymentCashbookProjectionService $staffPaymentProjectionService,
        private readonly ShopSettlementService $settlements,
    ) {}

    /**
     * Synchronizes Green Leaf ERP's owned shops into ShopLedgerProfile and returns the updated collection.
     *
     * @return Collection<int, ShopLedgerProfile>
     */
    public function syncAndGetProfiles(): Collection
    {
        $erpShops = Shop::query()
            ->where(function ($query): void {
                $query->whereNull('client_id')
                    ->orWhere(function ($ownedOrClientQuery): void {
                        $ownedOrClientQuery->where('accounting_enabled', true)
                            ->where(function ($eligibleQuery): void {
                                $eligibleQuery->whereNotNull('client_id')
                                    ->orWhere('accounting_mode', 'owned');
                            });
                    });
            })
            ->with('client')
            ->orderBy('id')
            ->get();

        $standardPreset = ShopConfigPreset::where('slug', 'standard-veg-shop')->first()
            ?? ShopConfigPreset::where('is_default', true)->first();
        $grandcityPreset = ShopConfigPreset::where('slug', 'grandcity-extended')->first();

        DB::transaction(function () use ($erpShops, $standardPreset, $grandcityPreset): void {
            $eligibleShopIds = $erpShops->modelKeys();

            ShopLedgerProfile::query()
                ->when($eligibleShopIds !== [], fn ($query) => $query->whereNotIn('shop_id', $eligibleShopIds))
                ->when($eligibleShopIds === [], fn ($query) => $query)
                ->update(['enabled' => false]);

            foreach ($erpShops as $erpShop) {
                $isGrandcity = Str::contains(strtoupper($erpShop->code), 'GRANDCITY')
                    || Str::contains(strtoupper($erpShop->name), 'GRANDCITY');
                $preset = $isGrandcity ? ($grandcityPreset ?? $standardPreset) : $standardPreset;
                $ledgerClient = $erpShop->client instanceof Client
                    ? $this->syncClient($erpShop->client)
                    : null;

                $profile = ShopLedgerProfile::firstOrNew(['shop_id' => $erpShop->id]);
                $profile->fill([
                    'code' => $erpShop->code,
                    'name' => $erpShop->name,
                    'profile_template' => $ledgerClient ? 'owned_standard' : 'direct_buyer',
                    'enabled' => true,
                    'client_id' => $ledgerClient?->id,
                ]);
                $profile->uuid ??= (string) Str::uuid();
                $profile->slug ??= Str::slug($erpShop->code.'-'.$erpShop->name);
                $profile->closing_mode ??= 'manual';
                $profile->preset_id ??= $preset?->id;
                $profile->save();

                $this->syncPresetSettingsToShop($profile, $preset);
                $this->ensureOtherEntriesForShop($erpShop->id);
                $this->settlements->ensureDefaults($profile);
            }
        });

        return ShopLedgerProfile::query()
            ->where('enabled', true)
            ->whereIn('shop_id', $erpShops->modelKeys())
            ->with(['shop.client', 'client', 'preset'])
            ->orderBy('shop_id')
            ->get();
    }

    private function syncClient(Client $client): LedgerClient
    {
        $existingProfileClientIds = ShopLedgerProfile::query()
            ->whereIn('shop_id', $client->shops()->select('id'))
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id');

        $legacyLedgerClient = $existingProfileClientIds->count() === 1
            ? LedgerClient::query()
                ->whereKey($existingProfileClientIds->first())
                ->whereNull('erp_client_id')
                ->first()
            : null;

        $ledgerClient = LedgerClient::query()
            ->where('erp_client_id', $client->id)
            ->orWhere(function ($query) use ($client): void {
                $query->whereNull('erp_client_id')->where('slug', Str::slug($client->code));
            })
            ->first() ?? $legacyLedgerClient ?? new LedgerClient;

        $ledgerClient->fill([
            'erp_client_id' => $client->id,
            'name' => $client->name,
            'slug' => Str::slug($client->code),
            'enabled' => $client->status === 'active',
        ])->save();

        return $ledgerClient;
    }

    /**
     * Automatically syncs approved ShopInvoice records from Green Leaf ERP
     * into cashbook ShopLedgerTransaction entries (code: purchase_bill).
     */
    public function syncInvoicesToCashbook(): void
    {
        $invoices = ShopInvoice::where('final_total', '>', 0)
            ->whereNotNull('finalized_at')
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->get();

        foreach ($invoices as $inv) {
            $this->invoiceProjectionService->syncInvoice($inv, 1);
        }
    }

    /**
     * Automatically syncs ShopStaffPayment records from Green Leaf ERP
     * into cashbook ShopLedgerTransaction entries (code: salary).
     */
    public function syncStaffPaymentsToCashbook(): void
    {
        if (! $this->staffPaymentProjectionService instanceof StaffPaymentCashbookProjectionService) {
            return;
        }

        $payments = ShopStaffPayment::query()
            ->where('amount', '>', 0)
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $this->staffPaymentProjectionService->syncPayment($payment, 1);
        }
    }

    public function syncPresetSettingsToShop(ShopLedgerProfile $profile, ?ShopConfigPreset $preset): void
    {
        if (! $preset) {
            return;
        }

        foreach ($preset->entrySettings as $presetSetting) {
            $effectiveFrom = $presetSetting->effective_from?->toDateString() ?? self::DEFAULT_EFFECTIVE_FROM;

            $shopSetting = ShopLedgerEntrySetting::query()
                ->where('shop_id', $profile->shop_id)
                ->where('entry_type_id', $presetSetting->entry_type_id)
                ->first();

            if ($shopSetting) {
                if ($shopSetting->effective_from?->toDateString() === $effectiveFrom) {
                    continue;
                }

                $shopSetting->update([
                    'effective_from' => min($shopSetting->effective_from?->toDateString() ?? $effectiveFrom, $effectiveFrom),
                ]);

                continue;
            }

            ShopLedgerEntrySetting::create([
                'shop_id' => $profile->shop_id,
                'entry_type_id' => $presetSetting->entry_type_id,
                'version' => 1,
                'effective_from' => min($effectiveFrom, self::DEFAULT_EFFECTIVE_FROM),
                'effective_to' => null,
                'enabled' => $presetSetting->enabled,
                'default_funding_source' => $presetSetting->default_funding_source,
                'allowed_funding_sources' => $presetSetting->allowed_funding_sources,
                'include_in_sales' => $presetSetting->include_in_sales,
                'include_in_income' => $presetSetting->include_in_income,
                'include_in_expense' => $presetSetting->include_in_expense,
                'include_in_pl' => $presetSetting->include_in_pl,
                'include_in_payable' => $presetSetting->include_in_payable ?? false,
                'generates_secondary_entry' => $presetSetting->generates_secondary_entry,
                'secondary_entry_type_id' => $presetSetting->secondary_entry_type_id,
                'secondary_amount_mode' => $presetSetting->secondary_amount_mode,
                'secondary_amount_value' => $presetSetting->secondary_amount_value,
                'petty_behavior' => $presetSetting->petty_behavior,
                'settlement_behavior' => $presetSetting->settlement_behavior,
                'company_pending_behavior' => $presetSetting->company_pending_behavior,
            ]);
        }
    }

    public function ensureOtherEntriesForShop(int $shopId): void
    {
        $otherIncomeType = LedgerEntryType::firstOrCreate(
            ['code' => 'other_income'],
            [
                'name' => 'Other Income',
                'category' => 'income',
                'active' => true,
                'display_order' => 7,
            ]
        );

        $otherExpenseType = LedgerEntryType::firstOrCreate(
            ['code' => 'other_expense'],
            [
                'name' => 'Other Expense',
                'category' => 'expense',
                'active' => true,
                'display_order' => 18,
            ]
        );

        $salaryType = LedgerEntryType::firstOrCreate(
            ['code' => 'salary'],
            [
                'name' => 'Salary',
                'category' => 'expense',
                'active' => true,
                'display_order' => 17,
            ]
        );

        ShopLedgerEntrySetting::query()->firstOrCreate(
            [
                'shop_id' => $shopId,
                'entry_type_id' => $otherIncomeType->id,
            ],
            [
                'version' => 1,
                'effective_from' => self::DEFAULT_EFFECTIVE_FROM,
                'effective_to' => null,
                'enabled' => true,
                'default_funding_source' => 'shop_cash',
                'allowed_funding_sources' => ['sales', 'bank'],
                'include_in_sales' => false,
                'include_in_income' => true,
                'include_in_expense' => false,
                'include_in_pl' => true,
                'settlement_behavior' => 'none',
                'petty_behavior' => 'none',
                'company_pending_behavior' => 'none',
            ]
        );

        ShopLedgerEntrySetting::query()->firstOrCreate(
            [
                'shop_id' => $shopId,
                'entry_type_id' => $otherExpenseType->id,
            ],
            [
                'version' => 1,
                'effective_from' => self::DEFAULT_EFFECTIVE_FROM,
                'effective_to' => null,
                'enabled' => true,
                'default_funding_source' => 'sales',
                'allowed_funding_sources' => ['sales', 'petty', 'company', 'company_later'],
                'include_in_sales' => false,
                'include_in_income' => false,
                'include_in_expense' => true,
                'include_in_pl' => true,
                'settlement_behavior' => 'none',
                'petty_behavior' => 'none',
                'company_pending_behavior' => 'none',
            ]
        );

        ShopLedgerEntrySetting::query()->updateOrCreate(
            [
                'shop_id' => $shopId,
                'entry_type_id' => $salaryType->id,
            ],
            [
                'version' => 1,
                'effective_from' => self::DEFAULT_EFFECTIVE_FROM,
                'effective_to' => null,
                'enabled' => true,
                'default_funding_source' => 'sales',
                'allowed_funding_sources' => ['sales', 'petty', 'company', 'company_later'],
                'include_in_sales' => false,
                'include_in_income' => false,
                'include_in_expense' => true,
                'include_in_pl' => true,
                'settlement_behavior' => 'none',
                'petty_behavior' => 'none',
                'company_pending_behavior' => 'none',
            ]
        );
    }
}
