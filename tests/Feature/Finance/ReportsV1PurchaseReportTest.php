<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsV1PurchaseReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow('2026-10-23');

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cash_only_purchaser_reconciles_and_renders_correctly(): void
    {
        $purchaser = User::factory()->create(['name' => 'Cash Specialist']);
        $purchaser->assignRole('purchaser');

        $this->invoiceWithItems('2026-10-23', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Cucumber', 'line_total' => 500.00],
        ], $purchaser);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', ['period' => 'today']));

        $response->assertOk();
        $response->assertSee('Cash Specialist');
        $response->assertSee('₹500.00'); // Cash purchase
        $response->assertSee('₹0.00'); // Credit purchase

        $dashboard = $response->viewData('dashboard');
        $this->assertEquals(500.0, (float) $dashboard['summary']->cash_purchase);
        $this->assertEquals(0.0, (float) $dashboard['summary']->credit_purchase);
        $this->assertEquals(500.0, (float) $dashboard['summary']->total_purchase);
    }

    public function test_credit_only_purchaser_reconciles_and_renders_correctly(): void
    {
        $purchaser = User::factory()->create(['name' => 'Credit Specialist']);
        $purchaser->assignRole('purchaser');

        $this->invoiceWithItems('2026-10-23', 'Credit', [
            ['category' => 'Fruits', 'product' => 'Grapes', 'line_total' => 1200.00],
        ], $purchaser);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', ['period' => 'today']));

        $response->assertOk();
        $response->assertSee('Credit Specialist');
        $response->assertSee('₹1,200.00');

        $dashboard = $response->viewData('dashboard');
        $this->assertEquals(0.0, (float) $dashboard['summary']->cash_purchase);
        $this->assertEquals(1200.0, (float) $dashboard['summary']->credit_purchase);
        $this->assertEquals(1200.0, (float) $dashboard['summary']->total_purchase);
    }

    public function test_purchaser_with_both_cash_and_credit_reconciles(): void
    {
        $purchaser = User::factory()->create(['name' => 'Hybrid Buyer']);
        $purchaser->assignRole('purchaser');

        $this->invoiceWithItems('2026-10-23', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Carrot', 'line_total' => 300.00],
        ], $purchaser);

        $this->invoiceWithItems('2026-10-23', 'Credit', [
            ['category' => 'Fruits', 'product' => 'Orange', 'line_total' => 700.00],
        ], $purchaser);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', ['period' => 'today']));

        $response->assertOk();
        $response->assertSee('Hybrid Buyer');

        $dashboard = $response->viewData('dashboard');
        $this->assertEquals(300.0, (float) $dashboard['summary']->cash_purchase);
        $this->assertEquals(700.0, (float) $dashboard['summary']->credit_purchase);
        $this->assertEquals(1000.0, (float) $dashboard['summary']->total_purchase);

        $purchaserRow = collect($dashboard['allPurchasers'])->firstWhere('purchaser_id', $purchaser->id);
        $this->assertNotNull($purchaserRow);
        $this->assertEquals(300.0, (float) $purchaserRow->cash_purchase);
        $this->assertEquals(700.0, (float) $purchaserRow->credit_purchase);
        $this->assertEquals(1000.0, (float) $purchaserRow->total_purchase);
    }

    public function test_multiple_purchasers_and_vendors_reconcile_totals(): void
    {
        $purchaserA = User::factory()->create(['name' => 'Purchaser Ashraf']);
        $purchaserB = User::factory()->create(['name' => 'Purchaser Bilal']);
        $vendor1 = Supplier::factory()->create(['name' => 'Vendor Delta']);
        $vendor2 = Supplier::factory()->create(['name' => 'Vendor Echo']);

        $this->invoiceWithItems('2026-10-23', 'Cash', [['category' => 'Veg', 'product' => 'Potato', 'line_total' => 400.00]], $purchaserA, $vendor1);
        $this->invoiceWithItems('2026-10-23', 'Credit', [['category' => 'Fruit', 'product' => 'Banana', 'line_total' => 600.00]], $purchaserA, $vendor2);
        $this->invoiceWithItems('2026-10-23', 'Cash', [['category' => 'Veg', 'product' => 'Tomato', 'line_total' => 800.00]], $purchaserB, $vendor1);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', ['period' => 'today']));

        $response->assertOk();
        $dashboard = $response->viewData('dashboard');

        // Total cash: 400 + 800 = 1200
        // Total credit: 600
        // Total overall: 1800
        $this->assertEquals(1200.0, (float) $dashboard['summary']->cash_purchase);
        $this->assertEquals(600.0, (float) $dashboard['summary']->credit_purchase);
        $this->assertEquals(1800.0, (float) $dashboard['summary']->total_purchase);

        $allPurchasersTotal = collect($dashboard['allPurchasers'])->sum(fn ($p) => (float) $p->total_purchase);
        $this->assertEquals(1800.0, $allPurchasersTotal);
    }

    public function test_bill_line_items_detail_drilldown(): void
    {
        $purchaser = User::factory()->create(['name' => 'Item Inspector']);
        $invoice = $this->invoiceWithItems('2026-10-23', 'Cash', [
            ['category' => 'Gourmet', 'product' => 'Exotic Mushroom', 'line_total' => 450.00, 'grade' => 'A', 'quantity' => 3, 'unit_price' => 150.00],
            ['category' => 'Herbs', 'product' => 'Fresh Basil', 'line_total' => 150.00, 'grade' => 'B', 'quantity' => 5, 'unit_price' => 30.00],
        ], $purchaser);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', ['period' => 'today']));

        $response->assertOk();
        $response->assertSee('Exotic Mushroom');
        $response->assertSee('Fresh Basil');
        $response->assertSee('Grade A');
        $response->assertSee('Grade B');
        $response->assertSee('150.00');
        $response->assertSee('30.00');

        $bill = collect($response->viewData('dashboard')['bills']->items())->firstWhere('invoice_id', $invoice->id);
        $this->assertNotNull($bill);
        $this->assertCount(2, $bill->items);
    }

    public function test_cancelled_bills_are_strictly_excluded_from_totals(): void
    {
        $purchaser = User::factory()->create(['name' => 'Audit Tester']);
        $validInvoice = $this->invoiceWithItems('2026-10-23', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Beans', 'line_total' => 250.00],
        ], $purchaser);

        $cancelledInvoice = $this->invoiceWithItems('2026-10-23', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Garlic', 'line_total' => 9999.00],
        ], $purchaser);
        $cancelledInvoice->update(['status' => 'cancelled']);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', ['period' => 'today']));

        $response->assertOk();
        $response->assertSee($validInvoice->invoice_number);
        $response->assertDontSee($cancelledInvoice->invoice_number);
        $response->assertDontSee('₹9,999.00');

        $dashboard = $response->viewData('dashboard');
        $this->assertEquals(250.0, (float) $dashboard['summary']->total_purchase);
    }

    public function test_partially_paid_credit_bills_remain_credit_purchases(): void
    {
        $purchaser = User::factory()->create(['name' => 'Partial Payer']);
        $invoice = $this->invoiceWithItems('2026-10-23', 'Credit', [
            ['category' => 'Fruits', 'product' => 'Mango', 'line_total' => 2000.00],
        ], $purchaser);

        // Simulate a partial payment of 500 towards this 2000 credit invoice
        $invoice->update([
            'paid_amount' => 500.00,
            'payment_status' => 'partial',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', ['period' => 'today']));

        $response->assertOk();
        $dashboard = $response->viewData('dashboard');

        // Total credit purchase should remain full invoice net (2000.00), NOT deducted by partial payment
        $this->assertEquals(2000.0, (float) $dashboard['summary']->credit_purchase);
        $this->assertEquals(0.0, (float) $dashboard['summary']->cash_purchase);
        $this->assertEquals(2000.0, (float) $dashboard['summary']->total_purchase);
    }

    public function test_cross_month_custom_date_filter_reconciles(): void
    {
        $this->invoiceWithItems('2026-09-28', 'Cash', [['category' => 'Veg', 'product' => 'Cabbage', 'line_total' => 350.00]]);
        $this->invoiceWithItems('2026-10-05', 'Credit', [['category' => 'Fruit', 'product' => 'Papaya', 'line_total' => 650.00]]);
        $this->invoiceWithItems('2026-10-25', 'Cash', [['category' => 'Veg', 'product' => 'Spinach', 'line_total' => 800.00]]); // outside range

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period_mode' => 'custom',
            'from' => '2026-09-25',
            'to' => '2026-10-10',
        ]));

        $response->assertOk();
        $dashboard = $response->viewData('dashboard');

        $this->assertEquals(350.0, (float) $dashboard['summary']->cash_purchase);
        $this->assertEquals(650.0, (float) $dashboard['summary']->credit_purchase);
        $this->assertEquals(1000.0, (float) $dashboard['summary']->total_purchase);
    }

    public function test_empty_period_displays_empty_state_without_crashing(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period_mode' => 'custom',
            'from' => '2020-01-01',
            'to' => '2020-01-05',
        ]));

        $response->assertOk();
        $response->assertSee('No purchases recorded for this period');
        $dashboard = $response->viewData('dashboard');
        $this->assertEquals(0.0, (float) $dashboard['summary']->total_purchase);
    }

    public function test_purchaser_amount_click_filter_scoping(): void
    {
        $purchaserA = User::factory()->create(['name' => 'Drilldown Purchaser A']);
        $purchaserB = User::factory()->create(['name' => 'Drilldown Purchaser B']);

        $invA = $this->invoiceWithItems('2026-10-23', 'Cash', [['category' => 'Veg', 'product' => 'Peas', 'line_total' => 110.00]], $purchaserA);
        $invACredit = $this->invoiceWithItems('2026-10-23', 'Credit', [['category' => 'Veg', 'product' => 'Corn', 'line_total' => 220.00]], $purchaserA);
        $invB = $this->invoiceWithItems('2026-10-23', 'Cash', [['category' => 'Veg', 'product' => 'Mint', 'line_total' => 330.00]], $purchaserB);

        // Filter by Purchaser A, cash only
        $responseCashOnly = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'purchaser_id' => $purchaserA->id,
            'payment' => 'cash',
        ]));

        $responseCashOnly->assertOk();
        $responseCashOnly->assertSee($invA->invoice_number);
        $responseCashOnly->assertDontSee($invACredit->invoice_number);
        $responseCashOnly->assertDontSee($invB->invoice_number);

        // Filter by Purchaser A, credit only
        $responseCreditOnly = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'purchaser_id' => $purchaserA->id,
            'payment' => 'credit',
        ]));

        $responseCreditOnly->assertOk();
        $responseCreditOnly->assertSee($invACredit->invoice_number);
        $responseCreditOnly->assertDontSee($invA->invoice_number);
        $responseCreditOnly->assertDontSee($invB->invoice_number);
    }

    public function test_unauthorized_user_cannot_access_purchase_report(): void
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)->get(route('admin.cashbook.finance.purchase'));
        // Assert redirected away (e.g. to dashboard or login) or forbidden
        $this->assertTrue(in_array($response->status(), [302, 403], true));
    }

    /** @param array<int, array{category:string,product:string,line_total:float,warehouse_code?:string,grade?:string,quantity?:float,unit_price?:float}> $items */
    private function invoiceWithItems(string $businessDate, string $paymentMethod, array $items, ?User $purchaser = null, ?Supplier $supplier = null): PurchaseInvoice
    {
        $purchaser ??= User::factory()->create();
        $supplier ??= Supplier::factory()->create();
        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $businessDate,
            'status' => 'submitted',
            'cart_number' => 'VC-'.str()->upper(str()->random(12)),
            'payment_method' => $paymentMethod,
        ]);
        $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);
        $total = collect($items)->sum('line_total');
        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $supplier->id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'PUR-'.str()->upper(str()->random(10)),
            'amount' => $total,
            'discount_amount' => 0,
            'payment_method' => $paymentMethod,
            'payment_status' => strtolower($paymentMethod) === 'credit' ? 'credit_pending_approval' : 'paid',
            'payment_paid_by' => strtolower($paymentMethod) === 'credit' ? 'vendor_credit' : 'purchaser',
            'paid_amount' => strtolower($paymentMethod) === 'credit' ? 0 : $total,
            'status' => 'approved',
        ]);
        foreach ($items as $item) {
            $warehouseCode = $item['warehouse_code'] ?? 'VEG-WH';
            $warehouse = Warehouse::query()->firstOrCreate(
                ['code' => $warehouseCode],
                ['name' => $warehouseCode === 'FRT-WH' ? 'Fruit Warehouse' : 'Vegetable Warehouse', 'is_active' => true],
            );
            $category = Category::query()->firstOrCreate(['name' => $item['category']], ['is_active' => true]);
            $product = Product::factory()->create([
                'name' => $item['product'],
                'category_id' => $category->id,
                'default_warehouse_id' => $warehouse->id,
            ]);
            PurchaserCartItem::query()->create([
                'purchaser_cart_id' => $cart->id,
                'product_id' => $product->id,
                'grade' => $item['grade'] ?? 'A',
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? $item['line_total'],
                'line_total' => $item['line_total'],
            ]);
        }

        return $invoice;
    }

    public function test_summary_kpi_cards_link_to_dedicated_invoices_page_with_payment_filters(): void
    {
        $purchaser = User::factory()->create(['name' => 'Card Link Test User']);
        $purchaser->assignRole('purchaser');

        $this->invoiceWithItems('2026-10-23', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Cucumber', 'line_total' => 500.00],
        ], $purchaser);

        $this->invoiceWithItems('2026-10-23', 'Credit', [
            ['category' => 'Fruits', 'product' => 'Grapes', 'line_total' => 800.00],
        ], $purchaser);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', ['period' => 'today']));

        $response->assertOk();

        // Cash Purchase Card links to dedicated invoices view with payment=cash
        $response->assertSee(route('admin.cashbook.finance.purchase.invoices'), false);
        $response->assertSee('payment=cash', false);

        // Credit Purchase Card links to dedicated invoices view with payment=credit
        $response->assertSee('payment=credit', false);

        // Total Purchase Card links to dedicated invoices view with payment=all
        $response->assertSee('payment=all', false);
    }

    public function test_bills_section_contains_search_input_and_sortable_table_headers(): void
    {
        $purchaser = User::factory()->create(['name' => 'Searchable Purchaser']);
        $purchaser->assignRole('purchaser');

        $this->invoiceWithItems('2026-10-23', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Carrot', 'line_total' => 250.00],
        ], $purchaser);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', ['period' => 'today']));

        $response->assertOk();

        // Real-time search input exists
        $response->assertSee('name="search"', false);
        $response->assertSee('x-model="searchQuery"', false);
        $response->assertSee('filterRows()', false);

        // Table headers have sortTable click handlers
        $response->assertSee("sortTable('date', 'date')", false);
        $response->assertSee("sortTable('ref', 'text')", false);
        $response->assertSee("sortTable('vendor', 'text')", false);
        $response->assertSee("sortTable('purchaser', 'text')", false);
        $response->assertSee("sortTable('type', 'text')", false);
        $response->assertSee("sortTable('amount', 'num')", false);
        $response->assertSee("sortTable('status', 'text')", false);
    }

    public function test_dedicated_invoices_page_renders_sortable_headers_and_search(): void
    {
        $purchaser = User::factory()->create(['name' => 'Invoice Ledger User']);
        $purchaser->assignRole('purchaser');

        $invoice = $this->invoiceWithItems('2026-10-23', 'Credit', [
            ['category' => 'Fruits', 'product' => 'Apple', 'line_total' => 950.00],
        ], $purchaser);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', [
            'period' => 'today',
            'payment' => 'credit',
        ]));

        $response->assertOk();
        $response->assertSee($invoice->invoice_number);
        $response->assertSee('Invoice Ledger');
        $response->assertSee('name="search"', false);

        // Invoices table header sorting
        $response->assertSee("sortTable(0, 'date')", false);
        $response->assertSee("sortTable(1, 'text')", false);
        $response->assertSee("sortTable(6, 'num')", false);
    }
}
