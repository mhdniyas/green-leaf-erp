<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Cashbook\ShopSalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopSalesReportTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop1;

    private Shop $shop2;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop1 = Shop::factory()->create(['name' => 'Shop Alpha', 'code' => 'SHOP-A']);
        $this->shop2 = Shop::factory()->create(['name' => 'Shop Beta', 'code' => 'SHOP-B']);

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
    }

    public function test_payment_setting_to_sales_mapping(): void
    {
        $salesType = LedgerEntryType::create([
            'code' => 'custom_sales',
            'name' => 'Custom Sales',
            'category' => 'income',
            'active' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $salesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'include_in_sales' => true,
            'sales_report_bucket' => 'sales',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $salesType->id,
            'amount' => 1500.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $this->assertEquals(1500.00, $report['summary']['total_sales']);
        $this->assertEquals(1500.00, $report['summary']['net_total']);
    }

    public function test_payment_setting_to_rent_mapping(): void
    {
        $rentType = LedgerEntryType::create([
            'code' => 'rent_expense',
            'name' => 'Shop Rent',
            'category' => 'expense',
            'active' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $rentType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'include_in_expense' => true,
            'sales_report_bucket' => 'rent',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $rentType->id,
            'amount' => 800.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'affects_expense' => true,
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $this->assertEquals(800.00, $report['summary']['total_rent']);
        $this->assertEquals(0.00, $report['summary']['total_other_expense']);
        $this->assertEquals(800.00, $report['summary']['total_expenses']);
        $this->assertEquals(-800.00, $report['summary']['net_total']);
    }

    public function test_payment_setting_to_purchase_mapping(): void
    {
        $purchaseType = LedgerEntryType::create([
            'code' => 'cash_purchase',
            'name' => 'Cash Purchase',
            'category' => 'expense',
            'active' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $purchaseType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'include_in_expense' => true,
            'sales_report_bucket' => 'purchase',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $purchaseType->id,
            'amount' => 1200.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'affects_expense' => true,
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $this->assertEquals(1200.00, $report['summary']['total_purchase']);
        $this->assertEquals(0.00, $report['summary']['total_other_expense']);
    }

    public function test_payment_setting_to_other_expense_mapping(): void
    {
        $expenseType = LedgerEntryType::create([
            'code' => 'electricity',
            'name' => 'Electricity Bill',
            'category' => 'expense',
            'active' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $expenseType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'include_in_expense' => true,
            'sales_report_bucket' => 'other_expense',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $expenseType->id,
            'amount' => 450.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'affects_expense' => true,
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $this->assertEquals(450.00, $report['summary']['total_other_expense']);
        $this->assertEquals(0.00, $report['summary']['total_rent']);
        $this->assertEquals(0.00, $report['summary']['total_purchase']);
    }

    public function test_payment_setting_to_ignore_mapping(): void
    {
        $transferType = LedgerEntryType::create([
            'code' => 'company_to_petty',
            'name' => 'Company → Petty',
            'category' => 'transfer',
            'active' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $transferType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'ignore',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $transferType->id,
            'amount' => 5000.00,
            'direction' => 'income',
            'funding_source' => 'company',
            'petty_delta' => 5000.00,
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $this->assertEquals(0.00, $report['summary']['total_sales']);
        $this->assertEquals(0.00, $report['summary']['total_expenses']);
    }

    public function test_report_is_not_label_driven(): void
    {
        $type = LedgerEntryType::create([
            'code' => 'custom_type',
            'name' => 'Generic Code Name',
            'category' => 'expense',
            'active' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $type->id,
            'display_name' => 'Random Name',
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'purchase',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $type->id,
            'amount' => 2500.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'affects_expense' => true,
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        // Configured report bucket is 'purchase', so it MUST appear under Purchase, not Other Expense
        $this->assertEquals(2500.00, $report['summary']['total_purchase']);
        $this->assertEquals(0.00, $report['summary']['total_other_expense']);
    }

    public function test_gl_bill_double_count_prevention(): void
    {
        $purchaseType = LedgerEntryType::create([
            'code' => 'cash_purchase',
            'name' => 'Cash Purchase',
            'category' => 'expense',
            'active' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $purchaseType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'purchase',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $purchaseType->id,
            'amount' => 1000.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        // GL Bill in system
        $order = ShopOrder::factory()->create(['shop_id' => $this->shop1->shop_id]);
        ShopInvoice::create([
            'shop_id' => $this->shop1->shop_id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-999',
            'business_date' => '2026-09-15',
            'final_total' => 3000.00,
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        // Cash purchase is 1000, GL bill is 3000 reference. Total cash purchases in report must be 1000!
        $this->assertEquals(1000.00, $report['summary']['total_purchase']);
        $this->assertEquals(3000.00, $report['summary']['gl_bills_total']);
        $this->assertEquals(1000.00, $report['summary']['total_expenses']);
    }

    public function test_exclusions_from_other_expenses(): void
    {
        $rentType = LedgerEntryType::create(['code' => 'rent_expense', 'name' => 'Rent', 'category' => 'expense']);
        $purchaseType = LedgerEntryType::create(['code' => 'cash_purchase', 'name' => 'Cash Purchase', 'category' => 'expense']);
        $otherType = LedgerEntryType::create(['code' => 'food', 'name' => 'Food', 'category' => 'expense']);

        ShopLedgerEntrySetting::create(['shop_id' => $this->shop1->shop_id, 'entry_type_id' => $rentType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true, 'sales_report_bucket' => 'rent']);
        ShopLedgerEntrySetting::create(['shop_id' => $this->shop1->shop_id, 'entry_type_id' => $purchaseType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true, 'sales_report_bucket' => 'purchase']);
        ShopLedgerEntrySetting::create(['shop_id' => $this->shop1->shop_id, 'entry_type_id' => $otherType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true, 'sales_report_bucket' => 'other_expense']);

        ShopLedgerTransaction::create(['shop_id' => $this->shop1->shop_id, 'business_date' => '2026-09-10', 'entry_type_id' => $rentType->id, 'amount' => 500.00, 'direction' => 'expense', 'funding_source' => 'sales', 'status' => 'approved']);
        ShopLedgerTransaction::create(['shop_id' => $this->shop1->shop_id, 'business_date' => '2026-09-10', 'entry_type_id' => $purchaseType->id, 'amount' => 700.00, 'direction' => 'expense', 'funding_source' => 'sales', 'status' => 'approved']);
        ShopLedgerTransaction::create(['shop_id' => $this->shop1->shop_id, 'business_date' => '2026-09-10', 'entry_type_id' => $otherType->id, 'amount' => 200.00, 'direction' => 'expense', 'funding_source' => 'sales', 'status' => 'approved']);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $this->assertEquals(500.00, $report['summary']['total_rent']);
        $this->assertEquals(700.00, $report['summary']['total_purchase']);
        $this->assertEquals(200.00, $report['summary']['total_other_expense']);
        $this->assertEquals(1400.00, $report['summary']['total_expenses']);
    }

    public function test_shop_isolation_and_date_filtering(): void
    {
        $salesType = LedgerEntryType::create(['code' => 'cash_sales', 'name' => 'Cash Sales', 'category' => 'income']);

        ShopLedgerEntrySetting::create(['shop_id' => $this->shop1->shop_id, 'entry_type_id' => $salesType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true, 'sales_report_bucket' => 'sales']);
        ShopLedgerEntrySetting::create(['shop_id' => $this->shop2->shop_id, 'entry_type_id' => $salesType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true, 'sales_report_bucket' => 'sales']);

        // Shop 1 inside date range
        ShopLedgerTransaction::create(['shop_id' => $this->shop1->shop_id, 'business_date' => '2026-09-10', 'entry_type_id' => $salesType->id, 'amount' => 1000.00, 'direction' => 'income', 'funding_source' => 'sales', 'status' => 'approved']);

        // Shop 1 outside date range
        ShopLedgerTransaction::create(['shop_id' => $this->shop1->shop_id, 'business_date' => '2026-10-05', 'entry_type_id' => $salesType->id, 'amount' => 5000.00, 'direction' => 'income', 'funding_source' => 'sales', 'status' => 'approved']);

        // Shop 2 inside date range
        ShopLedgerTransaction::create(['shop_id' => $this->shop2->shop_id, 'business_date' => '2026-09-10', 'entry_type_id' => $salesType->id, 'amount' => 9999.00, 'direction' => 'income', 'funding_source' => 'sales', 'status' => 'approved']);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $this->assertEquals(1000.00, $report['summary']['total_sales']);
    }

    public function test_export_pdf_excel_csv_routes(): void
    {
        $salesType = LedgerEntryType::create(['code' => 'cash_sales', 'name' => 'Cash Sales', 'category' => 'income']);
        ShopLedgerEntrySetting::create(['shop_id' => $this->shop1->shop_id, 'entry_type_id' => $salesType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true, 'sales_report_bucket' => 'sales']);
        ShopLedgerTransaction::create(['shop_id' => $this->shop1->shop_id, 'business_date' => '2026-09-10', 'entry_type_id' => $salesType->id, 'amount' => 1500.00, 'direction' => 'income', 'funding_source' => 'sales', 'status' => 'approved']);

        // PDF Export
        $pdfResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.sales-report.pdf', ['shop' => $this->shop1->shop_id, 'month' => '2026-09']));
        $pdfResponse->assertStatus(200);
        $pdfResponse->assertHeader('content-type', 'application/pdf');

        // Excel Export
        $excelResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.sales-report.excel', ['shop' => $this->shop1->shop_id, 'month' => '2026-09']));
        $excelResponse->assertStatus(200);

        // CSV Export
        $csvResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.sales-report.csv', ['shop' => $this->shop1->shop_id, 'month' => '2026-09']));
        $csvResponse->assertStatus(200);
        $csvResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_default_shop_route_renders_dedicated_sales_report_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->shop1->shop_id, 'month' => '2026-09']));

        $response->assertStatus(200);
        $response->assertViewIs('admin.cashbook.shops.sales-report');
        $response->assertSee('Sales &amp; Financial Execution Report', false);
        $response->assertSee('Cashbook Overview');
        $response->assertSee('Sales (₹)', false);
        $response->assertSee('Rent (₹)', false);
        $response->assertSee('Cash Purchase (₹)', false);
        $response->assertSee('Other Expense (₹)', false);
        $response->assertSee('Net Balance (₹)', false);

        // Assert operations components/modals are NOT loaded on default sales report page
        $response->assertDontSee('Manual Expense Allocation');
        $response->assertDontSee('Add Settlement Adjustment');
    }

    public function test_overview_route_renders_cashbook_operations_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.overview', ['shop' => $this->shop1->shop_id, 'month' => '2026-09']));

        $response->assertStatus(200);
        $response->assertViewIs('admin.cashbook.shops.overview');
        $response->assertSee('Sales Report');
        $response->assertSee('OPERATIONS');
        $response->assertSee('COMPANY SETTLEMENT');
    }

    public function test_time_sort_upto_today_and_upto_yesterday_shortcuts_work(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->shop1->shop_id]));

        $response->assertStatus(200);
        $response->assertSee('Today');
        $response->assertSee('Yesterday');
        $response->assertSee('Up to Today');
        $response->assertSee('Up to Yesterday');
    }
}
