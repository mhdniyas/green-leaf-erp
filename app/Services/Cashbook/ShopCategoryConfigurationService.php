<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CategoryVendorMapping;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

final class ShopCategoryConfigurationService
{
    /**
     * Update Basic & Header Configuration for a shop category setting.
     */
    public function updateBasicAndHeader(ShopLedgerEntrySetting $setting, array $data): ShopLedgerEntrySetting
    {
        $setting->update([
            'enabled' => array_key_exists('enabled', $data) ? (bool) $data['enabled'] : $setting->enabled,
            'display_name' => array_key_exists('display_name', $data) ? ($data['display_name'] !== null && trim((string) $data['display_name']) !== '' ? trim((string) $data['display_name']) : null) : $setting->display_name,
            'header_group_id' => ! empty($data['header_group_id']) ? (int) $data['header_group_id'] : null,
            'header_display_order' => isset($data['header_display_order']) && $data['header_display_order'] !== null ? (int) $data['header_display_order'] : ($setting->header_display_order ?? 0),
        ]);

        return $setting;
    }

    /**
     * Update Settlement Configuration for a shop category setting.
     * Updates both settlement_behavior string AND shop_cashbook_relation_items in the settlement engine.
     */
    public function updateSettlement(ShopLedgerEntrySetting $setting, array $data): ShopLedgerEntrySetting
    {
        DB::transaction(function () use ($setting, $data): void {
            $relationId = ! empty($data['relation_id']) ? (int) $data['relation_id'] : null;
            $role = isset($data['role']) && in_array($data['role'], ['add', 'subtract'], true) ? $data['role'] : null;
            $behavior = isset($data['settlement_behavior']) ? (string) $data['settlement_behavior'] : null;

            if ($behavior !== null) {
                $setting->settlement_behavior = $behavior;
            }

            if ($relationId !== null) {
                $setting->vendor_settlement_relation_id = $relationId;
            } elseif (array_key_exists('relation_id', $data) && $data['relation_id'] === null) {
                $setting->vendor_settlement_relation_id = null;
            }

            $setting->save();

            // Handle real settlement engine relation items (shop_cashbook_relation_items)
            if ($relationId && $role) {
                $relation = ShopCashbookRelation::where('id', $relationId)
                    ->where('shop_id', $setting->shop_id)
                    ->first();

                if ($relation) {
                    ShopCashbookRelationItem::updateOrCreate(
                        [
                            'relation_id' => $relation->id,
                            'shop_ledger_entry_setting_id' => $setting->id,
                        ],
                        [
                            'role' => $role,
                            'header_mode' => 'all_categories',
                        ]
                    );
                }
            } elseif (array_key_exists('relation_id', $data) && empty($data['relation_id'])) {
                // If relation removed, delete relation items for this setting
                ShopCashbookRelationItem::where('shop_ledger_entry_setting_id', $setting->id)->delete();
            }
        });

        return $setting;
    }

    /**
     * Update Company Relation & Bank Account Configuration.
     */
    public function updateCompanyRelation(ShopLedgerEntrySetting $setting, array $data): ShopLedgerEntrySetting
    {
        $setting->update([
            'company_account_id' => ! empty($data['company_account_id']) ? (int) $data['company_account_id'] : null,
            'default_funding_source' => isset($data['default_funding_source']) ? (string) $data['default_funding_source'] : $setting->default_funding_source,
        ]);

        return $setting;
    }

    /**
     * Update Vendor Relation & Purchase Configuration for a shop.
     * Scoped strictly by shop via shop_ledger_entry_setting_id.
     */
    public function updateVendorRelation(ShopLedgerEntrySetting $setting, array $data): ShopLedgerEntrySetting
    {
        DB::transaction(function () use ($setting, $data): void {
            $setting->update([
                'is_vendor_purchase' => array_key_exists('is_vendor_purchase', $data) ? (bool) $data['is_vendor_purchase'] : $setting->is_vendor_purchase,
                'vendor_purchase_payment_type' => isset($data['vendor_purchase_payment_type']) ? (string) $data['vendor_purchase_payment_type'] : $setting->vendor_purchase_payment_type,
                'vendor_access_mode' => isset($data['vendor_access_mode']) ? (string) $data['vendor_access_mode'] : $setting->vendor_access_mode,
                'mirror_to_cashbook' => array_key_exists('mirror_to_cashbook', $data) ? (bool) $data['mirror_to_cashbook'] : (bool) ($setting->mirror_to_cashbook ?? true),
            ]);

            if (array_key_exists('pinned_supplier_ids', $data) && is_array($data['pinned_supplier_ids'])) {
                CategoryVendorMapping::where('shop_ledger_entry_setting_id', $setting->id)->delete();
                foreach ($data['pinned_supplier_ids'] as $supplierId) {
                    if (! empty($supplierId)) {
                        CategoryVendorMapping::create([
                            'shop_ledger_entry_setting_id' => $setting->id,
                            'shop_supplier_id' => (int) $supplierId,
                        ]);
                    }
                }
            }
        });

        return $setting;
    }

