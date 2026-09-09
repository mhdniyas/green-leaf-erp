<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\ProcurementExpense;
use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaserExpenseReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create(['name' => 'John Purchaser']);
        $this->purchaser->assignRole('purchaser');

        $this->supplier = Supplier::factory()->create(['name' => 'Fresh Farm Supplier']);
        $this->product = Product::factory()->create(['name' => 'Fresh Mango', 'unit' => 'kg']);
    }

    public function test_admin_purchaser_expense_report_page_returns_200(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses');

        $response->assertStatus(200);
        $response->assertSee('Purchaser Purchase &amp; Expense Report', false);
    }

    public function test_same_date_and_filter_as_purchaser_history_gives_same_totals(): void
    {
        $today = now('Asia/Kolkata')->format('Y-m-d');

        // Create purchase cart
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $today,
            'status' => 'submitted',
            'cart_number' => 'CART-TEST-001',
            'discount_amount' => 0.0,
            'payment_method' => 'Cash',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10.0,
            'unit' => 'kg',
            'unit_price' => 50.0,
            'line_total' => 500.0,
        ]);

        // Create procurement expense with accounting entry
        $chartAcc = Account::create([
            'name' => 'General Expense Account',
            'code' => 'ACC-GEN-EXP',
            'type' => 'expense',
        ]);
        $cat = CompanyAccountingCategory::create([
            'account_id' => $chartAcc->id,
            'name' => 'General Expense',
            'type' => 'expense',
            'is_active' => true,
        ]);
        $acc = CompanyAccount::create([
            'name' => 'Main Cash Account',
            'account_number' => 'ACC-001',
            'account_type' => 'cash',
            'enabled' => true,
        ]);
        $entry = CompanyAccountingEntry::create([
            'company_accounting_category_id' => $cat->id,
            'company_account_id' => $acc->id,
            'type' => 'expense',
            'business_date' => $today,
            'payment_mode' => 'cash',
            'amount' => 150.0,
            'reference' => 'ACC-REF-999',
            'description' => 'Accounting entry reference',
            'status' => 'final',
            'created_by' => $this->admin->id,
        ]);

        ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'company_accounting_entry_id' => $entry->id,
            'expense_date' => $today,
            'category' => 'transport',
            'amount' => 150.0,
            'note' => 'Truck fare',
        ]);

        // Fetch from purchaser history
        $historyResponse = $this->actingAs($this->purchaser)->get("/purchaser/history?date={$today}&include_expenses=1");
        $historyResponse->assertStatus(200);

        // Fetch from Admin Report Service
        $service = app(PurchaserExpenseReportService::class);
        $reportData = $service->getReportData([
            'purchaser' => $this->purchaser->public_uuid,
            'date_from' => $today,
            'date_to' => $today,
        ]);

        $this->assertEquals(500.0, $reportData['summary']['total_purchase']);
        $this->assertEquals(150.0, $reportData['summary']['total_expenses']);
        $this->assertEquals(650.0, $reportData['summary']['combined_total']);
    }

    public function test_purchaser_filter(): void
    {
        $otherPurchaser = User::factory()->create(['name' => 'Jane Purchaser']);
        $today = now('Asia/Kolkata')->format('Y-m-d');

        // Purchaser 1 cart
        $cart1 = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $today,
            'status' => 'submitted',
            'cart_number' => 'CART-001',
            'discount_amount' => 0.0,
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart1->id,
            'product_id' => $this->product->id,
            'quantity' => 2.0,
            'line_total' => 100.0,
        ]);

        // Purchaser 2 cart
        $cart2 = PurchaserCart::create([
            'user_id' => $otherPurchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $today,
            'status' => 'submitted',
            'cart_number' => 'CART-002',
            'discount_amount' => 0.0,
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart2->id,
            'product_id' => $this->product->id,
            'quantity' => 5.0,
            'line_total' => 250.0,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses?purchaser='.$this->purchaser->public_uuid);

        $response->assertStatus(200);
        $response->assertSee('100.00');
        $response->assertDontSee('250.00');
    }

    public function test_supplier_filter(): void
    {
        $otherSupplier = Supplier::factory()->create(['name' => 'Organic Valley']);
        $today = now('Asia/Kolkata')->format('Y-m-d');

        $cart1 = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $today,
            'status' => 'submitted',
            'cart_number' => 'CART-SUPP-1',
            'discount_amount' => 0.0,
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart1->id,
            'product_id' => $this->product->id,
            'quantity' => 1.0,
            'line_total' => 300.0,
        ]);

        $cart2 = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $otherSupplier->id,
            'business_date' => $today,
            'status' => 'submitted',
            'cart_number' => 'CART-SUPP-2',
            'discount_amount' => 0.0,
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart2->id,
            'product_id' => $this->product->id,
            'quantity' => 1.0,
            'line_total' => 700.0,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses?supplier_id='.$this->supplier->id);

        $response->assertStatus(200);
        $response->assertSee('300.00');
        $response->assertDontSee('700.00');
    }

    public function test_date_range_and_month_filter(): void
    {
        $cart1 = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-15',
            'status' => 'submitted',
            'cart_number' => 'CART-AUG-1',
            'discount_amount' => 0.0,
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart1->id,
            'product_id' => $this->product->id,
            'quantity' => 1.0,
            'line_total' => 450.0,
        ]);

        // Date range query
        $rangeResponse = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses?date_from=2026-08-01&date_to=2026-08-31');
        $rangeResponse->assertStatus(200);
        $rangeResponse->assertSee('450.00');

        // Month filter query
        $monthResponse = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses?month=2026-08');
        $monthResponse->assertStatus(200);
        $monthResponse->assertSee('450.00');
    }

    public function test_pdf_export_returns_200_and_download(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses/export/pdf');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_csv_export_returns_200_and_download(): void
    {
        $today = now('Asia/Kolkata')->format('Y-m-d');
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $today,
            'status' => 'submitted',
            'cart_number' => 'CART-CSV-01',
            'discount_amount' => 0.0,
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2.0,
            'line_total' => 200.0,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses/export/csv');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Date,Purchaser,Supplier,Reference', $response->streamedContent());
    }

    public function test_excel_export_returns_200_and_download(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchaser-expenses/export/excel');

        $response->assertStatus(200);
    }

    public function test_export_rows_and_totals_equal_screen(): void
    {
        $today = now('Asia/Kolkata')->format('Y-m-d');
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $today,
            'status' => 'submitted',
            'cart_number' => 'CART-MATCH-1',
            'discount_amount' => 0.0,
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10.0,
            'line_total' => 1200.0,
        ]);

        ProcurementExpense::create([
            'user_id' => $this->purchaser->id,
            'expense_date' => $today,
            'category' => 'labour',
            'amount' => 300.0,
            'note' => 'Unloading labour',
        ]);

        $service = app(PurchaserExpenseReportService::class);
        $screenData = $service->getReportData(['date_from' => $today, 'date_to' => $today], 25);
        $exportData = $service->getReportData(['date_from' => $today, 'date_to' => $today], null);

        $this->assertEquals($screenData['summary']['total_purchase'], $exportData['summary']['total_purchase']);
        $this->assertEquals($screenData['summary']['total_expenses'], $exportData['summary']['total_expenses']);
        $this->assertEquals($screenData['summary']['combined_total'], $exportData['summary']['combined_total']);
        $this->assertEquals(count($screenData['all_rows']), count($exportData['all_rows']));
    }

    public function test_purchaser_detail_link_preselects_purchaser(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchase/purchasers/'.$this->purchaser->public_uuid);

        $response->assertStatus(200);
        $response->assertSee(route('admin.cashbook.finance.purchase.purchaser-expenses', ['purchaser' => $this->purchaser->public_uuid]), false);

        $redirectResponse = $this->actingAs($this->admin)->get('/admin/cashbook/finance/purchasers/'.$this->purchaser->public_uuid.'/details');
        $redirectResponse->assertRedirect();
    }
}
