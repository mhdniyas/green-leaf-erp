<?php

declare(strict_types=1);

namespace App\Services\Cashbook\PaymentsSettings;

use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class PaymentsAdvancedSettingsService
{
    /**
     * Get view model for Advanced tab.
     *
     * @return array<string, mixed>
     */
    public function getViewModel(Shop $shop): array
    {
        $shopId = (int) $shop->id;

        $settings = ShopLedgerEntrySetting::query()
            ->with(['entryType', 'headerGroup'])
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->get();

        // Check relation items for settlement conflicts
        $relationItems = ShopCashbookRelationItem::query()
            ->with('relation')
            ->whereIn('shop_ledger_entry_setting_id', $settings->pluck('id'))
            ->get()
            ->groupBy('shop_ledger_entry_setting_id');

        $categories = [];
        $conflictCount = 0;

        foreach ($settings as $setting) {
            $legacyBehavior = strtolower((string) ($setting->settlement_behavior ?? 'ignore'));
            $items = $relationItems->get($setting->id, collect());

            $conflicts = [];
            foreach ($items as $ri) {
                $relationRole = strtolower((string) $ri->role); // add or subtract
                $relationName = $ri->relation?->name ?? 'Settlement';

                if ($legacyBehavior === 'add' && $relationRole === 'subtract') {
                    $conflicts[] = "Legacy settlement_behavior is 'add', but modern Relation '{$relationName}' has role 'SUBTRACT'. Modern relation takes precedence.";
                } elseif ($legacyBehavior === 'subtract' && $relationRole === 'add') {
                    $conflicts[] = "Legacy settlement_behavior is 'subtract', but modern Relation '{$relationName}' has role 'ADD'. Modern relation takes precedence.";
                } elseif ($legacyBehavior === 'ignore' && in_array($relationRole, ['add', 'subtract'], true)) {
                    $conflicts[] = "Legacy settlement_behavior is 'ignore', but modern Relation '{$relationName}' actively uses it as '{$relationRole}'. Modern relation takes precedence.";
                }
            }

            if (! empty($conflicts)) {
                $conflictCount++;
            }

            $categories[] = [
                'setting' => $setting,
                'name' => $setting->displayName(),
                'header_name' => $setting->headerGroup?->name ?? 'General',
                'default_funding_source' => $setting->default_funding_source ?? 'sales',
                'allowed_funding_sources' => (array) ($setting->allowed_funding_sources ?? ['sales', 'shop_cash']),
                'company_pending_behavior' => $setting->company_pending_behavior ?? 'ignore',
                'petty_behavior' => $setting->petty_behavior ?? 'ignore',
                'settlement_behavior' => $setting->settlement_behavior ?? 'ignore',
                'include_in_payable' => (bool) $setting->include_in_payable,
                'payable_direction' => $setting->payable_direction ?? 'add',
                'is_legacy_protected' => true,
                'conflicts' => $conflicts,
            ];
        }

        return [
            'categories' => $categories,
            'conflict_count' => $conflictCount,
            'notice' => 'Notice: Modern settlement calculation is driven authoritatively by shop_cashbook_relations and relation items. Legacy settlement_behavior is displayed read-only to prevent corruption.',
        ];
    }

    /**
     * Save non-legacy advanced settings for a shop (strict shop isolation).
     */
    public function saveSettings(Shop $shop, array $settingsData, int $userId): void
    {
        $shopId = (int) $shop->id;

        DB::transaction(function () use ($shopId, $settingsData): void {
            foreach ($settingsData as $settingId => $data) {
                $setting = ShopLedgerEntrySetting::query()
                    ->where('shop_id', $shopId)
                    ->where('id', $settingId)
                    ->first();

                if (! $setting) {
                    continue;
                }

                // Update non-legacy fields only
                $setting->update([
                    'default_funding_source' => $data['default_funding_source'] ?? $setting->default_funding_source,
                    'allowed_funding_sources' => (array) ($data['allowed_funding_sources'] ?? $setting->allowed_funding_sources),
                    'company_pending_behavior' => $data['company_pending_behavior'] ?? $setting->company_pending_behavior,
                    'include_in_payable' => isset($data['include_in_payable']) ? (bool) $data['include_in_payable'] : $setting->include_in_payable,
                    'payable_direction' => $data['payable_direction'] ?? $setting->payable_direction,
                ]);
            }
        });
    }
}
