<?php

declare(strict_types=1);

namespace App\Services\Cashbook\PaymentsSettings;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\ShopAccountingOpening;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\RelationSettlementCalculator;
use App\Services\Cashbook\ShopSettlementService;

class PaymentsSettingsOverviewService
{
    public function __construct(
        private readonly BalanceCalculator $balanceCalculator,
        private readonly ShopSettlementService $settlementService,
        private readonly RelationSettlementCalculator $relationCalculator,
    ) {}

    /**
     * Build the full overview payload for a shop over the current month.
     *
     * @return array<string, mixed>
     */
    public function getOverviewData(Shop $shop, string $startDate, string $endDate): array
    {
        $shopId = (int) $shop->id;

        // 1. Direct Company Collections (Paytm, Card, UPI, etc.)
        $directSettings = ShopLedgerEntrySetting::query()
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->whereNotNull('company_account_id')
            ->pluck('entry_type_id')
            ->filter();

        $directCollectionsTotal = (float) ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereIn('status', ['posted', 'approved'])
            ->whereNull('voided_at')
            ->whereIn('entry_type_id', $directSettings)
            ->sum('amount');

        // 2. Shop Paid Company & Pending Verification
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shopId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', '!=', 'rejected')
            ->get();

        $shopPaidCompany = (float) $paymentRequests->where('status', 'approved')->sum(fn ($r) => (float) ($r->admin_verified_amount ?: $r->requested_amount));
        $pendingVerification = (float) $paymentRequests->where('status', 'pending')->sum('requested_amount');
        $reconciledPayments = (float) $paymentRequests->where('reconciliation_status', 'reconciled')->sum(fn ($r) => (float) ($r->reconciled_amount ?: $r->admin_verified_amount));

        // 3. Company Paid Shop
        $companyPaidShop = (float) ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereIn('status', ['posted', 'approved'])
            ->whereNull('voided_at')
            ->whereHas('entryType', fn ($sub) => $sub->where('code', 'company_paid_shop'))
            ->sum('amount');

        // 4. Petty Balance
        $openingRecord = ShopAccountingOpening::query()
            ->where('shop_id', $shopId)
            ->orderByDesc('accounting_start_date')
            ->first();
        $openingPetty = (float) ($openingRecord?->opening_petty_balance ?? 0.0);
        $pettyDelta = (float) ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->whereNull('voided_at')
            ->sum('petty_delta');
        $pettyBalance = round($openingPetty + $pettyDelta, 2);

        // 5. Settlement Position (Using Authoritative Default Payable Relation)
        $payableRelation = $this->settlementService->getDefaultPaymentPayable($shopId);
        $settlementPosition = 0.0;
        $settlementName = 'Default Payable';
        if ($payableRelation instanceof ShopCashbookRelation) {
            $settlementName = $payableRelation->name;
            $settlementCalc = $this->settlementService->calculateCompanyPayable($shopId, $startDate, $endDate);
            $settlementPosition = (float) ($settlementCalc['net_settlement'] ?? 0.0);
        }

        // 6. Allocations (Allocated vs Unallocated on Approved Payments)
        $approvedPaymentIds = $paymentRequests->where('status', 'approved')->pluck('id');
        $allocatedAmount = empty($approvedPaymentIds->all()) ? 0.0 : (float) ShopPaymentLedgerAllocation::query()
            ->whereIn('payment_request_id', $approvedPaymentIds)
            ->where('status', 'active')
            ->sum('amount');
        $unallocatedAmount = max(0.0, round($shopPaidCompany - $allocatedAmount, 2));

        // 7. Bridge Relationship Summary
        $activeCompanyAccounts = CompanyAccount::query()->where('enabled', true)->count();
        $configuredRelationsCount = ShopCashbookRelation::query()->where('shop_id', $shopId)->where('enabled', true)->count();
        $directCategoriesCount = $directSettings->count();

        return [
            'cards' => [
                'direct_collections' => round($directCollectionsTotal, 2),
                'shop_paid_company' => round($shopPaidCompany, 2),
                'company_paid_shop' => round($companyPaidShop, 2),
                'petty_balance' => round($pettyBalance, 2),
                'settlement_position' => round($settlementPosition, 2),
                'settlement_name' => $settlementName,
                'allocated_amount' => round($allocatedAmount, 2),
                'unallocated_amount' => round($unallocatedAmount, 2),
                'pending_verification' => round($pendingVerification, 2),
                'reconciled_amount' => round($reconciledPayments, 2),
            ],
            'lifecycle_summary' => [
                'direct_categories_count' => $directCategoriesCount,
                'relations_count' => $configuredRelationsCount,
                'company_accounts_count' => $activeCompanyAccounts,
                'payable_relation_name' => $settlementName,
            ],
        ];
    }
}
