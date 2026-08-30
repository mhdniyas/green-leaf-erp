<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\JournalService;
use App\Services\Finance\PurchaserFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserCashMovementLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private CompanyAccount $bankAccount;

    private CompanyAccount $cashAccount;

    private PurchaserFinanceService $purchaserFinanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->purchaser = User::factory()->create(['name' => 'Purchaser Raman']);
        $this->purchaser->assignRole('purchaser');

        $this->bankAccount = CompanyAccount::query()->create([
            'name' => 'Kotak Bank Main',
            'account_type' => 'bank',
            'bank_name' => 'Kotak Mahindra Bank',
            'enabled' => true,
        ]);

        $this->cashAccount = CompanyAccount::query()->create([
            'name' => 'Company Cash Box',
            'account_type' => 'cash',
            'enabled' => true,
        ]);

        Account::query()->firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1200'], ['name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1300'], ['name' => 'Purchaser Advances', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]);

        $this->purchaserFinanceService = app(PurchaserFinanceService::class);
    }

    public function test_cash_position_summary_computes_given_returned_net_uses_and_expected_cash(): void
    {
        // 1. Company gives ₹50,000 to purchaser
        PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 50000.00,
            'description' => 'Market funding',
            'payment_source' => 'Bank',
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);

        // 2. Purchaser returns ₹10,000 back to company
        PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 10000.00,
            'description' => 'Unused cash returned',
            'payment_source' => 'Bank',
            'business_date' => '2026-08-22',
            'created_by' => $this->admin->id,
        ]);

        // 3. Purchaser spends ₹25,000 on purchase invoice
        $supplier = Supplier::factory()->create();
        $invoice = PurchaseInvoice::factory()->for($supplier)->create([
            'invoice_number' => 'INV-CASH-001',
            'amount' => 25000.00,
            'discount_amount' => 0.00,
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
            'status' => 'approved',
            'purchaser_submitted_by' => $this->purchaser->id,
        ]);

        PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'purchase_invoice_id' => $invoice->id,
            'type' => 'out',
            'amount' => 25000.00,
            'description' => 'Cash purchase bill payment',
            'payment_source' => 'Cash',
            'business_date' => '2026-08-23',
            'created_by' => $this->purchaser->id,
        ]);

        $summary = $this->purchaserFinanceService->summaryFor((int) $this->purchaser->id);

        $this->assertEquals(50000.00, $summary['cash_given']);
        $this->assertEquals(10000.00, $summary['cash_returned']);
        $this->assertEquals(40000.00, $summary['net_funding']);
        $this->assertEquals(25000.00, $summary['cash_used_invoices']);
        $this->assertEquals(35000.00, $summary['cash_used']);
        $this->assertEquals(15000.00, $summary['remaining_advance']);
    }

    public function test_record_purchaser_funding_given_creates_credit_and_debit_advance_credit_cash_journal(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'direction' => 'company_to_purchaser',
            'amount' => 30000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-GIVEN-30K',
            'description' => 'Morning market cash',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $credit = PurchaserCredit::query()->where('purchaser_id', $this->purchaser->id)->firstOrFail();
        $this->assertEquals('in', $credit->type);
        $this->assertEquals(30000.00, (float) $credit->amount);

        $journal = JournalEntry::query()
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $credit->id)
            ->with('transactions.account')
            ->firstOrFail();

        $this->assertSame('purchaser_funding', $journal->source_event);
        $debitLine = $journal->transactions->firstWhere('type', 'debit');
        $creditLine = $journal->transactions->firstWhere('type', 'credit');

        $this->assertSame('1300', $debitLine->account->code); // Purchaser Advances
        $this->assertEquals(30000.00, (float) $debitLine->amount);
        $this->assertSame('1020', $creditLine->account->code); // Bank Account
        $this->assertEquals(30000.00, (float) $creditLine->amount);
    }

    public function test_record_purchaser_return_creates_credit_out_and_debit_cash_credit_advance_journal(): void
    {
        // First give funding so purchaser has advance
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'direction' => 'company_to_purchaser',
            'amount' => 40000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
        ]);

        // Record return of ₹12,000 from purchaser to company
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'direction' => 'purchaser_to_company',
            'amount' => 12000.00,
            'business_date' => '2026-08-26',
            'payment_source' => 'Cash',
            'company_account_id' => $this->cashAccount->id,
            'reference' => 'RET-CASH-12K',
            'description' => 'Returned surplus cash',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $returnCredit = PurchaserCredit::query()
            ->where('purchaser_id', $this->purchaser->id)
            ->where('type', 'out')
            ->whereNull('purchase_invoice_id')
            ->firstOrFail();

        $this->assertEquals(12000.00, (float) $returnCredit->amount);

        $journal = JournalEntry::query()
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $returnCredit->id)
            ->with('transactions.account')
            ->firstOrFail();

        $this->assertSame('purchaser_funding_return', $journal->source_event);
        $debitLine = $journal->transactions->firstWhere('type', 'debit');
        $creditLine = $journal->transactions->firstWhere('type', 'credit');

        $this->assertSame('1010', $debitLine->account->code); // Cash on Hand (debit to receive)
        $this->assertEquals(12000.00, (float) $debitLine->amount);
        $this->assertSame('1300', $creditLine->account->code); // Purchaser Advances (credit to reduce)
        $this->assertEquals(12000.00, (float) $creditLine->amount);

        $summary = $this->purchaserFinanceService->summaryFor((int) $this->purchaser->id);
        $this->assertEquals(28000.00, $summary['remaining_advance']);
    }

    public function test_purchaser_return_exceeding_remaining_advance_fails_validation(): void
    {
        // Purchaser only has ₹5,000 advance
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'direction' => 'company_to_purchaser',
            'amount' => 5000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Cash',
        ]);

        // Attempting to return ₹10,000 must fail validation
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'direction' => 'purchaser_to_company',
            'amount' => 10000.00,
            'business_date' => '2026-08-26',
            'payment_source' => 'Cash',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertEquals(0, PurchaserCredit::query()->where('purchaser_id', $this->purchaser->id)->where('type', 'out')->count());
    }

    public function test_update_purchaser_return_updates_record_and_journals(): void
    {
        $giveCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 20000.00,
            'payment_source' => 'Bank',
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($giveCredit);

        $returnCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 5000.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-21',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($returnCredit);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.update', [$this->purchaser->public_uuid, $returnCredit->id]), [
            'amount' => 7000.00,
            'business_date' => '2026-08-22',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UPD-REF-7K',
            'description' => 'Updated return notes',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $returnCredit->refresh();
        $this->assertEquals(7000.00, (float) $returnCredit->amount);
        $this->assertSame('UPD-REF-7K', $returnCredit->reference);

        $journal = JournalEntry::query()
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $returnCredit->id)
            ->firstOrFail();

        $this->assertEquals(7000.00, (float) $journal->total_debit);
    }

    public function test_delete_purchaser_return_removes_record_and_journals_safely(): void
    {
        $giveCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 20000.00,
            'payment_source' => 'Bank',
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($giveCredit);

        $returnCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 5000.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-21',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($returnCredit);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.delete', [$this->purchaser->public_uuid, $returnCredit->id]), [
            'reason' => 'duplicate_entry',
            'notes' => 'Deleting duplicate return',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('purchaser_credits', ['id' => $returnCredit->id]);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => PurchaserCredit::class,
            'source_id' => $returnCredit->id,
        ]);
    }

    public function test_running_balance_is_computed_accurately_across_sequential_movements(): void
    {
        // 1. Day 1: Given ₹10,000 -> Running balance = ₹10,000
        $c1 = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 10000.00,
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);

        // 2. Day 2: Return ₹3,000 -> Running balance = ₹7,000
        $c2 = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 3000.00,
            'business_date' => '2026-08-21',
            'created_by' => $this->admin->id,
        ]);

        // 3. Day 3: Given ₹15,000 -> Running balance = ₹22,000
        $c3 = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 15000.00,
            'business_date' => '2026-08-22',
            'created_by' => $this->admin->id,
        ]);

        // 4. Day 4: Spend ₹8,000 -> Running balance = ₹14,000
        $supplier = Supplier::factory()->create();
        $invoice = PurchaseInvoice::factory()->for($supplier)->create(['amount' => 8000.00]);
        $c4 = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'purchase_invoice_id' => $invoice->id,
            'type' => 'out',
            'amount' => 8000.00,
            'business_date' => '2026-08-23',
            'created_by' => $this->admin->id,
        ]);

        $paginator = $this->purchaserFinanceService->transactionsFor((int) $this->purchaser->id, '2026-08-01', '2026-08-31');
        $items = collect($paginator->items());

        $row1 = $items->firstWhere('id', $c1->id);
        $row2 = $items->firstWhere('id', $c2->id);
        $row3 = $items->firstWhere('id', $c3->id);
        $row4 = $items->firstWhere('id', $c4->id);

        $this->assertEquals(10000.00, (float) $row1->running_balance);
        $this->assertEquals(7000.00, (float) $row2->running_balance);
        $this->assertEquals(22000.00, (float) $row3->running_balance);
        $this->assertEquals(14000.00, (float) $row4->running_balance);

        $this->assertSame('Company → Purchaser', $row1->direction_label);
        $this->assertSame('Purchaser → Company', $row2->direction_label);
        $this->assertSame('Cash Purchase Spend', $row4->direction_label);
        $this->assertSame('Admin User', $row1->created_by_name);
    }

    public function test_purchaser_finance_page_renders_cash_position_cards_and_movement_ledger(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 12500.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-PAGE-TEST',
            'business_date' => '2026-08-25',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit);

        $financeResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'period' => 'month',
            'tab' => 'finance',
        ]));

        $financeResponse->assertOk();
        $financeResponse->assertSee('Purchaser Cash Position');
        $financeResponse->assertSee('Expected Cash With Purchaser');
        $financeResponse->assertSee('View Funding &amp; Cash Movement', false);
        $financeResponse->assertSee('12,500.00');

        $fundingResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'period' => 'month',
            'tab' => 'funding',
        ]));

        $fundingResponse->assertOk();
        $fundingResponse->assertSee('Purchaser Funding');
        $fundingResponse->assertSee('Company → Purchaser Given');
        $fundingResponse->assertSee('Purchaser → Company Returned');
        $fundingResponse->assertSee('Net Company Funding');
        $fundingResponse->assertSee('Purchases / Uses From Funding');
        $fundingResponse->assertSee('Expected Cash With Purchaser');
        $fundingResponse->assertSee('Company ↔ Purchaser Cash Movement');
        $fundingResponse->assertSee('12,500.00');
        $fundingResponse->assertSee('UTR-PAGE-TEST');
        $fundingResponse->assertSee('openMovementDetailsModal', false);
        $fundingResponse->assertSee('openFundingSplitModal', false);
    }

    public function test_candidate_statements_for_return_finds_incoming_statement_entry(): void
    {
        $returnCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 6500.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-24',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($returnCredit);

        // Statement with incoming credit to bank account
        $stmtIn = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-24',
            'direction' => 'in',
            'amount' => 6500.00,
            'reference' => 'NEFT-RET-6500',
            'narration' => 'Purchaser refund deposit',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        $candidates = $this->purchaserFinanceService->candidateStatementsForCredit($returnCredit);

        $this->assertCount(1, $candidates['pending']);
        $this->assertEquals($stmtIn->id, $candidates['pending'][0]['id']);
        $this->assertEquals(6500.00, (float) $candidates['pending'][0]['amount']);
    }

    public function test_store_purchaser_return_with_statement_entry_id_reconciles_atomically(): void
    {
        // First give funding
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'direction' => 'company_to_purchaser',
            'amount' => 30000.00,
            'business_date' => '2026-08-20',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
        ]);

        $stmtIn = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-22',
            'direction' => 'in',
            'amount' => 8000.00,
            'reference' => 'RET-STMT-8K',
            'source' => 'imported',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'is_finalized' => false,
            'imported_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'direction' => 'purchaser_to_company',
            'amount' => 8000.00,
            'business_date' => '2026-08-22',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'statement_entry_id' => $stmtIn->id,
            'reference' => 'RET-STMT-8K',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $stmtIn->refresh();
        $this->assertSame('reconciled', $stmtIn->status);
        $this->assertEquals(8000.00, (float) $stmtIn->matched_amount);
        $this->assertTrue((bool) $stmtIn->is_finalized);
    }

    public function test_manual_company_to_purchaser_funding_row_shows_edit_action(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 15000.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-EDITABLE-01',
            'business_date' => '2026-08-25',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'period' => 'month',
            'tab' => 'funding',
        ]));

        $response->assertOk();
        $response->assertSee('openEditFundingModal('.$credit->id, false);
        $response->assertSee('openDeleteFundingModal('.$credit->id, false);
    }

    public function test_manual_purchaser_to_company_return_row_shows_edit_action(): void
    {
        // First funding
        $funding = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 40000.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($funding);

        // Return
        $return = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 10000.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'RET-EDITABLE-01',
            'business_date' => '2026-08-22',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($return);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'period' => 'month',
            'tab' => 'funding',
        ]));

        $response->assertOk();
        $response->assertSee('openEditFundingModal('.$return->id, false);
        $response->assertSee('openDeleteFundingModal('.$return->id, false);
    }

    public function test_cash_purchase_spend_row_does_not_expose_manual_funding_edit(): void
    {
        $supplier = Supplier::factory()->create();
        $invoice = PurchaseInvoice::factory()->for($supplier)->create([
            'invoice_number' => 'INV-SPEND-999',
            'amount' => 5000.00,
            'discount_amount' => 0.00,
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
            'status' => 'approved',
            'purchaser_submitted_by' => $this->purchaser->id,
        ]);

        $spendCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'purchase_invoice_id' => $invoice->id,
            'type' => 'out',
            'amount' => 5000.00,
            'description' => 'Cash purchase bill payment',
            'payment_source' => 'Cash',
            'business_date' => '2026-08-25',
            'created_by' => $this->purchaser->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'period' => 'month',
            'tab' => 'funding',
        ]));

        $response->assertOk();
        $response->assertSee('INV-SPEND-999');
        $response->assertDontSee('openEditFundingModal('.$spendCredit->id, false);
        $response->assertDontSee('openDeleteFundingModal('.$spendCredit->id, false);
    }

    public function test_changing_amount_updates_existing_row_without_duplicate_or_extra_journal(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 100000.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-INITIAL-100K',
            'description' => 'Initial funding',
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit);

        $initialCreditCount = PurchaserCredit::query()->count();
        $initialJournalCount = JournalEntry::query()->count();

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.update', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'amount' => 10000.00,
            'business_date' => '2026-08-20',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-CORRECTED-10K',
            'description' => 'Corrected funding note',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify exact single row updated - no duplicate
        $this->assertEquals($initialCreditCount, PurchaserCredit::query()->count());
        $this->assertEquals($initialJournalCount, JournalEntry::query()->count());

        $credit->refresh();
        $this->assertEquals(10000.00, (float) $credit->amount);
        $this->assertSame('UTR-CORRECTED-10K', $credit->reference);
        $this->assertSame('Corrected funding note', $credit->description);

        $journal = JournalEntry::query()->where('source_type', PurchaserCredit::class)->where('source_id', $credit->id)->firstOrFail();
        $this->assertEquals(10000.00, (float) $journal->primary_amount);
    }

    public function test_changing_date_reorders_ledger_and_recalculates_running_balances(): void
    {
        // Day 1: Funding 20,000
        $credit1 = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 20000.00,
            'business_date' => '2026-08-10',
            'payment_source' => 'Cash',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit1);

        // Day 3: Funding 30,000
        $credit2 = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 30000.00,
            'business_date' => '2026-08-15',
            'payment_source' => 'Cash',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit2);

        // Move credit2 to Day 0 (2026-08-05)
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.update', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit2->id,
        ]), [
            'amount' => 30000.00,
            'business_date' => '2026-08-05',
            'payment_source' => 'Cash',
            'company_account_id' => $this->cashAccount->id,
        ]);

        $transactions = $this->purchaserFinanceService->transactionsFor((int) $this->purchaser->id, '2026-08-01', '2026-08-31');
        $items = $transactions->items();

        // New order should be credit1 (Aug 10, running balance 50,000) then credit2 (Aug 05, running balance 30,000)
        $this->assertEquals($credit1->id, $items[0]->id);
        $this->assertEquals(50000.00, (float) $items[0]->running_balance);

        $this->assertEquals($credit2->id, $items[1]->id);
        $this->assertEquals(30000.00, (float) $items[1]->running_balance);
    }

    public function test_changing_payment_method_and_account_updates_journal_accounts(): void
    {
        // Created with Bank (Account 1020)
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 25000.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);
        $journal = app(JournalService::class)->recordPurchaserCredit($credit);

        // Change to Cash (Account 1010)
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.update', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'amount' => 25000.00,
            'business_date' => '2026-08-20',
            'payment_source' => 'Cash',
            'company_account_id' => $this->cashAccount->id,
            'reference' => 'CASH-VOUCHER-01',
        ]);

        $credit->refresh();
        $this->assertSame('Cash', $credit->payment_source);
        $this->assertEquals($this->cashAccount->id, $credit->company_account_id);

        $journal->refresh();
        $transactions = $journal->transactions()->with('account')->get();

        $cashCreditTx = $transactions->firstWhere('account.code', '1010');
        $this->assertNotNull($cashCreditTx);
        $this->assertSame('credit', $cashCreditTx->type);
        $this->assertEquals(25000.00, (float) $cashCreditTx->amount);
    }

    public function test_summary_cards_and_expected_cash_refresh_after_edit(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 80000.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit);

        // Summary before
        $before = $this->purchaserFinanceService->summaryFor((int) $this->purchaser->id);
        $this->assertEquals(80000.00, $before['cash_given']);
        $this->assertEquals(80000.00, $before['expected_cash'] ?? $before['remaining_advance']);

        // Update amount from 80k to 20k
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.update', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'amount' => 20000.00,
            'business_date' => '2026-08-20',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
        ]);

        // Summary after
        $after = $this->purchaserFinanceService->summaryFor((int) $this->purchaser->id);
        $this->assertEquals(20000.00, $after['cash_given']);
        $this->assertEquals(20000.00, $after['net_funding']);
        $this->assertEquals(20000.00, $after['remaining_advance']);
    }

    public function test_matched_movement_follows_reconciliation_protection_rules(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 15000.00,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit);

        // Matched finalized statement
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'out',
            'amount' => 15000.00,
            'source' => 'purchaser_funding',
            'source_type' => PurchaserCredit::class,
            'source_id' => $credit->id,
            'status' => 'reconciled',
            'matched_amount' => 15000.00,
            'is_finalized' => true,
        ]);

        // Attempting direct edit throws validation exception
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.update', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'amount' => 12000.00,
            'business_date' => '2026-08-20',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
        ]);

        $response->assertSessionHasErrors(['credit']);
    }

    public function test_delete_purchaser_funding_removes_journal_and_recalculates_running_balance(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 18000.00,
            'payment_source' => 'Cash',
            'company_account_id' => $this->cashAccount->id,
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.delete', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'reason' => 'Duplicate Entry',
            'notes' => 'Accidentally recorded twice',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('purchaser_credits', ['id' => $credit->id]);
        $this->assertDatabaseMissing('journal_entries', ['source_type' => PurchaserCredit::class, 'source_id' => $credit->id]);

        $summary = $this->purchaserFinanceService->summaryFor((int) $this->purchaser->id);
        $this->assertEquals(0.00, $summary['cash_given']);
        $this->assertEquals(0.00, $summary['remaining_advance']);
    }

    public function test_unauthorized_user_cannot_edit_or_delete_purchaser_funding(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('purchaser');

        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 10000.00,
            'payment_source' => 'Cash',
            'business_date' => '2026-08-20',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit);

        $this->actingAs($staff)->post(route('admin.cashbook.finance.purchasers.funding.update', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'amount' => 5000.00,
            'business_date' => '2026-08-20',
            'payment_source' => 'Cash',
            'company_account_id' => $this->cashAccount->id,
        ])->assertForbidden();

        $this->actingAs($staff)->post(route('admin.cashbook.finance.purchasers.funding.delete', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'reason' => 'Duplicate Entry',
        ])->assertForbidden();
    }

    public function test_production_shaped_legacy_funding_drill_down_matches_summary_card_exactly(): void
    {
        // 1. Legacy funding row (created years ago before optional metadata existed: no company_account_id, no statement/reconciliation, no reference)
        $legacy1 = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 1136243.00,
            'business_date' => '2024-01-15',
            'payment_source' => null,
            'company_account_id' => null,
            'reference' => null,
            'description' => null,
            'created_by' => null,
        ]);

        // 2. Another legacy funding row in 2025
        $legacy2 = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 1000000.00,
            'business_date' => '2025-06-10',
            'payment_source' => 'Bank',
            'company_account_id' => null,
            'reference' => null,
            'description' => 'Direct transfer',
            'created_by' => $this->admin->id,
        ]);

        // Total company given = ₹21,36,243.00

        // Check summaryFor
        $summary = $this->purchaserFinanceService->summaryFor((int) $this->purchaser->id);
        $this->assertEquals(2136243.00, $summary['cash_given']);
        $this->assertEquals(2136243.00, $summary['remaining_advance']);

        // Check fundingSplitsFor for current month (2026-08-01 to 2026-08-31)
        $splits = $this->purchaserFinanceService->fundingSplitsFor((int) $this->purchaser->id, '2026-08-01', '2026-08-31');

        $this->assertCount(2, $splits['given']);
        $this->assertEquals(2136243.00, $splits['cumulative']['cash_given']);
        $this->assertEquals(0.00, $splits['period']['cash_given']);

        $givenSum = array_sum(array_column($splits['given'], 'amount'));
        $this->assertEquals(2136243.00, $givenSum);

        // Verify in_period flags
        $this->assertFalse((bool) $splits['given'][0]->in_period);
        $this->assertFalse((bool) $splits['given'][1]->in_period);

        // Verify HTML page response contains the exact split data in JSON and rendered cards
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'period' => 'month',
            'tab' => 'funding',
        ]));

        $response->assertOk();
        $response->assertSee('2,136,243.00');
        // Ensure fundingSplitsData JSON contains non-empty given array
        $response->assertSee('1136243');
        $response->assertSee('1000000');
    }

    public function test_split_modal_edit_workflow_updates_same_record_recalculates_totals_and_preserves_open_split(): void
    {
        // 1. Initial funding: ₹20,00,000
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 2000000.00,
            'business_date' => '2026-08-15',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'TXN-INITIAL-2000K',
            'description' => 'Initial funding from bank',
            'created_by' => $this->admin->id,
        ]);
        app(JournalService::class)->recordPurchaserCredit($credit);

        // Verify initial state
        $summaryBefore = $this->purchaserFinanceService->summaryFor((int) $this->purchaser->id);
        $this->assertEquals(2000000.00, $summaryBefore['cash_given']);
        $this->assertEquals(2000000.00, $summaryBefore['remaining_advance']);

        $splitsBefore = $this->purchaserFinanceService->fundingSplitsFor((int) $this->purchaser->id, '2026-08-01', '2026-08-31');
        $this->assertCount(1, $splitsBefore['given']);
        $this->assertEquals(2000000.00, $splitsBefore['cumulative']['cash_given']);

        // 2. Perform Edit from inside split modal: change ₹20,00,000 to ₹2,00,000 with open_split=given
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.update', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'amount' => 200000.00,
            'business_date' => '2026-08-16',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'TXN-CORRECTED-200K',
            'description' => 'Corrected funding amount',
            'tab' => 'funding',
            'open_split' => 'given',
        ]);

        // Redirect must include tab=funding and open_split=given
        $response->assertRedirect(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'period' => 'month',
            'tab' => 'funding',
            'open_split' => 'given',
        ]));

        // 3. Verify SAME record updated in DB (no duplicates)
        $this->assertDatabaseCount('purchaser_credits', 1);
        $this->assertDatabaseHas('purchaser_credits', [
            'id' => $credit->id,
            'amount' => 200000.00,
            'business_date' => '2026-08-16 00:00:00',
            'reference' => 'TXN-CORRECTED-200K',
        ]);

        // 4. Verify Journal entry updated in-place (single journal entry)
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseHas('journal_transactions', [
            'amount' => 200000.00,
        ]);

        // 5. Verify summary and split totals recalculated
        $summaryAfter = $this->purchaserFinanceService->summaryFor((int) $this->purchaser->id);
        $this->assertEquals(200000.00, $summaryAfter['cash_given']);
        $this->assertEquals(200000.00, $summaryAfter['remaining_advance']);

        $splitsAfter = $this->purchaserFinanceService->fundingSplitsFor((int) $this->purchaser->id, '2026-08-01', '2026-08-31');
        $this->assertCount(1, $splitsAfter['given']);
        $this->assertEquals(200000.00, $splitsAfter['cumulative']['cash_given']);
        $this->assertEquals(200000.00, $splitsAfter['period']['cash_given']);
    }
}
