<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\JournalTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompanyMainAccountService
{
    /**
     * @var list<string>
     */
    private const MAIN_ACCOUNT_CODES = ['1010', '1020'];

    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    /**
     * @return Collection<int, CompanyAccountingCategory>
     */
    public function categories(?string $type = null, bool $activeOnly = true): Collection
    {
        return CompanyAccountingCategory::query()
            ->with('account')
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Account>
     */
    public function ledgerAccountsForType(string $type): Collection
    {
        $accountType = $type === 'income' ? 'revenue' : 'expense';

        return Account::query()
            ->where('type', $accountType)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createCategory(array $payload): CompanyAccountingCategory
    {
        $account = Account::query()
            ->whereKey((int) $payload['account_id'])
            ->where('is_active', true)
            ->firstOrFail();
        $expectedType = $payload['type'] === 'income' ? 'revenue' : 'expense';

        if ($account->type !== $expectedType) {
            throw new RuntimeException('Selected ledger account does not match the category type.');
        }

        return CompanyAccountingCategory::query()->create([
            'type' => (string) $payload['type'],
            'name' => trim((string) $payload['name']),
            'account_id' => $account->id,
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createEntry(array $payload, int $userId): CompanyAccountingEntry
    {
        return DB::transaction(function () use ($payload, $userId): CompanyAccountingEntry {
            $category = CompanyAccountingCategory::query()
                ->with('account')
                ->whereKey((int) $payload['company_accounting_category_id'])
                ->where('is_active', true)
                ->firstOrFail();

            if ($category->type !== $payload['type']) {
                throw new RuntimeException('Selected category does not match the entry type.');
            }

            $entry = CompanyAccountingEntry::query()->create([
                'company_accounting_category_id' => $category->id,
                'type' => (string) $payload['type'],
                'business_date' => Carbon::parse((string) $payload['business_date'])->toDateString(),
                'payment_mode' => (string) $payload['payment_mode'],
                'payment_reference' => filled($payload['payment_reference'] ?? null) ? trim((string) $payload['payment_reference']) : null,
                'amount' => round((float) $payload['amount'], 2),
                'reference' => filled($payload['reference'] ?? null) ? trim((string) $payload['reference']) : null,
                'description' => filled($payload['description'] ?? null) ? trim((string) $payload['description']) : null,
                'status' => CompanyAccountingEntry::StatusFinal,
                'created_by' => $userId,
            ]);

            $journalEntry = $this->journalService->recordCompanyAccountingEntry($entry->fresh('category.account'), $userId);

            $entry->update(['journal_entry_id' => $journalEntry->id]);

            return $entry->fresh(['category.account', 'journalEntry.transactions.account', 'creator']);
        });
    }

    public function reverseEntry(CompanyAccountingEntry $entry, int $userId, ?string $note = null): CompanyAccountingEntry
    {
        return DB::transaction(function () use ($entry, $userId, $note): CompanyAccountingEntry {
            $entry = CompanyAccountingEntry::query()
                ->with('category.account')
                ->lockForUpdate()
                ->findOrFail($entry->id);

            if ($entry->status === CompanyAccountingEntry::StatusReversed) {
                return $entry;
            }

            $reversalJournal = $this->journalService->recordCompanyAccountingReversal($entry, $userId, $note);

            $entry->update([
                'status' => CompanyAccountingEntry::StatusReversed,
                'reversal_journal_entry_id' => $reversalJournal->id,
                'reversed_by' => $userId,
                'reversed_at' => now(),
                'reversal_note' => filled($note) ? trim((string) $note) : null,
            ]);

            return $entry->fresh(['category.account', 'journalEntry', 'reversalJournalEntry', 'creator', 'reversedBy']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function report(Carbon $date): array
    {
        $selectedDate = $date->copy()->startOfDay();
        $monthStart = $selectedDate->copy()->startOfMonth();
        $monthEnd = $selectedDate->copy()->endOfMonth();

        return [
            'date' => $selectedDate,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'daily' => $this->periodSummary($selectedDate, $selectedDate),
            'monthly' => $this->periodSummary($monthStart, $monthEnd),
            'daily_rows' => $this->dailyRows($monthStart, $monthEnd),
            'category_rows' => $this->categoryRows($monthStart, $monthEnd),
            'entries' => $this->entriesForPeriod($monthStart, $monthEnd),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function periodSummary(Carbon $startDate, Carbon $endDate): array
    {
        $rows = $this->journalRowsForPeriod($startDate, $endDate);
        $income = round((float) $rows->where('type', 'income')->sum('amount'), 2);
        $expense = round((float) $rows->where('type', 'expense')->sum('amount'), 2);

        return [
            'entry_count' => $rows->count(),
            'income_total' => $income,
            'expense_total' => $expense,
            'net_total' => round($income - $expense, 2),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function dailyRows(Carbon $startDate, Carbon $endDate): Collection
    {
        $rows = collect();

        for ($cursor = $startDate->copy(); $cursor->lte($endDate); $cursor->addDay()) {
            $rows->push(array_merge($this->periodSummary($cursor, $cursor), [
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('d M'),
            ]));
        }

        return $rows;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryRows(Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->journalRowsForPeriod($startDate, $endDate)
            ->groupBy(fn (array $row): string => $row['type'].'|'.$row['category_name'])
            ->map(fn (Collection $rows): array => [
                'type' => (string) $rows->first()['type'],
                'category_name' => (string) $rows->first()['category_name'],
                'account_label' => (string) $rows->first()['account_label'],
                'entry_count' => $rows->pluck('journal_entry_id')->unique()->count(),
                'total_amount' => round((float) $rows->sum('amount'), 2),
            ])
            ->sortBy([
                fn (array $row): int => $row['type'] === 'income' ? 0 : 1,
                fn (array $row): string => $row['category_name'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function entriesForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->journalRowsForPeriod($startDate, $endDate)
            ->sortByDesc('sort_key')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function journalRowsForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return JournalTransaction::query()
            ->with(['account', 'journalEntry.transactions.account'])
            ->whereHas('account', fn ($query) => $query->whereIn('code', self::MAIN_ACCOUNT_CODES))
            ->whereHas('journalEntry', function ($query) use ($startDate, $endDate): void {
                $query
                    ->whereDate('entry_date', '>=', $startDate)
                    ->whereDate('entry_date', '<=', $endDate);
            })
            ->get()
            ->map(function (JournalTransaction $transaction): array {
                $entry = $transaction->journalEntry;
                $counterpartyTransactions = $entry->transactions
                    ->reject(fn (JournalTransaction $line): bool => in_array((string) $line->account?->code, self::MAIN_ACCOUNT_CODES, true));
                $counterpartyAccounts = $counterpartyTransactions
                    ->map(fn (JournalTransaction $line): string => (string) ($line->account?->name ?? 'Journal Entry'))
                    ->unique()
                    ->values();
                $companyEntry = $entry->source_type === CompanyAccountingEntry::class
                    ? CompanyAccountingEntry::query()->with('category')->find($entry->source_id)
                    : null;
                $categoryName = $companyEntry?->category?->name
                    ?? ($counterpartyAccounts->isNotEmpty() ? $counterpartyAccounts->implode(', ') : (string) ($entry->reference ?: 'Journal Entry'));
                $direction = $transaction->type === 'debit' ? 'income' : 'expense';

                return [
                    'journal_entry_id' => (int) $entry->id,
                    'journal_transaction_id' => (int) $transaction->id,
                    'date' => $entry->entry_date,
                    'type' => $direction,
                    'category_name' => $categoryName,
                    'account_label' => (string) ($transaction->account?->code.' - '.$transaction->account?->name),
                    'counterparty_label' => $counterpartyAccounts->implode(', ') ?: 'Journal Entry',
                    'payment_label' => (string) ($transaction->account?->name ?? 'Main Account'),
                    'journal_reference' => (string) ($entry->reference ?: 'JRN-'.$entry->id),
                    'description' => (string) ($entry->description ?: $entry->reference ?: $categoryName),
                    'source_label' => $this->sourceLabel((string) $entry->source_type, (string) $entry->source_event),
                    'amount' => round((float) $transaction->amount, 2),
                    'reversible_entry' => $companyEntry instanceof CompanyAccountingEntry && $entry->source_event === 'final' && $companyEntry->status === CompanyAccountingEntry::StatusFinal
                        ? $companyEntry
                        : null,
                    'sort_key' => $entry->entry_date->format('Y-m-d').'-'.str_pad((string) $entry->id, 10, '0', STR_PAD_LEFT).'-'.str_pad((string) $transaction->id, 10, '0', STR_PAD_LEFT),
                ];
            })
            ->values();
    }

    private function sourceLabel(string $sourceType, string $sourceEvent): string
    {
        if ($sourceType === CompanyAccountingEntry::class) {
            return $sourceEvent === 'reversal' ? 'Main Account Reversal' : 'Main Account';
        }

        return match (true) {
            str_contains($sourceType, 'ShopInvoicePaymentRequest') => 'Invoice Payment',
            str_contains($sourceType, 'ShopInvoice') => 'Shop Invoice',
            str_contains($sourceType, 'PurchaseInvoice') => str_starts_with($sourceEvent, 'company_vendor_credit_payment:')
                ? 'Vendor Payment'
                : 'Direct Purchase',
            str_contains($sourceType, 'PurchaserCredit') => 'Purchaser Cash',
            str_contains($sourceType, 'PayrollPayment') => 'Payroll',
            default => 'Journal',
        };
    }
}
