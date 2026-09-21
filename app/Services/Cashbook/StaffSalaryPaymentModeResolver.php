<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\DTO\Cashbook\ResolvedSalaryPaymentMode;
use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\SalaryHrTransactionType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopSalaryBridgeSetting;
use Illuminate\Validation\ValidationException;

class StaffSalaryPaymentModeResolver
{
    public const SHOP_OWNER_ALLOWED_MODES = [
        'sales_cash',
        'petty',
        'company_payable',
    ];

    public function __construct(
        private readonly ShopSettlementService $settlementService,
    ) {}

    /**
     * @return array{allowed_modes: array<int, string>, default_mode: ?string, bridge_setting: ?ShopSalaryBridgeSetting}
     */
    public function resolveAllowedModesForShop(int $shopId, string $paymentType, bool $isShopOwner = true): array
    {
        $typeEnum = match ($paymentType) {
            'advance', 'salary_advance' => SalaryHrTransactionType::SalaryAdvance,
            'salary_adjustment', 'adjustment' => SalaryHrTransactionType::SalaryAdjustment,
            'advance_recovery', 'recovery' => SalaryHrTransactionType::AdvanceRecovery,
            default => SalaryHrTransactionType::Salary,
        };

        $bridgeSetting = ShopSalaryBridgeSetting::query()
            ->where('shop_id', $shopId)
            ->where('transaction_type', $typeEnum->value)
            ->where('is_enabled', true)
            ->first();

        if (! $bridgeSetting || ! $bridgeSetting->shop_ledger_entry_setting_id) {
            $configuredModes = $typeEnum->defaultAllowedModes();
            $allowedModes = array_values(array_filter(
                $configuredModes,
                fn (string $mode): bool => ! $isShopOwner || in_array($mode, self::SHOP_OWNER_ALLOWED_MODES, true)
            ));

            return [
                'allowed_modes' => $allowedModes,
                'default_mode' => $typeEnum->defaultPaymentMode(),
                'bridge_setting' => null,
            ];
        }

        $configuredModes = is_array($bridgeSetting->allowed_payment_modes)
            ? $bridgeSetting->allowed_payment_modes
            : [];

        $allowedModes = array_values(array_filter(
            $configuredModes,
            fn (string $mode): bool => ! $isShopOwner || in_array($mode, self::SHOP_OWNER_ALLOWED_MODES, true)
        ));

        $defaultMode = (string) ($bridgeSetting->default_payment_mode ?? '');
        if (! in_array($defaultMode, $allowedModes, true)) {
            $defaultMode = $allowedModes[0] ?? null;
        }

        return [
            'allowed_modes' => $allowedModes,
            'default_mode' => $defaultMode,
            'bridge_setting' => $bridgeSetting,
        ];
    }

    /**
     * Authoritatively resolve and validate a salary/advance payment mode.
     */
    public function resolvePaymentMode(
        int $shopId,
        string $paymentType,
        string $rawMode,
        bool $isShopOwner = true
    ): ResolvedSalaryPaymentMode {
        $canonicalMode = match ($rawMode) {
            'sales', 'sales_income', 'sales_cash' => 'sales_cash',
            'petty', 'petty_cash' => 'petty',
            'company', 'company_payable' => 'company_payable',
            default => strtolower(trim($rawMode)),
        };

        if ($isShopOwner && ! in_array($canonicalMode, self::SHOP_OWNER_ALLOWED_MODES, true)) {
            throw ValidationException::withMessages([
                'fund_source' => 'Shop owners can only pay using Sales Cash, Petty, or Company Payable.',
            ]);
        }

        $config = $this->resolveAllowedModesForShop($shopId, $paymentType, $isShopOwner);
        $bridgeSetting = $config['bridge_setting'];

        if (! in_array($canonicalMode, $config['allowed_modes'], true)) {
            throw ValidationException::withMessages([
                'fund_source' => "Payment mode '{$rawMode}' is not allowed for this shop under Salary Settings.",
            ]);
        }

        if ($canonicalMode === 'company_payable') {
            $settlementRelation = null;
            if ($bridgeSetting?->company_payable_settlement_id) {
                $settlementRelation = ShopCashbookRelation::query()
                    ->where('shop_id', $shopId)
                    ->where('id', $bridgeSetting->company_payable_settlement_id)
                    ->where('enabled', true)
                    ->first();
            }

            $settlementRelation ??= $this->settlementService->getCompanyPayableSettlement($shopId);

            if (! $settlementRelation instanceof ShopCashbookRelation) {
                throw ValidationException::withMessages([
                    'fund_source' => 'Company Payable settlement relation is incomplete or not configured for this shop.',
                ]);
            }

            return new ResolvedSalaryPaymentMode(
                bridgeMode: $canonicalMode,
                fundingSource: FundingSource::Company,
                settlementRelation: $settlementRelation,
            );
        }

        $fundingSource = match ($canonicalMode) {
            'petty' => FundingSource::Petty,
            default => FundingSource::Sales,
        };

        return new ResolvedSalaryPaymentMode(
            bridgeMode: $canonicalMode,
            fundingSource: $fundingSource,
            settlementRelation: null,
        );
    }
}
