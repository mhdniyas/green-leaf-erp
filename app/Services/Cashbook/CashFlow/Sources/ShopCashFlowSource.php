<?php

declare(strict_types=1);

namespace App\Services\Cashbook\CashFlow\Sources;

use App\DTOs\Cashbook\MoneyMovement;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ShopCashFlowSource implements CashFlowSourceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MoneyMovement>
     */
    public function forMonth(string $month, array $filters = []): Collection
    {
        $start = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
        $end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();

        $query = ShopLedgerTransaction::query()
            ->with(['shop', 'entryType', 'companyAccount', 'entryType.settings' => function ($q) use ($filters): void {
                if (! empty($filters['shop_id'])) {
                    $q->where('shop_id', (int) $filters['shop_id']);
                }
            }])
            ->where('status', 'active')
            ->whereBetween('business_date', [$start, $end]);

        if (! empty($filters['shop_id'])) {
            $query->where('shop_id', (int) $filters['shop_id']);
        }

        $transactions = $query->orderBy('business_date')->orderBy('id')->get();
        $companyAccounts = CompanyAccount::all()->keyBy('id');

        // Preload shop entry settings mapped by [shop_id][entry_type_id]
        $settings = ShopLedgerEntrySetting::query()
            ->with('headerGroup')
            ->when(! empty($filters['shop_id']), fn ($q) => $q->where('shop_id', (int) $filters['shop_id']))
            ->get()
            ->groupBy('shop_id');

        $movements = collect();

        foreach ($transactions as $tx) {
            $date = $tx->business_date->toDateString();
            $shopName = $tx->shop?->name ?? 'Shop #'.$tx->shop_id;
            $shopId = (int) $tx->shop_id;
            $amount = (float) $tx->amount;
            if ($amount <= 0) {
                continue;
            }

            // Resolve company account: first from transaction, then from setting/header
            $companyAccountId = $tx->company_account_id;
            if (! $companyAccountId && isset($settings[$shopId])) {
                $shopSetting = $settings[$shopId]->firstWhere('entry_type_id', $tx->entry_type_id);
                if ($shopSetting) {
                    if ($shopSetting->headerGroup && $shopSetting->headerGroup->cash_flow_mode === 'company_account') {
                        $companyAccountId = $shopSetting->headerGroup->company_account_id;
                    } elseif ($shopSetting->company_account_id) {
                        $companyAccountId = $shopSetting->company_account_id;
                    }
                }
            }

            $entryTypeName = $tx->entryType?->name ?? 'Transaction';

            // Scenario A: Transaction is linked to a Company Bank/Cash Account (e.g. Paytm -> HDFC, or Card -> Kotak)
            if ($companyAccountId && $companyAccounts->has($companyAccountId)) {
                $bank = $companyAccounts->get($companyAccountId);
                $bankName = $bank->name ?: ($bank->bank_name ?: 'Company Account');

                if ($tx->direction === 'credit') {
                    // Money deposited to Company Bank from Shop collection (Paytm/Card/UPI/Cash deposit)
                    $movements->push(new MoneyMovement(
                        date: $date,
                        sourceType: 'shop',
                        sourceId: $tx->id,
                        fromEntityType: 'shop',
                        fromEntityId: $shopId,
                        fromEntityName: "{$shopName} ({$entryTypeName})",
                        toEntityType: 'company_bank',
                        toEntityId: $bank->id,
                        toEntityName: $bankName,
                        amount: $amount,
                        movementType: 'shop_settlement',
                        category: 'collection',
                        referenceType: 'shop_ledger_transaction',
                        referenceId: $tx->id,
                        notes: $tx->notes,
                        metadata: ['shop_id' => $shopId, 'company_account_id' => $bank->id]
                    ));
                } else {
                    // Money paid out from Company Account on behalf of shop
                    $movements->push(new MoneyMovement(
                        date: $date,
                        sourceType: 'shop',
                        sourceId: $tx->id,
                        fromEntityType: 'company_bank',
                        fromEntityId: $bank->id,
                        fromEntityName: $bankName,
                        toEntityType: 'shop',
                        toEntityId: $shopId,
                        toEntityName: $shopName,
                        amount: $amount,
                        movementType: 'company_to_shop',
                        category: 'expense',
                        referenceType: 'shop_ledger_transaction',
                        referenceId: $tx->id,
                        notes: $tx->notes,
                        metadata: ['shop_id' => $shopId, 'company_account_id' => $bank->id]
                    ));
                }

                continue;
            }

            // Scenario B: Settlement movement between Shop and Company without explicit account yet
            if ((float) $tx->settlement_delta > 0) {
                $settleAmount = (float) $tx->settlement_delta;
                if ($tx->settlement_direction === 'minus') {
                    // Shop owes company / payable to company
                    $movements->push(new MoneyMovement(
                        date: $date,
                        sourceType: 'shop',
                        sourceId: $tx->id,
                        fromEntityType: 'shop',
                        fromEntityId: $shopId,
                        fromEntityName: $shopName,
                        toEntityType: 'company',
                        toEntityId: null,
                        toEntityName: 'Main Company',
                        amount: $settleAmount,
                        movementType: 'shop_settlement',
                        category: 'settlement',
                        referenceType: 'shop_ledger_transaction',
                        referenceId: $tx->id,
                        notes: $tx->notes,
                        metadata: ['shop_id' => $shopId]
                    ));
                } else {
                    // Company owes shop / receivable to shop
                    $movements->push(new MoneyMovement(
                        date: $date,
                        sourceType: 'shop',
                        sourceId: $tx->id,
                        fromEntityType: 'company',
                        fromEntityId: null,
                        fromEntityName: 'Main Company',
                        toEntityType: 'shop',
                        toEntityId: $shopId,
                        toEntityName: $shopName,
                        amount: $settleAmount,
                        movementType: 'company_settlement',
                        category: 'settlement',
                        referenceType: 'shop_ledger_transaction',
                        referenceId: $tx->id,
                        notes: $tx->notes,
                        metadata: ['shop_id' => $shopId]
                    ));
                }

                continue;
            }

            // Scenario C: Shop Sales (External Customers -> Shop Cash)
            if ($tx->affects_sales || $tx->affects_income) {
                $movements->push(new MoneyMovement(
                    date: $date,
                    sourceType: 'shop',
                    sourceId: $tx->id,
                    fromEntityType: 'external_customer',
                    fromEntityId: null,
                    fromEntityName: 'External Sales',
                    toEntityType: 'shop',
                    toEntityId: $shopId,
                    toEntityName: $shopName,
                    amount: $amount,
                    movementType: 'shop_sales',
                    category: 'sales',
                    referenceType: 'shop_ledger_transaction',
                    referenceId: $tx->id,
                    notes: $tx->notes,
                    metadata: ['shop_id' => $shopId, 'entry_type' => $entryTypeName]
                ));

                continue;
            }

            // Scenario D: Shop Expense (Shop Cash -> Expense)
            if ($tx->affects_expense) {
                $movements->push(new MoneyMovement(
                    date: $date,
                    sourceType: 'shop',
                    sourceId: $tx->id,
                    fromEntityType: 'shop',
                    fromEntityId: $shopId,
                    fromEntityName: $shopName,
                    toEntityType: 'shop_expense',
                    toEntityId: null,
                    toEntityName: $entryTypeName,
                    amount: $amount,
                    movementType: 'shop_expense',
                    category: 'expense',
                    referenceType: 'shop_ledger_transaction',
                    referenceId: $tx->id,
                    notes: $tx->notes,
                    metadata: ['shop_id' => $shopId, 'funding_source' => $tx->funding_source]
                ));
            }
        }

        // Also check ShopPaymentLedgerAllocation (recorded settlement allocations)
        $allocations = ShopPaymentLedgerAllocation::query()
            ->with(['shop', 'paymentRequest'])
            ->whereBetween('created_at', [
                Carbon::parse($start)->startOfDay(),
                Carbon::parse($end)->endOfDay(),
            ])
            ->when(! empty($filters['shop_id']), fn ($q) => $q->where('shop_id', (int) $filters['shop_id']))
            ->get();

        foreach ($allocations as $alloc) {
            $date = $alloc->created_at->toDateString();
            $shopName = $alloc->shop?->name ?? 'Shop #'.$alloc->shop_id;
            $amount = (float) $alloc->amount;
            if ($amount <= 0) {
                continue;
            }

            $movements->push(new MoneyMovement(
                date: $date,
                sourceType: 'shop',
                sourceId: $alloc->id,
                fromEntityType: 'shop',
                fromEntityId: (int) $alloc->shop_id,
                fromEntityName: $shopName,
                toEntityType: 'company',
                toEntityId: null,
                toEntityName: 'Main Company',
                amount: $amount,
                movementType: 'shop_payment_allocation',
                category: 'reconciliation',
                referenceType: 'shop_payment_ledger_allocation',
                referenceId: $alloc->id,
                notes: 'Reconciliation allocation for payment request #'.$alloc->payment_request_id,
                metadata: ['shop_id' => (int) $alloc->shop_id, 'batch_uuid' => $alloc->batch_uuid]
            ));
        }

        return $movements;
    }

    /**
     * Compute opening shop cash/position before the start date.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function openingBalances(string $startDate, array $filters = []): array
    {
        $shops = Shop::query()
            ->when(! empty($filters['shop_id']), fn ($q) => $q->where('id', (int) $filters['shop_id']))
            ->get();

        $balances = [];

        foreach ($shops as $shop) {
            // Check snapshot on or immediately before start date
            $snapshot = ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shop->id)
                ->where('business_date', '<=', $startDate)
                ->orderByDesc('business_date')
                ->first();

            if ($snapshot) {
                $opening = $snapshot->business_date->toDateString() === $startDate
                    ? (float) $snapshot->opening_shop_position
                    : (float) $snapshot->closing_shop_position;
                $petty = $snapshot->business_date->toDateString() === $startDate
                    ? (float) $snapshot->opening_petty
                    : (float) $snapshot->closing_petty;
            } else {
                // Sum all transactions prior to startDate
                $opening = (float) ShopLedgerTransaction::query()
                    ->where('shop_id', $shop->id)
                    ->where('status', 'active')
                    ->where('business_date', '<', $startDate)
                    ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) as net_pos")
                    ->value('net_pos');
                $petty = 0.0;
            }

            $balances[$shop->id] = [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'opening_position' => round($opening, 2),
                'opening_petty' => round($petty, 2),
            ];
        }

        return $balances;
    }
}
