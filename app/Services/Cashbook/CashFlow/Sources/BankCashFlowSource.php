<?php

declare(strict_types=1);

namespace App\Services\Cashbook\CashFlow\Sources;

use App\DTOs\Cashbook\MoneyMovement;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BankCashFlowSource implements CashFlowSourceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MoneyMovement>
     */
    public function forMonth(string $month, array $filters = []): Collection
    {
        $start = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
        $end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();

        $accountsQuery = CompanyAccount::query()->where('enabled', true);
        if (! empty($filters['company_account_id'])) {
            $accountsQuery->where('id', (int) $filters['company_account_id']);
        }
        $accounts = $accountsQuery->get()->keyBy('id');

        $entriesQuery = CompanyAccountStatementEntry::query()
            ->with(['companyAccount', 'journalEntry'])
            ->whereIn('company_account_id', $accounts->pluck('id'))
            ->whereBetween('transaction_date', [$start, $end]);

        $entries = $entriesQuery->orderBy('transaction_date')->orderBy('id')->get();
        $movements = collect();

        // Build quick lookup for other accounts to detect internal transfers
        $accountNames = $accounts->mapWithKeys(function (CompanyAccount $acc) {
            $name = strtoupper(trim($acc->name ?: ($acc->bank_name ?: '')));

            return [$acc->id => $name];
        });

        foreach ($entries as $entry) {
            $account = $entry->companyAccount;
            if (! $account) {
                continue;
            }

            $date = $entry->transaction_date ? $entry->transaction_date->toDateString() : $start;
            $bankName = $account->name ?: ($account->bank_name ?: 'Account #'.$account->id);
            $amount = (float) $entry->amount;
            if ($amount <= 0) {
                continue;
            }

            $narration = (string) $entry->narration;
            $isInternal = false;
            $otherAccountId = null;
            $otherAccountName = null;

            // 1. Check explicit counterpart link
            if ($entry->counterpart_type === CompanyAccount::class && ! empty($entry->counterpart_id)) {
                $isInternal = true;
                $otherAccountId = (int) $entry->counterpart_id;
                $otherAccount = $accounts->get($otherAccountId);
                $otherAccountName = $otherAccount?->name ?: ($otherAccount?->bank_name ?: 'Other Bank');
            } else {
                // 2. Heuristic detection: does narration mention another company bank?
                $upperNarr = strtoupper($narration);
                foreach ($accountNames as $accId => $name) {
                    if ($accId !== $account->id && strlen($name) >= 3 && str_contains($upperNarr, $name)) {
                        $isInternal = true;
                        $otherAccountId = $accId;
                        $otherAccountName = $accounts->get($accId)?->name;
                        break;
                    }
                }
            }

            if ($entry->direction === 'out') {
                if ($isInternal) {
                    $movements->push(new MoneyMovement(
                        date: $date,
                        sourceType: 'bank',
                        sourceId: $entry->id,
                        fromEntityType: 'company_bank',
                        fromEntityId: $account->id,
                        fromEntityName: $bankName,
                        toEntityType: 'company_bank',
                        toEntityId: $otherAccountId,
                        toEntityName: $otherAccountName ?: 'Company Bank Transfer',
                        amount: $amount,
                        movementType: 'internal_bank_transfer',
                        category: 'transfer',
                        referenceType: 'statement_entry',
                        referenceId: $entry->id,
                        referenceNumber: $entry->reference,
                        notes: $narration,
                        metadata: [
                            'from_account_id' => $account->id,
                            'to_account_id' => $otherAccountId,
                            'source' => $entry->source,
                        ]
                    ));
                } else {
                    $movements->push(new MoneyMovement(
                        date: $date,
                        sourceType: 'bank',
                        sourceId: $entry->id,
                        fromEntityType: 'company_bank',
                        fromEntityId: $account->id,
                        fromEntityName: $bankName,
                        toEntityType: 'external_party',
                        toEntityId: null,
                        toEntityName: $narration ?: 'Outgoing Payment',
                        amount: $amount,
                        movementType: 'bank_withdrawal',
                        category: 'withdrawal',
                        referenceType: 'statement_entry',
                        referenceId: $entry->id,
                        referenceNumber: $entry->reference,
                        notes: $narration,
                        metadata: [
                            'account_id' => $account->id,
                            'source' => $entry->source,
                            'source_type' => $entry->source_type,
                            'source_id' => $entry->source_id,
                        ]
                    ));
                }
            } else {
                // Incoming to Bank
                if (! $isInternal) {
                    $movements->push(new MoneyMovement(
                        date: $date,
                        sourceType: 'bank',
                        sourceId: $entry->id,
                        fromEntityType: 'external_party',
                        fromEntityId: null,
                        fromEntityName: $narration ?: 'Incoming Receipt',
                        toEntityType: 'company_bank',
                        toEntityId: $account->id,
                        toEntityName: $bankName,
                        amount: $amount,
                        movementType: 'bank_deposit',
                        category: 'deposit',
                        referenceType: 'statement_entry',
                        referenceId: $entry->id,
                        referenceNumber: $entry->reference,
                        notes: $narration,
                        metadata: [
                            'account_id' => $account->id,
                            'source' => $entry->source,
                            'source_type' => $entry->source_type,
                            'source_id' => $entry->source_id,
                        ]
                    ));
                }
            }
        }

        return $movements;
    }

    /**
     * Compute opening balance for each bank prior to startDate.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function openingBalances(string $startDate, array $filters = []): array
    {
        $accountsQuery = CompanyAccount::query()->where('enabled', true);
        if (! empty($filters['company_account_id'])) {
            $accountsQuery->where('id', (int) $filters['company_account_id']);
        }
        $accounts = $accountsQuery->get();

        $priorMovements = DB::table('cashbook_company_account_statement_entries')
            ->whereDate('transaction_date', '<', $startDate)
            ->whereIn('company_account_id', $accounts->pluck('id'))
            ->groupBy('company_account_id')
            ->selectRaw("
                company_account_id,
                SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) as total_out
            ")
            ->get()
            ->keyBy('company_account_id');

        $balances = [];

        foreach ($accounts as $acc) {
            $initialOpening = (float) $acc->opening_balance;
            $priorIn = (float) ($priorMovements->get($acc->id)?->total_in ?? 0);
            $priorOut = (float) ($priorMovements->get($acc->id)?->total_out ?? 0);
            $calculatedOpening = round($initialOpening + $priorIn - $priorOut, 2);

            $balances[$acc->id] = [
                'account_id' => $acc->id,
                'name' => $acc->name ?: ($acc->bank_name ?: 'Account #'.$acc->id),
                'account_type' => $acc->account_type,
                'initial_opening' => $initialOpening,
                'prior_in' => $priorIn,
                'prior_out' => $priorOut,
                'opening_balance' => $calculatedOpening,
            ];
        }

        return $balances;
    }
}
