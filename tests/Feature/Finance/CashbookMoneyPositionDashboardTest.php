<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookMoneyPositionDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $sana;

    private Shop $casio;

    private CompanyAccount $kotakBank;

    private CompanyAccount $hdfcBank;

    private CompanyAccount $cashBox;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashSalesType;

    private LedgerEntryType $shopPaidCompanyType;

    private DailyLedgerService $dailyLedgerService;

    private CompanyPaymentReconciliationService $reconciliationService;

    private CompanyMoneyPositionService $moneyPositionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        $this->sana = Shop::factory()->create(['name' => 'Sana', 'code' => 'SANA', 'status' => 'active']);
        $this->casio = Shop::factory()->create(['name' => 'Casio', 'code' => 'CASIO', 'status' => 'active']);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => '9988776655',
            'account_type' => 'bank',
            'current_balance' => 520000.00,
            'enabled' => true,
        ]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC Bank',
            'bank_name' => 'HDFC Bank Ltd',
            'account_number' => '1122334455',
            'account_type' => 'bank',
            'current_balance' => 150000.00,
            'enabled' => true,
        ]);

        $this->cashBox = CompanyAccount::create([
            'name' => 'Main Cash Box',
            'bank_name' => 'Main Company Cash Box',
            'account_number' => 'CASH-MAIN',
            'account_type' => 'cash',
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);

        $this->paytmType = LedgerEntryType::firstOrCreate(
            ['code' => 'paytm_sales'],
            [
                'name' => 'Paytm / UPI Collection',
                'category' => 'income',
                'display_order' => 10,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->cardType = LedgerEntryType::firstOrCreate(
            ['code' => 'card_sales'],
            [
                'name' => 'Card / POS Collection',
                'category' => 'income',
                'display_order' => 11,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->cashSalesType = LedgerEntryType::firstOrCreate(
            ['code' => 'cash_sales'],
            [
                'name' => 'Cash Sales',
                'category' => 'income',
                'display_order' => 1,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->shopPaidCompanyType = LedgerEntryType::firstOrCreate(
            ['code' => 'shop_paid_company'],
            [
                'name' => 'Shop Remittance to Company',
                'category' => 'expense',
                'display_order' => 99,
                'active' => true,
                'is_system' => true,
            ]
        );

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->kotakBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'settlement_behavior' => 'increase',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->cashSalesType->id,
            'company_account_id' => $this->cashBox->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'settlement_behavior' => 'increase',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->shopPaidCompanyType->id,
            'company_account_id' => $this->kotakBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => false,
            'include_in_income' => false,
            'include_in_expense' => true,
            'include_in_pl' => false,
            'settlement_behavior' => 'decrease',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        $this->dailyLedgerService = app(DailyLedgerService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
        $this->moneyPositionService = app(CompanyMoneyPositionService::class);
    }

    public function test_account_position_calculates_verified_pending_and_projected(): void
    {
        // Sana records Paytm collection ₹48,962 and approves it
        $record = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->dailyLedgerService->approveEntry($record['transaction'], $this->admin->id);

        $position = $this->moneyPositionService->getAccountPosition($this->kotakBank);

        $this->assertSame(520000.00, $position['verified_balance']);
        $this->assertSame(48962.00, $position['pending_in']);
        $this->assertSame(0.00, $position['pending_out']);
        $this->assertSame(48962.00, $position['net_pending']);
        $this->assertSame(568962.00, $position['projected_position']);
        $this->assertSame(1, $position['pending_count']);
    }

    public function test_pending_outgoing_amount_subtracts_correctly(): void
    {
        // Create an unfinalized outgoing statement entry
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->kotakBank->id,
            'transaction_date' => '2026-08-22',
            'direction' => 'out',
            'amount' => 20000.00,
            'reference' => 'SUPPLIER-PENDING',
            'source' => 'supplier_payment',
            'status' => 'unmatched',
            'is_finalized' => false,
            'matched_amount' => 0.00,
        ]);

        $position = $this->moneyPositionService->getAccountPosition($this->kotakBank);

        $this->assertSame(520000.00, $position['verified_balance']);
        $this->assertSame(0.00, $position['pending_in']);
        $this->assertSame(20000.00, $position['pending_out']);
        $this->assertSame(-20000.00, $position['net_pending']);
        $this->assertSame(500000.00, $position['projected_position']);
    }

    public function test_superseded_and_duplicate_rows_are_excluded_from_pending(): void
    {
        // Create an unfinalized statement entry marked as superseded
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->kotakBank->id,
            'transaction_date' => '2026-08-22',
            'direction' => 'in',
            'amount' => 10000.00,
            'reference' => 'VOIDED-STMT',
            'source' => 'shop_collection',
            'status' => 'superseded',
            'is_finalized' => false,
            'matched_amount' => 0.00,
        ]);

        // Create an unfinalized statement entry marked as duplicate_flagged
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->kotakBank->id,
            'transaction_date' => '2026-08-22',
            'direction' => 'in',
            'amount' => 5000.00,
            'reference' => 'DUPLICATE-STMT',
            'source' => 'bank_feed',
            'status' => 'duplicate_flagged',
            'is_finalized' => false,
            'matched_amount' => 0.00,
        ]);

        $position = $this->moneyPositionService->getAccountPosition($this->kotakBank);

        // Neither superseded (10k) nor duplicate_flagged (5k) are included
        $this->assertSame(0.00, $position['net_pending']);
        $this->assertSame(520000.00, $position['projected_position']);
    }

    public function test_finalized_rows_are_excluded_from_pending_and_contribute_to_reconciliation(): void
    {
        // Finalized reconciled statement
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->kotakBank->id,
            'transaction_date' => '2026-08-22',
            'direction' => 'in',
            'amount' => 40000.00,
            'reference' => 'RECON-101',
            'source' => 'shop_collection',
            'status' => 'reconciled',
            'is_finalized' => true,
            'matched_amount' => 40000.00,
        ]);

        // Unfinalized pending statement
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->kotakBank->id,
            'transaction_date' => '2026-08-22',
            'direction' => 'in',
            'amount' => 10000.00,
            'reference' => 'PENDING-102',
            'source' => 'shop_collection',
            'status' => 'unmatched',
            'is_finalized' => false,
            'matched_amount' => 0.00,
        ]);

        $position = $this->moneyPositionService->getAccountPosition($this->kotakBank);

        $this->assertSame(10000.00, $position['net_pending']);
        // Reconciliation % = (40000 / (40000 + 10000)) * 100 = 80.0%
        $this->assertSame(80.0, $position['reconciliation_percentage']);
    }

    public function test_zero_denominator_returns_100_percent_reconciliation(): void
    {
        // Account with no statement activity
        $position = $this->moneyPositionService->getAccountPosition($this->hdfcBank);

        $this->assertSame(100.0, $position['reconciliation_percentage']);
        $this->assertSame(0, $position['pending_count']);
    }

    public function test_cash_with_shops_breakdown_derives_physical_unremitted_cash(): void
    {
        // Sana records cash sales of 16,550 and approves it (pending handover)
        $sanaCash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 16550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($sanaCash['transaction'], $this->admin->id);

        $summary = $this->moneyPositionService->getMoneyPositionSummary('2026-08-22');

        $this->assertSame(16550.00, $summary['cash_with_shops']['total_cash_with_shops']);
        $this->assertSame(50000.00, $summary['company_cash']['total_verified']);
        $this->assertSame(16550.00, $summary['company_cash']['total_pending']);
        $this->assertSame(66550.00, $summary['company_cash']['total_projected']);

        $sanaRow = collect($summary['cash_with_shops']['shops'])->firstWhere('shop_id', $this->sana->id);
        $this->assertNotNull($sanaRow);
        $this->assertSame(16550.00, $sanaRow['cash_with_shop']);
        $this->assertSame('WITH SHOP', $sanaRow['status']);
    }

    public function test_cash_verification_moves_cash_with_shop_to_verified_company_cash(): void
    {
        // 1. Sana records and approves cash collection ₹16,550
        $sanaCash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 16550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($sanaCash['transaction'], $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $sanaCash['transaction']->id)
            ->firstOrFail();

        // 2. Admin verifies physical handover
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);

        // 3. Money position reflects verified cash in company cash box
        $summary = $this->moneyPositionService->getMoneyPositionSummary('2026-08-22');

        $this->assertSame(66550.00, $summary['company_cash']['total_verified']);
        $this->assertSame(0.00, $summary['company_cash']['total_pending']);
        $this->assertSame(0.00, $summary['cash_with_shops']['total_cash_with_shops']);
    }

    public function test_floating_cheques_segregates_active_and_rejected(): void
    {
        $invoice = ShopInvoice::factory()->create(['shop_id' => $this->sana->id]);

        // Active floating cheque
        ShopInvoicePaymentRequest::create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->sana->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => 'SUB-1001',
            'request_type' => 'custom',
            'payment_method' => 'cheque',
            'payment_date' => '2026-08-22',
            'requested_amount' => 35000.00,
            'floating_amount' => 35000.00,
            'status' => 'pending',
            'cheque_status' => 'pending',
        ]);

        // Rejected cheque
        ShopInvoicePaymentRequest::create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->sana->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => 'SUB-1002',
            'request_type' => 'custom',
            'payment_method' => 'cheque',
            'payment_date' => '2026-08-22',
            'requested_amount' => 12000.00,
            'floating_amount' => 0.00,
            'status' => 'rejected',
            'cheque_status' => 'rejected',
        ]);

        $summary = $this->moneyPositionService->getFloatingChequesSummary('2026-08-22');

        $this->assertSame(35000.00, $summary['total_floating']);
        $this->assertSame(12000.00, $summary['rejected_total']);
        $this->assertSame(1, $summary['floating_count']);
    }

    public function test_bank_account_show_page_renders_account_position(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.bank-accounts.show', $this->kotakBank));

        $response->assertOk()
            ->assertSee('Verified Balance')
            ->assertSee('Pending Verification')
            ->assertSee('Projected Position')
            ->assertSee('Needs Attention');
    }

    public function test_finance_overview_page_renders_all_money_position_sections(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance'));

        $response->assertOk()
            ->assertSee('Verified Company Money')
            ->assertSee('Expected / In-Transit Funds')
            ->assertSee('Company Bank')
            ->assertSee('Cash With Shops')
            ->assertSee('Kotak Bank')
            ->assertSee('520,000.00');
    }
}
