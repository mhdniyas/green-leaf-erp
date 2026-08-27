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

    public function test_3_to_9_candidate_statements_direction_ranking_and_filters(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 15000.00,
            'description' => 'Target funding',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'IMPS9988',
            'business_date' => '2026-08-25',
            'created_by' => $this->admin->id,
        ]);

        // Candidate 1: Exact amount OUT, same account, matching ref (Highest score)
        $cand1 = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-25',
            'direction' => 'out',
            'amount' => 15000.00,
            'reference' => 'IMPS9988',
            'narration' => 'Transfer to purchaser IMPS9988',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // Candidate 2: Different amount OUT (Lower score)
        $cand2 = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-24',
            'direction' => 'out',
            'amount' => 20000.00,
            'reference' => 'OTHER-REF',
            'narration' => 'Different amount',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // Non-candidate 3: IN direction deposit of 15,000 (MUST NOT BE SUGGESTED)
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-25',
            'direction' => 'in',
            'amount' => 15000.00,
            'reference' => 'IMPS9988-IN',
            'narration' => 'Customer deposit',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // Non-candidate 4: Already finalized statement (MUST NOT BE SUGGESTED)
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-25',
            'direction' => 'out',
            'amount' => 15000.00,
            'reference' => 'ALREADY-FINAL',
            'source' => 'imported',
            'status' => 'reconciled',
            'is_finalized' => true,
            'matched_amount' => 15000.00,
            'imported_by' => $this->admin->id,
        ]);

        $candidates = $this->purchaserFinanceService->candidateStatementsForCredit($credit);

        $this->assertCount(2, $candidates);
        $this->assertEquals($cand1->id, $candidates[0]['statement']->id);
        $this->assertTrue($candidates[0]['is_exact_amount']);
        $this->assertGreaterThan($candidates[1]['score'], $candidates[0]['score']);

        // Check JSON candidate endpoint
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchasers.funding.candidates', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]));
        $response->assertOk();
        $response->assertJsonPath('candidates.0.id', $cand1->id);
    }

    public function test_10_to_15_match_statement_flow_double_match_protection_and_balance_preservation(): void
    {
        $initialBalance = $this->purchaserFinanceService->summaryFor($this->purchaser->id);

        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 30000.00,
            'description' => 'Advance for ginger batch',
            'payment_source' => 'Bank',
            'business_date' => '2026-08-26',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit);

        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-26',
            'direction' => 'out',
            'amount' => 30000.00,
            'reference' => 'STMT-30K-001',
            'narration' => 'RTGS TO PURCHASER ALPHA',
            'source' => 'imported',
            'import_file_name' => 'hdfc_august.csv',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        $balanceBeforeMatch = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $this->assertEquals(30000.00, $balanceBeforeMatch['remaining_advance']);

        // Perform Match
        $matchResponse = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.match-statement', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'statement_entry_id' => $statement->id,
        ]);
        $matchResponse->assertRedirect();

        $statement->refresh();
        $this->assertTrue($statement->is_finalized);
        $this->assertEquals('reconciled', $statement->status);
        $this->assertSame(PurchaserCredit::class, $statement->source_type);
        $this->assertEquals($credit->id, $statement->source_id);
        $this->assertEquals($this->admin->id, $statement->reconciled_by);

        // Balance preservation check
        $balanceAfterMatch = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $this->assertEquals($balanceBeforeMatch['remaining_advance'], $balanceAfterMatch['remaining_advance']);
        $this->assertEquals($balanceBeforeMatch['cash_given'], $balanceAfterMatch['cash_given']);

        // Double match protection: matching again returns session errors
        $doubleMatchRes = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.match-statement', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'statement_entry_id' => $statement->id,
        ]);
        $doubleMatchRes->assertSessionHasErrors(['statement_entry_id']);
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
}
