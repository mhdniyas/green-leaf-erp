<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\JournalEntry;
use App\Models\PurchaserCredit;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Finance\JournalService;
use App\Services\Finance\PurchaserFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserFundingReconciliationTraceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private User $otherPurchaser;

    private CompanyAccount $bankAccount;

    private CompanyAccount $cashAccount;

    private PurchaserFinanceService $purchaserFinanceService;

    private CompanyPaymentReconciliationService $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->purchaser = User::factory()->create(['name' => 'Purchaser Alpha']);
        $this->purchaser->assignRole('purchaser');

        $this->otherPurchaser = User::factory()->create(['name' => 'Purchaser Beta']);
        $this->otherPurchaser->assignRole('purchaser');

        $this->bankAccount = CompanyAccount::query()->create([
            'name' => 'HDFC Current Account',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'enabled' => true,
        ]);

        $this->cashAccount = CompanyAccount::query()->create([
            'name' => 'Main Office Cash Vault',
            'account_type' => 'cash',
            'enabled' => true,
        ]);

        Account::query()->firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1200'], ['name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1300'], ['name' => 'Purchaser Advances', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]);

        $this->purchaserFinanceService = app(PurchaserFinanceService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
    }

    public function test_1_and_2_new_purchaser_funding_starts_unmatched_without_synthetic_statement(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 25000.00,
            'business_date' => '2026-08-27',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-TEST-25K',
            'description' => 'Advance funding for market',
        ]);

        $response->assertRedirect();

        $credit = PurchaserCredit::query()->where('purchaser_id', $this->purchaser->id)->firstOrFail();
        $journalEntry = JournalEntry::query()
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $credit->id)
            ->firstOrFail();

        $this->assertEquals(25000.00, (float) $credit->amount);
        $this->assertEquals(25000.00, (float) $journalEntry->total_debit);

        // Crucial: No synthetic statement entry created
        $statementCount = CompanyAccountStatementEntry::query()->where('journal_entry_id', $journalEntry->id)->count();
        $this->assertEquals(0, $statementCount, 'New funding must not auto-create synthetic statement entries.');

        // Purchaser finance tab status
        $transactions = $this->purchaserFinanceService->transactionsFor($this->purchaser->id, '2026-08-01', '2026-08-31');
        $this->assertEquals('unmatched', $transactions->items()[0]->status);
    }

    public function test_6_to_9_candidate_statement_matching_query_and_direction_safety(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 15000.00,
            'description' => 'Advance for ginger batch',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-25',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit);

        // Candidate 1: Same account, exact amount OUT, pending (MUST BE IN PENDING)
        $cand1 = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-25',
            'direction' => 'out',
            'amount' => 15000.00,
            'reference' => 'IMPS1234-OUT',
            'narration' => 'Advance transfer to purchaser',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // Candidate 2: Old date exact amount OUT (MUST STILL BE RETURNED in PENDING)
        $candOld = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-05-10',
            'direction' => 'out',
            'amount' => 15000.00,
            'reference' => 'OLD-OUT-15K',
            'narration' => 'Old withdrawal',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // Non-candidate 3: Different amount OUT (MUST BE EXCLUDED)
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-24',
            'direction' => 'out',
            'amount' => 20000.00,
            'reference' => 'OTHER-AMOUNT',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // Non-candidate 4: IN direction deposit of 15,000 (MUST BE EXCLUDED)
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-25',
            'direction' => 'in',
            'amount' => 15000.00,
            'reference' => 'IMPS9988-IN',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // Non-candidate 5: Different company account (MUST BE EXCLUDED when bank account is selected)
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->cashAccount->id,
            'transaction_date' => '2026-08-25',
            'direction' => 'out',
            'amount' => 15000.00,
            'reference' => 'CASH-VAULT-15K',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // Reconciled candidate 6: Exact amount OUT but already finalized (MUST BE IN RECONCILED)
        $candReconciled = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'out',
            'amount' => 15000.00,
            'reference' => 'ALREADY-FINAL-15K',
            'source' => 'imported',
            'status' => 'matched',
            'is_finalized' => true,
            'matched_amount' => 15000.00,
            'imported_by' => $this->admin->id,
        ]);

        $candidatesData = $this->purchaserFinanceService->candidateStatementsForCredit($credit);

        $this->assertEquals(2, $candidatesData['counts']['pending']);
        $this->assertEquals(1, $candidatesData['counts']['reconciled']);
        $this->assertEquals(1, $candidatesData['counts']['exact_date_pending']);
        $this->assertEquals(0, $candidatesData['counts']['exact_date_reconciled']);
        $this->assertEquals($cand1->id, $candidatesData['pending'][0]['id']);
        $this->assertEquals('exact', $candidatesData['pending'][0]['date_match']);
        $this->assertEquals('EXACT DATE', $candidatesData['pending'][0]['date_badge_text']);
        $this->assertEquals($candOld->id, $candidatesData['pending'][1]['id']);
        $this->assertEquals('other', $candidatesData['pending'][1]['date_match']);
        $this->assertEquals($candReconciled->id, $candidatesData['reconciled'][0]['id']);

        // Check JSON candidate endpoint
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchasers.funding.candidates', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]));
        $response->assertOk();
        $response->assertJsonPath('counts.pending', 2);
        $response->assertJsonPath('counts.reconciled', 1);
        $response->assertJsonPath('counts.exact_date_pending', 1);
        $response->assertJsonPath('counts.exact_date_reconciled', 0);
        $response->assertJsonPath('pending.0.id', $cand1->id);
        $response->assertJsonPath('pending.0.date_match', 'exact');
        $response->assertJsonPath('pending.0.date_badge_text', 'EXACT DATE');
        $response->assertJsonPath('reconciled.0.id', $candReconciled->id);
    }

    public function test_candidate_date_classification_and_sorting_priorities(): void
    {
        $fundingDate = '2026-08-27';
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 100000.00,
            'description' => 'Target 100k funding',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => $fundingDate,
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit);

        $initialBalance = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $initialJournalCount = JournalEntry::query()->count();
        $initialCreditCount = PurchaserCredit::query()->count();

        // 1. Exact Date Pending (27 Aug 2026) -> should be #1
        $exactPending = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-27',
            'direction' => 'out',
            'amount' => 100000.00,
            'reference' => 'EXACT-PENDING-27AUG',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // 2. 1 Day Away Future (28 Aug 2026)
        $plus1Pending = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-28',
            'direction' => 'out',
            'amount' => 100000.00,
            'reference' => 'PLUS1-28AUG',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // 3. 1 Day Away Past (26 Aug 2026)
        $minus1Pending = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-26',
            'direction' => 'out',
            'amount' => 100000.00,
            'reference' => 'MINUS1-26AUG',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // 4. 7 Days Away Past (20 Aug 2026)
        $minus7Pending = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'out',
            'amount' => 100000.00,
            'reference' => 'MINUS7-20AUG',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // Exclusions:
        // 5. Wrong bank
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->cashAccount->id,
            'transaction_date' => '2026-08-27',
            'direction' => 'out',
            'amount' => 100000.00,
            'reference' => 'WRONG-BANK',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // 6. Wrong amount
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-27',
            'direction' => 'out',
            'amount' => 95000.00,
            'reference' => 'WRONG-AMOUNT',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // 7. Wrong direction (IN)
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-27',
            'direction' => 'in',
            'amount' => 100000.00,
            'reference' => 'WRONG-DIRECTION-IN',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // Reconciled section candidates:
        // 8. Reconciled 2 days away (25 Aug 2026)
        $reconciled2Days = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-25',
            'direction' => 'out',
            'amount' => 100000.00,
            'reference' => 'RECONCILED-2DAYS',
            'source' => 'imported',
            'status' => 'matched',
            'is_finalized' => true,
            'matched_amount' => 100000.00,
            'imported_by' => $this->admin->id,
        ]);

        // 9. Reconciled Exact Date (27 Aug 2026) -> should be #1 in Reconciled
        $reconciledExact = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-27',
            'direction' => 'out',
            'amount' => 100000.00,
            'reference' => 'RECONCILED-EXACT-27AUG',
            'source' => 'imported',
            'status' => 'matched',
            'is_finalized' => true,
            'matched_amount' => 100000.00,
            'imported_by' => $this->admin->id,
        ]);

        $data = $this->purchaserFinanceService->candidateStatementsForCredit($credit);

        // Verification of Counts
        $this->assertEquals(4, $data['counts']['pending']);
        $this->assertEquals(2, $data['counts']['reconciled']);
        $this->assertEquals(1, $data['counts']['exact_date_pending']);
        $this->assertEquals(1, $data['counts']['exact_date_reconciled']);

        // Verification of Pending Sort Order
        // 1st: Exact Date (diff = 0)
        $this->assertEquals($exactPending->id, $data['pending'][0]['id']);
        $this->assertEquals('exact', $data['pending'][0]['date_match']);
        $this->assertEquals('EXACT DATE', $data['pending'][0]['date_badge_text']);
        $this->assertEquals(0, $data['pending'][0]['date_difference_days']);

        // 2nd: 1 day away (28 Aug has newer date than 26 Aug, so 28 Aug first)
        $this->assertEquals($plus1Pending->id, $data['pending'][1]['id']);
        $this->assertEquals('other', $data['pending'][1]['date_match']);
        $this->assertEquals('1 DAY AWAY', $data['pending'][1]['date_badge_text']);
        $this->assertEquals(1, $data['pending'][1]['date_difference_days']);

        // 3rd: 1 day away (26 Aug)
        $this->assertEquals($minus1Pending->id, $data['pending'][2]['id']);
        $this->assertEquals('other', $data['pending'][2]['date_match']);
        $this->assertEquals('1 DAY AWAY', $data['pending'][2]['date_badge_text']);
        $this->assertEquals(1, $data['pending'][2]['date_difference_days']);

        // 4th: 7 days away (20 Aug)
        $this->assertEquals($minus7Pending->id, $data['pending'][3]['id']);
        $this->assertEquals('other', $data['pending'][3]['date_match']);
        $this->assertEquals('7 DAYS AWAY', $data['pending'][3]['date_badge_text']);
        $this->assertEquals(7, $data['pending'][3]['date_difference_days']);

        // Verification of Reconciled Sort Order
        // 1st: Exact Date (27 Aug)
        $this->assertEquals($reconciledExact->id, $data['reconciled'][0]['id']);
        $this->assertEquals('exact', $data['reconciled'][0]['date_match']);
        $this->assertEquals('EXACT DATE', $data['reconciled'][0]['date_badge_text']);

        // 2nd: 2 days away (25 Aug)
        $this->assertEquals($reconciled2Days->id, $data['reconciled'][1]['id']);
        $this->assertEquals('other', $data['reconciled'][1]['date_match']);
        $this->assertEquals('2 DAYS AWAY', $data['reconciled'][1]['date_badge_text']);

        // API Endpoint validation
        $apiRes = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchasers.funding.candidates', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]));
        $apiRes->assertOk();
        $apiRes->assertJsonPath('counts.exact_date_pending', 1);
        $apiRes->assertJsonPath('counts.exact_date_reconciled', 1);
        $apiRes->assertJsonPath('pending.0.date_badge_text', 'EXACT DATE');
        $apiRes->assertJsonPath('reconciled.0.date_badge_text', 'EXACT DATE');

        // Verify No Automatic Reconciliation / No Side Effects
        $exactPending->refresh();
        $this->assertFalse($exactPending->is_finalized);
        $this->assertEquals('unmatched', $exactPending->status);

        $afterBalance = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $this->assertEquals($initialBalance['remaining_advance'], $afterBalance['remaining_advance']);
        $this->assertEquals($initialCreditCount, PurchaserCredit::query()->count());
        $this->assertEquals($initialJournalCount, JournalEntry::query()->count());
    }

    public function test_10_to_15_match_statement_flow_and_replace_match_atomicity(): void
    {
        // 1. Create Funding A
        $creditA = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 30000.00,
            'description' => 'Funding A',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-26',
            'created_by' => $this->admin->id,
        ]);
        $jeA = app(JournalService::class)->recordPurchaserCredit($creditA);

        // 2. Create Funding B
        $creditB = PurchaserCredit::query()->create([
            'purchaser_id' => $this->otherPurchaser->id,
            'type' => 'in',
            'amount' => 30000.00,
            'description' => 'Funding B',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-26',
            'created_by' => $this->admin->id,
        ]);
        $jeB = app(JournalService::class)->recordPurchaserCredit($creditB);

        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-26',
            'direction' => 'out',
            'amount' => 30000.00,
            'reference' => 'STMT-30K-TXN',
            'narration' => 'RTGS TO PURCHASER',
            'source' => 'imported',
            'import_file_name' => 'hdfc_august.csv',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        $balanceBeforeA = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $balanceBeforeB = $this->purchaserFinanceService->summaryFor($this->otherPurchaser->id);

        // Match statement to Funding A
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.match-statement', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $creditA->id,
        ]), [
            'statement_entry_id' => $statement->id,
        ])->assertRedirect();

        $statement->refresh();
        $this->assertTrue($statement->is_finalized);
        $this->assertEquals($creditA->id, $statement->source_id);

        $txA = $this->purchaserFinanceService->transactionsFor($this->purchaser->id, '2026-08-01', '2026-08-31');
        $this->assertEquals('matched', collect($txA->items())->firstWhere('id', $creditA->id)->status);

        // Count check before replace match
        $creditCountBefore = PurchaserCredit::query()->count();
        $journalCountBefore = JournalEntry::query()->count();

        // 3. Replace Match: Switch statement to Funding B
        $replaceRes = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.replace-match', [
            'purchaser' => $this->otherPurchaser->public_uuid,
            'credit' => $creditB->id,
        ]), [
            'statement_entry_id' => $statement->id,
        ]);
        $replaceRes->assertRedirect();

        $statement->refresh();
        $this->assertTrue($statement->is_finalized);
        $this->assertEquals($creditB->id, $statement->source_id);
        $this->assertEquals($jeB->id, $statement->journal_entry_id);

        // 4. Funding A returns to UNMATCHED
        $txAAfter = $this->purchaserFinanceService->transactionsFor($this->purchaser->id, '2026-08-01', '2026-08-31');
        $this->assertEquals('unmatched', collect($txAAfter->items())->firstWhere('id', $creditA->id)->status);

        // 5. Funding B becomes MATCHED
        $txBAfter = $this->purchaserFinanceService->transactionsFor($this->otherPurchaser->id, '2026-08-01', '2026-08-31');
        $this->assertEquals('matched', collect($txBAfter->items())->firstWhere('id', $creditB->id)->status);

        // 6. Balance check: zero balance changes
        $balanceAfterA = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $balanceAfterB = $this->purchaserFinanceService->summaryFor($this->otherPurchaser->id);
        $this->assertEquals($balanceBeforeA['remaining_advance'], $balanceAfterA['remaining_advance']);
        $this->assertEquals($balanceBeforeB['remaining_advance'], $balanceAfterB['remaining_advance']);

        // 7. Zero new records created
        $this->assertEquals($creditCountBefore, PurchaserCredit::query()->count());
        $this->assertEquals($journalCountBefore, JournalEntry::query()->count());
    }

    public function test_16_and_17_manual_cash_and_manual_statement_counterpart_creation(): void
    {
        $creditCash = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 10000.00,
            'description' => 'Vault cash given',
            'payment_source' => 'Cash',
            'business_date' => '2026-08-27',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($creditCash);

        // Match with manual cash counterpart
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.match-manual', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $creditCash->id,
        ]), [
            'amount' => 10000.00,
            'business_date' => '2026-08-27',
            'company_account_id' => $this->cashAccount->id,
            'reference' => 'PETTY-001',
            'description' => 'Cash handed to purchaser',
        ])->assertRedirect();

        $cashTransactions = $this->purchaserFinanceService->transactionsFor($this->purchaser->id, '2026-08-01', '2026-08-31');
        $cashRow = collect($cashTransactions->items())->firstWhere('id', $creditCash->id);
        $this->assertEquals('manual_cash', $cashRow->status);

        // Match with manual bank counterpart
        $creditBank = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 12000.00,
            'description' => 'Direct transfer',
            'payment_source' => 'Bank',
            'business_date' => '2026-08-27',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($creditBank);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.match-manual', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $creditBank->id,
        ]), [
            'amount' => 12000.00,
            'business_date' => '2026-08-27',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'MANUAL-NEFT',
            'description' => 'Bank transfer manual note',
        ])->assertRedirect();

        $bankTransactions = $this->purchaserFinanceService->transactionsFor($this->purchaser->id, '2026-08-01', '2026-08-31');
        $bankRow = collect($bankTransactions->items())->firstWhere('id', $creditBank->id);
        $this->assertEquals('manual_statement', $bankRow->status);
    }

    public function test_18_to_21_trace_endpoint_unmatch_imported_and_block_unmatch_manual(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 45000.00,
            'description' => 'Pepper advance',
            'payment_source' => 'Bank',
            'business_date' => '2026-08-27',
            'created_by' => $this->admin->id,
        ]);
        $je = app(JournalService::class)->recordPurchaserCredit($credit);

        $importedStmt = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-27',
            'direction' => 'out',
            'amount' => 45000.00,
            'reference' => 'IMP-45K',
            'narration' => 'RTGS Outward',
            'source' => 'imported',
            'import_file_name' => 'bank_stmt.csv',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        $this->reconciliationService->reconcileStatementJournal($importedStmt, $je, 45000.00, $this->admin->id);

        // 18. Trace endpoint check
        $traceRes = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchasers.funding.trace', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]));
        $traceRes->assertOk();
        $traceRes->assertJsonPath('reconciled', true);
        $traceRes->assertJsonPath('statement.source_classification', 'Imported Statement');
        $traceRes->assertJsonPath('statement.is_imported', true);
        $traceRes->assertJsonPath('can_unmatch', true);

        // 19 & 20. Unmatch imported statement
        $unmatchRes = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.unmatch', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]));
        $unmatchRes->assertRedirect();

        $importedStmt->refresh();
        $this->assertFalse($importedStmt->is_finalized);
        $this->assertEquals('unmatched', $importedStmt->status);
        $this->assertEquals(0, (float) $importedStmt->matched_amount);
        $this->assertNull($importedStmt->journal_entry_id);

        // Funding transaction returns to UNMATCHED
        $tx = $this->purchaserFinanceService->transactionsFor($this->purchaser->id, '2026-08-01', '2026-08-31');
        $this->assertEquals('unmatched', collect($tx->items())->firstWhere('id', $credit->id)->status);

        // 21. Block unmatching manual counterpart
        $creditManual = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 8000.00,
            'description' => 'Manual test',
            'payment_source' => 'Cash',
            'business_date' => '2026-08-27',
            'created_by' => $this->admin->id,
        ]);
        $jeManual = app(JournalService::class)->recordPurchaserCredit($creditManual);

        $manualStmt = $this->reconciliationService->createStatementEntry([
            'company_account_id' => $this->cashAccount->id,
            'transaction_date' => '2026-08-27',
            'direction' => 'out',
            'amount' => 8000.00,
            'reference' => 'MAN-8K',
            'narration' => 'Cash ledger entry',
            'source' => 'manual',
            'source_type' => PurchaserCredit::class,
            'source_id' => $creditManual->id,
        ], $this->admin->id);
        $this->reconciliationService->reconcileStatementJournal($manualStmt, $jeManual, 8000.00, $this->admin->id);

        $blockedUnmatchRes = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.unmatch', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $creditManual->id,
        ]));
        $blockedUnmatchRes->assertSessionHasErrors(['statement_entry_id']);
    }

    public function test_22_to_24_authorization_and_cross_purchaser_protection(): void
    {
        $creditOther = PurchaserCredit::query()->create([
            'purchaser_id' => $this->otherPurchaser->id,
            'type' => 'in',
            'amount' => 5000.00,
            'description' => 'Other purchaser funding',
            'payment_source' => 'Bank',
            'business_date' => '2026-08-27',
            'created_by' => $this->admin->id,
        ]);

        // Cross-purchaser URL tampering must return 404
        $res = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchasers.funding.candidates', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $creditOther->id,
        ]));
        $res->assertNotFound();

        // Non-admin user cannot access funding candidates
        $regularUser = User::factory()->create();
        $this->actingAs($regularUser)->getJson(route('admin.cashbook.finance.purchasers.funding.candidates', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $creditOther->id,
        ]))->assertForbidden();
    }

    public function test_25_replace_match_rolls_back_if_validation_or_transaction_fails(): void
    {
        $creditA = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 50000.00,
            'description' => 'Funding A',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-27',
            'created_by' => $this->admin->id,
        ]);
        $jeA = app(JournalService::class)->recordPurchaserCredit($creditA);

        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-27',
            'direction' => 'out',
            'amount' => 50000.00,
            'reference' => 'STMT-ROLLBACK-TEST',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // First match
        $this->reconciliationService->reconcileStatementJournal($statement, $jeA, 50000.00, $this->admin->id);

        $creditB = PurchaserCredit::query()->create([
            'purchaser_id' => $this->otherPurchaser->id,
            'type' => 'in',
            'amount' => 50000.00,
            'description' => 'Funding B',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-27',
            'created_by' => $this->admin->id,
        ]);
        $jeB = app(JournalService::class)->recordPurchaserCredit($creditB);

        // Attempt replace match with invalid statement entry id
        $res = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.replace-match', [
            'purchaser' => $this->otherPurchaser->public_uuid,
            'credit' => $creditB->id,
        ]), [
            'statement_entry_id' => 99999999, // non-existent
        ]);
        $res->assertSessionHasErrors(['statement_entry_id']);

        // Verify state remains strictly untouched
        $statement->refresh();
        $this->assertTrue($statement->is_finalized);
        $this->assertEquals($creditA->id, $statement->source_id);
        $this->assertEquals($jeA->id, $statement->journal_entry_id);
    }

    public function test_edit_purchaser_funding_updates_credit_and_journal_entry(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 20000.00,
            'description' => 'Original note',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-ORIGINAL',
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);
        $je = app(JournalService::class)->recordPurchaserCredit($credit);

        // Verify edit button is visible on purchaser finance page
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.purchasers.show', [
                'purchaser' => $this->purchaser->public_uuid,
                'period' => 'month',
                'tab' => 'finance',
            ]))
            ->assertOk()
            ->assertSee('Edit Purchaser Funding')
            ->assertSee('openEditFundingModal', false);

        // Edit funding
        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchasers.funding.update', [
                'purchaser' => $this->purchaser->public_uuid,
                'credit' => $credit->id,
            ]), [
                'amount' => 35000.00,
                'business_date' => '2026-08-22',
                'payment_source' => 'Cash',
                'company_account_id' => $this->cashAccount->id,
                'reference' => 'CASH-EDITED-35K',
                'description' => 'Updated funding note',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $credit->refresh();
        $this->assertEquals(35000.00, (float) $credit->amount);
        $this->assertEquals('2026-08-22', $credit->business_date->format('Y-m-d'));
        $this->assertEquals('Cash', $credit->payment_source);
        $this->assertEquals($this->cashAccount->id, $credit->company_account_id);
        $this->assertEquals('CASH-EDITED-35K', $credit->reference);
        $this->assertEquals('Updated funding note', $credit->description);

        $je->refresh();
        $this->assertEquals(35000.00, (float) $je->primary_amount);
        $this->assertEquals('2026-08-22', $je->entry_date->format('Y-m-d'));
        $this->assertEquals('CASH-EDITED-35K', $je->reference);
        $this->assertEquals(35000.00, (float) $je->transactions()->where('type', 'debit')->value('amount'));
        $this->assertEquals(35000.00, (float) $je->transactions()->where('type', 'credit')->value('amount'));
    }

    public function test_edit_purchaser_funding_unlinks_statement_if_amount_modified_while_matched(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 20000.00,
            'description' => 'Matched funding',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);
        $je = app(JournalService::class)->recordPurchaserCredit($credit);

        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'out',
            'amount' => 20000.00,
            'reference' => 'STMT-20K',
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $this->reconciliationService->reconcileStatementJournal($statement, $je, 20000.00, $this->admin->id);
        $statement->refresh();
        $this->assertTrue((bool) $statement->is_finalized);

        // Edit amount from 20000 to 28000
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchasers.funding.update', [
                'purchaser' => $this->purchaser->public_uuid,
                'credit' => $credit->id,
            ]), [
                'amount' => 28000.00,
                'business_date' => '2026-08-20',
                'payment_source' => 'Bank',
                'company_account_id' => $this->bankAccount->id,
                'reference' => 'STMT-20K',
                'description' => 'Matched funding modified amount',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $statement->refresh();
        $this->assertFalse((bool) $statement->is_finalized);
        $this->assertEquals('unmatched', $statement->status);
        $this->assertNull($statement->journal_entry_id);
    }
}
