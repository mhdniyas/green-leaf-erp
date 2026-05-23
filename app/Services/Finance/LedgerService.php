<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\JournalTransaction;
use Illuminate\Support\Collection;

class LedgerService
{
    /**
     * Get list of ledger transactions.
     *
     * @return Collection<int, JournalTransaction>
     */
    public function getLedgerTransactions(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $accountId = null
    ): Collection {
        return JournalTransaction::with(['journalEntry.createdBy', 'account'])
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                if ($startDate) {
                    $q->where('entry_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('entry_date', '<=', $endDate);
                }
            })
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->get()
            ->sortBy(fn ($tx) => $tx->journalEntry->entry_date->timestamp);
    }

    /**
     * Generate Profit & Loss statement.
     */
    public function getPnLReport(?string $startDate = null, ?string $endDate = null): array
    {
        $accounts = Account::active()->get();

        $revenue = 0.0;
        $cogs = 0.0;
        $wastage = 0.0;
        $expensesList = [];
        $totalExpenses = 0.0;

        foreach ($accounts as $account) {
            $balance = $account->getBalanceForPeriod($startDate, $endDate);
            if ($account->type === 'revenue') {
                $revenue += $balance;
            } elseif ($account->type === 'expense') {
                if ($account->code === '5100') {
                    $cogs += $balance;
                } elseif ($account->code === '5200') {
                    $wastage += $balance;
                } else {
                    $expensesList[] = [
                        'name' => $account->name,
                        'code' => $account->code,
                        'balance' => $balance,
                    ];
                    $totalExpenses += $balance;
                }
            }
        }

        $grossProfit = $revenue - $cogs - $wastage;
        $netProfit = $grossProfit - $totalExpenses;

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'wastage' => $wastage,
            'gross_profit' => $grossProfit,
            'expenses' => $expensesList,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
        ];
    }

    /**
     * Generate Balance Sheet snapshot.
     */
    public function getBalanceSheetReport(?string $endDate = null): array
    {
        $accounts = Account::active()->get();

        $assets = [];
        $totalAssets = 0.0;

        $liabilities = [];
        $totalLiabilities = 0.0;

        $equity = [];
        $totalEquity = 0.0;

        foreach ($accounts as $account) {
            $balance = $account->getBalanceForPeriod(null, $endDate);

            if ($account->type === 'asset') {
                $assets[] = ['name' => $account->name, 'code' => $account->code, 'balance' => $balance];
                $totalAssets += $balance;
            } elseif ($account->type === 'liability') {
                $liabilities[] = ['name' => $account->name, 'code' => $account->code, 'balance' => $balance];
                $totalLiabilities += $balance;
            } elseif ($account->type === 'equity') {
                $equity[] = ['name' => $account->name, 'code' => $account->code, 'balance' => $balance];
                $totalEquity += $balance;
            }
        }

        // Add current year Net Profit as Retained Earnings line item
        $pnl = $this->getPnLReport(null, $endDate);
        $netProfit = $pnl['net_profit'];

        $equity[] = ['name' => 'Current Period Net Profit (P&L)', 'code' => 'P&L', 'balance' => $netProfit];
        $totalEquity += $netProfit;

        return [
            'assets' => $assets,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilities,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equity,
            'total_equity' => $totalEquity,
        ];
    }

    /**
     * Generate Cash Flow statement.
     */
    public function getCashFlowReport(?string $startDate = null, ?string $endDate = null): array
    {
        $cashAccount = Account::where('code', '1010')->first();
        $bankAccount = Account::where('code', '1020')->first();

        $cashAccountId = $cashAccount ? (int) $cashAccount->id : null;
        $bankAccountId = $bankAccount ? (int) $bankAccount->id : null;

        $inflows = 0.0;
        $outflows = 0.0;
        $movements = [];

        $accountIds = array_filter([$cashAccountId, $bankAccountId]);

        if (! empty($accountIds)) {
            $txs = JournalTransaction::with(['journalEntry.transactions.account'])
                ->whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    if ($startDate) {
                        $q->where('entry_date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->where('entry_date', '<=', $endDate);
                    }
                })
                ->get();

            foreach ($txs as $tx) {
                $amount = (float) $tx->amount;
                $entry = $tx->journalEntry;

                // Find counterpart account names
                $counterparts = [];
                foreach ($entry->transactions as $otherTx) {
                    if (! in_array($otherTx->account_id, $accountIds)) {
                        $counterparts[] = $otherTx->account->name;
                    }
                }

                $counterpartText = implode(', ', $counterparts);
                if ($tx->type === 'debit') {
                    $inflows += $amount;
                    $movements[] = [
                        'date' => $entry->entry_date->format('Y-m-d'),
                        'reference' => $entry->reference,
                        'description' => $entry->description,
                        'category' => $counterpartText ?: 'Transfer',
                        'amount' => $amount,
                        'type' => 'inflow',
                    ];
                } else {
                    $outflows += $amount;
                    $movements[] = [
                        'date' => $entry->entry_date->format('Y-m-d'),
                        'reference' => $entry->reference,
                        'description' => $entry->description,
                        'category' => $counterpartText ?: 'Transfer',
                        'amount' => -$amount,
                        'type' => 'outflow',
                    ];
                }
            }
        }

        // Sort movements chronologically
        usort($movements, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return [
            'inflows' => $inflows,
            'outflows' => $outflows,
            'net_cash_flow' => $inflows - $outflows,
            'movements' => $movements,
        ];
    }
}
