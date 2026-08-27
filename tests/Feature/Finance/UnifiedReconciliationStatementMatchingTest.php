<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Finance\PurchaserFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedReconciliationStatementMatchingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CompanyAccount $bankAccount;

    private CompanyAccount $cashAccount;

    private CompanyPaymentReconciliationService $reconciliationService;

    private PurchaserFinanceService $purchaserFinanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->bankAccount = CompanyAccount::query()->create([
            'name' => 'Main Operating Bank',
            'account_type' => 'bank',
            'bank_name' => 'State Bank of India',
            'account_number' => '123456789012',
            'enabled' => true,
            'is_default' => true,
        ]);

        $this->cashAccount = CompanyAccount::query()->create([
            'name' => 'Main Cash Vault',
            'account_type' => 'cash',
            'enabled' => true,
        ]);

        Account::query()->firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset']);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset']);
        Account::query()->firstOrCreate(['code' => '1030'], ['name' => 'Purchaser Advance', 'type' => 'asset']);
        Account::query()->firstOrCreate(['code' => '2010'], ['name' => 'Accounts Payable', 'type' => 'liability']);
        Account::query()->firstOrCreate(['code' => '4010'], ['name' => 'Sales Revenue', 'type' => 'revenue']);
        Account::query()->firstOrCreate(['code' => '5010'], ['name' => 'Operating Expense', 'type' => 'expense']);

        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
        $this->purchaserFinanceService = app(PurchaserFinanceService::class);
    }

    public function test_shared_resolver_supports_all_source_types_and_direction_safety(): void
    {
        // 1. Outgoing Statement candidates for PurchaserCredit (OUT)
        $purchaser = User::factory()->create();
        Role::firstOrCreate(['name' => 'purchaser']);
        $purchaser->assignRole('purchaser');
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $purchaser->id,
            'type' => 'credit',
            'amount' => 50000.00,
            'business_date' => '2026-08-20',
            'company_account_id' => $this->bankAccount->id,
            'payment_source' => 'Bank',
            'created_by' => $this->admin->id,
        ]);

        // Same account, exact amount, OUT direction (Matching)
        $matchingStmt = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'out',
            'amount' => 50000.00,
            'reference' => 'NEFT-MATCH-50K',
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        // Wrong direction: IN direction (Must NOT match)
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'in',
            'amount' => 50000.00,
            'reference' => 'NEFT-WRONG-DIR',
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        // Wrong account (Must NOT match)
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->cashAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'out',
            'amount' => 50000.00,
            'reference' => 'CASH-WRONG-ACC',
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $candidates = $this->reconciliationService->findStatementCandidates(
            companyAccountId: $this->bankAccount->id,
            amount: 50000.00,
            direction: 'out',
            referenceDate: '2026-08-20'
        );

        $this->assertCount(1, $candidates['pending']);
        $this->assertSame($matchingStmt->id, $candidates['pending'][0]['id']);
        $this->assertSame('exact', $candidates['pending'][0]['date_match']);
        $this->assertSame(0, $candidates['pending'][0]['date_difference_days']);
        $this->assertSame('EXACT DATE', $candidates['pending'][0]['date_badge_text']);

        // 2. Incoming Statement candidates for ShopInvoicePaymentRequest (IN)
        $shop = Shop::factory()->create();
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $shop->id,
            'requested_amount' => 15000.00,
            'reconciled_amount' => 0,
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'payment_date' => '2026-08-22',
            'created_by' => $this->admin->id,
        ]);

        $shopStmt = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-22',
            'direction' => 'in',
            'amount' => 15000.00,
            'reference' => 'SHOP-PAY-15K',
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $inCandidates = $this->reconciliationService->findStatementCandidates(
            companyAccountId: $this->bankAccount->id,
            amount: 15000.00,
            direction: 'in',
            referenceDate: '2026-08-22'
        );

        $this->assertCount(1, $inCandidates['pending']);
        $this->assertSame($shopStmt->id, $inCandidates['pending'][0]['id']);
        $this->assertSame('exact', $inCandidates['pending'][0]['date_match']);
    }

    public function test_date_classification_and_sorting_priorities_in_statement_candidates(): void
    {
        $fundingDate = '2026-08-20';

        // 1. Statement with 5 days away (2026-08-25)
        $stmt5Days = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-25',
            'direction' => 'out',
            'amount' => 25000.00,
            'reference' => 'STMT-5-DAYS',
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        // 2. Statement with Exact Date (2026-08-20)
        $stmtExact = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'out',
            'amount' => 25000.00,
            'reference' => 'STMT-EXACT',
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        // 3. Statement with 1 day away (2026-08-21)
        $stmt1Day = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-21',
            'direction' => 'out',
            'amount' => 25000.00,
            'reference' => 'STMT-1-DAY',
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $candidates = $this->reconciliationService->findStatementCandidates(
            companyAccountId: $this->bankAccount->id,
            amount: 25000.00,
            direction: 'out',
            referenceDate: $fundingDate
        );

        $this->assertCount(3, $candidates['pending']);

        // Priority 1: Exact date
        $this->assertSame($stmtExact->id, $candidates['pending'][0]['id']);
        $this->assertSame('exact', $candidates['pending'][0]['date_match']);
        $this->assertSame('EXACT DATE', $candidates['pending'][0]['date_badge_text']);

        // Priority 2: 1 day away
        $this->assertSame($stmt1Day->id, $candidates['pending'][1]['id']);
        $this->assertSame('other', $candidates['pending'][1]['date_match']);
        $this->assertSame(1, $candidates['pending'][1]['date_difference_days']);
        $this->assertSame('1 DAY AWAY', $candidates['pending'][1]['date_badge_text']);

        // Priority 3: 5 days away
        $this->assertSame($stmt5Days->id, $candidates['pending'][2]['id']);
        $this->assertSame('other', $candidates['pending'][2]['date_match']);
        $this->assertSame(5, $candidates['pending'][2]['date_difference_days']);
        $this->assertSame('5 DAYS AWAY', $candidates['pending'][2]['date_badge_text']);
    }

    public function test_journal_candidates_for_statement_returns_pending_and_reconciled_with_date_priority(): void
    {
        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-15',
            'direction' => 'out',
            'amount' => 10000.00,
            'reference' => 'STMT-MATCH-10K',
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        // Open Journal Entry (10 days away)
        $openJe10Days = $this->createExpenseJournal(10000.00, '2026-08-25', 'EXP-10-DAYS');

        // Open Journal Entry (Exact Date)
        $openJeExact = $this->createExpenseJournal(10000.00, '2026-08-15', 'EXP-EXACT');

        // Reconciled Journal Entry (Exact Date, already linked to another finalized statement)
        $reconciledJe = $this->createExpenseJournal(10000.00, '2026-08-15', 'EXP-RECONCILED');
        $priorStmt = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'journal_entry_id' => $reconciledJe->id,
            'transaction_date' => '2026-08-15',
            'direction' => 'out',
            'amount' => 10000.00,
            'matched_amount' => 10000.00,
            'status' => 'reconciled',
            'is_finalized' => true,
            'finalized_at' => now(),
            'reconciled_by' => $this->admin->id,
            'reconciled_at' => now(),
        ]);

        $candidates = $this->reconciliationService->findJournalCandidatesForStatement($statement);

        $this->assertCount(2, $candidates['pending']);
        $this->assertCount(1, $candidates['reconciled']);
        $this->assertSame(1, $candidates['counts']['exact_date_pending']);
        $this->assertSame(1, $candidates['counts']['exact_date_reconciled']);

        // First pending should be exact date
        $this->assertSame($openJeExact->id, $candidates['pending'][0]['id']);
        $this->assertSame('exact', $candidates['pending'][0]['date_match']);
        $this->assertSame('EXACT DATE', $candidates['pending'][0]['date_badge_text']);

        // Second pending should be 10 days away
        $this->assertSame($openJe10Days->id, $candidates['pending'][1]['id']);
        $this->assertSame('other', $candidates['pending'][1]['date_match']);
        $this->assertSame('10 DAYS AWAY', $candidates['pending'][1]['date_badge_text']);

        // Reconciled list contains previously reconciled journal
        $this->assertSame($reconciledJe->id, $candidates['reconciled'][0]['id']);
        $this->assertSame('MATCHED', $candidates['reconciled'][0]['status']);
    }

    public function test_replace_match_in_reconciliation_returns_old_transaction_to_needs_action(): void
    {
        // 1. Create Transaction A and Statement row, reconciled together
        $jeA = $this->createExpenseJournal(30000.00, '2026-08-10', 'EXP-TRANS-A');
        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-10',
            'direction' => 'out',
            'amount' => 30000.00,
            'reference' => 'BANK-STMT-30K',
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $this->reconciliationService->reconcileStatementJournal($statement, $jeA, 30000.00, $this->admin->id);
        $this->assertTrue((bool) $statement->fresh()->is_finalized);
        $this->assertSame($jeA->id, $statement->fresh()->journal_entry_id);

        // 2. Create Transaction B (Pending)
        $jeB = $this->createExpenseJournal(30000.00, '2026-08-10', 'EXP-TRANS-B');

        // 3. Replace Match: replace statement match from Transaction A to Transaction B
        $candidateRefB = rtrim(strtr(base64_encode(Crypt::encryptString('journal-entry:'.$jeB->getKey())), '+/', '-_'), '=');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.match-existing', $statement), [
                'candidate_ref' => $candidateRefB,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // 4. Verify Statement is now linked to Transaction B
        $this->assertSame($jeB->id, $statement->fresh()->journal_entry_id);
        $this->assertTrue((bool) $statement->fresh()->is_finalized);

        // 5. Verify Transaction A has no finalized statements and reappears as Pending / Needs Action
        $this->assertFalse($jeA->statementEntries()->where('is_finalized', true)->exists());

        $candidatesForA = $this->reconciliationService->findStatementCandidates(
            companyAccountId: $this->bankAccount->id,
            amount: 30000.00,
            direction: 'out',
            referenceDate: '2026-08-10'
        );

        // Statement is now in reconciled list for Transaction A
        $this->assertCount(1, $candidatesForA['reconciled']);
        $this->assertSame($statement->id, $candidatesForA['reconciled'][0]['id']);
    }

    private function createExpenseJournal(float $amount, string $date, string $ref): JournalEntry
    {
        $bankAccount = Account::where('code', '1020')->firstOrFail();
        $expenseAccount = Account::where('code', '5010')->firstOrFail();

        $category = CompanyAccountingCategory::query()->firstOrCreate(['name' => 'Office', 'type' => 'expense']);
        $companyEntry = CompanyAccountingEntry::query()->create([
            'type' => 'expense',
            'payment_mode' => 'bank',
            'company_accounting_category_id' => $category->id,
            'company_account_id' => $this->bankAccount->id,
            'amount' => $amount,
            'business_date' => $date,
            'description' => 'Expense '.$ref,
            'reference' => $ref,
            'created_by' => $this->admin->id,
        ]);

        $journalEntry = JournalEntry::query()->create([
            'entry_date' => $date,
            'source_type' => CompanyAccountingEntry::class,
            'source_id' => $companyEntry->id,
            'source_event' => 'final',
            'reference' => $ref,
            'description' => 'Expense '.$ref,
            'primary_amount' => $amount,
            'is_balanced' => true,
            'created_by' => $this->admin->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $expenseAccount->id,
            'type' => 'debit',
            'amount' => $amount,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $bankAccount->id,
            'type' => 'credit',
            'amount' => $amount,
        ]);

        return $journalEntry;
    }
}
