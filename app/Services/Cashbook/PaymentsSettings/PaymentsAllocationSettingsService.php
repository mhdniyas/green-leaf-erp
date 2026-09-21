<?php

declare(strict_types=1);

namespace App\Services\Cashbook\PaymentsSettings;

use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Services\Cashbook\ShopPaymentLedgerReconciliationService;
use App\Services\Cashbook\ShopSettlementService;
use Illuminate\Support\Facades\DB;

class PaymentsAllocationSettingsService
{
    public function __construct(
        private readonly ShopSettlementService $settlementService,
        private readonly ShopPaymentLedgerReconciliationService $allocationService,
    ) {}

    /**
     * Get view model for Allocation tab.
     *
     * @return array<string, mixed>
     */
    public function getViewModel(Shop $shop, string $startDate, string $endDate): array
    {
        $shopId = (int) $shop->id;
        $profile = ShopLedgerProfile::query()->where('shop_id', $shopId)->first();
        $paymentConfig = is_array($profile?->payment_configuration) ? $profile->payment_configuration : [];
        $expenseAllocationConfig = $this->settlementService->expenseAllocationConfiguration($shop);

        $payableRelation = $this->settlementService->getDefaultPaymentPayable($shopId);
        $paidRelation = $this->settlementService->getDefaultPaymentPaid($shopId);

        $relations = ShopCashbookRelation::query()
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->orderBy('display_order')
            ->get();

        $allExpenseSettings = ShopLedgerEntrySetting::query()
            ->with(['entryType', 'headerGroup', 'companyAccount'])
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->where(fn ($q) => $q->where('include_in_expense', true)->orWhere('include_in_payable', true))
            ->get();

        // Approved payments
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shopId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'approved')
            ->get();

        $totalApproved = (float) $paymentRequests->sum(fn ($r) => (float) ($r->admin_verified_amount ?: $r->requested_amount));

        $paymentIds = $paymentRequests->pluck('id');
        $totalAllocated = empty($paymentIds->all()) ? 0.0 : (float) ShopPaymentLedgerAllocation::query()
            ->whereIn('payment_request_id', $paymentIds)
            ->where('status', 'active')
            ->sum('amount');
        $totalUnallocated = max(0.0, round($totalApproved - $totalAllocated, 2));

        // Open payable candidates in period (Oldest first)
        $payableTransactions = ShopLedgerTransaction::query()
            ->with(['entryType', 'paymentLedgerAllocations'])
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereIn('status', ['posted', 'approved'])
            ->whereNull('voided_at')
            ->where(fn ($q) => $q->where('direction', 'expense')->orWhere('affects_expense', true))
            ->whereNull('company_account_id')
            ->orderBy('business_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $openPayables = [];
        $totalOpenPayableAmount = 0.0;
        foreach ($payableTransactions as $tx) {
            $allocatedToTx = (float) $tx->paymentLedgerAllocations->where('status', 'active')->sum('amount');
            $remaining = max(0.0, round((float) $tx->amount - $allocatedToTx, 2));

            if ($remaining > 0.0) {
                $totalOpenPayableAmount += $remaining;
                $openPayables[] = [
                    'transaction' => $tx,
                    'name' => $tx->entryType?->name ?? 'Expense',
                    'date' => $tx->business_date?->toDateString(),
                    'amount' => (float) $tx->amount,
                    'allocated' => $allocatedToTx,
                    'remaining' => $remaining,
                ];
            }
        }

        // Live Allocation Simulation Preview for a sample unallocated amount or ₹20,000
        $sampleAmount = $totalUnallocated > 0 ? $totalUnallocated : 20000.0;
        $simulatedRemaining = $sampleAmount;
        $simulatedAllocations = [];

        foreach ($openPayables as $op) {
            if ($simulatedRemaining <= 0.0) {
                break;
            }
            $alloc = min($simulatedRemaining, $op['remaining']);
            $simulatedAllocations[] = [
                'name' => $op['name'],
                'date' => $op['date'],
                'amount' => $alloc,
            ];
            $simulatedRemaining -= $alloc;
        }

        return [
            'setup' => [
                'payable_relation' => $payableRelation,
                'paid_relation' => $paidRelation,
                'expense_allocation' => $expenseAllocationConfig,
                'all_expense_settings' => $allExpenseSettings,
                'relations' => $relations,
                'allocation_rule' => 'Automatic oldest-first allocation by business date ASC, ID ASC.',
            ],
            'output' => [
                'total_approved_payments' => round($totalApproved, 2),
                'allocated' => round($totalAllocated, 2),
                'unallocated' => round($totalUnallocated, 2),
                'open_payables_total' => round($totalOpenPayableAmount, 2),
                'open_payables_count' => count($openPayables),
            ],
            'preview' => [
                'sample_amount' => $sampleAmount,
                'allocations' => $simulatedAllocations,
                'remaining' => round($simulatedRemaining, 2),
            ],
            'open_payables' => array_slice($openPayables, 0, 15),
        ];
    }

    /**
     * Save allocation settings for a shop.
     */
    public function saveSettings(Shop $shop, array $input, int $userId): void
    {
        $shopId = (int) $shop->id;

        DB::transaction(function () use ($shopId, $input): void {
            $profile = ShopLedgerProfile::query()->where('shop_id', $shopId)->lockForUpdate()->firstOrFail();
            $config = is_array($profile->payment_configuration) ? $profile->payment_configuration : [];

            $config['expense_allocation'] = [
                'enabled' => (bool) ($input['enabled'] ?? true),
                'auto_allocate' => (bool) ($input['auto_allocate'] ?? true),
                'category_ids' => array_values(array_map('intval', (array) ($input['category_ids'] ?? []))),
                'default_category_id' => ! empty($input['default_category_id']) ? (int) $input['default_category_id'] : null,
            ];

            $profile->update(['payment_configuration' => $config]);

            if (! empty($input['payable_relation_id'])) {
                $payable = ShopCashbookRelation::query()->where('shop_id', $shopId)->where('id', (int) $input['payable_relation_id'])->first();
                if ($payable) {
                    $this->settlementService->setDefaultPaymentPayable($profile, $payable);
                }
            }

            if (! empty($input['paid_relation_id'])) {
                $paid = ShopCashbookRelation::query()->where('shop_id', $shopId)->where('id', (int) $input['paid_relation_id'])->first();
                if ($paid) {
                    $this->settlementService->setDefaultPaymentPaid($profile, $paid);
                }
            }
        });
    }
}
