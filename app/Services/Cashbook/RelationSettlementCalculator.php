<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopCashbookRelation;

class RelationSettlementCalculator
{
    /**
     * Calculate settlement details for a given relation, entry amounts, and balance inputs.
     * Pure in-memory computation. ZERO database mutations.
     *
     * @param  array<int, float>  $entryAmounts  Map of [setting_id => amount]
     * @param  float  $openingEligibleBalance  Opening shop balance available
     * @param  float  $todayShopHeldCollections  Net cash collections produced today
     * @return array<string, mixed>
     */
    public function calculate(
        ShopCashbookRelation $relation,
        array $entryAmounts,
        float $openingEligibleBalance = 0.0,
        float $todayShopHeldCollections = 0.0
    ): array {
        $grossAdditions = 0.0;
        $grossDeductions = 0.0;
        $itemsBreakdown = [];

        foreach ($relation->items as $item) {
            $settingId = $item->shop_ledger_entry_setting_id ? (int) $item->shop_ledger_entry_setting_id : null;
            $headerGroupId = $item->header_group_id ? (int) $item->header_group_id : null;
            $sourceSettlementId = $item->source_settlement_id ? (int) $item->source_settlement_id : null;
            $headerMode = $item->header_mode ?? 'all_categories';

            if ($sourceSettlementId !== null) {
                $rawAmt = (float) ($entryAmounts['settlement_'.$sourceSettlementId] ?? 0.0);
                $name = 'Settlement: '.($item->sourceSettlement?->name ?? ('#'.$sourceSettlementId));
                $category = 'settlement';
            } elseif ($headerGroupId !== null) {
                $taggedProductAmt = (float) ($entryAmounts['header_tagged_product_'.$headerGroupId] ?? 0.0);
                $categoriesAmt = 0.0;
                if ($item->headerGroup) {
                    foreach ($item->headerGroup->entrySettings as $setting) {
                        $categoriesAmt += (float) ($entryAmounts[$setting->id] ?? 0.0);
                    }
                }

                $hgName = $item->headerGroup?->name ?? ('Header #'.$headerGroupId);
                if ($headerMode === 'tagged_products_only') {
                    $rawAmt = $taggedProductAmt;
                    $name = $hgName.' (Product Total Only)';
                } elseif ($headerMode === 'categories_and_products') {
                    $rawAmt = $categoriesAmt + $taggedProductAmt;
                    $name = $hgName.' (Categories + Product Total)';
                } else {
                    $rawAmt = $categoriesAmt;
                    $name = $hgName.' (All Categories Only)';
                }
                $category = strtolower((string) ($item->headerGroup?->type ?? 'header'));
            } else {
                $rawAmt = (float) ($entryAmounts[$settingId] ?? 0.0);
                $name = $item->setting?->displayName() ?? $item->setting?->entryType?->name ?? 'Unknown Entry';
                $category = $item->setting?->entryType?->category ?? 'other';
            }

            $amount = max(0.0, $rawAmt);

            $role = strtolower((string) ($item->role ?? 'add'));
            if ($role === 'subtract') {
                $grossDeductions += $amount;
                $signedAmount = -$amount;
            } else {
                $grossAdditions += $amount;
                $signedAmount = $amount;
            }

            $itemsBreakdown[] = [
                'item_id' => $item->id,
                'setting_id' => $settingId,
                'header_group_id' => $headerGroupId,
                'header_mode' => $headerMode,
                'name' => $name,
                'category' => $category,
                'role' => $role,
                'amount' => $amount,
                'signed_amount' => $signedAmount,
            ];
        }

        $netSettlement = $grossAdditions - $grossDeductions;

        $eligibilityRule = $relation->eligibility_rule;

        if ($eligibilityRule === 'previous_day_balance') {
            // Previous-Day Balance Only: today's new collection CANNOT fund today's relation settlement
            $eligibleAmount = max(0.0, $openingEligibleBalance);
            if ($netSettlement > 0) {
                $settledAmount = min($netSettlement, $eligibleAmount);
                $remainingSettlementPayable = $netSettlement - $settledAmount;
                $closingEligibleBalance = ($openingEligibleBalance - $settledAmount) + $todayShopHeldCollections;
            } else {
                $settledAmount = $netSettlement;
                $remainingSettlementPayable = 0.0;
                $closingEligibleBalance = $openingEligibleBalance - $netSettlement + $todayShopHeldCollections;
            }
        } else {
            // Default/Current Available Balance or Unselected
            $eligibleAmount = max(0.0, $openingEligibleBalance + $todayShopHeldCollections);
            if ($netSettlement > 0) {
                $settledAmount = min($netSettlement, $eligibleAmount);
                $remainingSettlementPayable = $netSettlement - $settledAmount;
                $closingEligibleBalance = ($openingEligibleBalance + $todayShopHeldCollections) - $settledAmount;
            } else {
                $settledAmount = $netSettlement;
                $remainingSettlementPayable = 0.0;
                $closingEligibleBalance = $openingEligibleBalance + $todayShopHeldCollections - $netSettlement;
            }
        }

        return [
            'relation_id' => $relation->id,
            'public_uuid' => $relation->public_uuid,
            'name' => $relation->name,
            'relation_type' => $relation->relation_type,
            'settlement_source' => $relation->settlement_source,
            'eligibility_rule' => $relation->eligibility_rule,
            'enabled' => (bool) $relation->enabled,
            'items' => $itemsBreakdown,
            'grossAdditions' => $grossAdditions,
            'grossDeductions' => $grossDeductions,
            'netSettlement' => $netSettlement,
            'openingEligibleBalance' => $openingEligibleBalance,
            'todayShopHeldCollections' => $todayShopHeldCollections,
            'eligibleAmount' => $eligibleAmount,
            'settledAmount' => $settledAmount,
            'remainingSettlementPayable' => $remainingSettlementPayable,
            'closingEligibleBalance' => $closingEligibleBalance,
        ];
    }
}
