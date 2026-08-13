<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\LedgerClient;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopConfigPreset;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Client;
use App\Models\Shop;
use App\Models\ShopInvoice;
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
    /**
     * Synchronizes Green Leaf ERP's owned shops into ShopLedgerProfile and returns the updated collection.
     *
     * @return Collection<int, ShopLedgerProfile>
     */
    public function syncAndGetProfiles(): Collection
    {
        $erpShops = Shop::query()
            ->cashbookEligible()
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

                $this->ensureShopSettingsExist($profile, $preset);
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
        $purchaseBillType = LedgerEntryType::where('code', 'purchase_bill')->first();
        if (! $purchaseBillType) {
            return;
        }

        $alreadySynced = ShopLedgerTransaction::where('entry_type_id', $purchaseBillType->id)
            ->where('reference_type', ShopInvoice::class)
            ->pluck('reference_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $syncedMap = array_flip($alreadySynced);

        $invoices = ShopInvoice::where('final_total', '>', 0)
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'shop_id', 'business_date', 'invoice_number', 'final_total']);

        foreach ($invoices as $inv) {
            if (isset($syncedMap[(int) $inv->id])) {
                continue;
            }

            try {
                app(TransactionGenerator::class)->record([
                    'shop_id' => (int) $inv->shop_id,
                    'business_date' => $inv->business_date?->toDateString() ?? today()->toDateString(),
                    'entry_type_code' => 'purchase_bill',
                    'amount' => (float) $inv->final_total,
                    'funding_source' => 'company',
                    'reference_type' => ShopInvoice::class,
                    'reference_id' => (int) $inv->id,
                    'notes' => 'Auto from invoice '.$inv->invoice_number,
                    'entered_by' => 1,
                ]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Ensures default entry settings are created for a shop profile if none exist yet.
     */
    private function ensureShopSettingsExist(ShopLedgerProfile $profile, ?ShopConfigPreset $preset): void
    {
        $existingCount = ShopLedgerEntrySetting::where('shop_id', $profile->shop_id)->count();

        if ($existingCount > 0 || ! $preset) {
            return;
        }

        foreach ($preset->entrySettings as $presetSetting) {
            ShopLedgerEntrySetting::create([
                'shop_id' => $profile->shop_id,
                'entry_type_id' => $presetSetting->entry_type_id,
                'version' => 1,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'enabled' => $presetSetting->enabled,
                'default_funding_source' => $presetSetting->default_funding_source,
                'allowed_funding_sources' => $presetSetting->allowed_funding_sources,
                'include_in_sales' => $presetSetting->include_in_sales,
                'include_in_income' => $presetSetting->include_in_income,
                'include_in_expense' => $presetSetting->include_in_expense,
                'include_in_pl' => $presetSetting->include_in_pl,
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
}
