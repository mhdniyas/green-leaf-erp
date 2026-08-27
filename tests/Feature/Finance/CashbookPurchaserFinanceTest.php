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
use App\Services\Purchasing\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookPurchaserFinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Supplier $supplier;

    private CompanyAccount $bankCompanyAccount;

    private PurchaseInvoiceService $purchaseInvoiceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->purchaser = User::factory()->create(['name' => 'Purchaser A']);
        $this->purchaser->assignRole('purchaser');

        $this->supplier = Supplier::factory()->create([
            'name' => 'Daily Market Vendor',
            'type' => 'Market Agent',
            'category' => 'own_purchase',
            'credit_approved' => true,
        ]);

        $this->bankCompanyAccount = CompanyAccount::query()->create([
            'name' => 'South Indian Bank',
            'account_type' => 'bank',
            'bank_name' => 'South Indian Bank',
            'enabled' => true,
        ]);

        Account::query()->firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1200'], ['name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1300'], ['name' => 'Purchaser Advances', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]);

        $this->purchaseInvoiceService = app(PurchaseInvoiceService::class);
    }

    public function test_purchaser_list_includes_zero_activity_and_keeps_period_totals_separate_from_current_balance(): void
    {
        $this->travelTo(Carbon::parse('2026-08-27'));
        $empty = User::factory()->create(['name' => 'Zero Activity Purchaser']);
        $empty->assignRole('purchaser');
        PurchaserCredit::query()->create(['purchaser_id' => $this->purchaser->id, 'type' => 'in', 'amount' => 1000, 'business_date' => '2026-08-26']);
        PurchaserCredit::query()->create(['purchaser_id' => $this->purchaser->id, 'type' => 'out', 'amount' => 100, 'business_date' => '2026-08-27']);
        $url = route('admin.cashbook.finance.purchase.purchasers', ['period' => 'today']);
        $response = $this->actingAs($this->admin)->get($url)->assertOk()->assertSee($empty->name)->assertSee('Current Advance')->assertSee('₹0.00');
        $rows = $response->viewData('sectionData')['rows'];
        $this->assertSame(2, $rows->total());
        $row = $rows->firstWhere('purchaser_id', $this->purchaser->id);
        $this->assertEquals(0, $row->funding);
        $this->assertEquals(100, $row->funding_used);
        $this->assertEquals(900, $row->balance);
        $this->assertEquals(1, $row->transaction_count);
        $zero = $rows->firstWhere('purchaser_id', $empty->id);
        $this->assertEquals(0, $zero->funding);
        $this->assertEquals(0, $zero->funding_used);
        $this->assertEquals(0, $zero->transaction_count);
        $this->get($url.'&search=Zero')->assertOk()->assertViewHas('sectionData', fn ($data) => $data['rows']->total() === 1);
        $this->get(route('admin.cashbook.finance.purchase.purchasers', ['period' => 'yesterday']))->assertOk()
            ->assertViewHas('sectionData', fn ($data) => (float) $data['rows']->firstWhere('purchaser_id', $this->purchaser->id)->funding === 1000.0);
    }

    public function test_company_funding_creates_cashbook_journal_and_starts_unmatched(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 50000.00,
            'business_date' => '2026-08-22',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankCompanyAccount->id,
            'reference' => 'FUND-UTR-001',
            'description' => 'Weekly purchase advance',
        ]);

        $response->assertRedirect(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'period' => 'month',
            'tab' => 'finance',
        ]));

        $credit = PurchaserCredit::query()->where('purchaser_id', $this->purchaser->id)->firstOrFail();
        $journalEntry = JournalEntry::query()
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $credit->id)
            ->where('source_event', 'purchaser_funding')
            ->with('transactions.account')
            ->firstOrFail();

        $this->assertEquals(50000.00, $journalEntry->total_debit);
        $this->assertEquals(50000.00, $journalEntry->total_credit);
        $this->assertEquals('1300', $journalEntry->transactions->firstWhere('type', 'debit')->account->code);
        $this->assertEquals('1020', $journalEntry->transactions->firstWhere('type', 'credit')->account->code);

        // Starts unmatched (no synthetic auto-finalized statement entry)
        $statementEntry = CompanyAccountStatementEntry::query()->where('journal_entry_id', $journalEntry->id)->first();
        $this->assertNull($statementEntry, 'New funding must start without auto-created statement entry');

        // Now explicitly match with manual cash/statement counterpart
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.match-manual', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'amount' => 50000.00,
            'business_date' => '2026-08-22',
            'company_account_id' => $this->bankCompanyAccount->id,
            'reference' => 'FUND-UTR-001',
            'description' => 'Weekly purchase advance counterpart',
        ])->assertRedirect();

        $statementEntry = CompanyAccountStatementEntry::query()->where('journal_entry_id', $journalEntry->id)->firstOrFail();
        $this->assertEquals('out', $statementEntry->direction);
        $this->assertSame('manual', $statementEntry->source);
        $this->assertSame(PurchaserCredit::class, $statementEntry->source_type);
        $this->assertSame($credit->id, $statementEntry->source_id);
        $this->assertTrue($statementEntry->is_finalized);
        $this->assertEquals('reconciled', $statementEntry->status);
        $this->assertEquals(50000.00, (float) $statementEntry->matched_amount);
        $this->assertEquals($this->bankCompanyAccount->id, $statementEntry->company_account_id);

        $journalEntry->refresh();
        $this->assertTrue($journalEntry->is_finalized);
        $this->assertEquals('finalized', $journalEntry->reconciliation_status);
    }

    public function test_system_first_purchaser_funding_appears_in_approved_transactions_when_reconciled(): void
    {
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 75000.00,
            'business_date' => '2026-08-22',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankCompanyAccount->id,
            'reference' => 'FUND-JOURNAL-VIS',
            'description' => 'Journal visibility test',
        ])->assertRedirect();

        $credit = PurchaserCredit::query()->where('purchaser_id', $this->purchaser->id)->firstOrFail();
        $journalEntry = JournalEntry::query()
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $credit->id)
            ->where('source_event', 'purchaser_funding')
            ->firstOrFail();

        // Match manual counterpart
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.match-manual', [
            'purchaser' => $this->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), [
            'amount' => 75000.00,
            'business_date' => '2026-08-22',
            'company_account_id' => $this->bankCompanyAccount->id,
        ])->assertRedirect();

        // Must appear exactly once in Approved Transactions
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'));
        $response->assertOk();

        $entries = $response->viewData('journalEntries')->getCollection();
        $matching = $entries->where('id', $journalEntry->id);
        $this->assertCount(1, $matching, 'Purchaser Funding must appear exactly once in Approved Transactions.');

        $visible = $matching->first();
        $this->assertStringStartsWith('Purchaser Funding', $visible->source_label);
        $this->assertEquals(75000.00, (float) $visible->primary_amount);
        $this->assertTrue($visible->is_finalized);

        $statement = $visible->statementEntries->firstWhere('is_finalized', true);
        $this->assertNotNull($statement, 'JournalEntry must have a finalized statement entry.');
        $this->assertEquals('out', $statement->direction);
        $this->assertEquals($this->bankCompanyAccount->id, $statement->company_account_id);

        // No duplicate statement entries
        $this->assertCount(1, $visible->statementEntries);
    }

    public function test_statement_first_purchaser_funding_appears_in_approved_transactions(): void
    {
        Role::firstOrCreate(['name' => 'purchaser']);

        // Import an unmatched OUT statement
        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => '2026-08-22',
            'direction' => 'out',
            'amount' => 60000.00,
            'reference' => 'STMT-PURCH-001',
            'narration' => 'Bank transfer to purchaser',
            'source' => 'manual',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        // Classify as Purchaser Funding
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.finance.reconciliation.classify-purchaser-funding', $statement),
            [
                'purchaser_uuid' => $this->purchaser->public_uuid,
                'description' => 'Statement-first funding test',
            ]
        )->assertRedirect();

        $statement->refresh();
        $this->assertTrue($statement->is_finalized);
        $this->assertEquals('reconciled', $statement->status);
        $this->assertSame(PurchaserCredit::class, $statement->source_type);
        $this->assertNotNull($statement->journal_entry_id);

        $journalEntry = JournalEntry::find($statement->journal_entry_id);
        $this->assertNotNull($journalEntry);
        $this->assertEquals('purchaser_funding', $journalEntry->source_event);
        $this->assertTrue($journalEntry->is_finalized);

        // Must appear exactly once in Approved Transactions
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'));
        $response->assertOk();

        $entries = $response->viewData('journalEntries')->getCollection();
        $matching = $entries->where('id', $journalEntry->id);
        $this->assertCount(1, $matching, 'Statement-first Purchaser Funding must appear exactly once in Approved Transactions.');

        $visible = $matching->first();
        $this->assertStringStartsWith('Purchaser Funding', $visible->source_label);
        $this->assertEquals(60000.00, (float) $visible->primary_amount);
        $this->assertEquals($this->bankCompanyAccount->id, $visible->statementEntries->firstWhere('is_finalized', true)->company_account_id);

        // No duplicate journal entries or statement entries
        $duplicateJe = JournalEntry::where('source_type', PurchaserCredit::class)
            ->where('source_id', $statement->source_id)
            ->count();
        $this->assertEquals(1, $duplicateJe, 'No duplicate JournalEntry for PurchaserCredit.');

        $duplicateSe = CompanyAccountStatementEntry::where('journal_entry_id', $journalEntry->id)->count();
        $this->assertEquals(1, $duplicateSe, 'No duplicate CompanyAccountStatementEntry for this JournalEntry.');
    }

    public function test_pending_and_partially_reconciled_purchaser_funding_hidden_in_journal(): void
    {
        // Pending: funding without company_account_id (no statement entry)
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 10000.00,
            'business_date' => '2026-08-22',
            'payment_source' => 'Bank',
            // No company_account_id — no statement entry created
        ])->assertRedirect();

        $pendingCredit = PurchaserCredit::query()->where('purchaser_id', $this->purchaser->id)->latest()->firstOrFail();
        $pendingJe = JournalEntry::where('source_type', PurchaserCredit::class)
            ->where('source_id', $pendingCredit->id)
            ->firstOrFail();

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'));
        $response->assertOk();

        $visibleIds = $response->viewData('journalEntries')->getCollection()->pluck('id');
        $this->assertFalse($visibleIds->contains($pendingJe->id), 'Pending (no statement) funding must be hidden in Approved Transactions.');
    }

    public function test_purchaser_cash_bill_uses_advance_and_never_touches_cash_or_bank(): void
    {
        $invoice = $this->createPurchaserInvoice('CASH-BILL-001', 10000.00);

        $updatedInvoice = $this->purchaseInvoiceService->updatePayment($invoice, [
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'payment_purchaser_id' => $this->purchaser->id,
            'paid_amount' => 10000.00,
            'payment_note' => 'Cash bill from purchaser advance',
            'payment_details' => null,
        ]);

        $journalEntry = JournalEntry::query()
            ->where('source_type', PurchaseInvoice::class)
            ->where('source_id', $updatedInvoice->id)
            ->where('source_event', 'purchaser_daily_purchase_payment:paid-1000000')
            ->with('transactions.account')
            ->firstOrFail();

        $codes = $journalEntry->transactions->pluck('account.code')->all();

        $this->assertEquals('1200', $journalEntry->transactions->firstWhere('type', 'debit')->account->code);
        $this->assertEquals('1300', $journalEntry->transactions->firstWhere('type', 'credit')->account->code);
        $this->assertNotContains('1010', $codes);
        $this->assertNotContains('1020', $codes);
        $this->assertEquals('unreconciled', $journalEntry->reconciliation_status);
        $this->assertDatabaseMissing('cashbook_company_account_statement_entries', ['journal_entry_id' => $journalEntry->id]);
    }

    public function test_summary_and_detail_keep_credit_purchases_separate_from_advance(): void
    {
        PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 50000.00,
            'description' => 'Opening funding',
            'payment_source' => 'Bank',
            'created_by' => $this->admin->id,
            'business_date' => '2026-08-22',
        ]);

        foreach ([2350.00, 2400.00, 5788.00] as $index => $amount) {
            $invoice = $this->createPurchaserInvoice('CASH-BILL-00'.($index + 1), $amount);
            $this->purchaseInvoiceService->updatePayment($invoice, [
                'payment_method' => 'Cash',
                'payment_paid_by' => 'purchaser',
                'payment_purchaser_id' => $this->purchaser->id,
                'paid_amount' => $amount,
                'payment_note' => 'Cash utilization',
                'payment_details' => null,
            ]);
        }

        $creditInvoice = $this->createPurchaserInvoice('CREDIT-BILL-001', 13500.00, [
            'payment_method' => 'Credit',
            'payment_paid_by' => 'vendor_credit',
            'payment_status' => 'credit_pending_approval',
            'paid_amount' => 0.00,
        ]);

        $legacySummary = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchasers'));
        $legacySummary->assertRedirect(route('admin.cashbook.finance.purchase.purchasers', ['period' => 'month']));

        $legacyDetail = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchasers.details', $this->purchaser->public_uuid));
        $detailUrl = route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'period' => 'month',
            'tab' => 'finance',
        ]);
        $legacyDetail->assertRedirect($detailUrl);

        $detail = $this->actingAs($this->admin)->get($detailUrl);
        $detail->assertOk();
        $detail->assertSee('50,000.00');
        $detail->assertSee('10,538.00');
        $detail->assertSee('39,462.00');
        $detail->assertSee('13,500.00');
        $detail->assertSee('CASH-BILL-001');
        $detail->assertSee('CREDIT-BILL-001');
        $detail->assertSee('Cash');
        $detail->assertSee('Credit');

        $vendorCredit = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $vendorCredit->assertOk();
        $vendorCredit->assertSee($creditInvoice->invoice_number);
    }

    public function test_journal_source_identity_prevents_duplicate_entries(): void
    {
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 12000.00,
            'description' => 'Duplicate check',
            'payment_source' => 'Cash',
            'created_by' => $this->admin->id,
            'business_date' => '2026-08-22',
        ]);

        $service = app(JournalService::class);
        $first = $service->recordPurchaserCredit($credit);
        $second = $service->recordPurchaserCredit($credit);

        $this->assertTrue($first->is($second));
        $this->assertEquals(1, JournalEntry::query()
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $credit->id)
            ->where('source_event', 'purchaser_funding')
            ->count());
        $this->assertTrue($first->is_balanced);
    }

    public function test_purchaser_finance_pages_avoid_n_plus_one_queries(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $purchaser = User::factory()->create(['name' => "Purchaser {$i}"]);
            $purchaser->assignRole('purchaser');

            PurchaserCredit::query()->create([
                'purchaser_id' => $purchaser->id,
                'type' => 'in',
                'amount' => 10000.00,
                'business_date' => '2026-08-22',
            ]);

            $invoice = $this->createPurchaserInvoice("N1-CASH-{$i}", 1000.00, [], $purchaser);
            $this->purchaseInvoiceService->updatePayment($invoice, [
                'payment_method' => 'Cash',
                'payment_paid_by' => 'purchaser',
                'payment_purchaser_id' => $purchaser->id,
                'paid_amount' => 1000.00,
                'payment_note' => 'N1 check',
                'payment_details' => null,
            ]);
        }

        DB::enableQueryLog();
        $summary = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers', ['period' => 'month']));
        $summary->assertOk();
        $summaryQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        DB::enableQueryLog();
        $detail = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $this->purchaser->public_uuid,
            'period' => 'month',
            'tab' => 'finance',
        ]));
        $detail->assertOk();
        $detailQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(30, $summaryQueries, "Purchaser finance summary executed {$summaryQueries} queries.");
        $this->assertLessThan(35, $detailQueries, "Purchaser finance detail executed {$detailQueries} queries.");
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPurchaserInvoice(string $invoiceNumber, float $amount, array $overrides = [], ?User $purchaser = null): PurchaseInvoice
    {
        $purchaser ??= $this->purchaser;

        return PurchaseInvoice::factory()->for($this->supplier)->create(array_merge([
            'supplier_id' => $this->supplier->id,
            'purchaser_submitted_by' => $purchaser->id,
            'invoice_number' => $invoiceNumber,
            'amount' => $amount,
            'status' => 'pending',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Cash',
            'payment_status' => 'unpaid',
            'payment_paid_by' => 'purchaser',
            'created_at' => '2026-08-22 09:00:00',
        ], $overrides));
    }
}
