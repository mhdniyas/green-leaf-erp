<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\LedgerClient;
use App\Models\Cashbook\ShopConfigPreset;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;
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
        // Sync all shops that participate in cashbook accounting:
        //   - accounting_mode = 'owned' (Aiswarya Veg style managed shops)
        //   - client_id IS NOT NULL (explicitly client-linked)
        //   - accounting_mode = 'regular' with accounting_enabled (direct buyer shops: Fortune, Quick Mart, etc.)
        // Auto-enable accounting for direct non-owned shops if disabled in database
        Shop::query()
            ->whereNull('client_id')
            ->where('accounting_enabled', false)
            ->update(['accounting_enabled' => true, 'accounting_mode' => 'regular']);

        $erpShops = Shop::query()
            ->where('accounting_enabled', true)
            ->where(function ($query): void {
                $query->where('accounting_mode', 'owned')
                    ->orWhere('accounting_mode', 'regular')
                    ->orWhereNotNull('client_id');
            })
            ->orderBy('id')
            ->get();

        $standardPreset  = ShopConfigPreset::where('slug', 'standard-veg-shop')->first()
            ?? ShopConfigPreset::where('is_default', true)->first();
        $grandcityPreset = ShopConfigPreset::where('slug', 'grandcity-extended')->first();

        foreach ($erpShops as $erpShop) {
            if (! $erpShop->accounting_enabled) {
                $erpShop->update(['accounting_enabled' => true]);
            }

            $isGrandcity = Str::contains(strtoupper($erpShop->code), 'GRANDCITY')
                || Str::contains(strtoupper($erpShop->name), 'GRANDCITY');

            $preset = $isGrandcity ? ($grandcityPreset ?? $standardPreset) : $standardPreset;

            $isDirectShop = $erpShop->client_id === null || (string) $erpShop->accounting_mode === 'regular';

            $profile = ShopLedgerProfile::updateOrCreate(
                ['shop_id' => $erpShop->id],
                [
                    'uuid'             => ShopLedgerProfile::where('shop_id', $erpShop->id)->value('uuid') ?? (string) Str::uuid(),
                    'slug'             => Str::slug($erpShop->code . '-' . $erpShop->name),
                    'code'             => $erpShop->code,
                    'name'             => $erpShop->name,
                    'profile_template' => $isDirectShop ? 'direct_buyer' : 'owned_standard',
                    'enabled'          => true,
                    'closing_mode'     => 'manual',
                    'preset_id'        => $preset?->id,
                    'client_id'        => ($erpShop->client_id && LedgerClient::where('id', $erpShop->client_id)->exists()) ? $erpShop->client_id : null,
                ]
            );

            $this->ensureShopSettingsExist($profile, $preset);
        }

        $this->syncInvoicesToCashbook();

        return ShopLedgerProfile::with(['client', 'preset'])->orderBy('shop_id')->get();
    }

    /**
     * Automatically syncs approved ShopInvoice records from Green Leaf ERP
     * into cashbook ShopLedgerTransaction entries (code: purchase_bill).
     */
    public function syncInvoicesToCashbook(): void
    {
        $purchaseBillType = \App\Models\Cashbook\LedgerEntryType::where('code', 'purchase_bill')->first();
        if (! $purchaseBillType) {
            return;
        }

        $alreadySynced = \App\Models\Cashbook\ShopLedgerTransaction::where('entry_type_id', $purchaseBillType->id)
            ->where('reference_type', \App\Models\ShopInvoice::class)
            ->pluck('reference_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $syncedMap = array_flip($alreadySynced);

        $invoices = \App\Models\ShopInvoice::where('final_total', '>', 0)
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'shop_id', 'business_date', 'invoice_number', 'final_total']);

        foreach ($invoices as $inv) {
            if (isset($syncedMap[(int) $inv->id])) {
                continue;
            }

            try {
                app(TransactionGenerator::class)->record([
                    'shop_id'          => (int) $inv->shop_id,
                    'business_date'    => $inv->business_date?->toDateString() ?? today()->toDateString(),
                    'entry_type_code'  => 'purchase_bill',
                    'amount'          => (float) $inv->final_total,
                    'funding_source'  => 'company',
                    'reference_type'  => \App\Models\ShopInvoice::class,
                    'reference_id'    => (int) $inv->id,
                    'notes'           => 'Auto from invoice ' . $inv->invoice_number,
                    'entered_by'      => 1,
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
                'shop_id'                   => $profile->shop_id,
                'entry_type_id'             => $presetSetting->entry_type_id,
                'version'                   => 1,
                'effective_from'            => '2026-01-01',
                'effective_to'              => null,
                'enabled'                   => $presetSetting->enabled,
                'default_funding_source'    => $presetSetting->default_funding_source,
                'allowed_funding_sources'   => $presetSetting->allowed_funding_sources,
                'include_in_sales'          => $presetSetting->include_in_sales,
                'include_in_income'         => $presetSetting->include_in_income,
                'include_in_expense'        => $presetSetting->include_in_expense,
                'include_in_pl'             => $presetSetting->include_in_pl,
                'generates_secondary_entry' => $presetSetting->generates_secondary_entry,
                'secondary_entry_type_id'   => $presetSetting->secondary_entry_type_id,
                'secondary_amount_mode'     => $presetSetting->secondary_amount_mode,
                'secondary_amount_value'    => $presetSetting->secondary_amount_value,
                'petty_behavior'            => $presetSetting->petty_behavior,
                'settlement_behavior'       => $presetSetting->settlement_behavior,
                'company_pending_behavior'  => $presetSetting->company_pending_behavior,
            ]);
        }
    }
}
