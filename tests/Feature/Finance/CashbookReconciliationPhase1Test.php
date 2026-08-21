<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashbookReconciliationPhase1Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private CompanyAccount $companyAccount;

    private Account $bankAccount;

    private Account $arAccount;

    private CompanyPaymentReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->shop = Shop::factory()->create();
        $this->companyAccount = CompanyAccount::query()->create([
            'name' => 'Test Bank Account',
            'account_type' => 'bank',
            'bank_name' => 'South Indian Bank',
            'enabled' => true,
        ]);

        $this->bankAccount = Account::query()->firstOrCreate(
            ['code' => '1020'],
            ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]
        );

        $this->arAccount = Account::query()->firstOrCreate(
            ['code' => '1100'],
            ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]
        );

        $this->service = app(CompanyPaymentReconciliationService::class);
    }

    public function test_statement_entry_and_reconciliation_stores_journal_entry_id_and_finalized_flags_upon_full_match(): void
    {
        $paymentRequest = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 1000.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);

        $journalEntry = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-TEST-1000',
            'description' => 'Shop payment request #'.$paymentRequest->id,
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $paymentRequest->id,
            'source_event' => 'client-balance-payment:'.$paymentRequest->id,
            'created_by' => $this->admin->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->bankAccount->id,
            'type' => 'debit',
            'amount' => 1000.00,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->arAccount->id,
            'type' => 'credit',
            'amount' => 1000.00,
        ]);

        $statementEntry = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 1000.00,
            'reference' => 'BANK-STMT-1000',
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $reconciliation = $this->service->reconcilePayment(
            $paymentRequest,
            [
                'company_account_id' => $this->companyAccount->id,
                'statement_entry_id' => $statementEntry->id,
                'journal_entry_id' => $journalEntry->id,
                'statement_amount' => 1000.00,
                'cleared_amount' => 1000.00,
                'difference_amount' => 0,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );

        $this->assertEquals($journalEntry->id, $reconciliation->journal_entry_id);
        $this->assertTrue($reconciliation->is_finalized);
        $this->assertNotNull($reconciliation->finalized_at);

        $statementEntry->refresh();
        $this->assertEquals($journalEntry->id, $statementEntry->journal_entry_id);
        $this->assertTrue($statementEntry->is_finalized);
        $this->assertEquals('reconciled', $statementEntry->status);
    }

    public function test_reconciliation_fails_if_journal_entry_is_unbalanced(): void
    {
        $paymentRequest = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 1000.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);

        $unbalancedJe = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-UNBALANCED',
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $paymentRequest->id,
            'source_event' => 'unbalanced:'.$paymentRequest->id,
            'created_by' => $this->admin->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $unbalancedJe->id,
            'account_id' => $this->bankAccount->id,
            'type' => 'debit',
            'amount' => 1000.00,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $unbalancedJe->id,
            'account_id' => $this->arAccount->id,
            'type' => 'credit',
            'amount' => 800.00, // Unbalanced!
        ]);

        $statementEntry = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->reconcilePayment(
            $paymentRequest,
            [
                'company_account_id' => $this->companyAccount->id,
                'statement_entry_id' => $statementEntry->id,
                'journal_entry_id' => $unbalancedJe->id,
                'statement_amount' => 1000.00,
                'cleared_amount' => 1000.00,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );
    }

    public function test_reconciliation_fails_if_direction_does_not_match_bank_cash_line(): void
    {
        $paymentRequest = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 1000.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);

        $je = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-WRONG-DIR',
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $paymentRequest->id,
            'source_event' => 'wrong-dir:'.$paymentRequest->id,
            'created_by' => $this->admin->id,
        ]);

        // Has Credit 1020 Bank instead of Debit 1020 for an IN statement entry
        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->arAccount->id,
            'type' => 'debit',
            'amount' => 1000.00,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->bankAccount->id,
            'type' => 'credit',
            'amount' => 1000.00,
        ]);

        $statementEntry = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in', // Deposit direction requires Debit 1020
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->reconcilePayment(
            $paymentRequest,
            [
                'company_account_id' => $this->companyAccount->id,
                'statement_entry_id' => $statementEntry->id,
                'journal_entry_id' => $je->id,
                'statement_amount' => 1000.00,
                'cleared_amount' => 1000.00,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );
    }

    public function test_reconciliation_fails_if_journal_entry_already_linked_to_finalized_statement_entry(): void
    {
        $je = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-DUPLICATE-CHECK',
            'created_by' => $this->admin->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->bankAccount->id,
            'type' => 'debit',
            'amount' => 500.00,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->arAccount->id,
            'type' => 'credit',
            'amount' => 500.00,
        ]);

        // Existing finalized statement entry linked to JE
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'journal_entry_id' => $je->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 500.00,
            'status' => 'reconciled',
            'is_finalized' => true,
            'finalized_at' => now(),
            'imported_by' => $this->admin->id,
        ]);

        $paymentRequest2 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 500.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);

        $statementEntry2 = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 500.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->reconcilePayment(
            $paymentRequest2,
            [
                'company_account_id' => $this->companyAccount->id,
                'statement_entry_id' => $statementEntry2->id,
                'journal_entry_id' => $je->id,
                'statement_amount' => 500.00,
                'cleared_amount' => 500.00,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );
    }
}