    /**
     * Update Reports & Accounting Flags for a shop.
     */
    public function updateAccountingFlags(ShopLedgerEntrySetting $setting, array $data): ShopLedgerEntrySetting
    {
        $setting->update([
            'include_in_sales' => (bool) ($data['include_in_sales'] ?? false),
            'include_in_income' => (bool) ($data['include_in_income'] ?? false),
            'include_in_expense' => (bool) ($data['include_in_expense'] ?? false),
            'include_in_pl' => (bool) ($data['include_in_pl'] ?? false),
            'include_in_payable' => (bool) ($data['include_in_payable'] ?? false),
            'payable_direction' => ! empty($data['payable_direction']) ? (string) $data['payable_direction'] : null,
            'sales_report_bucket' => ! empty($data['sales_report_bucket']) ? (string) $data['sales_report_bucket'] : 'default',
        ]);

        return $setting;
    }

    /**
     * Update Advanced Settings for a shop.
     */
    public function updateAdvancedSettings(ShopLedgerEntrySetting $setting, array $data): ShopLedgerEntrySetting
    {
        $setting->update([
            'note_enabled' => (bool) ($data['note_enabled'] ?? false),
            'edit_policy' => isset($data['edit_policy']) ? (string) $data['edit_policy'] : 'past_days_allowed',
            'petty_behavior' => ! empty($data['petty_behavior']) ? (string) $data['petty_behavior'] : null,
            'company_pending_behavior' => ! empty($data['company_pending_behavior']) ? (string) $data['company_pending_behavior'] : null,
            'generates_secondary_entry' => (bool) ($data['generates_secondary_entry'] ?? false),
            'secondary_entry_type_id' => ! empty($data['secondary_entry_type_id']) ? (int) $data['secondary_entry_type_id'] : null,
            'secondary_amount_mode' => isset($data['secondary_amount_mode']) ? (string) $data['secondary_amount_mode'] : 'same_amount',
            'secondary_amount_value' => isset($data['secondary_amount_value']) && $data['secondary_amount_value'] !== '' ? (float) $data['secondary_amount_value'] : null,
            'mirror_to_cashbook' => (bool) ($data['mirror_to_cashbook'] ?? false),
        ]);

        return $setting;
    }

    /**
     * Assign or unassign a category to/from shops.
     * Assign: creates or sets enabled = true.
     * Unassign: sets enabled = false without deleting rows.
     */
    public function assignShops(LedgerEntryType $entryType, array $shopIds): void
    {
        DB::transaction(function () use ($entryType, $shopIds): void {
            $allShopIds = Shop::query()->pluck('id')->all();
            $targetShopIds = array_map('intval', $shopIds);

            foreach ($allShopIds as $sId) {
                $setting = ShopLedgerEntrySetting::where('shop_id', $sId)
                    ->where('entry_type_id', $entryType->id)
                    ->first();

                if (in_array($sId, $targetShopIds, true)) {
                    if ($setting) {
                        if (! $setting->enabled) {
                            $setting->update(['enabled' => true]);
                        }
                    } else {
                        ShopLedgerEntrySetting::create([
                            'shop_id' => $sId,
                            'entry_type_id' => $entryType->id,
                            'display_name' => null,
                            'enabled' => true,
                            'effective_from' => now()->toDateString(),
                            'default_funding_source' => match (strtolower((string) $entryType->category)) {
                                'income' => 'sales',
                                'expense' => 'sales',
                                default => 'none',
                            },
                            'include_in_sales' => $entryType->code === 'cash_sales',
                            'include_in_income' => strtolower((string) $entryType->category) === 'income',
                            'include_in_expense' => strtolower((string) $entryType->category) === 'expense',
                            'include_in_pl' => true,
                        ]);
                    }
                } else {
                    if ($setting && $setting->enabled) {
                        $setting->update(['enabled' => false]);
                    }
                }
            }
        });
    }
}
