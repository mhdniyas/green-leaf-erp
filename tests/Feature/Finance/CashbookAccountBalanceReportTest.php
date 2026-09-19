<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\AccountBalanceReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookAccountBalanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CompanyAccount $bankAccount;

    private CompanyAccount $cashAccount;

    private Shop $shop;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
        ]);

        $this->bankAccount = CompanyAccount::create([
            'name' => 'HDFC Primary Bank',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'account_number' => '1234567890',
            'opening_balance' => 50000.00,
            'current_balance' => 75000.00,
            'is_default' => true,
            'enabled' => true,
        ]);

        $this->cashAccount = CompanyAccount::create([
            'name' => 'Main Vault Cash',
            'account_type' => 'cash',
            'bank_name' => 'Company Vault',
            'account_number' => 'CASH-01',
            'opening_balance' => 10000.00,
            'current_balance' => 15000.00,
            'is_default' => false,
            'enabled' => true,
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Jindal City Shop',
            'code' => 'JND01',
            'status' => 'active',
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Agri Supplies Ltd',
            'type' => 'vendor',
            'email' => 'sales@agrisupplies.test',
            'phone' => '9876543210',
        ]);
    }

    public function test_account_balance_report_page_loads_with_this_month_default(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.account-balance'));

        $response->assertOk();
        $response->assertSee('Account Balance Report');
        $response->assertSee('HDFC Primary Bank');
        $response->assertSee('Main Vault Cash');
        $response->assertSee('Actual Balance');
        $response->assertSee('Expected Balance');
        $response->assertSee('Read Only');
    }

    public function test_custom_range_across_months_filter(): void
    {
        $fromDate = Carbon::today()->subMonths(2)->startOfMonth()->toDateString();
        $toDate = Carbon::today()->subMonth()->endOfMonth()->toDateString();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.account-balance', [
                'period_mode' => 'custom',
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]));

        $response->assertOk();
        $response->assertSee('CUSTOM VIEW');
    }

    public function test_month_and_day_period_mode_filters(): void
    {
        $responseMonth = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.account-balance', ['period_mode' => 'month', 'month' => '2026-09']));
        $responseMonth->assertOk();
        $responseMonth->assertSee('MONTH VIEW');
        $responseMonth->assertSee('SEPTEMBER 2026');

        $responseDay = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.account-balance', ['period_mode' => 'day', 'date' => '2026-09-19']));
        $responseDay->assertOk();
        $responseDay->assertSee('DAY VIEW');
    }

    public function test_confirmed_statement_entries_affect_actual_balance_and_movements(): void
    {
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankAccount->id,
            'direction' => 'in',
            'amount' => 5000.00,
            'status' => 'cleared',
            'is_finalized' => true,
            'entry_date' => Carbon::today()->toDateString(),
            'transaction_date' => Carbon::today()->toDateString(),
            'description' => 'Confirmed Customer Deposit',
        ]);

        $service = app(AccountBalanceReportService::class);
        $report = $service->generateReport('this_month');

        $this->assertEquals(90000.00, $report->summary['actual_balance']); // 75k + 15k
        $this->assertCount(2, $report->accounts);
    }

    public function test_pending_unfinalized_statement_entries_affect_floating_not_actual(): void
    {
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankAccount->id,
            'direction' => 'in',
            'amount' => 12000.00,
            'status' => 'pending',
            'is_finalized' => false,
            'entry_date' => Carbon::today()->toDateString(),
            'transaction_date' => Carbon::today()->toDateString(),
            'description' => 'Uncleared Customer Transfer',
        ]);

        $service = app(AccountBalanceReportService::class);
        $report = $service->generateReport('this_month');

        $this->assertEquals(90000.00, $report->summary['actual_balance']); // 75k + 15k
        $this->assertEquals(12000.00, $report->summary['floating_in']);
        $this->assertEquals(102000.00, $report->summary['expected_balance']); // 90k + 12k
    }

    public function test_floating_in_and_floating_out_tracking(): void
    {
        // Pending Cheque Payment Request from Shop
        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'company_account_id' => $this->bankAccount->id,
            'payment_method' => 'cheque',
            'cheque_number' => 'CHQ-889900',
            'requested_amount' => 25000.00,
            'approved_amount' => 25000.00,
            'floating_amount' => 25000.00,
            'status' => 'pending',
            'cheque_status' => 'pending',
            'payment_date' => Carbon::today()->toDateString(),
        ]);

        // Outbound Statement Entry (Pending Transfer to Vendor)
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankAccount->id,
            'direction' => 'out',
            'amount' => 8000.00,
            'status' => 'pending',
            'is_finalized' => false,
            'entry_date' => Carbon::today()->toDateString(),
            'transaction_date' => Carbon::today()->toDateString(),
            'party_name' => 'Agri Supplies Ltd',
        ]);

        $service = app(AccountBalanceReportService::class);
        $report = $service->generateReport('this_month');

        $this->assertEquals(25000.00, $report->summary['floating_in']);
        $this->assertEquals(8000.00, $report->summary['floating_out']);
        $this->assertEquals(17000.00, $report->summary['net_floating']); // 25k - 8k
        $this->assertEquals(107000.00, $report->summary['expected_balance']); // 90k + 25k - 8k
    }

    public function test_floating_out_party_name_resolution_for_purchaser_and_vendor(): void
    {
        $purchaserUser = User::factory()->create([
            'name' => 'John Doe',
        ]);

        // Outbound statement entry to a purchaser
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankAccount->id,
            'direction' => 'out',
            'amount' => 15000.00,
            'status' => 'pending',
            'is_finalized' => false,
            'transaction_date' => Carbon::today()->toDateString(),
            'counterpart_type' => User::class,
            'counterpart_id' => $purchaserUser->id,
            'source' => 'purchaser_funding',
        ]);

        // Outbound statement entry to a vendor
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankAccount->id,
            'direction' => 'out',
            'amount' => 20000.00,
            'status' => 'pending',
            'is_finalized' => false,
            'transaction_date' => Carbon::today()->toDateString(),
            'counterpart_type' => Supplier::class,
            'counterpart_id' => $this->supplier->id,
            'source' => 'vendor_settlement',
        ]);

        $service = app(AccountBalanceReportService::class);
        $report = $service->generateReport('this_month');

        $this->assertEquals(35000.00, $report->summary['floating_out']);
        $toParties = array_column($report->floatingOut, 'to');

        $this->assertContains('John Doe (Purchaser)', $toParties);
        $this->assertContains('Agri Supplies Ltd (Vendor)', $toParties);
    }

    public function test_double_count_prevention_between_floating_in_and_shop_receivables(): void
    {
        // Shop owes 100,000 gross
        ShopDailyLedgerSnapshot::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::today()->toDateString(),
            'closing_shop_position' => 100000.00,
        ]);

        // Shop sent cheque of 40,000 pending clearance
        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'company_account_id' => $this->bankAccount->id,
            'payment_method' => 'cheque',
            'cheque_number' => 'CHQ-771122',
            'requested_amount' => 40000.00,
            'approved_amount' => 40000.00,
            'floating_amount' => 40000.00,
            'status' => 'pending',
            'cheque_status' => 'pending',
            'payment_date' => Carbon::today()->toDateString(),
        ]);

        $service = app(AccountBalanceReportService::class);
        $report = $service->generateReport('this_month');

        $this->assertEquals(40000.00, $report->summary['floating_in']);
        $this->assertEquals(60000.00, $report->summary['receivables']); // 100k gross - 40k floating = 60k net
        $this->assertEquals(130000.00, $report->summary['expected_balance']); // Actual (90k) + Floating In (40k) = 130k (Receivables NOT added to Expected)
    }

    public function test_vendor_payables_creation_and_period_movement(): void
    {
        PurchaseInvoice::factory()->for($this->supplier)->create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-2026-001',
            'amount' => 35000.00,
            'paid_amount' => 10000.00,
            'status' => 'approved',
        ]);

        $service = app(AccountBalanceReportService::class);
        $report = $service->generateReport('this_month');

        $this->assertEquals(25000.00, $report->summary['payables']); // 35k - 10k
        $this->assertNotEmpty($report->payables);
    }

    public function test_report_performs_zero_database_mutations_and_is_strictly_read_only(): void
    {
        $initialAccountsCount = CompanyAccount::count();
        $initialStatementsCount = CompanyAccountStatementEntry::count();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.account-balance'));

        $response->assertOk();

        $this->assertEquals($initialAccountsCount, CompanyAccount::count());
        $this->assertEquals($initialStatementsCount, CompanyAccountStatementEntry::count());
    }

    public function test_floating_items_are_strictly_date_filtered_and_sorted_latest_first(): void
    {
        $septStart = '2026-09-01';
        $septEnd = '2026-09-30';

        // Item from August (previous month - should NOT appear in Sept filter)
        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'company_account_id' => $this->bankAccount->id,
            'payment_method' => 'cheque',
            'cheque_number' => 'CHQ-AUG-01',
            'requested_amount' => 10000.00,
            'approved_amount' => 10000.00,
            'floating_amount' => 10000.00,
            'status' => 'pending',
            'cheque_status' => 'pending',
            'payment_date' => '2026-08-16',
        ]);

        // Item from Sept 10
        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'company_account_id' => $this->bankAccount->id,
            'payment_method' => 'cheque',
            'cheque_number' => 'CHQ-SEPT-10',
            'requested_amount' => 15000.00,
            'approved_amount' => 15000.00,
            'floating_amount' => 15000.00,
            'status' => 'pending',
            'cheque_status' => 'pending',
            'payment_date' => '2026-09-10',
        ]);

        // Item from Sept 25 (Later date in Sept)
        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'company_account_id' => $this->bankAccount->id,
            'payment_method' => 'cheque',
            'cheque_number' => 'CHQ-SEPT-25',
            'requested_amount' => 20000.00,
            'approved_amount' => 20000.00,
            'floating_amount' => 20000.00,
            'status' => 'pending',
            'cheque_status' => 'pending',
            'payment_date' => '2026-09-25',
        ]);

        $service = app(AccountBalanceReportService::class);
        $report = $service->generateReport('custom', $septStart, $septEnd, 'month', '2026-09');

        $this->assertEquals(35000.00, $report->summary['floating_in']); // 15k + 20k (10k from Aug excluded)
        $this->assertCount(2, $report->floatingIn);

        // Assert latest date appears first
        $this->assertEquals('2026-09-25', $report->floatingIn[0]['date']);
        $this->assertEquals('2026-09-10', $report->floatingIn[1]['date']);
    }

    public function test_admin_can_delete_unfinalized_statement_entry(): void
    {
        $stmt = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankAccount->id,
            'amount' => 5000.00,
            'direction' => 'out',
            'is_finalized' => 0,
            'status' => 'pending',
            'transaction_date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.cashbook.account-balance.statements.delete', $stmt));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cashbook_company_account_statement_entries', ['id' => $stmt->id]);
    }

    public function test_cannot_delete_finalized_statement_entry(): void
    {
        $stmt = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankAccount->id,
            'amount' => 5000.00,
            'direction' => 'out',
            'is_finalized' => 1,
            'status' => 'reconciled',
            'transaction_date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.cashbook.account-balance.statements.delete', $stmt));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('cashbook_company_account_statement_entries', ['id' => $stmt->id]);
    }

    public function test_admin_can_reject_payment_request(): void
    {
        $req = ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'company_account_id' => $this->bankAccount->id,
            'payment_method' => 'cheque',
            'cheque_number' => 'CHQ-REJ-99',
            'requested_amount' => 12000.00,
            'approved_amount' => 12000.00,
            'floating_amount' => 12000.00,
            'status' => 'pending',
            'cheque_status' => 'pending',
            'payment_date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.cashbook.account-balance.payment-requests.delete', $req->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('shop_invoice_payment_requests', [
            'id' => $req->id,
            'status' => 'rejected',
        ]);
    }
}
