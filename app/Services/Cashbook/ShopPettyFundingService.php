<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Services\Finance\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopPettyFundingService
{
    public function __construct(
        private readonly DailyLedgerService $dailyLedgerService,
        private readonly JournalService $journalService,
        private readonly CompanyPaymentReconciliationService $reconciliationService,
    ) {}

    /** @param array{business_date:string, amount:float, company_account:CompanyAccount, request_uuid:string, reference?:string|null, notes?:string|null} $input */
    public function fund(Shop $shop, array $input, int $userId): CompanyAccountStatementEntry
    {
        return DB::transaction(function () use ($shop, $input, $userId): CompanyAccountStatementEntry {
            $companyAccount = CompanyAccount::query()
                ->whereKey($input['company_account']->id)
                ->where('enabled', true)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = CompanyAccountStatementEntry::query()
                ->where('request_uuid', $input['request_uuid'])
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CompanyAccountStatementEntry) {
                if ((int) $existing->company_account_id !== (int) $companyAccount->id || $existing->source_type !== ShopLedgerTransaction::class) {
                    throw ValidationException::withMessages(['request_uuid' => 'Request identity has already been used for a different money movement.']);
                }

                return $existing;
            }

            $amount = round((float) $input['amount'], 2);
            $statement = CompanyAccountStatementEntry::query()->create([
                'company_account_id' => $companyAccount->id,
                'request_uuid' => $input['request_uuid'],
                'transaction_date' => $input['business_date'],
                'value_date' => $input['business_date'],
                'direction' => 'out',
                'amount' => $amount,
                'reference' => $input['reference'] ?? 'SHOP-PETTY-'.$shop->code,
                'narration' => 'Company petty funding for '.$shop->name,
                'source' => 'shop_petty_funding',
                'status' => 'unmatched',
                'matched_amount' => 0,
                'notes' => $input['notes'] ?? null,
                'imported_by' => $userId,
            ]);

            $companyAccount->decrement('current_balance', $amount);

            $result = $this->dailyLedgerService->recordEntry([
                'shop_id' => $shop->id,
                'business_date' => $input['business_date'],
                'entry_type_code' => 'company_to_petty',
                'amount' => $amount,
                'funding_source' => 'company',
                'company_account_id' => $companyAccount->id,
                'reference_type' => CompanyAccountStatementEntry::class,
                'reference_id' => $statement->id,
                'entered_by' => $userId,
                'notes' => $input['notes'] ?? null,
            ]);

            $transaction = $result['transaction'];
            $journal = $this->journalService->recordShopPettyFunding($transaction, $companyAccount, $userId);

            $statement->update([
                'journal_entry_id' => $journal->id,
                'source_type' => ShopLedgerTransaction::class,
                'source_id' => $transaction->id,
                'counterpart_type' => ShopLedgerTransaction::class,
                'counterpart_id' => $transaction->id,
            ]);

            return $statement->fresh(['companyAccount', 'journalEntry.transactions.account', 'sourceRecord']);
        }, attempts: 3);
    }

    /** @param array{notes?:string|null} $input */
    public function fundFromStatement(Shop $shop, CompanyAccountStatementEntry $statement, array $input, int $userId): CompanyAccountStatementEntry
    {
        return DB::transaction(function () use ($shop, $statement, $input, $userId): CompanyAccountStatementEntry {
            $statement = CompanyAccountStatementEntry::query()
                ->with('companyAccount')
                ->whereKey($statement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($statement->is_finalized || $statement->status !== 'unmatched' || $statement->journal_entry_id !== null || $statement->source_type !== null || $statement->direction !== 'out') {
                throw ValidationException::withMessages(['statement' => 'This statement row cannot be classified as shop petty funding.']);
            }

            $companyAccount = $statement->companyAccount;
            if (! $companyAccount instanceof CompanyAccount || ! $companyAccount->enabled || ! in_array($companyAccount->account_type, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['statement' => 'Statement company account is not valid for shop petty funding.']);
            }

            $amount = round((float) $statement->amount, 2);
            $businessDate = $statement->transaction_date?->toDateString() ?? today()->toDateString();

            $result = $this->dailyLedgerService->recordEntry([
                'shop_id' => $shop->id,
                'business_date' => $businessDate,
                'entry_type_code' => 'company_to_petty',
                'amount' => $amount,
                'funding_source' => 'company',
                'company_account_id' => $companyAccount->id,
                'reference_type' => CompanyAccountStatementEntry::class,
                'reference_id' => $statement->id,
                'entered_by' => $userId,
                'notes' => $input['notes'] ?? null,
            ]);

            $transaction = $result['transaction'];
            $journal = $this->journalService->recordShopPettyFunding($transaction, $companyAccount, $userId);

            $statement->update([
                'journal_entry_id' => $journal->id,
                'source' => 'shop_petty_funding',
                'source_type' => ShopLedgerTransaction::class,
                'source_id' => $transaction->id,
                'counterpart_type' => ShopLedgerTransaction::class,
                'counterpart_id' => $transaction->id,
                'narration' => $statement->narration ?: 'Company petty funding for '.$shop->name,
                'notes' => $input['notes'] ?? null,
            ]);

            $this->reconciliationService->reconcileStatementJournal(
                $statement,
                $journal,
                $amount,
                $userId,
            );

            return $statement->fresh(['companyAccount', 'journalEntry.transactions.account', 'sourceRecord']);
        }, attempts: 3);
    }
}
