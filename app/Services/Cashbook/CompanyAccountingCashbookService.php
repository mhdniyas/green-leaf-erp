<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Services\Finance\CompanyMainAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyAccountingCashbookService
{
    public function __construct(
        private readonly CompanyMainAccountService $mainAccountService,
        private readonly CompanyPaymentReconciliationService $reconciliationService,
    ) {}

    /** @param array{type:string, company_accounting_category_id:int, company_account_uuid:string, business_date:string, amount:float, reference?:string|null, description?:string|null, request_uuid:string} $input */
    public function create(array $input, int $userId): CompanyAccountingEntry
    {
        return DB::transaction(function () use ($input, $userId): CompanyAccountingEntry {
            $existingMovement = CompanyAccountStatementEntry::query()
                ->where('request_uuid', $input['request_uuid'])
                ->lockForUpdate()
                ->first();

            if ($existingMovement instanceof CompanyAccountStatementEntry) {
                $entry = CompanyAccountingEntry::query()->whereKey($existingMovement->source_id)->firstOrFail();

                return $entry->fresh(['category.account', 'companyAccount', 'journalEntry.transactions.account']);
            }

            $companyAccount = CompanyAccount::query()
                ->where('public_uuid', $input['company_account_uuid'])
                ->where('enabled', true)
                ->lockForUpdate()
                ->firstOrFail();
            $category = CompanyAccountingCategory::query()
                ->with('account')
                ->whereKey($input['company_accounting_category_id'])
                ->where('type', $input['type'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $expectedAccountType = $input['type'] === 'income' ? 'revenue' : 'expense';
            if (! $category->account || ! $category->account->is_active || $category->account->type !== $expectedAccountType) {
                throw ValidationException::withMessages(['company_accounting_category_id' => 'Selected category has no active matching ledger account.']);
            }

            $this->validateOtherCategoryDescription($category, $input['description'] ?? null);

            $entry = $this->mainAccountService->createEntry([
                'type' => $input['type'],
                'company_accounting_category_id' => $category->id,
                'company_account_id' => $companyAccount->id,
                'business_date' => $input['business_date'],
                'payment_mode' => $companyAccount->account_type === 'cash' ? 'cash' : 'bank',
                'payment_reference' => $input['reference'] ?? null,
                'amount' => $input['amount'],
                'reference' => $input['reference'] ?? null,
                'description' => $input['description'] ?? null,
            ], $userId);

            $journalEntry = $entry->journalEntry;
            if (! $journalEntry) {
                throw new \RuntimeException('Company accounting entry did not create its canonical journal entry.');
            }

            $this->reconciliationService->createStatementEntry([
                'company_account_id' => $companyAccount->id,
                'journal_entry_id' => $journalEntry->id,
                'request_uuid' => $input['request_uuid'],
                'transaction_date' => $entry->business_date->toDateString(),
                'value_date' => $entry->business_date->toDateString(),
                'direction' => $entry->type === 'income' ? 'in' : 'out',
                'amount' => (float) $entry->amount,
                'reference' => $entry->reference,
                'narration' => $entry->description ?: $category->name,
                'source' => 'company_accounting_entry',
                'source_type' => CompanyAccountingEntry::class,
                'source_id' => $entry->id,
                'counterpart_type' => CompanyAccountingCategory::class,
                'counterpart_id' => $category->id,
                'notes' => $entry->description,
            ], $userId);

            return $entry->fresh(['category.account', 'companyAccount', 'journalEntry.transactions.account']);
        }, attempts: 3);
    }

    /** @param array{type:string, company_accounting_category_id:int, description?:string|null} $input */
    public function createFromStatement(CompanyAccountStatementEntry $statement, array $input, int $userId): CompanyAccountingEntry
    {
        return DB::transaction(function () use ($statement, $input, $userId): CompanyAccountingEntry {
            $statement = CompanyAccountStatementEntry::query()
                ->with('companyAccount')
                ->whereKey($statement->id)
                ->lockForUpdate()
                ->firstOrFail();

            $type = (string) $input['type'];
            $expectedDirection = $type === 'income' ? 'in' : 'out';

            if ($statement->is_finalized || $statement->status !== 'unmatched' || $statement->journal_entry_id !== null || $statement->source_type !== null || $statement->direction !== $expectedDirection) {
                throw ValidationException::withMessages(['statement' => 'This statement row cannot be classified for this action.']);
            }

            $companyAccount = $statement->companyAccount;
            if (! $companyAccount instanceof CompanyAccount || ! $companyAccount->enabled || ! in_array($companyAccount->account_type, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['statement' => 'Statement company account is not valid for classification.']);
            }

            $category = CompanyAccountingCategory::query()
                ->with('account')
                ->whereKey((int) $input['company_accounting_category_id'])
                ->where('type', $type)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $expectedAccountType = $type === 'income' ? 'revenue' : 'expense';
            if (! $category->account || ! $category->account->is_active || $category->account->type !== $expectedAccountType) {
                throw ValidationException::withMessages(['company_accounting_category_id' => 'Selected category has no active matching ledger account.']);
            }

            $this->validateOtherCategoryDescription($category, $input['description'] ?? null);

            $entry = $this->mainAccountService->createEntry([
                'type' => $type,
                'company_accounting_category_id' => $category->id,
                'company_account_id' => $companyAccount->id,
                'business_date' => $statement->transaction_date?->toDateString() ?? today()->toDateString(),
                'payment_mode' => $companyAccount->account_type === 'cash' ? 'cash' : 'bank',
                'payment_reference' => $statement->reference,
                'amount' => (float) $statement->amount,
                'reference' => $statement->reference,
                'description' => $input['description'] ?? ($statement->narration ?: $category->name),
            ], $userId);

            $journalEntry = $entry->journalEntry;
            if (! $journalEntry) {
                throw new \RuntimeException('Company accounting entry did not create its canonical journal entry.');
            }

            $statement->update([
                'source' => 'company_accounting_entry',
                'source_type' => CompanyAccountingEntry::class,
                'source_id' => $entry->id,
                'counterpart_type' => CompanyAccountingCategory::class,
                'counterpart_id' => $category->id,
                'narration' => $statement->narration ?: ($entry->description ?: $category->name),
                'notes' => $entry->description,
            ]);

            $this->reconciliationService->reconcileStatementJournal(
                $statement,
                $journalEntry,
                (float) $statement->amount,
                $userId,
            );

            return $entry->fresh(['category.account', 'companyAccount', 'journalEntry.transactions.account', 'cashbookMovement']);
        }, attempts: 3);
    }

    private function validateOtherCategoryDescription(CompanyAccountingCategory $category, ?string $description): void
    {
        if (mb_strtolower(trim($category->name)) === 'other' && blank($description)) {
            throw ValidationException::withMessages(['description' => 'Notes / Description is required when category is Other.']);
        }
    }
}
