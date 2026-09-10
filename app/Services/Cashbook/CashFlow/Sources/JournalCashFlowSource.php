<?php

declare(strict_types=1);

namespace App\Services\Cashbook\CashFlow\Sources;

use App\DTOs\Cashbook\MoneyMovement;
use App\Models\CompanyAccountingEntry;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class JournalCashFlowSource implements CashFlowSourceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MoneyMovement>
     */
    public function forMonth(string $month, array $filters = []): Collection
    {
        $start = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
        $end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();

        $movements = collect();

        // 1. Company Accounting Entries (Other Business Revenue / Operating Expenses)
        $accountingEntries = CompanyAccountingEntry::query()
            ->with(['category', 'companyAccount'])
            ->where('status', '!=', 'reversed')
            ->whereBetween('business_date', [$start, $end])
            ->orderBy('business_date')
            ->get();

        foreach ($accountingEntries as $entry) {
            $date = $entry->business_date ? $entry->business_date->toDateString() : $start;
            $amount = (float) $entry->amount;
            if ($amount <= 0) {
                continue;
            }

            $catName = $entry->category?->name ?? 'Other Business';
            $companyAccount = $entry->companyAccount;
            $bankName = $companyAccount
                ? ($companyAccount->name ?: ($companyAccount->bank_name ?: 'Company Bank'))
                : 'Company Cash Vault';
            $bankId = $companyAccount?->id;

            if ($entry->type === 'income') {
                $movements->push(new MoneyMovement(
                    date: $date,
                    sourceType: 'journal',
                    sourceId: $entry->id,
                    fromEntityType: 'other_revenue',
                    fromEntityId: $entry->company_accounting_category_id,
                    fromEntityName: $catName,
                    toEntityType: $companyAccount ? 'company_bank' : 'company_vault',
                    toEntityId: $bankId,
                    toEntityName: $bankName,
                    amount: $amount,
                    movementType: 'other_income',
                    category: 'income',
                    referenceType: 'company_accounting_entry',
                    referenceId: $entry->id,
                    referenceNumber: $entry->reference,
                    notes: $entry->description ?: $catName,
                    metadata: [
                        'entry_id' => $entry->id,
                        'category_id' => $entry->company_accounting_category_id,
                    ]
                ));
            } else {
                $movements->push(new MoneyMovement(
                    date: $date,
                    sourceType: 'journal',
                    sourceId: $entry->id,
                    fromEntityType: $companyAccount ? 'company_bank' : 'company_vault',
                    fromEntityId: $bankId,
                    fromEntityName: $bankName,
                    toEntityType: 'other_expense',
                    toEntityId: $entry->company_accounting_category_id,
                    toEntityName: $catName,
                    amount: $amount,
                    movementType: 'other_expense',
                    category: 'expense',
                    referenceType: 'company_accounting_entry',
                    referenceId: $entry->id,
                    referenceNumber: $entry->reference,
                    notes: $entry->description ?: $catName,
                    metadata: [
                        'entry_id' => $entry->id,
                        'category_id' => $entry->company_accounting_category_id,
                    ]
                ));
            }
        }

        // 2. Manual Journal Entries (Unallocated / adjustments / manual entries)
        $manualJournals = JournalEntry::query()
            ->with(['transactions.account'])
            ->where(function ($q): void {
                $q->whereNull('source_type')
                    ->orWhere('source_type', 'manual')
                    ->orWhere('source_event', 'like', 'manual%');
            })
            ->whereBetween('entry_date', [$start, $end])
            ->get();

        foreach ($manualJournals as $journal) {
            $date = $journal->entry_date ? $journal->entry_date->toDateString() : $start;
            $debits = (float) $journal->transactions->where('type', 'debit')->sum('amount');
            $credits = (float) $journal->transactions->where('type', 'credit')->sum('amount');
            $amount = max($debits, $credits);

            if ($amount <= 0 && isset($journal->total_amount)) {
                $amount = (float) $journal->total_amount;
            }
            if ($amount <= 0 && isset($journal->amount)) {
                $amount = (float) $journal->amount;
            }

            if ($amount <= 0) {
                continue;
            }

            $movements->push(new MoneyMovement(
                date: $date,
                sourceType: 'journal',
                sourceId: $journal->id,
                fromEntityType: 'unallocated',
                fromEntityId: null,
                fromEntityName: 'Manual Journal / Adjustment',
                toEntityType: 'unallocated',
                toEntityId: null,
                toEntityName: 'Needs Review',
                amount: $amount,
                movementType: 'manual_journal',
                category: 'adjustment',
                referenceType: 'journal_entry',
                referenceId: $journal->id,
                referenceNumber: $journal->entry_number,
                notes: $journal->narration ?: 'Manual accounting adjustment',
                metadata: [
                    'journal_entry_id' => $journal->id,
                    'is_review' => true,
                ]
            ));
        }

        return $movements;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function openingBalances(string $startDate, array $filters = []): array
    {
        return [];
    }
}
