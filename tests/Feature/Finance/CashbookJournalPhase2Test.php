<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\DirectCompanySale;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Finance\CompanyMainAccountService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookJournalPhase2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private CompanyAccount $bankCompanyAccount;

    private CompanyAccount $cashCompanyAccount;

    private Account $bankAccount;

    private Account $cashAccount;

    private Account $arAccount;

    private Account $expenseAccount;

    private CompanyPaymentReconciliationService $reconciliationService;

    private CompanyMainAccountService $mainAccountService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->shop = Shop::factory()->create();

        $this->bankCompanyAccount = CompanyAccount::query()->create([
            'name' => 'South Indian Bank',
            'account_type' => 'bank',
            'bank_name' => 'South Indian Bank',
            'enabled' => true,
        ]);

        $this->cashCompanyAccount = CompanyAccount::query()->create([
            'name' => 'Main Cash Vault',
            'account_type' => 'cash',
            'bank_name' => 'Vault',
            'enabled' => true,
        ]);

        $this->bankAccount = Account::query()->firstOrCreate(
            ['code' => '1020'],
            ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]
        );

        $this->cashAccount = Account::query()->firstOrCreate(
            ['code' => '1010'],
            ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]
        );

        $this->arAccount = Account::query()->firstOrCreate(
            ['code' => '1100'],
            ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]
        );

        $this->expenseAccount = Account::query()->firstOrCreate(
            ['code' => '5900'],
            ['name' => 'Miscellaneous Expense', 'type' => 'expense', 'is_active' => true]
        );

        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
        $this->mainAccountService = app(CompanyMainAccountService::class);
    }

    public function test_journal_transaction_appears_in_cashbook_with_reference_and_totals(): void
    {
        $je = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'TEST-CASHBOOK-001',
            'description' => 'Vendor Payment Test',
            'created_by' => $this->admin->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->expenseAccount->id,
            'type' => 'debit',
            'amount' => 5000.00,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->bankAccount->id,
            'type' => 'credit',
            'amount' => 5000.00,
        ]);
        $this->finalizeJournalEntry($je, $this->bankCompanyAccount, 'out', 5000.00);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'));

        $response->assertOk();
        $response->assertSee('JE-'.str_pad((string) $je->id, 4, '0', STR_PAD_LEFT));
        $response->assertSee('Vendor Payment Test');
        $response->assertSee('5,000.00');
        $response->assertSee('FINALIZED');
    }

    public function test_reconciliation_statuses_unreconciled_partial_reconciled_finalized(): void
    {
        // 1. Unreconciled Entry
        $jeUnrec = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-UNREC',
            'created_by' => $this->admin->id,
        ]);
        JournalTransaction::query()->create(['journal_entry_id' => $jeUnrec->id, 'account_id' => $this->bankAccount->id, 'type' => 'debit', 'amount' => 1000.00]);
        JournalTransaction::query()->create(['journal_entry_id' => $jeUnrec->id, 'account_id' => $this->arAccount->id, 'type' => 'credit', 'amount' => 1000.00]);

        $this->assertEquals('unreconciled', $jeUnrec->reconciliation_status);

        // 2. Partially Reconciled Entry
        $paymentReq = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 2000.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);

        $jePart = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-PARTIAL',
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $paymentReq->id,
            'created_by' => $this->admin->id,
        ]);
        JournalTransaction::query()->create(['journal_entry_id' => $jePart->id, 'account_id' => $this->bankAccount->id, 'type' => 'debit', 'amount' => 2000.00]);
        JournalTransaction::query()->create(['journal_entry_id' => $jePart->id, 'account_id' => $this->arAccount->id, 'type' => 'credit', 'amount' => 2000.00]);

        $stmtEntry1 = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->reconciliationService->reconcilePayment(
            $paymentReq,
            [
                'company_account_id' => $this->bankCompanyAccount->id,
                'statement_entry_id' => $stmtEntry1->id,
                'journal_entry_id' => $jePart->id,
                'statement_amount' => 1000.00,
                'cleared_amount' => 1000.00,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );

        $jePart->refresh();
        $this->assertEquals('partially_reconciled', $jePart->reconciliation_status);

        // 3. Fully Reconciled & Finalized
        $stmtEntry2 = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->reconciliationService->reconcilePayment(
            $paymentReq,
            [
                'company_account_id' => $this->bankCompanyAccount->id,
                'statement_entry_id' => $stmtEntry2->id,
                'journal_entry_id' => $jePart->id,
                'statement_amount' => 1000.00,
                'cleared_amount' => 1000.00,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );

        $jePart->refresh();
        $this->assertTrue($jePart->is_finalized);
        $this->assertEquals('finalized', $jePart->reconciliation_status);
    }

    public function test_finalized_statement_entry_cannot_be_re_reconciled(): void
    {
        $je = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-FINAL-IMMUTABLE',
            'created_by' => $this->admin->id,
        ]);
        JournalTransaction::query()->create(['journal_entry_id' => $je->id, 'account_id' => $this->bankAccount->id, 'type' => 'debit', 'amount' => 500.00]);
        JournalTransaction::query()->create(['journal_entry_id' => $je->id, 'account_id' => $this->arAccount->id, 'type' => 'credit', 'amount' => 500.00]);

        $paymentReq = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 500.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);

        $stmtEntry = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 500.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->reconciliationService->reconcilePayment(
            $paymentReq,
            [
                'company_account_id' => $this->bankCompanyAccount->id,
                'statement_entry_id' => $stmtEntry->id,
                'statement_amount' => 500.00,
                'cleared_amount' => 500.00,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );

        $stmtEntry->refresh();
        $this->assertTrue($stmtEntry->is_finalized);

        // Attempt second reconciliation against finalized statement entry
        $paymentReq2 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 500.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->reconciliationService->reconcilePayment(
            $paymentReq2,
            [
                'company_account_id' => $this->bankCompanyAccount->id,
                'statement_entry_id' => $stmtEntry->id,
                'statement_amount' => 500.00,
                'cleared_amount' => 500.00,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );
    }

    public function test_reconciliation_rejects_wrong_bank_or_cash_account_type(): void
    {
        $je = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-WRONG-ACC',
            'created_by' => $this->admin->id,
        ]);
        // Journal entry uses Cash account 1010
        JournalTransaction::query()->create(['journal_entry_id' => $je->id, 'account_id' => $this->cashAccount->id, 'type' => 'debit', 'amount' => 1000.00]);
        JournalTransaction::query()->create(['journal_entry_id' => $je->id, 'account_id' => $this->arAccount->id, 'type' => 'credit', 'amount' => 1000.00]);

        $paymentReq = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 1000.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);
        $je->update([
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $paymentReq->id,
            'source_event' => 'shop-payment-request:'.$paymentReq->id,
        ]);

        // Statement is from Bank account (expected 1020)
        $stmtEntry = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->reconciliationService->reconcilePayment(
            $paymentReq,
            [
                'company_account_id' => $this->bankCompanyAccount->id,
                'statement_entry_id' => $stmtEntry->id,
                'journal_entry_id' => $je->id,
                'statement_amount' => 1000.00,
                'cleared_amount' => 1000.00,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );
    }

    public function test_journal_entry_detail_page_renders_with_double_entry_lines_and_balance_proof(): void
    {
        $je = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-DETAIL-TEST',
            'description' => 'Test canonical detail view',
            'created_by' => $this->admin->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->bankAccount->id,
            'type' => 'debit',
            'amount' => 7500.00,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->arAccount->id,
            'type' => 'credit',
            'amount' => 7500.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal.entry-show', $je->id));

        $response->assertOk();
        $response->assertSee('JE-'.str_pad((string) $je->id, 4, '0', STR_PAD_LEFT));
        $response->assertSee('Test canonical detail view');
        $response->assertSee('7,500.00');
        $response->assertSee('Balanced');
    }

    public function test_reversal_creates_new_balanced_journal_entry_without_mutating_original(): void
    {
        $category = CompanyAccountingCategory::query()->create([
            'name' => 'Office Expense',
            'type' => 'expense',
            'account_id' => $this->expenseAccount->id,
            'is_active' => true,
        ]);

        $entry = $this->mainAccountService->createEntry([
            'company_accounting_category_id' => $category->id,
            'type' => 'expense',
            'business_date' => now()->toDateString(),
            'payment_mode' => 'bank',
            'amount' => 3000.00,
            'reference' => 'EXP-ORIGINAL',
            'description' => 'Office supplies',
        ], (int) $this->admin->id);

        $originalJeId = $entry->journal_entry_id;
        $this->assertNotNull($originalJeId);

        $originalJe = JournalEntry::query()->with('transactions')->find($originalJeId);
        $this->assertEquals(3000.00, $originalJe->total_debit);
        $this->assertEquals(3000.00, $originalJe->total_credit);

        // Reverse the entry
        $reversedEntry = $this->mainAccountService->reverseEntry($entry, (int) $this->admin->id, 'Entered wrong amount');

        $this->assertEquals('reversed', $reversedEntry->status);
        $this->assertNotNull($reversedEntry->reversal_journal_entry_id);

        // Assert original JE is unmodified
        $originalJe->refresh();
        $this->assertEquals(3000.00, $originalJe->total_debit);
        $this->assertEquals(3000.00, $originalJe->total_credit);

        // Assert reversal JE is a separate balanced entry
        $reversalJe = JournalEntry::query()->with('transactions')->find($reversedEntry->reversal_journal_entry_id);
        $this->assertNotNull($reversalJe);
        $this->assertNotEquals($originalJe->id, $reversalJe->id);
        $this->assertEquals(3000.00, $reversalJe->total_debit);
        $this->assertEquals(3000.00, $reversalJe->total_credit);
        $this->assertTrue($reversalJe->is_balanced);
    }

    public function test_tab_filters_bank_cash_income_expense_correctly(): void
    {
        // Bank entry
        $bankJe = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-BANK-ONLY',
            'description' => 'Bank deposit',
            'created_by' => $this->admin->id,
        ]);
        JournalTransaction::query()->create(['journal_entry_id' => $bankJe->id, 'account_id' => $this->bankAccount->id, 'type' => 'debit', 'amount' => 1200.00]);
        JournalTransaction::query()->create(['journal_entry_id' => $bankJe->id, 'account_id' => $this->arAccount->id, 'type' => 'credit', 'amount' => 1200.00]);
        $this->finalizeJournalEntry($bankJe, $this->bankCompanyAccount, 'in', 1200.00);

        // Cash entry
        $cashJe = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-CASH-ONLY',
            'description' => 'Cash receipt',
            'created_by' => $this->admin->id,
        ]);
        JournalTransaction::query()->create(['journal_entry_id' => $cashJe->id, 'account_id' => $this->cashAccount->id, 'type' => 'debit', 'amount' => 800.00]);
        JournalTransaction::query()->create(['journal_entry_id' => $cashJe->id, 'account_id' => $this->arAccount->id, 'type' => 'credit', 'amount' => 800.00]);
        $this->finalizeJournalEntry($cashJe, $this->cashCompanyAccount, 'in', 800.00);

        // Filter Bank tab
        $responseBank = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal', ['tab' => 'bank']));
        $responseBank->assertOk();
        $responseBank->assertSee('Bank deposit');
        $responseBank->assertDontSee('Cash receipt');

        // Filter Cash tab
        $responseCash = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal', ['tab' => 'cash']));
        $responseCash->assertOk();
        $responseCash->assertSee('Cash receipt');
        $responseCash->assertDontSee('Bank deposit');
    }

    public function test_no_n_plus_one_queries_on_journal_listing(): void
    {
        // Seed 15 journal entries with transactions and statement entries
        for ($i = 1; $i <= 15; $i++) {
            $je = JournalEntry::query()->create([
                'entry_date' => now()->toDateString(),
                'reference' => "JE-NPLUS1-{$i}",
                'description' => "N+1 test entry {$i}",
                'created_by' => $this->admin->id,
            ]);
            JournalTransaction::query()->create(['journal_entry_id' => $je->id, 'account_id' => $this->bankAccount->id, 'type' => 'debit', 'amount' => 100.00 * $i]);
            JournalTransaction::query()->create(['journal_entry_id' => $je->id, 'account_id' => $this->expenseAccount->id, 'type' => 'credit', 'amount' => 100.00 * $i]);
            $this->finalizeJournalEntry($je, $this->bankCompanyAccount, 'in', 100.00 * $i);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'));
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 15 rows rendered in a single page should not execute 15 individual subqueries per row.
        // Total queries (auth, session, roles, journal pagination, eager loads, counts) should be well bounded (< 25).
        $this->assertLessThan(25, $queryCount, "Query count {$queryCount} exceeded expected bounded query limit.");
    }

    public function test_shop_bank_payment_is_hidden_until_full_reconciliation_then_visible_with_linked_journal(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 50000.00,
            'payment_method' => 'online_upi',
            'payment_reference' => 'SHOP-BANK-50000',
            'payment_date' => now()->toDateString(),
            'status' => 'pending',
            'reconciliation_status' => 'floating',
            'requested_by' => $this->admin->id,
            'floating_amount' => 50000.00,
            'reconciled_amount' => 0,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal'))
            ->assertOk()
            ->assertDontSee('50,000.00');

        $firstStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 30000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->reconciliationService->reconcilePayment($payment, [
            'company_account_id' => $this->bankCompanyAccount->id,
            'statement_entry_id' => $firstStatement->id,
            'statement_amount' => 30000.00,
            'cleared_amount' => 30000.00,
            'difference_action' => 'none',
        ], (int) $this->admin->id);

        $journal = JournalEntry::query()
            ->where('source_type', ShopInvoicePaymentRequest::class)
            ->where('source_id', $payment->id)
            ->firstOrFail();

        $this->assertEquals('partially_reconciled', $journal->fresh(['transactions.account', 'statementEntries', 'reconciliations'])->reconciliation_status);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal'))
            ->assertOk()
            ->assertDontSee('50,000.00');

        $secondStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 20000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->reconciliationService->reconcilePayment($payment->fresh(), [
            'company_account_id' => $this->bankCompanyAccount->id,
            'statement_entry_id' => $secondStatement->id,
            'journal_entry_id' => $journal->id,
            'statement_amount' => 20000.00,
            'cleared_amount' => 20000.00,
            'difference_action' => 'none',
        ], (int) $this->admin->id);

        $payment->refresh();
        $journal->refresh()->load(['transactions.account', 'statementEntries', 'reconciliations']);

        $this->assertEquals('reconciled', $payment->reconciliation_status);
        $this->assertTrue($journal->is_finalized);
        $this->assertEquals($journal->id, $payment->reconciliations()->latest('id')->first()?->journal_entry_id);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal'))
            ->assertOk()
            ->assertSee('50,000.00')
            ->assertSee('FINALIZED');
    }

    public function test_shop_payment_reconciliation_rejects_invalid_matches_and_finalized_amount_change(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 1000.00,
            'payment_method' => 'online_upi',
            'payment_date' => now()->toDateString(),
            'status' => 'pending',
            'reconciliation_status' => 'floating',
            'requested_by' => $this->admin->id,
            'floating_amount' => 1000.00,
            'reconciled_amount' => 0,
        ]);

        $wrongDirection = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'out',
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        try {
            $this->reconciliationService->reconcilePayment($payment, [
                'company_account_id' => $this->bankCompanyAccount->id,
                'statement_entry_id' => $wrongDirection->id,
                'statement_amount' => 1000.00,
                'cleared_amount' => 1000.00,
                'difference_action' => 'none',
            ], (int) $this->admin->id);
            $this->fail('Wrong direction was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('statement_entry_id', $exception->errors());
        }

        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        try {
            $this->reconciliationService->reconcilePayment($payment, [
                'company_account_id' => $this->cashCompanyAccount->id,
                'statement_entry_id' => $statement->id,
                'statement_amount' => 1000.00,
                'cleared_amount' => 1000.00,
                'difference_action' => 'none',
            ], (int) $this->admin->id);
            $this->fail('Wrong company account was accepted.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        try {
            $this->reconciliationService->reconcilePayment($payment, [
                'company_account_id' => $this->bankCompanyAccount->id,
                'statement_entry_id' => $statement->id,
                'statement_amount' => 1200.00,
                'cleared_amount' => 1200.00,
                'difference_action' => 'none',
            ], (int) $this->admin->id);
            $this->fail('Amount mismatch was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('statement_amount', $exception->errors());
        }

        $this->reconciliationService->reconcilePayment($payment, [
            'company_account_id' => $this->bankCompanyAccount->id,
            'statement_entry_id' => $statement->id,
            'statement_amount' => 1000.00,
            'cleared_amount' => 1000.00,
            'difference_action' => 'none',
        ], (int) $this->admin->id);

        $this->expectException(RuntimeException::class);
        $payment->fresh()->update(['requested_amount' => 900.00]);
    }

    public function test_legacy_cash_shop_payment_endpoint_is_retired(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.accept-payment'), [
            'shop_id' => $this->shop->id,
            'business_date' => now()->toDateString(),
            'company_account_id' => $this->cashCompanyAccount->id,
            'payment_method' => 'cash',
            'settle_amount' => 1250.00,
            'petty_amount' => 0,
            'notes' => 'Cash shop receipt test',
        ]);

        $response->assertStatus(410);
        $this->assertDatabaseCount('shop_invoice_payment_requests', 0);
        $this->assertDatabaseCount('cashbook_company_account_statement_entries', 0);
    }

    public function test_cashbook_journal_hides_unfinalized_and_internal_non_cash_entries(): void
    {
        $unfinalizedVendor = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'UNFINALIZED-VENDOR',
            'description' => 'Unfinalized vendor payment',
            'created_by' => $this->admin->id,
        ]);
        JournalTransaction::query()->create(['journal_entry_id' => $unfinalizedVendor->id, 'account_id' => $this->expenseAccount->id, 'type' => 'debit', 'amount' => 700.00]);
        JournalTransaction::query()->create(['journal_entry_id' => $unfinalizedVendor->id, 'account_id' => $this->bankAccount->id, 'type' => 'credit', 'amount' => 700.00]);

        $internal = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'INTERNAL-UTILIZATION',
            'description' => 'Purchaser utilization internal entry',
            'created_by' => $this->admin->id,
        ]);
        JournalTransaction::query()->create(['journal_entry_id' => $internal->id, 'account_id' => $this->expenseAccount->id, 'type' => 'debit', 'amount' => 400.00]);
        JournalTransaction::query()->create(['journal_entry_id' => $internal->id, 'account_id' => $this->arAccount->id, 'type' => 'credit', 'amount' => 400.00]);

        $finalizedVendor = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'FINALIZED-VENDOR',
            'description' => 'Finalized vendor payment',
            'created_by' => $this->admin->id,
        ]);
        JournalTransaction::query()->create(['journal_entry_id' => $finalizedVendor->id, 'account_id' => $this->expenseAccount->id, 'type' => 'debit', 'amount' => 900.00]);
        JournalTransaction::query()->create(['journal_entry_id' => $finalizedVendor->id, 'account_id' => $this->bankAccount->id, 'type' => 'credit', 'amount' => 900.00]);
        $this->finalizeJournalEntry($finalizedVendor, $this->bankCompanyAccount, 'out', 900.00);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'));

        $response->assertOk();
        $response->assertSee('Finalized vendor payment');
        $response->assertDontSee('UNFINALIZED-VENDOR');
        $response->assertDontSee('INTERNAL-UTILIZATION');
    }

    public function test_approved_transactions_visibility_for_all_company_cash_flow_sources(): void
    {
        $otherBankAccount = CompanyAccount::query()->create([
            'name' => 'Other Bank',
            'account_type' => 'bank',
            'bank_name' => 'Other Bank',
            'enabled' => true,
        ]);

        $sources = [
            ['key' => 'shop_payment', 'type' => ShopInvoicePaymentRequest::class, 'direction' => 'in', 'amount' => 110.00, 'label' => 'Shop Payment', 'account' => $this->bankCompanyAccount],
            ['key' => 'direct_sale', 'type' => DirectCompanySale::class, 'direction' => 'in', 'amount' => 120.00, 'label' => 'Direct Company Sale', 'account' => $otherBankAccount],
            ['key' => 'custom_income', 'type' => CompanyAccountingEntry::class, 'direction' => 'in', 'amount' => 130.00, 'label' => 'Other Income', 'account' => $this->cashCompanyAccount],
            ['key' => 'vendor_settlement', 'type' => VendorSettlement::class, 'direction' => 'out', 'amount' => 210.00, 'label' => 'Vendor Settlement', 'account' => $this->bankCompanyAccount],
            ['key' => 'purchaser_funding', 'type' => PurchaserCredit::class, 'direction' => 'out', 'amount' => 220.00, 'label' => 'Purchaser Funding', 'account' => $this->bankCompanyAccount],
            ['key' => 'shop_petty_funding', 'type' => ShopLedgerTransaction::class, 'direction' => 'out', 'amount' => 230.00, 'label' => 'Shop Petty Funding', 'account' => $this->cashCompanyAccount],
            ['key' => 'custom_expense', 'type' => CompanyAccountingEntry::class, 'direction' => 'out', 'amount' => 240.00, 'label' => 'Other Expense', 'account' => $this->cashCompanyAccount],
        ];

        $finalizedIds = [];
        $hiddenIds = [];

        foreach ($sources as $index => $source) {
            $finalized = $this->cashFlowJournalForSource($source['type'], $source['direction'], $source['amount'], 'FINAL-'.$source['key'], $index + 1, $source['account']);
            $this->finalizeJournalEntry($finalized, $source['account'], $source['direction'], $source['amount']);
            $finalizedIds[$source['key']] = $finalized->id;

            $pending = $this->cashFlowJournalForSource($source['type'], $source['direction'], $source['amount'] + 1000, 'PENDING-'.$source['key'], $index + 101, $source['account']);
            $hiddenIds[] = $pending->id;

            $partial = $this->cashFlowJournalForSource($source['type'], $source['direction'], $source['amount'] + 2000, 'PARTIAL-'.$source['key'], $index + 201, $source['account']);
            $this->partiallyMatchJournalEntry($partial, $source['account'], $source['direction'], ($source['amount'] + 2000) / 2);
            $hiddenIds[] = $partial->id;
        }

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'));
        $response->assertOk()
            ->assertSee('Approved Transactions')
            ->assertSee('Reconciled and finalized company cash and bank transactions.');

        $entries = $response->viewData('journalEntries')->getCollection();
        $visibleIds = $entries->pluck('id');

        $this->assertCount(count($finalizedIds), $visibleIds);
        $this->assertSame($visibleIds->count(), $visibleIds->unique()->count());

        foreach ($sources as $source) {
            $journal = $entries->firstWhere('id', $finalizedIds[$source['key']]);
            $this->assertInstanceOf(JournalEntry::class, $journal);
            $this->assertStringStartsWith($source['label'], $journal->source_label);
            $this->assertSame($source['amount'], (float) $journal->primary_amount);

            $statement = $journal->statementEntries->firstWhere('is_finalized', true);
            $this->assertInstanceOf(CompanyAccountStatementEntry::class, $statement);
            $this->assertSame($source['direction'], $statement->direction);
            $this->assertSame($source['account']->id, $statement->company_account_id);
            $this->assertSame($source['amount'], (float) $statement->amount);
            $response->assertSee(route('admin.cashbook.finance.journal.entry-show', $journal), false);
        }

        foreach ($hiddenIds as $hiddenId) {
            $this->assertFalse($visibleIds->contains($hiddenId));
        }

        $bankOneResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal', [
            'company_account_id' => $this->bankCompanyAccount->id,
        ]));
        $bankOneIds = $bankOneResponse->viewData('journalEntries')->getCollection()->pluck('id');

        $this->assertTrue($bankOneIds->contains($finalizedIds['shop_payment']));
        $this->assertFalse($bankOneIds->contains($finalizedIds['direct_sale']));
    }

    private function finalizeJournalEntry(JournalEntry $journalEntry, CompanyAccount $companyAccount, string $direction, float $amount): CompanyAccountStatementEntry
    {
        return CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $companyAccount->id,
            'journal_entry_id' => $journalEntry->id,
            'transaction_date' => now()->toDateString(),
            'direction' => $direction,
            'amount' => $amount,
            'matched_amount' => $amount,
            'status' => 'reconciled',
            'is_finalized' => true,
            'finalized_at' => now(),
            'imported_by' => $this->admin->id,
        ]);
    }

    private function partiallyMatchJournalEntry(JournalEntry $journalEntry, CompanyAccount $companyAccount, string $direction, float $matchedAmount): CompanyAccountStatementEntry
    {
        return CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $companyAccount->id,
            'journal_entry_id' => $journalEntry->id,
            'transaction_date' => now()->toDateString(),
            'direction' => $direction,
            'amount' => $matchedAmount * 2,
            'matched_amount' => $matchedAmount,
            'status' => 'partially_matched',
            'is_finalized' => false,
            'imported_by' => $this->admin->id,
        ]);
    }

    private function cashFlowJournalForSource(string $sourceType, string $direction, float $amount, string $reference, int $sourceId, CompanyAccount $companyAccount): JournalEntry
    {
        $journalEntry = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => $reference,
            'description' => $reference,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_event' => $reference,
            'created_by' => $this->admin->id,
        ]);

        $cashBankAccount = $companyAccount->account_type === 'cash'
            ? $this->cashAccount
            : $this->bankAccount;

        if ($direction === 'in') {
            JournalTransaction::query()->create(['journal_entry_id' => $journalEntry->id, 'account_id' => $cashBankAccount->id, 'type' => 'debit', 'amount' => $amount]);
            JournalTransaction::query()->create(['journal_entry_id' => $journalEntry->id, 'account_id' => $this->arAccount->id, 'type' => 'credit', 'amount' => $amount]);
        } else {
            JournalTransaction::query()->create(['journal_entry_id' => $journalEntry->id, 'account_id' => $this->expenseAccount->id, 'type' => 'debit', 'amount' => $amount]);
            JournalTransaction::query()->create(['journal_entry_id' => $journalEntry->id, 'account_id' => $cashBankAccount->id, 'type' => 'credit', 'amount' => $amount]);
        }

        return $journalEntry;
    }
}
