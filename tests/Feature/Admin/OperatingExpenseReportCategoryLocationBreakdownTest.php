<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ProcurementExpense;
use App\Models\ProcurementExpenseCategory;
use App\Models\ProcurementExpenseSubCategory;
use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaserExpenseReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingExpenseReportCategoryLocationBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser1;

    private User $purchaser2;

    private Supplier $supplier;

    private Product $product;

    private ProcurementExpenseCategory $vehicleCategory;

    private ProcurementExpenseCategory $foodCategory;

    private ProcurementExpenseSubCategory $placeAB;

    private ProcurementExpenseSubCategory $placeC;

    private ProcurementExpenseSubCategory $canteen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser1 = User::factory()->create(['name' => 'Alice Purchaser']);
        $this->purchaser1->assignRole('purchaser');

        $this->purchaser2 = User::factory()->create(['name' => 'Bob Purchaser']);
        $this->purchaser2->assignRole('purchaser');

        $this->supplier = Supplier::factory()->create(['name' => 'City Veggie Market']);
        $this->product = Product::factory()->create(['name' => 'Tomatoes', 'unit' => 'kg']);

        $this->vehicleCategory = ProcurementExpenseCategory::create([
            'key' => 'vehicle',
            'name' => 'Vehicle',
            'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_REQUIRED,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->foodCategory = ProcurementExpenseCategory::create([
            'key' => 'food',
            'name' => 'Food',
            'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->placeAB = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $this->vehicleCategory->id,
            'name' => 'Place A -> B',
            'code' => 'PAB',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->placeC = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $this->vehicleCategory->id,
            'name' => 'Place C',
            'code' => 'PC',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->canteen = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $this->foodCategory->id,
            'name' => 'Main Canteen',
            'code' => 'MC',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_existing_period_and_custom_date_range_filters_expenses_and_breakdown(): void
    {
        $today = now('Asia/Kolkata')->toDateString();
        $lastMonth = now('Asia/Kolkata')->subMonth()->startOfMonth()->toDateString();

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 1200.00,
            'note' => 'Truck diesel',
        ]);

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $lastMonth,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 5000.00,
            'note' => 'Previous month diesel',
        ]);

        // Query today only via quick filter
        $responseToday = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses?quick_filter=today');
        $responseToday->assertOk();
        $responseToday->assertSee('1,200.00');
        $responseToday->assertDontSee('5,000.00');

        // Query custom range
        $responseRange = $this->actingAs($this->admin)->get("/admin/cashbook/finance/purchase/purchaser-expenses?date_from={$lastMonth}&date_to={$lastMonth}");
        $responseRange->assertOk();
        $responseRange->assertSee('5,000.00');
        $responseRange->assertDontSee('1,200.00');
    }

    public function test_purchaser_filtering_works_with_breakdown_and_table(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 800.00,
        ]);

        ProcurementExpense::create([
            'user_id' => $this->purchaser2->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeC->id,
            'sub_category' => 'Place C',
            'amount' => 2400.00,
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/cashbook/finance/purchase/purchaser-expenses?purchaser={$this->purchaser1->public_uuid}&quick_filter=today");
        $response->assertOk();
        $response->assertSee('800.00');
        $response->assertDontSee('2,400.00');
    }

    public function test_category_filter_isolates_matching_expenses_and_excludes_purchases(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // Purchase cart
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser1->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $today,
            'status' => 'submitted',
            'cart_number' => 'CART-001',
            'discount_amount' => 0.0,
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'line_total' => 1500.00,
        ]);

        // Vehicle expense
        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 450.00,
        ]);

        // Food expense
        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'food',
            'amount' => 200.00,
        ]);

        // Filter by vehicle
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses?category=vehicle&quick_filter=today');
        $response->assertOk();
        $response->assertSee('450.00');
        $response->assertDontSee('1,500.00'); // Purchases excluded
        $response->assertDontSee('200.00');   // Food excluded
    }

    public function test_location_filter_filters_expenses_by_specific_sub_category(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 750.00,
        ]);

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeC->id,
            'sub_category' => 'Place C',
            'amount' => 350.00,
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/cashbook/finance/purchase/purchaser-expenses?category=vehicle&location={$this->placeAB->id}&quick_filter=today");
        $response->assertOk();
        $response->assertSee('750.00');
        $response->assertDontSee('350.00');
    }

    public function test_location_filter_unassigned_filters_expenses_without_location(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 600.00,
        ]);

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => null,
            'sub_category' => null,
            'amount' => 150.00,
            'note' => 'Unassigned vehicle toll',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses?category=vehicle&location=unassigned&quick_filter=today');
        $response->assertOk();
        $response->assertSee('150.00');
        $response->assertSee('Unassigned vehicle toll');
        $response->assertDontSee('600.00');
    }

    public function test_incompatible_location_for_category_is_cleared_server_side(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 500.00,
        ]);

        // Pass location belonging to foodCategory ($this->canteen) while filtering for category=vehicle
        $service = app(PurchaserExpenseReportService::class);
        $data = $service->getReportData([
            'category' => 'vehicle',
            'location' => (string) $this->canteen->id,
            'quick_filter' => 'today',
        ]);

        // Server-side validation resets incompatible location to null, returning all vehicle expenses
        $this->assertEquals(500.00, $data['summary']['total_expenses']);
        $this->assertEquals(1, count($data['rows']));
    }

    public function test_inactive_historical_location_remains_reportable(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        $inactiveLoc = ProcurementExpenseSubCategory::create([
            'procurement_expense_category_id' => $this->vehicleCategory->id,
            'name' => 'Old Disused Route',
            'code' => 'OLD',
            'is_active' => false,
        ]);

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $inactiveLoc->id,
            'sub_category' => 'Old Disused Route',
            'amount' => 990.00,
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/cashbook/finance/purchase/purchaser-expenses?category=vehicle&location={$inactiveLoc->id}&quick_filter=today");
        $response->assertOk();
        $response->assertSee('990.00');
        $response->assertSee('Old Disused Route');
    }

    public function test_breakdown_totals_and_percentages_reconcile_with_report_grand_total(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // 1. Vehicle Place A -> B: 5,000 (50%)
        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 5000.00,
        ]);

        // 2. Vehicle Place C: 3,000 (30%)
        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeC->id,
            'sub_category' => 'Place C',
            'amount' => 3000.00,
        ]);

        // 3. Food (Unassigned): 2,000 (20%)
        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'food',
            'procurement_expense_sub_category_id' => null,
            'sub_category' => null,
            'amount' => 2000.00,
        ]);

        $service = app(PurchaserExpenseReportService::class);
        $data = $service->getReportData(['quick_filter' => 'today']);

        $breakdown = $data['breakdown'];
        $this->assertEquals(10000.00, $data['summary']['total_expenses']);
        $this->assertEquals(10000.00, $breakdown['grand_total_amount']);
        $this->assertEquals(3, $breakdown['grand_total_entries']);
        $this->assertEquals(100.0, $breakdown['grand_total_percentage']);

        // Check vehicle category
        $vehicleBreakdown = $breakdown['categories']->firstWhere('category_key', 'vehicle');
        $this->assertNotNull($vehicleBreakdown);
        $this->assertEquals(8000.00, $vehicleBreakdown['total_amount']);
        $this->assertEquals(80.0, $vehicleBreakdown['percentage']);

        // Check vehicle sub-locations
        $pabLoc = $vehicleBreakdown['locations']->firstWhere('location_name', 'Place A -> B');
        $this->assertNotNull($pabLoc);
        $this->assertEquals(5000.00, $pabLoc['total_amount']);
        $this->assertEquals(50.0, $pabLoc['percentage']);

        $pcLoc = $vehicleBreakdown['locations']->firstWhere('location_name', 'Place C');
        $this->assertNotNull($pcLoc);
        $this->assertEquals(3000.00, $pcLoc['total_amount']);
        $this->assertEquals(30.0, $pcLoc['percentage']);

        // Check food category
        $foodBreakdown = $breakdown['categories']->firstWhere('category_key', 'food');
        $this->assertNotNull($foodBreakdown);
        $this->assertEquals(2000.00, $foodBreakdown['total_amount']);
        $this->assertEquals(20.0, $foodBreakdown['percentage']);
    }

    public function test_pagination_preserves_category_and_location_filter_parameters(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // Create 30 expenses to force multiple pages (perPage=25)
        for ($i = 1; $i <= 30; $i++) {
            ProcurementExpense::create([
                'user_id' => $this->purchaser1->id,
                'expense_date' => $today,
                'category' => 'vehicle',
                'procurement_expense_sub_category_id' => $this->placeAB->id,
                'sub_category' => 'Place A -> B',
                'amount' => 100.00,
                'note' => "Vehicle trip #{$i}",
            ]);
        }

        $response = $this->actingAs($this->admin)->get("/admin/cashbook/finance/purchase/purchaser-expenses?category=vehicle&location={$this->placeAB->id}&page=2&quick_filter=today");
        $response->assertOk();
        $response->assertSee('Vehicle trip #');
        // Pagination link should retain category and location query parameters
        $response->assertSee('category=vehicle');
        $response->assertSee("location={$this->placeAB->id}");
    }

    public function test_csv_export_includes_category_and_location_respecting_filters(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 1400.00,
            'note' => 'Route fare',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses/export/csv?category=vehicle&quick_filter=today');
        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Location / Route', $content);
        $this->assertStringContainsString('Vehicle', $content);
        $this->assertStringContainsString('Place A -> B', $content);
        $this->assertStringContainsString('1400.00', $content);
    }

    public function test_excel_export_executes_cleanly_with_category_and_location(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 1400.00,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses/export/excel?category=vehicle&quick_filter=today');
        $response->assertOk();
    }

    public function test_pdf_export_displays_category_and_location_breakdown(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 2200.00,
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/cashbook/finance/purchase/purchaser-expenses/export/pdf?category=vehicle&location={$this->placeAB->id}&quick_filter=today");
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_no_duplicate_financial_records_or_double_counting(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        ProcurementExpense::create([
            'user_id' => $this->purchaser1->id,
            'expense_date' => $today,
            'category' => 'vehicle',
            'procurement_expense_sub_category_id' => $this->placeAB->id,
            'sub_category' => 'Place A -> B',
            'amount' => 300.00,
        ]);

        $service = app(PurchaserExpenseReportService::class);
        $data = $service->getReportData(['quick_filter' => 'today']);

        $this->assertEquals(1, $data['summary']['expenses_count']);
        $this->assertEquals(300.00, $data['summary']['total_expenses']);
        $this->assertEquals(300.00, $data['breakdown']['grand_total_amount']);
        $this->assertEquals(1, count($data['rows']));
    }
}
