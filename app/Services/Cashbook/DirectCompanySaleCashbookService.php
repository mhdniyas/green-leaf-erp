<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\DirectCompanySale;
use App\Services\Finance\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectCompanySaleCashbookService
{
    public function __construct(private readonly JournalService $journalService, private readonly CompanyPaymentReconciliationService $reconciliationService) {}

    /** @param array{business_date:string,customer_name?:string|null,amount:float,payment_method:string,company_account_uuid:string,reference?:string|null,note?:string|null,request_uuid:string} $input */
    public function create(array $input, int $userId): DirectCompanySale
    {
        return DB::transaction(function () use ($input, $userId): DirectCompanySale {
            $movement = CompanyAccountStatementEntry::query()->where('request_uuid', $input['request_uuid'])->lockForUpdate()->first();
            if ($movement instanceof CompanyAccountStatementEntry) {
                if ($movement->source_type !== DirectCompanySale::class) {
                    throw ValidationException::withMessages(['request_uuid' => 'Request identity is already used by another cashbook movement.']);
                }

                return DirectCompanySale::query()->whereKey($movement->source_id)->firstOrFail()->fresh(['companyAccount', 'journalEntry.transactions.account', 'cashbookMovement']);
            }
            $account = CompanyAccount::query()->where('public_uuid', $input['company_account_uuid'])->where('enabled', true)->lockForUpdate()->firstOrFail();
            if ($account->account_type !== $input['payment_method']) {
                throw ValidationException::withMessages(['payment_method' => 'Payment method must match the selected company account type.']);
            }
            $sale = DirectCompanySale::query()->create(['request_uuid' => $input['request_uuid'], 'business_date' => $input['business_date'], 'customer_name' => $input['customer_name'] ?? null, 'amount' => $input['amount'], 'payment_method' => $input['payment_method'], 'company_account_id' => $account->id, 'reference' => $input['reference'] ?? null, 'note' => $input['note'] ?? null, 'created_by' => $userId]);
            $journal = $this->journalService->recordDirectCompanySale($sale, $account, $userId);
            $sale->update(['journal_entry_id' => $journal->id]);
            $this->reconciliationService->createStatementEntry(['company_account_id' => $account->id, 'journal_entry_id' => $journal->id, 'request_uuid' => $input['request_uuid'], 'transaction_date' => $sale->business_date->toDateString(), 'value_date' => $sale->business_date->toDateString(), 'direction' => 'in', 'amount' => (float) $sale->amount, 'reference' => $sale->reference ?: 'DIRECT-SALE-'.$sale->id, 'narration' => 'Direct company sale'.($sale->customer_name ? ' - '.$sale->customer_name : ''), 'source' => 'direct_company_sale', 'source_type' => DirectCompanySale::class, 'source_id' => $sale->id, 'notes' => $sale->note], $userId);

            return $sale->fresh(['companyAccount', 'journalEntry.transactions.account', 'cashbookMovement']);
        }, attempts: 3);
    }
}
