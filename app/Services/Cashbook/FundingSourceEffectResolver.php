<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\DTO\Cashbook\LedgerEffect;
use App\Enums\Cashbook\BalanceMove;
use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\LedgerDirection;
use App\Models\Cashbook\ShopLedgerEntrySetting;

/**
 * "The accounting rule belongs to the funding source, not duplicated inside
 * every expense type." This is the one place the Sales / Petty / Company /
 * Company Later / Bank / External rules live.
 *
 * A ShopLedgerEntrySetting can still declare explicit settlement/petty/
 * company_pending overrides — that's how Transfers and Settlements (which
 * don't have a "funding source" in the expense/income sense) are modelled.
 */
class FundingSourceEffectResolver
{
    public function resolve(
        LedgerDirection $direction,
        FundingSource $fundingSource,
        float $amount,
        ShopLedgerEntrySetting $setting
    ): LedgerEffect {
        // Only treat a behavior as an active override when it is explicitly set
        // to a non-'none' value. The default stored value of 'none' is truthy
        // but must NOT bypass the funding-source logic — otherwise an expense
        // paid from petty cash would never produce petty_delta = -amount.
        $isActive = static fn (?string $v): bool => filled($v) && $v !== 'none';
        $hasOverride = $isActive($setting->settlement_behavior)
            || $isActive($setting->petty_behavior)
            || $isActive($setting->company_pending_behavior);

        if ($hasOverride) {
            return $this->resolveFromOverrides($direction, $amount, $setting);
        }

        return match ($direction) {
            LedgerDirection::Expense => $this->resolveExpense($fundingSource, $amount),
            LedgerDirection::Income  => $this->resolveIncome($amount, $setting),
            default                  => $this->resolveFromOverrides($direction, $amount, $setting),
        };
    }

    private function resolveExpense(FundingSource $source, float $amount): LedgerEffect
    {
        return match ($source) {
            // Sales: paid straight out of today's cash sales → reduces what
            // the shop owes on settlement.
            FundingSource::Sales => new LedgerEffect(
                plDelta: -$amount,
                settlementDelta: -$amount,
                settlementDirection: BalanceMove::Decrease,
                pettyDelta: 0.0,
                pettyDirection: BalanceMove::None,
                companyPendingDelta: 0.0,
                companyPendingDirection: BalanceMove::None,
            ),

            FundingSource::Petty => new LedgerEffect(
                plDelta: -$amount,
                settlementDelta: 0.0,
                settlementDirection: BalanceMove::None,
                pettyDelta: -$amount,
                pettyDirection: BalanceMove::Decrease,
                companyPendingDelta: 0.0,
                companyPendingDirection: BalanceMove::None,
            ),

            // Company / Bank / External: already paid directly by the payer.
            FundingSource::Company, FundingSource::Bank, FundingSource::External => new LedgerEffect(
                plDelta: -$amount,
                settlementDelta: 0.0,
                settlementDirection: BalanceMove::None,
                pettyDelta: 0.0,
                pettyDirection: BalanceMove::None,
                companyPendingDelta: 0.0,
                companyPendingDirection: BalanceMove::None,
            ),

            // Company Later: shop incurred it, company owes reimbursement.
            FundingSource::CompanyLater => new LedgerEffect(
                plDelta: -$amount,
                settlementDelta: 0.0,
                settlementDirection: BalanceMove::None,
                pettyDelta: 0.0,
                pettyDirection: BalanceMove::None,
                companyPendingDelta: $amount,
                companyPendingDirection: BalanceMove::Increase,
            ),

            // None: used for generated secondary expenses whose P/L effect
            // is already fully explained by the parent income.
            FundingSource::None => new LedgerEffect(
                plDelta: -$amount,
                settlementDelta: 0.0,
                settlementDirection: BalanceMove::None,
                pettyDelta: 0.0,
                pettyDirection: BalanceMove::None,
                companyPendingDelta: 0.0,
                companyPendingDirection: BalanceMove::None,
            ),
        };
    }

    private function resolveIncome(float $amount, ShopLedgerEntrySetting $setting): LedgerEffect
    {
        // Ordinary sales income increases P/L, and increases what the shop
        // owes the company on settlement.
        $settlementDelta = $setting->include_in_sales ? $amount : 0.0;

        return new LedgerEffect(
            plDelta: $setting->include_in_pl ? $amount : 0.0,
            settlementDelta: $settlementDelta,
            settlementDirection: $settlementDelta > 0 ? BalanceMove::Increase : BalanceMove::None,
            pettyDelta: 0.0,
            pettyDirection: BalanceMove::None,
            companyPendingDelta: 0.0,
            companyPendingDirection: BalanceMove::None,
        );
    }

    /**
     * Used for Transfers/Settlements, or any entry setting that explicitly
     * overrides the funding-source defaults.
     */
    private function resolveFromOverrides(LedgerDirection $direction, float $amount, ShopLedgerEntrySetting $setting): LedgerEffect
    {
        $settlementMove     = $setting->settlement_behavior ? BalanceMove::from($setting->settlement_behavior) : BalanceMove::None;
        $pettyMove          = $setting->petty_behavior ? BalanceMove::from($setting->petty_behavior) : BalanceMove::None;
        $companyPendingMove = $setting->company_pending_behavior ? BalanceMove::from($setting->company_pending_behavior) : BalanceMove::None;

        return new LedgerEffect(
            plDelta: $setting->include_in_pl ? $this->signedByDirection($direction, $amount) : 0.0,
            settlementDelta: $this->signedByMove($settlementMove, $amount),
            settlementDirection: $settlementMove,
            pettyDelta: $this->signedByMove($pettyMove, $amount),
            pettyDirection: $pettyMove,
            companyPendingDelta: $this->signedByMove($companyPendingMove, $amount),
            companyPendingDirection: $companyPendingMove,
        );
    }

    private function signedByMove(BalanceMove $move, float $amount): float
    {
        return match ($move) {
            BalanceMove::Increase => $amount,
            BalanceMove::Decrease => -$amount,
            BalanceMove::None     => 0.0,
        };
    }

    private function signedByDirection(LedgerDirection $direction, float $amount): float
    {
        return match ($direction) {
            LedgerDirection::Income  => $amount,
            LedgerDirection::Expense => -$amount,
            default                  => 0.0,
        };
    }
}
