<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\OtherExpense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OtherExpensePostingService
{
    private const COMPANY_CATEGORY_NAME = 'Other Purchaser Expenses';

    private const COMPANY_CATEGORY_ACCOUNT_CODE = '5900';

    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    public function syncDate(Carbon|string $expenseDate, int $userId): ?CompanyAccountingEntry
    {
        $date = $expenseDate instanceof Carbon
            ? $expenseDate->copy()->toDateString()
            : Carbon::parse($expenseDate)->toDateString();

        return DB::transaction(function () use ($date, $userId): ?CompanyAccountingEntry {
            $total = round((float) OtherExpense::query()
                ->whereDate('expense_date', $date)
                ->sum('amount'), 2);

            $reference = $this->referenceForDate($date);
            $entry = CompanyAccountingEntry::query()
                ->with(['category.account', 'journalEntry.transactions'])
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($total <= 0.00) {
                if ($entry instanceof CompanyAccountingEntry) {
                    OtherExpense::query()
                        ->whereDate('expense_date', $date)
                        ->where('company_accounting_entry_id', $entry->id)
                        ->update(['company_accounting_entry_id' => null]);
                    $this->deleteJournalForEntry($entry);
                    $entry->delete();
                }

                return null;
            }

            $category = $this->category();

            if (! $entry instanceof CompanyAccountingEntry) {
                $entry = CompanyAccountingEntry::query()->create([
                    'company_accounting_category_id' => $category->id,
                    'type' => 'expense',
                    'business_date' => $date,
                    'payment_mode' => 'cash',
                    'payment_reference' => null,
                    'amount' => $total,
                    'reference' => $reference,
                    'description' => $this->descriptionForDate($date, $total),
                    'status' => CompanyAccountingEntry::StatusFinal,
                    'created_by' => $userId,
                ]);

                $journalEntry = $this->journalService->recordCompanyAccountingEntry($entry->fresh('category.account'), $userId);
                $entry->update(['journal_entry_id' => $journalEntry->id]);
            } else {
                $entry->update([
                    'company_accounting_category_id' => $category->id,
                    'type' => 'expense',
                    'business_date' => $date,
                    'payment_mode' => 'cash',
                    'amount' => $total,
                    'description' => $this->descriptionForDate($date, $total),
                    'status' => CompanyAccountingEntry::StatusFinal,
                ]);

                $this->syncJournalAmount($entry->fresh(['category.account', 'journalEntry.transactions']), $userId);
            }

            OtherExpense::query()
                ->whereDate('expense_date', $date)
                ->update(['company_accounting_entry_id' => $entry->id]);

            return $entry->fresh(['category.account', 'journalEntry.transactions.account']);
        });
    }

    private function category(): CompanyAccountingCategory
    {
        $account = Account::query()
            ->where('code', self::COMPANY_CATEGORY_ACCOUNT_CODE)
            ->where('is_active', true)
            ->first();

        if (! $account instanceof Account) {
            throw new RuntimeException('Miscellaneous expense account is not configured.');
        }

        return CompanyAccountingCategory::query()->updateOrCreate(
            ['type' => 'expense', 'name' => self::COMPANY_CATEGORY_NAME],
            ['account_id' => $account->id, 'is_active' => true],
        );
    }

    private function syncJournalAmount(CompanyAccountingEntry $entry, int $userId): void
    {
        if (! $entry->journalEntry) {
            $journalEntry = $this->journalService->recordCompanyAccountingEntry($entry->fresh('category.account'), $userId);
            $entry->update(['journal_entry_id' => $journalEntry->id]);

            return;
        }

        $entry->loadMissing('category.account', 'journalEntry.transactions');

        if (! $entry->category?->account instanceof Account) {
            throw new RuntimeException('Other expense category must map to an expense account.');
        }

        $amount = round((float) $entry->amount, 2);
        $cashAccount = Account::query()
            ->where('code', '1010')
            ->where('is_active', true)
            ->firstOrFail();

        $expenseLine = $entry->journalEntry->transactions
            ->first(fn (JournalTransaction $transaction): bool => (int) $transaction->account_id === (int) $entry->category->account_id && $transaction->type === 'debit');
        $cashLine = $entry->journalEntry->transactions
            ->first(fn (JournalTransaction $transaction): bool => (int) $transaction->account_id === (int) $cashAccount->id && $transaction->type === 'credit');

        if (! $expenseLine instanceof JournalTransaction || ! $cashLine instanceof JournalTransaction) {
            $this->deleteJournalForEntry($entry);
            $journalEntry = $this->journalService->recordCompanyAccountingEntry($entry->fresh('category.account'), $userId);
            $entry->update(['journal_entry_id' => $journalEntry->id]);

            return;
        }

        $entry->journalEntry->update([
            'entry_date' => $entry->business_date->toDateString(),
            'reference' => $entry->reference,
            'description' => $entry->description,
            'created_by' => $userId,
        ]);
        $expenseLine->update(['amount' => $amount]);
        $cashLine->update(['amount' => $amount]);
    }

    private function deleteJournalForEntry(CompanyAccountingEntry $entry): void
    {
        $journalEntry = $entry->journalEntry;
        if (! $journalEntry instanceof JournalEntry) {
            return;
        }

        $journalEntry->transactions()->delete();
        $journalEntry->delete();
        $entry->update(['journal_entry_id' => null]);
    }

    private function referenceForDate(string $date): string
    {
        return 'OTHER-EXP-'.$date;
    }

    private function descriptionForDate(string $date, float $total): string
    {
        return 'Other purchaser expenses for '.Carbon::parse($date)->format('d M Y').' (Rs. '.number_format($total, 2).')';
    }
}
