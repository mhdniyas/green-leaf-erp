<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Cashbook\ShopFinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopFinancialReportActionCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'code' => 'CS01',
            'name' => 'Casio Boutique',
        ]);

        $this->profile = ShopLedgerProfile::query()->firstOrCreate(
            ['shop_id' => $this->shop->id],
            [
                'profile_template' => 'owned_standard',
                'name' => 'Casio Boutique',
                'code' => 'CS01',
                'slug' => 'av-casio-boutique',
                'status' => 'active',
                'payment_configuration' => [
                    'petty_cash' => [
                        'funding_source' => 'company_bank',
                        'default_bank_account_id' => 1,
                    ],
                ],
            ]
        );

        $this->bankAccount = CompanyAccount::query()->create([
            'name' => 'Main Operating Bank',
            'account_type' => 'bank',
            'enabled' => true,
            'current_balance' => 100000.00,
        ]);

        Account::query()->firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1500'], ['name' => 'Shop Petty Advances', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '5000'], ['name' => 'General Expenses', 'type' => 'expense', 'is_active' => true]);
    }

    /**
     * 1. Monthly report works
     */
    public function test_01_monthly_report_loads_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
                'period_mode' => 'month',
            ])
        );

        $response->assertOk();
        $response->assertSee('SHOP SUMMARY');
        $response->assertSee('COMPANY SETTLEMENT');
        $response->assertSee('CURRENT POSITION');
        $response->assertSee('PETTY');
        $response->assertSee('SHOP EXPENSES');
        $response->assertSee('OPERATIONS');

        $financialReport = $response->viewData('financialReport');
        $this->assertNotNull($financialReport);
        $this->assertSame('2026-09-01', $financialReport['period']['start']);
        $this->assertSame('2026-09-30', $financialReport['period']['end']);
    }

    /**
     * 2. Day view works
     */
    public function test_02_day_view_works(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
                'period_mode' => 'day',
                'day' => '2026-09-18',
            ])
        );

        $response->assertOk();
        $financialReport = $response->viewData('financialReport');
        $this->assertSame('2026-09-18', $financialReport['period']['start']);
        $this->assertSame('2026-09-18', $financialReport['period']['end']);
    }

    /**
     * 3. Day cannot leave selected month
     */
    public function test_03_day_cannot_leave_selected_month(): void
    {
        // Request August date while month is September 2026
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
                'period_mode' => 'day',
                'day' => '2026-08-15',
            ])
        );

        $response->assertOk();
        $financialReport = $response->viewData('financialReport');
        // Clamped to within September 2026
        $this->assertTrue(str_starts_with($financialReport['period']['start'], '2026-09'));
        $this->assertTrue(str_starts_with($financialReport['period']['end'], '2026-09'));
    }

    /**
     * 4. Custom range works
     */
    public function test_04_custom_range_works(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
                'period_mode' => 'custom',
                'custom_from' => '2026-09-05',
                'custom_to' => '2026-09-20',
            ])
        );

        $response->assertOk();
        $financialReport = $response->viewData('financialReport');
        $this->assertSame('2026-09-05', $financialReport['period']['start']);
        $this->assertSame('2026-09-20', $financialReport['period']['end']);
    }

    /**
     * 5. Custom range cannot leave selected month
     */
    public function test_05_custom_range_cannot_leave_selected_month(): void
    {
        // Custom dates out of range (e.g. 2026-08-01 to 2026-10-15)
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
                'period_mode' => 'custom',
                'custom_from' => '2026-08-01',
                'custom_to' => '2026-10-15',
            ])
        );

        $response->assertOk();
        $financialReport = $response->viewData('financialReport');
        // Clamped strictly to September 1 to September 30
        $this->assertSame('2026-09-01', $financialReport['period']['start']);
        $this->assertSame('2026-09-30', $financialReport['period']['end']);
    }

    /**
     * 6. Sales uses configured Sales entries
     * 7. Expenses use configured expense entries
     */
    public function test_06_and_07_sales_and_expenses_use_configured_entries(): void
    {
        $salesType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'daily_sales'],
            ['name' => 'Daily Sales', 'category' => 'sales', 'active' => true]
        );

        $rentType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'shop_rent'],
            ['name' => 'Shop Rent', 'category' => 'expense', 'active' => true]
        );

        // Configure entry settings
        ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'include_in_sales' => true,
            'include_in_expense' => false,
            'effective_from' => '2020-01-01',
            'enabled' => true,
        ]);

        ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'include_in_sales' => false,
            'include_in_expense' => true,
            'effective_from' => '2020-01-01',
            'enabled' => true,
        ]);

        // Post transactions in Sept 2026
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-10',
            'amount' => 100000.00,
            'direction' => 'in',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-09-10',
            'amount' => 20000.00,
            'direction' => 'out',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
        ]);

        $service = app(ShopFinancialReportService::class);
        $report = $service->generate($this->shop, '2026-09-01', '2026-09-30');

        $this->assertEquals(100000.00, $report['summary']['sales']);
        $this->assertEquals(20000.00, $report['summary']['expenses']);
        $this->assertEquals(20.0, $report['summary']['expenses_percentage']);
    }

    /**
     * 8. Petty funding is not counted as expense
     * 9. Petty Used is correct
     */
    public function test_08_and_09_petty_funding_not_expense_and_petty_used_correct(): void
    {
        $pettyFundingType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'company_to_petty'],
            ['name' => 'Company to Petty', 'category' => 'transfer', 'active' => true]
        );

        $teaExpenseType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'tea_expense'],
            ['name' => 'Tea Expense', 'category' => 'expense', 'active' => true]
        );

        ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $teaExpenseType->id,
            'include_in_expense' => true,
            'effective_from' => '2020-01-01',
            'enabled' => true,
        ]);

        // 1. Company funds petty: 10,000 (NOT an expense)
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $pettyFundingType->id,
            'entry_type_code' => 'company_to_petty',
            'business_date' => '2026-09-02',
            'amount' => 10000.00,
            'petty_delta' => 10000.00,
            'direction' => 'in',
            'funding_source' => 'company_bank',
            'status' => 'approved',
        ]);

        // 2. Petty used for tea: 1,500 (petty_delta = -1500)
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $teaExpenseType->id,
            'business_date' => '2026-09-05',
            'amount' => 1500.00,
            'petty_delta' => -1500.00,
            'direction' => 'out',
            'funding_source' => 'petty',
            'status' => 'approved',
        ]);

        $service = app(ShopFinancialReportService::class);
        $report = $service->generate($this->shop, '2026-09-01', '2026-09-30');

        $this->assertEquals(1500.00, $report['summary']['expenses']); // Only tea is expense, not company funding
        $this->assertEquals(1500.00, $report['summary']['petty_used']);
        $this->assertEquals('-₹1,500.00', $report['summary']['petty_used_formatted']);
        $this->assertEquals(10000.00, $report['petty']['funded']);
        $this->assertEquals(1500.00, $report['petty']['used']);
        $this->assertEquals(8500.00, $report['petty']['current']);
    }

    /**
     * 10. GL Bill summary is correct
     */
    public function test_10_gl_bill_summary_is_correct(): void
    {
        $order = ShopOrder::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-12',
            'state' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-TEST-GL-01',
            'business_date' => '2026-09-12',
            'subtotal' => 45000.00,
            'final_total' => 45000.00,
            'status' => 'finalized',
        ]);

        $service = app(ShopFinancialReportService::class);
        $report = $service->generate($this->shop, '2026-09-01', '2026-09-30');

        $this->assertEquals(45000.00, $report['summary']['gl_bills']);
    }

    /**
     * 11. Salary summary is correct
     */
    public function test_11_salary_summary_is_correct(): void
    {
        $salaryType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'salary'],
            ['name' => 'Staff Salary', 'category' => 'expense', 'active' => true]
        );

        ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryType->id,
            'include_in_expense' => true,
            'effective_from' => '2020-01-01',
            'enabled' => true,
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryType->id,
            'business_date' => '2026-09-05',
            'amount' => 35000.00,
            'direction' => 'out',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
        ]);

        $service = app(ShopFinancialReportService::class);
        $report = $service->generate($this->shop, '2026-09-01', '2026-09-30');

        $this->assertEquals(35000.00, $report['summary']['salary']);
        $this->assertEquals(35000.00, $report['summary']['expenses']);
    }

    /**
     * 12. Company Payable matches existing settlement source
     * 13. Received total is correct
     * 14. Payment mode split is correct
     * 15. Allocated is correct
     * 16. Unallocated is correct
     */
    public function test_12_to_16_settlement_and_payment_modes_are_correct(): void
    {
        $settlementType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'shop_cash_settlement'],
            ['name' => 'Cash Settlement', 'category' => 'settlement', 'active' => true]
        );

        // Daily obligation / due: 50,000
        $obligation = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $settlementType->id,
            'business_date' => '2026-09-15',
            'amount' => 50000.00,
            'settlement_delta' => 50000.00,
            'direction' => 'in',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
        ]);

        // Payment 1: Bank transfer ₹30,000 (approved, allocated ₹30,000)
        $payment1 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'payment_method' => 'bank',
            'payment_reference' => 'TXN-BANK-1',
            'payment_date' => '2026-09-16',
            'requested_amount' => 30000.00,
            'approved_amount' => 30000.00,
            'status' => 'approved',
        ]);

        ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment1->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $obligation->id,
            'amount' => 30000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        // Payment 2: UPI ₹10,000 (approved, unallocated)
        ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'payment_method' => 'upi',
            'payment_reference' => 'TXN-UPI-2',
            'payment_date' => '2026-09-17',
            'requested_amount' => 10000.00,
            'approved_amount' => 10000.00,
            'status' => 'approved',
        ]);

        $service = app(ShopFinancialReportService::class);
        $report = $service->generate($this->shop, '2026-09-01', '2026-09-30');

        $this->assertEquals(50000.00, $report['settlement']['due']);
        $this->assertEquals(40000.00, $report['settlement']['received']);
        $this->assertEquals(30000.00, $report['settlement']['allocated']);
        $this->assertEquals(10000.00, $report['settlement']['unallocated']);
        $this->assertEquals(20000.00, $report['settlement']['pending']); // 50,000 - 30,000 allocated

        // Check payment modes breakdown
        $modes = collect($report['payment_modes']);
        $bankMode = $modes->firstWhere('label', 'Bank Transfer');
        $upiMode = $modes->firstWhere('label', 'Paytm / UPI');

        $this->assertNotNull($bankMode);
        $this->assertEquals(30000.00, $bankMode['amount']);
        $this->assertNotNull($upiMode);
        $this->assertEquals(10000.00, $upiMode['amount']);
        $this->assertCount(2, $modes); // Only used modes shown
    }

    /**
     * 17. Shop Owes Company state works
     * 18. Company Owes Shop state works
     * 19. Settled state works
     */
    public function test_17_to_19_current_position_states(): void
    {
        $settlementType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'shop_cash_settlement'],
            ['name' => 'Cash Settlement', 'category' => 'settlement', 'active' => true]
        );

        $service = app(ShopFinancialReportService::class);

        // Case 1: Settled (no transactions)
        $report1 = $service->generate($this->shop, '2026-09-01', '2026-09-30');
        $this->assertSame('settled', $report1['position']['direction']);
        $this->assertEquals(0.00, $report1['position']['amount']);

        // Case 2: Shop owes company
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $settlementType->id,
            'business_date' => '2026-09-10',
            'amount' => 25000.00,
            'settlement_delta' => 25000.00,
            'direction' => 'in',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
        ]);

        $report2 = $service->generate($this->shop, '2026-09-01', '2026-09-30');
        $this->assertSame('shop_owes_company', $report2['position']['direction']);
        $this->assertEquals(25000.00, $report2['position']['amount']);

        // Case 3: Company owes shop (e.g. overpaid by ₹10,000)
        ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'payment_method' => 'bank',
            'payment_reference' => 'TXN-OVERPAY',
            'payment_date' => '2026-09-12',
            'requested_amount' => 35000.00,
            'approved_amount' => 35000.00,
            'status' => 'approved',
        ]);

        $report3 = $service->generate($this->shop, '2026-09-01', '2026-09-30');
        $this->assertSame('company_owes_shop', $report3['position']['direction']);
        $this->assertEquals(10000.00, $report3['position']['amount']);
    }

    /**
     * 20. Expense % of Sales works
     * 21. Zero Sales does not cause division error
     * 22. Expense funding split matches actual ledger funding
     */
    public function test_20_to_22_expense_percentage_zero_sales_and_funding_split(): void
    {
        $salesType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'daily_sales'],
            ['name' => 'Daily Sales', 'category' => 'sales', 'active' => true]
        );

        $rentType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'shop_rent'],
            ['name' => 'Shop Rent', 'category' => 'expense', 'active' => true]
        );

        ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'include_in_sales' => true,
            'effective_from' => '2020-01-01',
            'enabled' => true,
        ]);

        ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'include_in_expense' => true,
            'effective_from' => '2020-01-01',
            'enabled' => true,
        ]);

        // Case with 0 sales: Rent = 40,000 (30,000 Shop Balance + 10,000 Company Bank)
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-09-05',
            'amount' => 30000.00,
            'direction' => 'out',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-09-05',
            'amount' => 10000.00,
            'direction' => 'out',
            'funding_source' => 'company',
            'status' => 'approved',
        ]);

        $service = app(ShopFinancialReportService::class);
        $reportZeroSales = $service->generate($this->shop, '2026-09-01', '2026-09-30');

        $this->assertNull($reportZeroSales['expenses'][0]['percentage']); // Division by zero avoided, returns null (Blade renders '—')
        $this->assertEquals(40000.00, $reportZeroSales['expenses'][0]['amount']);
        $fundingSplits = collect($reportZeroSales['expenses'][0]['funding_split']);
        $shopBalSplit = $fundingSplits->firstWhere('source_key', 'shop_balance');
        $compSplit = $fundingSplits->firstWhere('source_key', 'company');
        $this->assertEquals(30000.00, $shopBalSplit['amount']);
        $this->assertEquals(10000.00, $compSplit['amount']);

        // Now post ₹5,00,000 sales -> Rent % of Sales should be 8.0%
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-10',
            'amount' => 500000.00,
            'direction' => 'in',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
        ]);

        $reportWithSales = $service->generate($this->shop, '2026-09-01', '2026-09-30');
        $this->assertEquals(8.0, $reportWithSales['expenses'][0]['percentage']);
    }

    /**
     * 23. Existing Settings button works
     * 24. Existing action buttons still work (modal forms rendered)
     * 25. Existing history View More pages still work
     */
    public function test_23_to_25_settings_action_modals_and_history_links(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', $this->profile->slug)
        );

        $response->assertOk();

        // 23. Settings button
        $response->assertSee(route('admin.cashbook.settings.shop', $this->profile->slug), false);

        // 24. Action triggers & modals
        $response->assertSee('showReceivePaymentModal');
        $response->assertSee('showFundPettyModal');
        $response->assertSee('showAddAdjustmentModal');

        // 25. History View More links
        $response->assertSee(route('admin.cashbook.shop.history.payments', $this->profile->slug), false);
        $response->assertSee(route('admin.cashbook.shop.history.petty', $this->profile->slug), false);
        $response->assertSee(route('admin.cashbook.shop.history.cheques', $this->profile->slug), false);
        $response->assertSee(route('admin.cashbook.shop.history.banking', $this->profile->slug), false);
        $response->assertSee(route('admin.cashbook.shop.history.adjustments', $this->profile->slug), false);
    }

    /**
     * Test Settlement Details page loads and displays authoritative breakdown.
     */
    public function test_26_settlement_details_page_loads_and_displays_breakdown(): void
    {
        $salesType = LedgerEntryType::query()->firstOrCreate(['code' => 'daily_sales'], ['name' => 'Cash Sales', 'category' => 'sales']);
        ShopLedgerEntrySetting::query()->firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
        ], [
            'display_name' => 'Cash Sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'is_company_payable' => true,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $tx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-10',
            'amount' => 50000.00,
            'settlement_delta' => 50000.00,
            'direction' => 'in',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
            'notes' => '10 Sep Daily Settlement',
        ]);

        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-999',
            'requested_amount' => 50000.00,
            'approved_amount' => 50000.00,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-10',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $tx->id,
            'amount' => 50000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.settlement-details', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
            ])
        );

        $response->assertOk();
        $response->assertSee('SETTLEMENT DETAILS');
        $response->assertSee('SETTLEMENT SUMMARY');
        $response->assertSee('How Period Due Was Calculated');
        $response->assertSee('Settlement Obligations');
        $response->assertSee('Payments Received');
        $response->assertSee('Allocation Details');
        $response->assertSee('FULLY SETTLED');
        $response->assertSee('PAY-999');
    }

    /**
     * Test Shop Owes Company vs Fully Settled status and unallocated payment behavior.
     */
    public function test_27_settlement_details_statuses_and_unallocated_distinction(): void
    {
        $salesType = LedgerEntryType::query()->firstOrCreate(['code' => 'daily_sales'], ['name' => 'Cash Sales', 'category' => 'sales']);
        ShopLedgerEntrySetting::query()->firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
        ], [
            'display_name' => 'Cash Sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'is_company_payable' => true,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        // Obligation of ₹1,00,000
        $tx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-12',
            'amount' => 100000.00,
            'settlement_delta' => 100000.00,
            'direction' => 'in',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
            'notes' => '12 Sep Sales Obligation',
        ]);

        // Payment of ₹1,00,000 received, but only ₹40,000 allocated
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-PARTIAL-1',
            'requested_amount' => 100000.00,
            'approved_amount' => 100000.00,
            'payment_method' => 'bank_transfer',
            'payment_date' => '2026-09-12',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $tx->id,
            'amount' => 40000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        $service = app(ShopFinancialReportService::class);
        $details = $service->getSettlementDetailsReport($this->shop, '2026-09-01', '2026-09-30');

        $this->assertEquals(100000.00, $details['summary']['period_due']);
        $this->assertEquals(40000.00, $details['summary']['allocated']);
        $this->assertEquals(60000.00, $details['summary']['remaining_due']);
        $this->assertEquals(60000.00, $details['summary']['unallocated']);
        $this->assertEquals('shop_owes_company', $details['summary']['status']);
        $this->assertEquals('SHOP OWES COMPANY', $details['summary']['status_label']);

        // Check view response
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.settlement-details', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
            ])
        );

        $response->assertOk();
        $response->assertSee('SHOP OWES COMPANY');
        $response->assertSee('Partially Settled');
        $response->assertSee('Partially Allocated');
    }

    /**
     * Test period parameters preserved across Action Center and Settlement Details.
     */
    public function test_28_period_parameters_custom_and_day_range_support(): void
    {
        // Custom period
        $customResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.settlement-details', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
                'period_mode' => 'custom',
                'from' => '2026-09-05',
                'to' => '2026-09-18',
            ])
        );
        $customResponse->assertOk();
        $customResponse->assertSee('05 Sep 2026 – 18 Sep 2026');

        // Day period
        $dayResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.settlement-details', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
                'period_mode' => 'day',
                'date' => '2026-09-18',
            ])
        );
        $dayResponse->assertOk();
        $dayResponse->assertSee('18 Sep 2026');
    }

    /**
     * Test authorization enforcement and cross-shop isolation.
     */
    public function test_29_authorization_and_shop_isolation(): void
    {
        $otherShop = Shop::factory()->create(['name' => 'Other Branch']);
        $otherProfile = ShopLedgerProfile::query()->create([
            'shop_id' => $otherShop->id,
            'name' => 'Other Branch',
            'code' => 'OB01',
            'slug' => 'other-branch',
        ]);

        $salesType = LedgerEntryType::query()->firstOrCreate(['code' => 'daily_sales'], ['name' => 'Cash Sales', 'category' => 'sales']);
        ShopLedgerTransaction::query()->create([
            'shop_id' => $otherShop->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-15',
            'amount' => 99999.00,
            'direction' => 'in',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
            'notes' => 'SECRET OTHER SHOP DATA',
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.settlement-details', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
            ])
        );

        $response->assertOk();
        $response->assertDontSee('SECRET OTHER SHOP DATA');
        $response->assertDontSee('99,999.00');

        // Guest is redirected/unauthorized
        auth()->logout();
        $guestResponse = $this->get(
            route('admin.cashbook.shop.settlement-details', [
                'shop' => $this->profile->slug,
            ])
        );
        $guestResponse->assertRedirect(route('login'));
    }

    /**
     * Test top card on Action Center displays correct breakdown and View Details link.
     */
    public function test_30_top_card_displays_breakdown_and_view_details_link(): void
    {
        $salesType = LedgerEntryType::query()->firstOrCreate(['code' => 'daily_sales'], ['name' => 'Cash Sales', 'category' => 'sales']);
        ShopLedgerEntrySetting::query()->firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
        ], [
            'display_name' => 'Cash Sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'is_company_payable' => true,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-08',
            'amount' => 75000.00,
            'settlement_delta' => 75000.00,
            'direction' => 'in',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
                'period_mode' => 'custom',
                'from' => '2026-09-01',
                'to' => '2026-09-15',
            ])
        );

        $response->assertOk();
        $response->assertSee('Balance After Allocation');
        $response->assertSee('Period Due');
        $response->assertSee('Allocated');
        $response->assertSee('Remaining');
        $response->assertSee('Total Sales');
        $response->assertSee('View Details');
        $response->assertSee('settlement-details');
        $response->assertSee('from=2026-09-01');
        $response->assertSee('to=2026-09-15');
    }

    /**
     * Test Company Owes Shop status when Allocated > Period Due.
     */
    public function test_31_company_owes_shop_scenario(): void
    {
        $salesType = LedgerEntryType::query()->firstOrCreate(['code' => 'daily_sales'], ['name' => 'Cash Sales', 'category' => 'sales']);
        ShopLedgerEntrySetting::query()->firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
        ], [
            'display_name' => 'Cash Sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'is_company_payable' => true,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $tx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-05',
            'amount' => 20000.00,
            'settlement_delta' => 20000.00,
            'direction' => 'in',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
        ]);

        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-OVER-1',
            'requested_amount' => 30000.00,
            'approved_amount' => 30000.00,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-05',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $tx->id,
            'amount' => 30000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        $service = app(ShopFinancialReportService::class);
        $details = $service->getSettlementDetailsReport($this->shop, '2026-09-01', '2026-09-30');

        $this->assertEquals('company_owes_shop', $details['summary']['status']);
        $this->assertEquals('COMPANY OWES SHOP', $details['summary']['status_label']);
    }

    /**
     * Test Period Due breakdown, Obligations, Payments, and Allocations mathematical totals.
     */
    public function test_32_mathematical_totals_and_breakdowns(): void
    {
        $salesType = LedgerEntryType::query()->firstOrCreate(['code' => 'daily_sales'], ['name' => 'Cash Sales', 'category' => 'sales']);
        ShopLedgerEntrySetting::query()->firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
        ], [
            'display_name' => 'Cash Sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'is_company_payable' => true,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $tx1 = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-01',
            'amount' => 30000.00,
            'settlement_delta' => 30000.00,
            'direction' => 'in',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
        ]);

        $tx2 = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-02',
            'amount' => 20000.00,
            'settlement_delta' => 20000.00,
            'direction' => 'in',
            'funding_source' => 'shop_balance',
            'status' => 'approved',
        ]);

        $payment1 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-MATH-1',
            'requested_amount' => 30000.00,
            'approved_amount' => 30000.00,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-01',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        $payment2 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-MATH-2',
            'requested_amount' => 20000.00,
            'approved_amount' => 20000.00,
            'payment_method' => 'bank_transfer',
            'payment_date' => '2026-09-02',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment1->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $tx1->id,
            'amount' => 30000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment2->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $tx2->id,
            'amount' => 15000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        $service = app(ShopFinancialReportService::class);
        $details = $service->getSettlementDetailsReport($this->shop, '2026-09-01', '2026-09-30');

        // Due: 30,000 + 20,000 = 50,000
        $this->assertEquals(50000.00, $details['summary']['period_due']);
        // Received: 30,000 + 20,000 = 50,000
        $this->assertEquals(50000.00, $details['summary']['received']);
        // Allocated: 30,000 + 15,000 = 45,000
        $this->assertEquals(45000.00, $details['summary']['allocated']);
        // Unallocated: 50,000 - 45,000 = 5,000
        $this->assertEquals(5000.00, $details['summary']['unallocated']);
        // Remaining Due: 50,000 - 45,000 = 5,000
        $this->assertEquals(5000.00, $details['summary']['remaining_due']);

        // Check sum of obligations equals sum of transactions
        $obligationsSum = collect($details['obligations'])->sum('amount');
        $this->assertEquals(50000.00, $obligationsSum);

        // Check sum of payments equals total received
        $paymentsSum = collect($details['payments'])->sum('amount');
        $this->assertEquals(50000.00, $paymentsSum);

        // Check sum of allocations equals total allocated
        $allocationsSum = collect($details['allocations'])->sum('amount');
        $this->assertEquals(45000.00, $allocationsSum);
    }

    /**
     * 21. Header contains Receive Payment, Fund Petty, and Settings actions
     */
    public function test_21_header_contains_receive_payment_fund_petty_and_settings_actions(): void
    {
        $this->profile->update([
            'payment_configuration' => [
                'petty' => [
                    'enabled' => true,
                    'allow_company_to_petty' => true,
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.shop.overview', [
                'shop' => $this->profile->slug,
                'month' => '2026-09',
                'period_mode' => 'month',
            ])
        );

        $response->assertOk();
        $response->assertSee('Receive Payment');
        $response->assertSee('Fund Petty');
        $response->assertSee('Settings');
        $response->assertSee('openReceivePayment()');
        $response->assertSee('showFundPettyModal = true');
        $response->assertSee(route('admin.cashbook.settings.shop', $this->profile->slug));
    }
}
