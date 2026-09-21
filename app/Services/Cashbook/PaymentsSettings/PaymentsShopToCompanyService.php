<?php

declare(strict_types=1);

namespace App\Services\Cashbook\PaymentsSettings;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Services\Cashbook\ShopSettlementService;
use Illuminate\Support\Facades\DB;

class PaymentsShopToCompanyService
{
    public function __construct(
        private readonly ShopSettlementService $settlementService,
    ) {}

    /**
     * Get view model for Shop -> Company tab.
     *
     * @return array<string, mixed>
     */
    public function getViewModel(Shop $shop, string $startDate, string $endDate): array
    {
        $shopId = (int) $shop->id;
        $profile = ShopLedgerProfile::query()->where('shop_id', $shopId)->first();
        $paymentConfig = is_array($profile?->payment_configuration) ? $profile->payment_configuration : [];

        $companyAccounts = CompanyAccount::query()
            ->where('enabled', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $relations = ShopCashbookRelation::query()
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->orderBy('display_order')
            ->get();

        $defaultPaidRelation = $this->settlementService->getDefaultPaymentPaid($shopId);

        $allowedMethods = $paymentConfig['shop_to_company']['allowed_methods'] ?? ['cash', 'online_upi', 'cheque', 'bank_transfer'];
        $defaultAccountId = $paymentConfig['shop_to_company']['default_account_id'] ?? null;
        $defaultAccount = $companyAccounts->firstWhere('id', $defaultAccountId) ?? $companyAccounts->firstWhere('is_default', true);

        // Fetch payment requests in period
        $requests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shopId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', '!=', 'rejected')
            ->get();

        $totalRequested = (float) $requests->sum('requested_amount');
        $totalApproved = (float) $requests->where('status', 'approved')->sum(fn ($r) => (float) ($r->admin_verified_amount ?: $r->requested_amount));
        $totalReceived = (float) $requests->whereIn('status', ['approved', 'partially_reconciled'])->sum(fn ($r) => (float) ($r->admin_verified_amount ?: $r->requested_amount));

        $approvedIds = $requests->where('status', 'approved')->pluck('id');
        $totalAllocated = empty($approvedIds->all()) ? 0.0 : (float) ShopPaymentLedgerAllocation::query()
            ->whereIn('payment_request_id', $approvedIds)
            ->where('status', 'active')
            ->sum('amount');
        $totalUnallocated = max(0.0, round($totalApproved - $totalAllocated, 2));

        $pendingReconciliation = (float) $requests->where('reconciliation_status', '!=', 'reconciled')->sum(fn ($r) => (float) ($r->admin_verified_amount ?: $r->requested_amount));

        // Find ledger receipt settings (e.g. shop_paid_company)
        $paymentReceiptSettings = ShopLedgerEntrySetting::query()
            ->with('entryType')
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'shop_paid_company'))
            ->get();

        return [
            'setup' => [
                'allowed_methods' => $allowedMethods,
                'default_account' => $defaultAccount,
                'paid_relation' => $defaultPaidRelation,
                'payment_receipt_settings' => $paymentReceiptSettings,
                'verification_required' => true,
                'allocation_enabled' => (bool) ($paymentConfig['expense_allocation']['enabled'] ?? true),
            ],
            'company_accounts' => $companyAccounts,
            'relations' => $relations,
            'output' => [
                'requested' => round($totalRequested, 2),
                'approved' => round($totalApproved, 2),
                'received' => round($totalReceived, 2),
                'allocated' => round($totalAllocated, 2),
                'unallocated' => round($totalUnallocated, 2),
                'pending_reconciliation' => round($pendingReconciliation, 2),
            ],
        ];
    }

    /**
     * Save Shop -> Company settings for a shop.
     */
    public function saveSettings(Shop $shop, array $input, int $userId): void
    {
        $shopId = (int) $shop->id;

        DB::transaction(function () use ($shopId, $input): void {
            $profile = ShopLedgerProfile::query()->where('shop_id', $shopId)->lockForUpdate()->firstOrFail();
            $config = is_array($profile->payment_configuration) ? $profile->payment_configuration : [];

            $config['shop_to_company'] = [
                'allowed_methods' => (array) ($input['allowed_methods'] ?? ['cash', 'online_upi', 'cheque', 'bank_transfer']),
                'default_account_id' => ! empty($input['default_account_id']) ? (int) $input['default_account_id'] : null,
            ];

            $profile->update(['payment_configuration' => $config]);

            if (! empty($input['paid_relation_id'])) {
                $relation = ShopCashbookRelation::query()
                    ->where('shop_id', $shopId)
                    ->where('id', (int) $input['paid_relation_id'])
                    ->first();

                if ($relation) {
                    $this->settlementService->setDefaultPaymentPaid($profile, $relation);
                }
            }
        });
    }
}
