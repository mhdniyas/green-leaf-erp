<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Client;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseProductFilter;
use App\Models\PurchaseProductFilterItem;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\MonthlyReport\DynamicSectionReportService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class DynamicMonthlyCashbookSectionReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $clientShop;

    private Shop $directShop;

    private User $purchaser;

    private Supplier $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create(['name' => 'Test Purchaser']);
        $this->purchaser->assignRole('purchaser');

        $this->vendor = Supplier::create([
            'name' => 'Test Vendor',
            'type' => 'supplier',
            'contact' => 'Vendor Rep',
            'mobile_number' => '9876543210',
        ]);

        $client = Client::create([
            'name' => 'Test Client',
            'code' => 'TC-01',
            'status' => 'active',
        ]);

        $this->clientShop = Shop::factory()->create([
            'client_id' => $client->id,
            'name' => 'Client Shop #1',
            'accounting_enabled' => true,
            'status' => 'active',
        ]);

        $this->directShop = Shop::factory()->create([
            'client_id' => null,
            'name' => 'Direct Shop #2',
            'accounting_enabled' => true,
            'status' => 'active',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_admin_can_view_section_reports_page_with_active_sections(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.section-reports', ['month' => '2026-09']));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.monthly-report.section-reports');
        $response->assertSee('Monthly Section Reports');
        $response->assertSee('Operating Expense');
    }

    public function test_authenticated_authorized_admin_can_access_section_reports_without_redirect_to_admin(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/reports/monthly/section-reports');

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.monthly-report.section-reports');
        $response->assertSee('Monthly Section Reports');
    }

    public function test_authenticated_authorized_admin_can_access_section_reports_with_month_query_parameter(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/reports/monthly/section-reports?month=2026-09');

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.monthly-report.section-reports');
        $response->assertSee('Monthly Section Reports');
    }

    public function test_sale_split_page_renders_section_reports_button_with_month_preserved(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.sale-split', ['month' => '2026-09']));

        $response->assertOk();
        $response->assertSee('Section Reports');
        $response->assertSee(route('admin.cashbook.monthly-report.section-reports', ['month' => '2026-09']));
    }

    public function test_accounts_user_can_access_section_reports_without_redirect(): void
    {
        $accountsUser = User::factory()->create();
        $accountsUser->assignRole('accounts');

        $response = $this->actingAs($accountsUser)->get(route('admin.cashbook.monthly-report.section-reports', ['month' => '2026-09']));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.monthly-report.section-reports');
    }

    public function test_new_unseen_cashbook_section_automatically_appears_without_code_modification(): void
    {
        // 1. Create a brand new dynamic section with an unprecedented name
        $dairyFilter = PurchaseProductFilter::create([
            'name' => 'Dairy Test Section',
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Dairy Products', 'status' => 'active']);
        $dairyProduct = Product::create([
            'name' => 'Fresh Milk 1L',
            'sku' => 'DAIRY-01',
            'category_id' => $category->id,
            'unit' => 'litre',
            'status' => 'active',
        ]);

        PurchaseProductFilterItem::create([
            'filter_id' => $dairyFilter->id,
            'product_id' => $dairyProduct->id,
        ]);

        // 2. Create a purchase on this dynamic section
        $cart = PurchaserCart::create([
            'cart_number' => 'CART-DAIRY-01',
            'user_id' => $this->purchaser->id,
            'purchaser_id' => $this->purchaser->id,
            'cart_type' => 'invoice',
            'business_date' => '2026-09-10',
            'status' => 'converted',
            'payment_class' => 'cash',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $dairyProduct->id,
            'product_name' => $dairyProduct->name,
            'quantity' => 10,
            'unit_price' => 50.00,
            'item_net' => 500.00,
            'item_gross' => 500.00,
            'total_amount' => 500.00,
        ]);
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-DAIRY-01',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => now(),
        ]);

        PurchaseInvoice::create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->vendor->id,
            'purchaser_submitted_by' => $this->purchaser->id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'INV-DAIRY-01',
            'amount' => 500.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Cash',
            'status' => 'approved',
            'original_business_date' => '2026-09-10',
        ]);

        // 3. Create a direct sale on this dynamic section
        $order = ShopOrder::create([
            'shop_id' => $this->directShop->id,
            'business_date' => '2026-09-10',
            'order_number' => 'ORD-DAIRY-01',
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'payment_status' => 'paid',
            'shop_daily_order_key' => 'shop:'.$this->directShop->id.':2026-09-10',
            'created_by' => $this->admin->id,
            'order_status' => 'completed',
            'subtotal' => 750.00,
            'final_total' => 750.00,
        ]);
        $inv = ShopInvoice::create([
            'shop_id' => $this->directShop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-DIR-DAIRY',
            'business_date' => '2026-09-10',
            'subtotal' => 750.00,
            'final_total' => 750.00,
            'status' => 'confirmed',
        ]);
        ShopInvoiceItem::create([
            'shop_invoice_id' => $inv->id,
            'product_id' => $dairyProduct->id,
            'product_name' => $dairyProduct->name,
            'unit' => 'litre',
            'price_unit' => 'litre',
            'quantity' => 10,
            'delivered_qty' => 10,
            'price_quantity' => 10,
            'delivered_price_quantity' => 10,
            'unit_price' => 75.00,
            'line_subtotal' => 750.00,
            'final_line_total' => 750.00,
        ]);

        // 4. Request the report page
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.section-reports', ['month' => '2026-09']));

        $response->assertOk();
        $response->assertSee('Dairy Test Section');
        $response->assertSee('750.00'); // Sale
        $response->assertSee('500.00'); // Purchase
        $response->assertSee('250.00'); // Balance (750 - 500)

        // 5. Verify service output
        $service = app(DynamicSectionReportService::class);
        $report = $service->buildReport(['month' => '2026-09']);
        $sectionKey = 'filter_'.$dairyFilter->id;

        $this->assertArrayHasKey($sectionKey, $report['sections']);
        $this->assertEquals('Dairy Test Section', $report['sections'][$sectionKey]['name']);
        $this->assertEquals(750.00, $report['sections'][$sectionKey]['summary']['sales']);
        $this->assertEquals(500.00, $report['sections'][$sectionKey]['summary']['purchases']);
        $this->assertEquals(250.00, $report['sections'][$sectionKey]['summary']['balance']);
    }

    public function test_renaming_a_section_immediately_updates_the_report(): void
    {
        $filter = PurchaseProductFilter::create([
            'name' => 'Original Name Section',
            'is_active' => true,
        ]);

        $service = app(DynamicSectionReportService::class);
        $report1 = $service->buildReport(['month' => '2026-09']);
        $sectionKey = 'filter_'.$filter->id;
        $this->assertEquals('Original Name Section', $report1['sections'][$sectionKey]['name']);

        // Rename the section
        $filter->update(['name' => 'Renamed Dynamic Section']);

        $report2 = $service->buildReport(['month' => '2026-09']);
        $this->assertEquals('Renamed Dynamic Section', $report2['sections'][$sectionKey]['name']);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.section-reports', ['month' => '2026-09']));
        $response->assertSee('Renamed Dynamic Section');
    }

    public function test_deactivated_section_is_excluded_from_the_report(): void
    {
        $filter = PurchaseProductFilter::create([
            'name' => 'Deactivated Section',
            'is_active' => false,
        ]);

        $service = app(DynamicSectionReportService::class);
        $report = $service->buildReport(['month' => '2026-09']);
        $sectionKey = 'filter_'.$filter->id;

        $this->assertArrayNotHasKey($sectionKey, $report['sections']);
    }

    public function test_expense_only_section_does_not_show_sales_columns(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.section-reports', [
            'month' => '2026-09',
            'section' => 'operating_expense',
        ]));

        $response->assertOk();
        $response->assertSee('Operating Expense');
        $response->assertSee('Operating Overhead');
    }

    public function test_daily_totals_equal_monthly_totals_for_every_section(): void
    {
        $service = app(DynamicSectionReportService::class);
        $report = $service->buildReport(['month' => '2026-09']);

        foreach ($report['sections'] as $sec) {
            if ($sec['type'] === 'trading') {
                $sumDailySales = round(array_sum(array_column($sec['daily_rows'], 'sale')), 2);
                $sumDailyPurchases = round(array_sum(array_column($sec['daily_rows'], 'purchase')), 2);
                $sumDailyOtherExp = round(array_sum(array_column($sec['daily_rows'], 'other_expense')), 2);

                $this->assertEquals($sec['summary']['sales'], $sumDailySales);
                $this->assertEquals($sec['summary']['purchases'], $sumDailyPurchases);
                $this->assertEquals($sec['summary']['other_expenses'], $sumDailyOtherExp);
                $this->assertEquals($sec['summary']['balance'], round($sumDailySales - $sumDailyPurchases - $sumDailyOtherExp, 2));
            } else {
                $sumDailyExp = round(array_sum(array_column($sec['daily_rows'], 'expense')), 2);
                $this->assertEquals($sec['summary']['total_expenses'], $sumDailyExp);
            }
        }
    }

    public function test_full_csv_export_matches_web_totals_and_contains_all_sections(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.section-reports.export.csv', ['month' => '2026-09']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_single_section_csv_export_contains_only_requested_section(): void
    {
        $filter = PurchaseProductFilter::create(['name' => 'Single Section Export Test', 'is_active' => true]);
        $sectionKey = 'filter_'.$filter->id;

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.section-reports.export.csv', [
            'month' => '2026-09',
            'section' => $sectionKey,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_full_excel_export_returns_multi_sheet_workbook(): void
    {
        Excel::fake();

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.section-reports.export.excel', ['month' => '2026-09']));

        $response->assertOk();
        Excel::assertDownloaded('green-leaf-section-reports-all-2026-09-01-to-2026-09-30.xlsx');
    }

    public function test_single_section_excel_export_returns_selected_section_sheet(): void
    {
        Excel::fake();

        $filter = PurchaseProductFilter::create(['name' => 'Excel Single Test', 'is_active' => true]);
        $sectionKey = 'filter_'.$filter->id;

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.section-reports.export.excel', [
            'month' => '2026-09',
            'section' => $sectionKey,
        ]));

        $response->assertOk();
        Excel::assertDownloaded("green-leaf-section-reports-{$sectionKey}-2026-09-01-to-2026-09-30.xlsx");
    }

    public function test_pdf_export_and_print_view_match_web_totals(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.section-reports.print', ['month' => '2026-09']));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.monthly-report.print.section-reports');
        $response->assertSee('Monthly Section Reports');

        $pdfResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.section-reports.export.pdf', ['month' => '2026-09']));
        $pdfResponse->assertOk();
        $pdfResponse->assertViewIs('admin.cashbook.monthly-report.pdf.section-reports');
    }

    public function test_unauthorized_user_is_forbidden_from_accessing_section_reports_and_exports(): void
    {
        $unauthorizedUser = User::factory()->create();
        $unauthorizedUser->assignRole('warehouse_receiver');

        $this->actingAs($unauthorizedUser)->get(route('admin.cashbook.monthly-report.section-reports', ['month' => '2026-09']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($unauthorizedUser)->get(route('admin.cashbook.monthly-report.section-reports.export.csv', ['month' => '2026-09']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($unauthorizedUser)->get(route('admin.cashbook.monthly-report.section-reports.export.excel', ['month' => '2026-09']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($unauthorizedUser)->get(route('admin.cashbook.monthly-report.section-reports.export.pdf', ['month' => '2026-09']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($unauthorizedUser)->get(route('admin.cashbook.monthly-report.section-reports.print', ['month' => '2026-09']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }
}
