<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;

final class CategoryExplanationService
{
    /**
     * Generate a human-readable dynamic explanation sentence for a shop's category setting.
     * Example: "Paytm is enabled under Sales for AV Casio. It is part of Income Settlement and adds to the settlement calculation. Money is received directly by the company through HDFC. It is included in Sales and P&L and has no vendor relation."
     */
    public function generateSummary(ShopLedgerEntrySetting $setting): string
    {
        $setting->loadMissing(['entryType', 'headerGroup', 'companyAccount', 'definedShopSuppliers', 'vendorSettlementRelation']);

        $categoryName = $setting->displayName();
        $shopName = $setting->shop?->name ?? 'Shop #'.$setting->shop_id;
        $headerName = $setting->headerGroup?->name;
        $isEnabled = (bool) $setting->enabled;

        $parts = [];

        // 1. Status & Header
        if ($isEnabled) {
            if ($headerName) {
                $parts[] = "{$categoryName} is enabled under {$headerName} for {$shopName}.";
            } else {
                $parts[] = "{$categoryName} is enabled for {$shopName} (no header assigned).";
            }
        } else {
            $parts[] = "{$categoryName} is currently disabled for {$shopName}.";
        }

        // 2. Settlement
        $relationTarget = $setting->vendorSettlementRelation;
        $settlementItem = ShopCashbookRelationItem::where('shop_ledger_entry_setting_id', $setting->id)->first();
        if ($settlementItem && $settlementItem->relation) {
            $settlementName = $settlementItem->relation->name;
            $roleLabel = strtolower((string) $settlementItem->role) === 'subtract' ? 'subtracts from' : 'adds to';
            $parts[] = "It is part of {$settlementName} and {$roleLabel} the settlement calculation.";
        } elseif ($relationTarget) {
            $settlementName = $relationTarget->name;
            $roleLabel = strtolower((string) $setting->settlement_behavior) === 'decrease' ? 'subtracts from' : 'adds to';
            $parts[] = "It is linked to {$settlementName} and {$roleLabel} the settlement calculation.";
        } elseif ($setting->settlement_behavior && $setting->settlement_behavior !== 'none') {
            $roleLabel = strtolower((string) $setting->settlement_behavior) === 'decrease' ? 'subtracts from' : 'adds to';
            $parts[] = "It {$roleLabel} the settlement calculation.";
        } else {
            $parts[] = 'It is not included in settlement calculation.';
        }

        // 3. Company Relation & Account
        $acc = $setting->companyAccount ?? $setting->headerGroup?->companyAccount;
        if ($acc) {
            $accName = $acc->name ?: $acc->bank_name ?: 'Company Account';
            $parts[] = "Money is received directly by the company through {$accName}.";
        } elseif ($setting->default_funding_source === 'company') {
            $parts[] = 'Paid directly by the company.';
        } elseif ($setting->default_funding_source === 'petty') {
            $parts[] = 'Paid from Petty cash.';
        } elseif ($setting->default_funding_source === 'sales' || $setting->default_funding_source === 'shop_balance') {
            $parts[] = 'Paid from Shop Cash.';
        } else {
            $parts[] = 'No specific company bank account mapped.';
        }

        // 4. Reports & Accounting Flags
        $reportFlags = [];
        if ($setting->include_in_sales) {
            $reportFlags[] = 'Sales';
        }
        if ($setting->include_in_income) {
            $reportFlags[] = 'Income';
        }
        if ($setting->include_in_expense) {
            $reportFlags[] = 'Expense';
        }
        if ($setting->include_in_pl) {
            $reportFlags[] = 'P&L';
        }
        if ($setting->include_in_payable) {
            $reportFlags[] = 'Payables';
        }

        if (! empty($reportFlags)) {
            $parts[] = 'It is included in '.implode(', ', $reportFlags).'.';
        } else {
            $parts[] = 'It is not included in standard sales or P&L report totals.';
        }

        // 5. Vendor Relation
        if ($setting->is_vendor_purchase) {
            $mode = ucfirst((string) ($setting->vendor_purchase_payment_type ?: 'purchase'));
            $pinnedCount = $setting->definedShopSuppliers->count();
            if ($pinnedCount > 0) {
                $parts[] = "It is a {$mode} Vendor Purchase category with {$pinnedCount} pinned vendor(s).";
            } else {
                $parts[] = "It is a {$mode} Vendor Purchase category.";
            }
        } else {
            $parts[] = 'It has no vendor relation.';
        }

        return implode(' ', $parts);
    }
}
