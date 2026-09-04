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
            $settingId = (int) $item->shop_ledger_entry_setting_id;
            $rawAmt = (float) ($entryAmounts[$settingId] ?? 0.0);
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
                'name' => $item->setting?->entryType?->name ?? 'Unknown Entry',
                'category' => $item->setting?->entryType?->category ?? 'other',
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
