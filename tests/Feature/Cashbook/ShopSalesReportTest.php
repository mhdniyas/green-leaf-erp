<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Cashbook\PaymentsSettings\ShopPaymentsReportConfigService;
use App\Services\Cashbook\ShopSalesReportService;
use Carbon\Carbon;
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
        $response->assertSee('Purchase (₹)', false);
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

    public function test_cash_purchase_visible_label_becomes_purchase(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->shop1->shop_id, 'month' => '2026-09']));

        $response->assertStatus(200);
        $response->assertSee('Purchase (₹)', false);
        $response->assertDontSee('Cash Purchase (₹)', false);
    }

    public function test_sales_daily_breakdown_sums_exactly_to_sales_table_value(): void
    {
        $type = LedgerEntryType::create(['code' => 'cash_sales_test', 'name' => 'Cash Sales Test', 'category' => 'income']);
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $type->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'sales',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $type->id,
            'amount' => 12500.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $dailyRow = collect($report['daily_rows'])->firstWhere('date', '2026-09-15');
        $this->assertNotNull($dailyRow);
        $this->assertEquals(12500.00, $dailyRow['sales']);

        $salesBreakdown = $dailyRow['breakdowns'][ShopPaymentsReportConfigService::HEADING_TOTAL_SALES];
        $this->assertEquals(12500.00, $salesBreakdown['total']);

        $sourcesSum = array_sum(array_column($salesBreakdown['sources'], 'total'));
        $this->assertEquals(12500.00, $sourcesSum);
    }

    public function test_rent_breakdown_sums_to_rent_value(): void
    {
        $rentType = LedgerEntryType::create(['code' => 'shop_rent_test', 'name' => 'Shop Rent Test', 'category' => 'expense']);
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $rentType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'rent',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $rentType->id,
            'amount' => 4000.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $dailyRow = collect($report['daily_rows'])->firstWhere('date', '2026-09-15');
        $this->assertEquals(4000.00, $dailyRow['rent']);

        $rentBreakdown = $dailyRow['breakdowns'][ShopPaymentsReportConfigService::HEADING_RENT_EXPENSE];
        $this->assertEquals(4000.00, $rentBreakdown['total']);
    }

    public function test_purchase_breakdown_sums_to_purchase_value(): void
    {
        $purchaseType = LedgerEntryType::create(['code' => 'cash_purchase_test', 'name' => 'Cash Purchase Test', 'category' => 'expense']);
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
            'amount' => 15600.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $dailyRow = collect($report['daily_rows'])->firstWhere('date', '2026-09-15');
        $this->assertEquals(15600.00, $dailyRow['purchase']);

        $purchaseBreakdown = $dailyRow['breakdowns'][ShopPaymentsReportConfigService::HEADING_CASH_PURCHASE];
        $this->assertEquals(15600.00, $purchaseBreakdown['total']);
    }

    public function test_other_expenses_breakdown_sums_to_other_expenses_value(): void
    {
        $expenseType = LedgerEntryType::create(['code' => 'mess_expense_test', 'name' => 'Mess Expense Test', 'category' => 'expense']);
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $expenseType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'other_expense',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $expenseType->id,
            'amount' => 4260.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $dailyRow = collect($report['daily_rows'])->firstWhere('date', '2026-09-15');
        $this->assertEquals(4260.00, $dailyRow['other_expense']);

        $otherBreakdown = $dailyRow['breakdowns'][ShopPaymentsReportConfigService::HEADING_OTHER_EXPENSE];
        $this->assertEquals(4260.00, $otherBreakdown['total']);
    }

    public function test_header_source_expands_into_its_categories(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop1->shop_id,
            'name' => 'Sales Header',
            'code' => 'sales_header',
            'type' => 'income',
            'display_order' => 1,
        ]);

        $type1 = LedgerEntryType::create(['code' => 'cash_sales_h1', 'name' => 'Cash Sales', 'category' => 'income']);
        $type2 = LedgerEntryType::create(['code' => 'paytm_sales_h2', 'name' => 'Paytm', 'category' => 'income']);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $type1->id,
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'sales',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $type2->id,
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'sales',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $type1->id,
            'amount' => 12000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $type2->id,
            'amount' => 10450.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $dailyRow = collect($report['daily_rows'])->firstWhere('date', '2026-09-15');
        $salesBreakdown = $dailyRow['breakdowns'][ShopPaymentsReportConfigService::HEADING_TOTAL_SALES];

        $headerSource = collect($salesBreakdown['sources'])->firstWhere('type', 'header');
        $this->assertNotNull($headerSource);
        $this->assertEquals('Sales Header', $headerSource['name']);
        $this->assertEquals(22450.00, $headerSource['total']);
        $this->assertCount(2, $headerSource['categories']);
    }

    public function test_direct_non_header_category_appears_independently(): void
    {
        $type = LedgerEntryType::create(['code' => 'other_sales_ind', 'name' => 'Other Sales Category', 'category' => 'income']);
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $type->id,
            'header_group_id' => null,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'sales',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $type->id,
            'amount' => 2000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $dailyRow = collect($report['daily_rows'])->firstWhere('date', '2026-09-15');
        $salesBreakdown = $dailyRow['breakdowns'][ShopPaymentsReportConfigService::HEADING_TOTAL_SALES];

        $categorySource = collect($salesBreakdown['sources'])->firstWhere('type', 'category');
        $this->assertNotNull($categorySource);
        $this->assertEquals('Other Sales Category', $categorySource['name']);
        $this->assertEquals(2000.00, $categorySource['total']);
    }

    public function test_product_total_expands_into_products_when_product_tagging_exists(): void
    {
        $product1 = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOM-1', 'unit' => 'kg', 'is_active' => true]);
        $product2 = Product::factory()->create(['name' => 'Onion', 'sku' => 'ONI-1', 'unit' => 'kg', 'is_active' => true]);

        $cart = PurchaserCart::create([
            'cart_number' => 'CART-001',
            'user_id' => $this->admin->id,
            'purchase_source' => 'shop',
            'destination_shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'status' => 'approved',
        ]);

        PurchaseInvoice::factory()->create([
            'shop_id' => $this->shop1->shop_id,
            'purchaser_cart_id' => $cart->id,
            'purchase_source' => 'shop',
            'invoice_number' => 'PINV-001',
            'amount' => 4200.00,
            'status' => 'approved',
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product1->id,
            'quantity' => 10,
            'unit_price' => 240.00,
            'line_total' => 2400.00,
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product2->id,
            'quantity' => 10,
            'unit_price' => 180.00,
            'line_total' => 1800.00,
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $purchaseBreakdown = $report['summary_breakdowns'][ShopPaymentsReportConfigService::HEADING_CASH_PURCHASE];
        $productSource = collect($purchaseBreakdown['sources'])->firstWhere('type', 'product_total');

        $this->assertNotNull($productSource);
        $this->assertNotEmpty($productSource['products']);

        $tomato = collect($productSource['products'])->firstWhere('name', 'Tomato');
        $this->assertNotNull($tomato);
        $this->assertEquals(2400.00, $tomato['total']);

        $onion = collect($productSource['products'])->firstWhere('name', 'Onion');
        $this->assertNotNull($onion);
        $this->assertEquals(1800.00, $onion['total']);
    }

    public function test_shop_without_product_tagging_does_not_show_fake_product_rows(): void
    {
        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop2->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $purchaseBreakdown = $report['summary_breakdowns'][ShopPaymentsReportConfigService::HEADING_CASH_PURCHASE];
        $productSource = collect($purchaseBreakdown['sources'])->firstWhere('type', 'product_total');

        if ($productSource) {
            $this->assertEmpty($productSource['products']);
        }
    }

    public function test_product_data_from_shop_a_cannot_appear_for_shop_b(): void
    {
        $product = Product::factory()->create(['name' => 'Potato', 'sku' => 'POT-1', 'unit' => 'kg', 'is_active' => true]);

        $cartShop1 = PurchaserCart::create([
            'cart_number' => 'CART-SHOP1',
            'user_id' => $this->admin->id,
            'destination_shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-15',
            'status' => 'approved',
        ]);

        PurchaseInvoice::factory()->create([
            'shop_id' => $this->shop1->shop_id,
            'purchaser_cart_id' => $cartShop1->id,
            'invoice_number' => 'PINV-SHOP1',
            'amount' => 1500.00,
            'status' => 'approved',
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $cartShop1->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 150.00,
            'line_total' => 1500.00,
        ]);

        $service = app(ShopSalesReportService::class);
        $reportShop2 = $service->generate($this->shop2->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $purchaseBreakdownShop2 = $reportShop2['summary_breakdowns'][ShopPaymentsReportConfigService::HEADING_CASH_PURCHASE];
        $productSourceShop2 = collect($purchaseBreakdownShop2['sources'])->firstWhere('type', 'product_total');

        if ($productSourceShop2) {
            $potatoInShop2 = collect($productSourceShop2['products'])->firstWhere('name', 'Potato');
            $this->assertNull($potatoInShop2);
        }
    }

    public function test_monthly_popup_total_equals_monthly_card(): void
    {
        $salesType = LedgerEntryType::create(['code' => 'cash_sales_m', 'name' => 'Cash Sales M', 'category' => 'income']);
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $salesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'sales',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-10',
            'entry_type_id' => $salesType->id,
            'amount' => 5000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-20',
            'entry_type_id' => $salesType->id,
            'amount' => 7000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $this->assertEquals(12000.00, $report['summary']['total_sales']);
        $this->assertEquals(12000.00, $report['summary_breakdowns'][ShopPaymentsReportConfigService::HEADING_TOTAL_SALES]['total']);
    }

    public function test_monthly_card_equals_sum_of_daily_values(): void
    {
        $salesType = LedgerEntryType::create(['code' => 'cash_sales_d', 'name' => 'Cash Sales D', 'category' => 'income']);
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $salesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'sales',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-05',
            'entry_type_id' => $salesType->id,
            'amount' => 3000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-12',
            'entry_type_id' => $salesType->id,
            'amount' => 4500.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $sumDailySales = array_sum(array_column($report['daily_rows'], 'sales'));
        $this->assertEquals(7500.00, $sumDailySales);
        $this->assertEquals(7500.00, $report['summary']['total_sales']);
    }

    public function test_balance_popup_uses_sales_minus_rent_minus_purchase_minus_other_expenses(): void
    {
        $salesType = LedgerEntryType::create(['code' => 'cash_sales_b', 'name' => 'Cash Sales B', 'category' => 'income']);
        $rentType = LedgerEntryType::create(['code' => 'rent_b', 'name' => 'Rent B', 'category' => 'expense']);
        $purchaseType = LedgerEntryType::create(['code' => 'purchase_b', 'name' => 'Purchase B', 'category' => 'expense']);
        $otherType = LedgerEntryType::create(['code' => 'other_b', 'name' => 'Other B', 'category' => 'expense']);

        ShopLedgerEntrySetting::create(['shop_id' => $this->shop1->shop_id, 'entry_type_id' => $salesType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true, 'sales_report_bucket' => 'sales']);
        ShopLedgerEntrySetting::create(['shop_id' => $this->shop1->shop_id, 'entry_type_id' => $rentType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true, 'sales_report_bucket' => 'rent']);
        ShopLedgerEntrySetting::create(['shop_id' => $this->shop1->shop_id, 'entry_type_id' => $purchaseType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true, 'sales_report_bucket' => 'purchase']);
        ShopLedgerEntrySetting::create(['shop_id' => $this->shop1->shop_id, 'entry_type_id' => $otherType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true, 'sales_report_bucket' => 'other_expense']);

        ShopLedgerTransaction::create(['shop_id' => $this->shop1->shop_id, 'business_date' => '2026-09-20', 'entry_type_id' => $salesType->id, 'amount' => 35500.00, 'direction' => 'income', 'funding_source' => 'sales', 'status' => 'approved']);
        ShopLedgerTransaction::create(['shop_id' => $this->shop1->shop_id, 'business_date' => '2026-09-20', 'entry_type_id' => $rentType->id, 'amount' => 4000.00, 'direction' => 'expense', 'funding_source' => 'sales', 'status' => 'approved']);
        ShopLedgerTransaction::create(['shop_id' => $this->shop1->shop_id, 'business_date' => '2026-09-20', 'entry_type_id' => $purchaseType->id, 'amount' => 15600.00, 'direction' => 'expense', 'funding_source' => 'sales', 'status' => 'approved']);
        ShopLedgerTransaction::create(['shop_id' => $this->shop1->shop_id, 'business_date' => '2026-09-20', 'entry_type_id' => $otherType->id, 'amount' => 4260.00, 'direction' => 'expense', 'funding_source' => 'sales', 'status' => 'approved']);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $dailyRow = collect($report['daily_rows'])->firstWhere('date', '2026-09-20');
        $balanceBreakdown = $dailyRow['breakdowns'][ShopPaymentsReportConfigService::HEADING_NET_OPERATING_BALANCE];

        $this->assertTrue($balanceBreakdown['is_balance']);
        $this->assertEquals(11640.00, $balanceBreakdown['total']);
        $this->assertEquals(35500.00, $balanceBreakdown['sales']);
        $this->assertEquals(4000.00, $balanceBreakdown['rent']);
        $this->assertEquals(15600.00, $balanceBreakdown['purchase']);
        $this->assertEquals(4260.00, $balanceBreakdown['other_expense']);
    }

    public function test_gl_bill_only_appears_as_summary_amount_with_no_product_split(): void
    {
        $glBillType = LedgerEntryType::create([
            'code' => 'purchase_bill',
            'name' => 'GL Bill',
            'category' => 'expense',
            'active' => true,
        ]);

        $glSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $glBillType->id,
            'display_name' => 'GL Bill',
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'purchase',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-22',
            'entry_type_id' => $glBillType->id,
            'amount' => 52564.90,
            'direction' => 'expense',
            'funding_source' => 'company',
            'status' => 'approved',
        ]);

        // Create a central shop order cart with products for warehouse fulfillment
        $product = Product::factory()->create(['name' => 'Tomato', 'unit' => 'kg']);
        $cart = PurchaserCart::create([
            'cart_number' => 'CART-GL',
            'user_id' => $this->admin->id,
            'purchase_source' => 'shop_order',
            'destination_shop_id' => null,
            'business_date' => '2026-09-22',
            'status' => 'approved',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 50.00,
            'line_total' => 5000.00,
        ]);

        $service = app(ShopPaymentsReportConfigService::class);
        $report = $service->calculateReport($this->shop1->shop_id, '2026-09-22', '2026-09-22', '2026-09');

        $dailyRow = $report['daily_rows'][0];
        $purchaseBreakdown = $dailyRow['breakdowns'][ShopPaymentsReportConfigService::HEADING_CASH_PURCHASE];

        $this->assertEquals(52564.90, $dailyRow['purchase']);
        $this->assertEquals(52564.90, $purchaseBreakdown['total']);

        $glBillSource = collect($purchaseBreakdown['sources'])->firstWhere('name', 'GL Bill');
        $this->assertNotNull($glBillSource);
        $this->assertEquals(52564.90, $glBillSource['total']);
        $this->assertEmpty($glBillSource['products'], 'GL Bill must NOT have product breakdown');

        $dvpSource = collect($purchaseBreakdown['sources'])->firstWhere('name', 'Direct Vendor Purchases');
        $this->assertNotNull($dvpSource);
        $this->assertEquals(0.00, $dvpSource['total']);
        $this->assertEmpty($dvpSource['products'], 'Central shop_order products must not leak into Direct Vendor Purchases');
    }

    public function test_direct_vendor_purchase_only_shows_product_split(): void
    {
        $product1 = Product::factory()->create(['name' => 'Onion', 'unit' => 'kg']);
        $product2 = Product::factory()->create(['name' => 'Lemon', 'unit' => 'kg']);

        $cart = PurchaserCart::create([
            'cart_number' => 'CART-DVP',
            'user_id' => $this->admin->id,
            'purchase_source' => 'shop',
            'destination_shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-22',
            'paid_amount' => 8200.00,
            'status' => 'approved',
        ]);
        PurchaseInvoice::factory()->create([
            'shop_id' => $this->shop1->shop_id,
            'purchaser_cart_id' => $cart->id,
            'purchase_source' => 'shop',
            'amount' => 8200.00,
            'status' => 'paid',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product1->id,
            'quantity' => 100,
            'unit_price' => 55.00,
            'line_total' => 5500.00,
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product2->id,
            'quantity' => 12,
            'unit_price' => 225.00,
            'line_total' => 2700.00,
        ]);

        $service = app(ShopPaymentsReportConfigService::class);
        $report = $service->calculateReport($this->shop1->shop_id, '2026-09-22', '2026-09-22', '2026-09');

        $dailyRow = $report['daily_rows'][0];
        $purchaseBreakdown = $dailyRow['breakdowns'][ShopPaymentsReportConfigService::HEADING_CASH_PURCHASE];

        $this->assertEquals(8200.00, $dailyRow['purchase']);
        $dvpSource = collect($purchaseBreakdown['sources'])->firstWhere('name', 'Direct Vendor Purchases');
        $this->assertNotNull($dvpSource);
        $this->assertEquals(8200.00, $dvpSource['total']);
        $this->assertCount(2, $dvpSource['products']);
        $this->assertEquals('Onion', $dvpSource['products'][0]['name']);
        $this->assertEquals(5500.00, $dvpSource['products'][0]['total']);
        $this->assertEquals('Lemon', $dvpSource['products'][1]['name']);
        $this->assertEquals(2700.00, $dvpSource['products'][1]['total']);
    }

    public function test_cash_purchase_plus_gl_bill_plus_direct_vendor_purchase_on_same_day(): void
    {
        // 1. Header Group CASH PURCHASE
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop1->shop_id,
            'name' => 'CASH PURCHASE',
            'type' => 'expense',
            'display_order' => 1,
        ]);

        $cashType = LedgerEntryType::create(['code' => 'others', 'name' => 'OTHERS', 'category' => 'expense']);
        $glBillType = LedgerEntryType::create(['code' => 'purchase_bill', 'name' => 'GL Bill', 'category' => 'expense']);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $cashType->id,
            'display_name' => 'OTHERS',
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => null,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->shop_id,
            'entry_type_id' => $glBillType->id,
            'display_name' => 'GL Bill',
            'header_group_id' => null,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'sales_report_bucket' => 'purchase',
        ]);

        // Transaction 1: Cash Purchase Others = 3600
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-22',
            'entry_type_id' => $cashType->id,
            'amount' => 3600.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'approved',
        ]);

        // Transaction 2: GL Bill = 52564.90
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-22',
            'entry_type_id' => $glBillType->id,
            'amount' => 52564.90,
            'direction' => 'expense',
            'funding_source' => 'company',
            'status' => 'approved',
        ]);

        // 2. Direct Vendor Purchase = 35765
        $product = Product::factory()->create(['name' => 'Banana Nendran Color', 'unit' => 'full_bunch']);
        $cart = PurchaserCart::create([
            'cart_number' => 'CART-VC',
            'user_id' => $this->admin->id,
            'purchase_source' => 'shop',
            'destination_shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-22',
            'paid_amount' => 35765.00,
            'status' => 'approved',
        ]);
        PurchaseInvoice::factory()->create([
            'shop_id' => $this->shop1->shop_id,
            'purchaser_cart_id' => $cart->id,
            'purchase_source' => 'shop',
            'amount' => 35765.00,
            'status' => 'paid',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 80,
            'unit_price' => 447.06,
            'line_total' => 35765.00,
        ]);

        $service = app(ShopPaymentsReportConfigService::class);
        $report = $service->calculateReport($this->shop1->shop_id, '2026-09-22', '2026-09-22', '2026-09');

        $dailyRow = $report['daily_rows'][0];
        $purchaseBreakdown = $dailyRow['breakdowns'][ShopPaymentsReportConfigService::HEADING_CASH_PURCHASE];

        $expectedTotal = round(3600.00 + 52564.90 + 35765.00, 2);
        $this->assertEquals($expectedTotal, $dailyRow['purchase']);
        $this->assertEquals($expectedTotal, $purchaseBreakdown['total']);

        // Assert Cash Purchase
        $headerSource = collect($purchaseBreakdown['sources'])->firstWhere('name', 'CASH PURCHASE');
        $this->assertNotNull($headerSource);
        $this->assertEquals(3600.00, $headerSource['total']);
        $this->assertEmpty($headerSource['products']);

        // Assert GL Bill
        $glSource = collect($purchaseBreakdown['sources'])->firstWhere('name', 'GL Bill');
        $this->assertNotNull($glSource);
        $this->assertEquals(52564.90, $glSource['total']);
        $this->assertEmpty($glSource['products'], 'GL Bill must never have internal product split');

        // Assert Direct Vendor Purchase
        $dvpSource = collect($purchaseBreakdown['sources'])->firstWhere('name', 'Direct Vendor Purchases');
        $this->assertNotNull($dvpSource);
        $this->assertEquals(35765.00, $dvpSource['total']);
        $this->assertCount(1, $dvpSource['products']);
        $this->assertEquals('Banana Nendran Color', $dvpSource['products'][0]['name']);
        $this->assertEquals(35765.00, $dvpSource['products'][0]['total']);
    }

    public function test_cross_shop_direct_vendor_purchases_are_strictly_isolated(): void
    {
        // Direct vendor purchase for Shop 2
        $product = Product::factory()->create(['name' => 'Foreign Shop Product', 'unit' => 'kg']);
        $cartShop2 = PurchaserCart::create([
            'cart_number' => 'CART-S2',
            'user_id' => $this->admin->id,
            'purchase_source' => 'shop',
            'destination_shop_id' => $this->shop2->shop_id,
            'business_date' => '2026-09-22',
            'paid_amount' => 9999.00,
            'status' => 'approved',
        ]);
        PurchaseInvoice::factory()->create([
            'shop_id' => $this->shop2->shop_id,
            'purchaser_cart_id' => $cartShop2->id,
            'purchase_source' => 'shop',
            'amount' => 9999.00,
            'status' => 'paid',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cartShop2->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 999.90,
            'line_total' => 9999.00,
        ]);

        // Query Shop 1 report
        $service = app(ShopPaymentsReportConfigService::class);
        $reportShop1 = $service->calculateReport($this->shop1->shop_id, '2026-09-22', '2026-09-22', '2026-09');

        $dailyRow = $reportShop1['daily_rows'][0];
        $purchaseBreakdown = $dailyRow['breakdowns'][ShopPaymentsReportConfigService::HEADING_CASH_PURCHASE];

        $this->assertEquals(0.00, $dailyRow['purchase']);
        $dvpSource = collect($purchaseBreakdown['sources'])->firstWhere('name', 'Direct Vendor Purchases');
        $this->assertEquals(0.00, $dvpSource['total']);
        $this->assertEmpty($dvpSource['products'], 'Shop 1 must not see Shop 2 products');
    }

    public function test_future_dates_with_all_zeros_are_excluded_from_daily_rows(): void
    {
        Carbon::setTestNow('2026-09-24');

        $salesType = LedgerEntryType::firstOrCreate(['code' => 'daily_sales'], ['name' => 'Daily Sales', 'category' => 'income']);

        // Create transaction on future date 2026-09-28
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->shop_id,
            'business_date' => '2026-09-28',
            'entry_type_id' => $salesType->id,
            'amount' => 500.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'status' => 'approved',
        ]);

        $service = app(ShopSalesReportService::class);
        $report = $service->generate($this->shop1->shop_id, '2026-09-01', '2026-09-30', 'month', '2026-09');

        $datesInDailyRows = array_column($report['daily_rows'], 'date');

        // Days up to today (2026-09-24) should be present (24 days) + 1 future day with data (2026-09-28) = 25 days
        $this->assertContains('2026-09-24', $datesInDailyRows);
        $this->assertContains('2026-09-01', $datesInDailyRows);
        $this->assertContains('2026-09-28', $datesInDailyRows);
        $this->assertCount(25, $report['daily_rows']);

        // Future dates without data should NOT be present
        $this->assertNotContains('2026-09-25', $datesInDailyRows);
        $this->assertNotContains('2026-09-30', $datesInDailyRows);

        Carbon::setTestNow();
    }
}
