<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Models\VendorSettlement;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Finance\VendorSettlementCorrectionService;
use App\Services\Purchasing\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookVendorCreditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private CompanyAccount $bankCompanyAccount;

    private Account $bankAccount;

    private Account $apAccount;

    private Account $inventoryAccount;

    private Account $advanceAccount;

    private PurchaseInvoiceService $purchaseInvoiceService;

    private CompanyPaymentReconciliationService $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Test Vegetables Co',
            'type' => 'Market Agent',
            'category' => 'own_purchase',
            'mobile_number' => '9876543210',
            'contact' => 'Vendor Manager',
            'credit_approved' => true,
        ]);

        $this->bankCompanyAccount = CompanyAccount::query()->create([
            'name' => 'South Indian Bank',
            'account_type' => 'bank',
            'bank_name' => 'South Indian Bank',
            'enabled' => true,
        ]);

        $this->bankAccount = Account::query()->firstOrCreate(
            ['code' => '1020'],
            ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]
        );

        $this->apAccount = Account::query()->firstOrCreate(
            ['code' => '2100'],
            ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]
        );

        $this->inventoryAccount = Account::query()->firstOrCreate(
            ['code' => '1200'],
            ['name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true]
        );

        $this->advanceAccount = Account::query()->firstOrCreate(
            ['code' => '1300'],
            ['name' => 'Purchaser Advances', 'type' => 'asset', 'is_active' => true]
        );

        Account::query()->firstOrCreate(['code' => '1400'], ['name' => 'Vendor Advances', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '4200'], ['name' => 'Vendor Settlement Discounts', 'type' => 'revenue', 'is_active' => true]);

        $this->purchaseInvoiceService = app(PurchaseInvoiceService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
    }

    public function test_credit_invoice_appears_in_vendor_credit_summary_and_aggregates(): void
    {
        $invoice1 = PurchaseInvoice::factory()->for($this->supplier)->create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-VC-001',
            'amount' => 50000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'created_at' => now(),
        ]);

        $invoice2 = PurchaseInvoice::factory()->for($this->supplier)->create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-VC-002',
            'amount' => 30000.00,
            'discount_amount' => 2000.00,
            'paid_amount' => 10000.00,
            'payment_method' => 'Credit',
            'payment_status' => 'partial',
            'payment_paid_by' => 'vendor_credit',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases'));

        $response->assertOk();
        $response->assertSee('Test Vegetables Co');
        $response->assertSee('78,000.00'); // Total net: 50,000 + 28,000
        $response->assertSee('10,000.00'); // Total paid: 10,000
        $response->assertSee('68,000.00'); // Total due: 50,000 + 18,000
        $supplierUrl = route('admin.cashbook.finance.vendor-credit.show', $this->supplier);
        $response->assertSee($supplierUrl, false);
        $response->assertSee('aria-label="View vendor credit for Test Vegetables Co"', false);

        $supplierSegment = basename((string) parse_url($supplierUrl, PHP_URL_PATH));
        $this->assertSame($this->supplier->public_uuid, $supplierSegment);
        $this->assertNotSame((string) $this->supplier->id, $supplierSegment);
    }

    public function test_vendor_credit_summary_excludes_unpaid_non_credit_invoices(): void
    {
        PurchaseInvoice::factory()->for($this->supplier)->create([
            'amount' => 1000,
            'discount_amount' => 0,
            'paid_amount' => 0,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
        ]);
        PurchaseInvoice::factory()->for($this->supplier)->create([
            'amount' => 500,
            'discount_amount' => 0,
            'paid_amount' => 0,
            'payment_method' => 'GPay',
            'payment_status' => 'unpaid',
            'payment_paid_by' => 'purchaser',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases'));

        $response->assertOk();
        $this->assertSame(1000.0, $response['kpi']['total_invoiced']);
        $this->assertSame(1, $response['kpi']['invoice_count']);
    }

    public function test_vendor_credit_summary_applies_the_selected_business_date_range(): void
    {
        foreach ([
            ['2026-08-10 08:00:00', 1000],
            ['2026-07-31 08:00:00', 500],
        ] as [$createdAt, $amount]) {
            PurchaseInvoice::factory()->for($this->supplier)->create([
                'amount' => $amount,
                'discount_amount' => 0,
                'paid_amount' => 0,
                'payment_method' => 'Credit',
                'payment_status' => 'credit_pending_approval',
                'payment_paid_by' => 'vendor_credit',
                'purchaser_cart_id' => null,
                'created_at' => $createdAt,
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases', [
            'period' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-25',
        ]));

        $response->assertOk();
        $this->assertSame(1000.0, $response['kpi']['total_invoiced']);
        $this->assertSame(1, $response['kpi']['invoice_count']);
    }

    public function test_credit_invoice_does_not_create_bank_reconciliation_need(): void
    {
        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-VC-NOREC',
            'amount' => 25000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'created_at' => now(),
        ]);

        // Assert no JournalEntry exists crediting Cash or Bank
        $jes = JournalEntry::query()
            ->where('source_type', PurchaseInvoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $this->assertCount(0, $jes);
    }

    public function test_partial_and_full_payment_flow_creates_canonical_journal_entry(): void
    {
        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create([
            'public_uuid' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-VC-PAYTEST',
            'amount' => 100000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'created_at' => now(),
        ]);

        // 1. Pay partial 40,000
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.pay', $invoice), [
            'payment_amount' => 40000.00,
            'payment_method' => 'Bank',
            'company_account_id' => $this->bankCompanyAccount->id,
            'payment_note' => 'Partial settlement UTR12345',
        ]);

        $response->assertRedirect();
        $invoice->refresh();

        $this->assertEquals(40000.00, $invoice->paid_amount);
        $this->assertEquals('partial', $invoice->payment_status);

        // Assert JournalEntry created: Dr Accounts Payable (2100), Cr Bank (1020)
        $je = JournalEntry::query()
            ->where('source_type', PurchaseInvoice::class)
            ->where('source_id', $invoice->id)
            ->with('transactions.account')
            ->where('source_event', 'company_vendor_credit_payment:paid-4000000')
            ->first();

        $this->assertNotNull($je);
        $this->assertEquals(40000.00, $je->total_debit);
        $this->assertEquals(40000.00, $je->total_credit);

        $debitLine = $je->transactions->firstWhere('type', 'debit');
        $creditLine = $je->transactions->firstWhere('type', 'credit');

        $this->assertEquals('2100', $debitLine->account->code);
        $this->assertEquals('1020', $creditLine->account->code);

        $statementEntry = CompanyAccountStatementEntry::query()
            ->where('journal_entry_id', $je->id)
            ->first();

        $this->assertNotNull($statementEntry);
        $this->assertEquals('out', $statementEntry->direction);
        $this->assertEquals(40000.00, (float) $statementEntry->amount);
        $this->assertFalse($statementEntry->is_finalized);

        // 2. Pay remaining 60,000
        $response2 = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.pay', $invoice), [
            'payment_amount' => 60000.00,
            'payment_method' => 'Bank',
            'company_account_id' => $this->bankCompanyAccount->id,
            'payment_note' => 'Final settlement UTR67890',
        ]);

        $response2->assertRedirect();
        $invoice->refresh();

        $this->assertEquals(100000.00, $invoice->paid_amount);
        $this->assertEquals('paid', $invoice->payment_status);
        $this->assertEquals(2, CompanyAccountStatementEntry::query()->count());
    }

    public function test_vendor_credit_show_page_renders_invoices_and_actions(): void
    {
        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-VC-DETAIL-001',
            'amount' => 45000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'status' => 'approved',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));

        $response->assertOk();
        $response->assertSee('Test Vegetables Co');
        $response->assertSee('BILL-VC-DETAIL-001');
        $response->assertSee('45,000.00');
        $response->assertSee('Settle');
        $response->assertSee('View Bills');
        $response->assertSee('href="#vendor-bills"', false);
    }

    public function test_numeric_vendor_credit_url_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier->id))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier->id), [])
            ->assertNotFound();
    }

    public function test_vendor_settlement_urls_use_public_uuid_and_reject_numeric_ids(): void
    {
        $settlement = VendorSettlement::query()->create([
            'supplier_id' => $this->supplier->id,
            'actual_payment_amount' => 1000,
            'payment_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $detailUrl = route('admin.cashbook.finance.vendor-credit.settlements.show', $settlement);
        $reconcileUrl = route('admin.cashbook.finance.vendor-credit.settlements.reconcile', $settlement);
        $detailSegment = basename((string) parse_url($detailUrl, PHP_URL_PATH));
        $reconcileSegment = basename(dirname((string) parse_url($reconcileUrl, PHP_URL_PATH)));

        $this->assertStringContainsString($settlement->public_uuid, $detailUrl);
        $this->assertStringContainsString($settlement->public_uuid, $reconcileUrl);
        $this->assertSame($settlement->public_uuid, $detailSegment);
        $this->assertSame($settlement->public_uuid, $reconcileSegment);
        $this->assertNotSame((string) $settlement->id, $detailSegment);
        $this->assertNotSame((string) $settlement->id, $reconcileSegment);

        $this->actingAs($this->admin)->get($detailUrl)->assertOk();
        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.settlements.show', $settlement->id))->assertNotFound();
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settlements.reconcile', $settlement->id), [])->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/cashbook/finance/vendor-credit/settlements/'.Str::uuid())->assertNotFound();
    }

    public function test_vendor_settlement_history_control_renders_a_valid_history_route(): void
    {
        $historyRoute = route('admin.cashbook.finance.vendor-credit.settlements');

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.reports.credit-purchases'))
            ->assertOk()
            ->assertSee('href="'.$historyRoute.'"', false)
            ->assertSee('Vendor Settlement History');

        $this->actingAs($this->admin)
            ->get($historyRoute)
            ->assertOk()
            ->assertSee('Vendor Settlement History');
    }

    public function test_no_n_plus_one_queries_on_vendor_credit_pages(): void
    {
        // Create 10 suppliers with 3 invoices each
        for ($s = 1; $s <= 10; $s++) {
            $sup = Supplier::factory()->create([
                'name' => "Supplier {$s}",
                'type' => 'Farmer',
                'category' => 'own_purchase',
                'credit_approved' => true,
            ]);

            for ($i = 1; $i <= 3; $i++) {
                PurchaseInvoice::factory()->for($sup)->create([
                    'supplier_id' => $sup->id,
                    'invoice_number' => "BILL-N1-{$s}-{$i}",
                    'amount' => 10000.00 * $i,
                    'discount_amount' => 0.00,
                    'paid_amount' => 0.00,
                    'payment_method' => 'Credit',
                    'payment_status' => 'credit_pending_approval',
                    'payment_paid_by' => 'vendor_credit',
                    'created_at' => now(),
                ]);
            }
        }

        DB::enableQueryLog();

        $responseSummary = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases'));
        $responseSummary->assertOk();

        $summaryQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(25, $summaryQueries, "Vendor credit summary executed {$summaryQueries} queries.");

        DB::enableQueryLog();

        $responseDetail = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $responseDetail->assertOk();

        $detailQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(40, $detailQueries, "Vendor credit detail executed {$detailQueries} queries.");
    }

    public function test_vendor_payment_statement_can_reconcile_and_finalize_company_payment_only(): void
    {
        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create([
            'public_uuid' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'BILL-VC-RECON-001',
            'amount' => 25000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.pay', $invoice), [
            'payment_amount' => 25000.00,
            'payment_method' => 'Bank',
            'company_account_id' => $this->bankCompanyAccount->id,
            'payment_note' => 'Vendor payout',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $journalEntry = JournalEntry::query()
            ->where('source_type', PurchaseInvoice::class)
            ->where('source_id', $invoice->id)
            ->where('source_event', 'company_vendor_credit_payment:paid-2500000')
            ->firstOrFail();

        $statementEntry = CompanyAccountStatementEntry::query()
            ->where('journal_entry_id', $journalEntry->id)
            ->firstOrFail();

        $this->assertFalse($journalEntry->is_finalized);
        $this->assertFalse($statementEntry->is_finalized);

        $this->actingAs($this->admin)->post(
            route('admin.cashbook.finance.reconciliation.match-journal', $statementEntry->secureRouteKey()),
            [
                'journal_entry_id' => $journalEntry->id,
                'cleared_amount' => 25000.00,
            ]
        )->assertRedirect();

        $journalEntry->refresh();
        $statementEntry->refresh();
        $invoice->refresh();

        $this->assertTrue($statementEntry->is_finalized);
        $this->assertTrue($journalEntry->is_finalized);
        $this->assertEquals('finalized', $journalEntry->reconciliation_status);
        $this->assertEquals('paid', $invoice->payment_status);
    }

    public function test_multi_invoice_settlement_creates_single_journal_and_vendor_advance(): void
    {
        $first = $this->creditInvoice('ADV-ONE', 95000);
        $second = $this->creditInvoice('ADV-TWO', 20000);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'actual_payment_amount' => 100000, 'settlement_discount_amount' => 0, 'vendor_advance_used_amount' => 0,
            'payment_date' => now()->toDateString(), 'payment_method' => 'Bank', 'company_account_id' => $this->bankCompanyAccount->id,
            'reference' => 'OVERPAY', 'allocations' => [
                ['purchase_invoice_id' => $first->id, 'cash_allocated' => 95000, 'advance_allocated' => 0, 'discount_allocated' => 0],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settlement = VendorSettlement::query()->with('journalEntry.transactions.account')->firstOrFail();
        $this->assertEquals(5000.00, (float) $settlement->new_vendor_advance_amount);
        $this->assertCount(1, VendorSettlement::query()->get());
        $this->assertEquals(100000.00, (float) $settlement->journalEntry->transactions->firstWhere('account.code', '1020')->amount);
        $this->assertEquals(5000.00, (float) VendorAdvance::query()->firstOrFail()->amount_remaining);
        $statement = CompanyAccountStatementEntry::query()->firstOrFail();
        $this->assertEquals(100000.00, (float) $statement->amount);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.reconciliation.match-journal', $statement->secureRouteKey()), [
            'journal_entry_id' => $settlement->journal_entry_id,
            'cleared_amount' => 100000,
        ])->assertRedirect();

        $this->assertTrue($settlement->fresh()->is_finalized);
        $this->assertTrue($settlement->journalEntry->fresh()->is_finalized);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'actual_payment_amount' => 0, 'settlement_discount_amount' => 0, 'vendor_advance_used_amount' => 5000,
            'payment_date' => now()->toDateString(), 'allocations' => [
                ['purchase_invoice_id' => $second->id, 'cash_allocated' => 0, 'advance_allocated' => 5000, 'discount_allocated' => 0],
            ],
        ])->assertRedirect();

        $second->refresh();
        $this->assertEquals('partial', $second->payment_status);
        $this->assertEquals(15000.00, $second->settlementOutstanding());
        $this->assertEquals(0.00, (float) VendorAdvance::query()->firstOrFail()->amount_remaining);
        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
    }

    public function test_settlement_discount_closes_invoice_without_changing_invoice_discount(): void
    {
        $invoice = $this->creditInvoice('DISC-ONE', 105000);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'actual_payment_amount' => 100000, 'settlement_discount_amount' => 5000, 'vendor_advance_used_amount' => 0,
            'payment_date' => now()->toDateString(), 'payment_method' => 'Bank', 'company_account_id' => $this->bankCompanyAccount->id,
            'allocations' => [['purchase_invoice_id' => $invoice->id, 'cash_allocated' => 100000, 'advance_allocated' => 0, 'discount_allocated' => 5000]],
        ])->assertRedirect();

        $invoice->refresh();
        $settlement = VendorSettlement::query()->with('journalEntry.transactions.account')->firstOrFail();
        $this->assertEquals(0.00, (float) $invoice->discount_amount);
        $this->assertEquals('paid', $invoice->payment_status);
        $this->assertEquals(100000.00, (float) $settlement->journalEntry->transactions->firstWhere('account.code', '1020')->amount);
        $this->assertEquals(5000.00, (float) $settlement->journalEntry->transactions->firstWhere('account.code', '4200')->amount);
    }

    public function test_simple_settlement_auto_allocates_oldest_and_can_apply_difference_as_discount(): void
    {
        $first = $this->creditInvoice('AUTO-ONE', 40000);
        $second = $this->creditInvoice('AUTO-TWO', 35000);
        $third = $this->creditInvoice('AUTO-THREE', 30000);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'invoice_ids' => [$first->id, $second->id, $third->id],
            'actual_payment_amount' => 100000, 'difference_treatment' => 'outstanding', 'allocation_order' => 'oldest',
            'payment_date' => now()->toDateString(), 'payment_method' => 'Bank', 'company_account_id' => $this->bankCompanyAccount->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $allocations = VendorSettlement::query()->firstOrFail()->allocations()->orderBy('purchase_invoice_id')->get();
        $this->assertEquals(40000.00, (float) $allocations[0]->cash_allocated);
        $this->assertEquals(35000.00, (float) $allocations[1]->cash_allocated);
        $this->assertEquals(25000.00, (float) $allocations[2]->cash_allocated);
        $this->assertEquals(0.00, (float) $allocations[2]->discount_allocated);

        $discountInvoice = $this->creditInvoice('AUTO-DISCOUNT', 5000);
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'invoice_ids' => [$discountInvoice->id],
            'actual_payment_amount' => 0, 'difference_treatment' => 'discount', 'allocation_order' => 'oldest',
            'payment_date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $allocation = VendorSettlement::query()->latest('id')->firstOrFail()->allocations()->firstOrFail();
        $this->assertEquals(5000.00, (float) $allocation->discount_allocated);
    }

    public function test_legacy_vendor_settlement_flow_creates_core_records_without_company_account(): void
    {
        $first = $this->creditInvoice('LEGACY-ONE', 40000);
        $second = $this->creditInvoice('LEGACY-TWO', 35000);
        $third = $this->creditInvoice('LEGACY-THREE', 30000);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'invoice_ids' => [$first->id, $second->id, $third->id],
            'actual_payment_amount' => 100000,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settlement = VendorSettlement::query()->with('allocations', 'journalEntry')->sole();

        $this->assertCount(3, $settlement->allocations);
        $this->assertNotNull($settlement->journalEntry);
        $this->assertSame('unreconciled', $settlement->reconciliation_status);
        $this->assertFalse($settlement->is_finalized);
        $this->assertEquals(40000.00, (float) $first->fresh()->paid_amount);
        $this->assertEquals(35000.00, (float) $second->fresh()->paid_amount);
        $this->assertEquals(25000.00, (float) $third->fresh()->paid_amount);
    }

    public function test_direct_vendor_settlement_finalizes_one_cashbook_movement_immediately(): void
    {
        $invoice = $this->creditInvoice('DIRECT-FINAL', 105000);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'invoice_ids' => [$invoice->id], 'actual_payment_amount' => 100000, 'difference_treatment' => 'discount', 'allocation_order' => 'oldest',
            'payment_date' => now()->toDateString(), 'payment_method' => 'Bank', 'company_account_id' => $this->bankCompanyAccount->id, 'reference' => 'UTR-DIRECT-1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settlement = VendorSettlement::query()->firstOrFail();
        $statement = CompanyAccountStatementEntry::query()->firstOrFail();
        $this->assertTrue($settlement->is_finalized);
        $this->assertTrue($statement->is_finalized);
        $this->assertEquals('reconciled', $statement->status);
        $this->assertEquals(100000.00, (float) $statement->amount);
        $this->assertSame($settlement->journal_entry_id, $statement->journal_entry_id);
        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
    }

    public function test_settlement_history_actions_and_detail_audit_breakdown(): void
    {
        $pendingInvoice = $this->creditInvoice('PENDING-ACTION', 30000);
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'invoice_ids' => [$pendingInvoice->id],
            'actual_payment_amount' => 30000,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank',
            'reference' => 'PENDING-ACTION-REF',
        ])->assertRedirect();

        $pendingSettlement = VendorSettlement::query()->sole();
        $this->reconciliationService->createStatementEntry([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'out',
            'amount' => 29999,
            'reference' => 'WRONG-AMOUNT-STATEMENT',
            'source' => 'pdf_import',
        ], $this->admin->id);
        $historyResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.settlements'));
        $historyResponse->assertOk()
            ->assertSee('PENDING RECONCILIATION')
            ->assertSee('Reconcile Now')
            ->assertSee('View Details');

        $supplierDetailResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $supplierDetailResponse->assertOk()
            ->assertSee('Action')
            ->assertSee('Reconcile Now')
            ->assertSee(route('admin.cashbook.finance.vendor-credit.settlements.show', $pendingSettlement), false);

        $detailResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.settlements.show', $pendingSettlement));
        $detailResponse->assertOk()
            ->assertSee('PENDING-ACTION')
            ->assertSee('30,000.00')
            ->assertSee('Search vendor, reference, date, amount...')
            ->assertSee('No exact amount match found. Upload latest statement, then return here to link it.')
            ->assertDontSee('WRONG-AMOUNT-STATEMENT')
            ->assertSee(route('admin.cashbook.finance.journal.entry-show', $pendingSettlement->journal_entry_id), false)
            ->assertSee(route('admin.cashbook.finance.vendor-credit.show', $this->supplier), false);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settlements.reconcile', $pendingSettlement), [
            'company_account_id' => $this->bankCompanyAccount->id,
        ])->assertRedirect();

        $pendingSettlement->refresh();
        $this->assertTrue($pendingSettlement->is_finalized);
        $this->assertSame('finalized', $pendingSettlement->reconciliation_status);
        $this->assertSame(2, CompanyAccountStatementEntry::query()->count());
        $this->assertEquals(30000.00, (float) $pendingSettlement->allocations()->sum('total_settled'));
        $this->assertEquals(
            (float) $pendingSettlement->actual_payment_amount + (float) $pendingSettlement->vendor_advance_used_amount + (float) $pendingSettlement->settlement_discount_amount - (float) $pendingSettlement->new_vendor_advance_amount,
            (float) $pendingSettlement->allocations()->sum('total_settled'),
        );

        $finalizedHistory = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.settlements'));
        $finalizedHistory->assertOk()
            ->assertSee('FINALIZED')
            ->assertSee('View Details')
            ->assertDontSee('Reconcile Now');

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settlements.reconcile', $pendingSettlement), [
            'company_account_id' => $this->bankCompanyAccount->id,
        ])->assertStatus(422);
    }

    public function test_finalized_vendor_settlement_cannot_be_mutated(): void
    {
        $invoice = $this->creditInvoice('IMMUTABLE-SETTLEMENT', 10000);
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'invoice_ids' => [$invoice->id],
            'actual_payment_amount' => 10000,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank',
            'company_account_id' => $this->bankCompanyAccount->id,
        ])->assertRedirect();

        $settlement = VendorSettlement::query()->sole();

        $correctionService = app(VendorSettlementCorrectionService::class);
        $correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Correcting test settlement');

        $this->assertDatabaseMissing('vendor_settlements', ['id' => $settlement->id]);
    }

    public function test_existing_statement_transaction_is_authoritative_and_reused_once(): void
    {
        $invoice = $this->creditInvoice('EXISTING-STMT', 105000);
        $statement = $this->reconciliationService->createStatementEntry([
            'company_account_id' => $this->bankCompanyAccount->id, 'transaction_date' => now()->toDateString(), 'direction' => 'out',
            'amount' => 100000, 'reference' => 'UTR-IMPORTED-1', 'narration' => 'NEFT supplier', 'source' => 'pdf_import',
        ], $this->admin->id);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'invoice_ids' => [$invoice->id], 'actual_payment_amount' => 1, 'difference_treatment' => 'discount', 'allocation_order' => 'oldest',
            'payment_date' => now()->subDay()->toDateString(), 'payment_method' => 'Bank', 'company_account_id' => $this->bankCompanyAccount->id,
            'statement_entry_id' => $statement->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settlement = VendorSettlement::query()->firstOrFail();
        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertEquals(100000.00, (float) $settlement->actual_payment_amount);
        $this->assertTrue($statement->fresh()->is_finalized);
        $this->assertSame($settlement->journal_entry_id, $statement->fresh()->journal_entry_id);

        $detailResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.settlements.show', $settlement));
        $statementRoute = route('admin.cashbook.finance.reconciliation', [
            'statementRef' => $statement->secureRouteKey(),
            'company_account_id' => $statement->company_account_id,
            'month' => $statement->transaction_date?->format('Y-m'),
        ]);
        $detailResponse->assertOk()->assertSee('UTR-IMPORTED-1');
        $this->actingAs($this->admin)->get($statementRoute)->assertOk();

        $otherInvoice = $this->creditInvoice('EXISTING-STMT-2', 100000);
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'invoice_ids' => [$otherInvoice->id], 'actual_payment_amount' => 100000, 'difference_treatment' => 'outstanding', 'allocation_order' => 'oldest',
            'payment_date' => now()->toDateString(), 'payment_method' => 'Bank', 'company_account_id' => $this->bankCompanyAccount->id,
            'statement_entry_id' => $statement->id,
        ])->assertSessionHasErrors('statement_entry_id');
    }

    public function test_newly_created_vendor_settlement_appears_immediately_in_settlement_history(): void
    {
        $invoice = $this->creditInvoice('SETTLE-HIST-01', 50000);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'invoice_ids' => [$invoice->id],
            'actual_payment_amount' => 50000,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank',
            'company_account_id' => $this->bankCompanyAccount->id,
            'reference' => 'REF-SETTLE-HIST-999',
        ])->assertRedirect();

        $settlement = VendorSettlement::query()->latest('id')->firstOrFail();
        $this->assertTrue($settlement->is_finalized);
        $this->assertSame('finalized', $settlement->reconciliation_status);

        $historyResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.settlements'));
        $historyResponse->assertOk();
        $historyResponse->assertSee('REF-SETTLE-HIST-999');
        $historyResponse->assertSee('50,000.00');
        $historyResponse->assertSee($this->supplier->name);
        $historyResponse->assertSee('FINALIZED');
        $historyResponse->assertSee($this->bankCompanyAccount->name);
    }

    public function test_settlement_history_month_and_status_filtering(): void
    {
        $invoice = $this->creditInvoice('SETTLE-FILTER-01', 30000);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'invoice_ids' => [$invoice->id],
            'actual_payment_amount' => 30000,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank',
            'company_account_id' => $this->bankCompanyAccount->id,
            'reference' => 'REF-FILTER-MATCH',
        ])->assertRedirect();

        // 1. Matching current month filter
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.settlements', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('REF-FILTER-MATCH');

        // 2. Non-matching past month filter
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.settlements', ['month' => '2025-01']))
            ->assertOk()
            ->assertDontSee('REF-FILTER-MATCH');

        // 3. Status filter finalized
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.settlements', ['status' => 'finalized']))
            ->assertOk()
            ->assertSee('REF-FILTER-MATCH');

        // 4. Status filter pending
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit.settlements', ['status' => 'pending']))
            ->assertOk()
            ->assertDontSee('REF-FILTER-MATCH');
    }

    private function creditInvoice(string $number, float $amount): PurchaseInvoice
    {
        return PurchaseInvoice::factory()->for($this->supplier)->create([
            'supplier_id' => $this->supplier->id, 'invoice_number' => $number, 'amount' => $amount, 'discount_amount' => 0,
            'paid_amount' => 0, 'payment_method' => 'Credit', 'payment_status' => 'credit_pending_approval', 'payment_paid_by' => 'vendor_credit',
        ]);
    }
}
