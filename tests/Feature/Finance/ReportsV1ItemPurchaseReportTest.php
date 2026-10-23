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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsV1ItemPurchaseReportTest extends TestCase
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

    private function createPurchase(
        Product $product,
        string $businessDate,
        string $grade,
        float $quantity,
        float $unitPrice,
        ?User $purchaser = null,
        ?Supplier $vendor = null,
        string $paymentMethod = 'Cash',
        string $invoiceStatus = 'approved'
    ): PurchaseInvoice {
        $purchaser ??= User::factory()->create(['name' => 'Purchaser '.str()->random(5)]);
        $vendor ??= Supplier::factory()->create(['name' => 'Vendor '.str()->random(5), 'mobile_number' => '9876543210', 'location' => 'Market Yard']);

        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $vendor->id,
            'business_date' => $businessDate,
            'status' => 'submitted',
            'cart_number' => 'PC-'.str()->upper(str()->random(10)),
            'payment_method' => $paymentMethod,
        ]);

        $lineTotal = round($quantity * $unitPrice, 2);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'grade' => $grade !== 'NULL' ? $grade : '',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ]);

        $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);

        return PurchaseInvoice::query()->create([
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $vendor->id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'INV-'.str()->upper(str()->random(8)),
            'amount' => $lineTotal,
            'discount_amount' => 0,
            'payment_method' => $paymentMethod,
            'payment_status' => strtolower($paymentMethod) === 'credit' ? 'credit_pending_approval' : 'paid',
            'payment_paid_by' => strtolower($paymentMethod) === 'credit' ? 'vendor_credit' : 'purchaser',
            'paid_amount' => strtolower($paymentMethod) === 'credit' ? 0 : $lineTotal,
            'status' => $invoiceStatus,
        ]);
    }

    public function test_sidebar_includes_item_purchase_report_under_reports_v1(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'));

        $response->assertOk();
        $response->assertSee('Reports V1');
        $response->assertSee('Purchase Report');
        $response->assertSee('Item Purchase Report');
        $response->assertSee(route('admin.cashbook.reports-v1.item-purchase'));
    }

    public function test_landing_page_shows_product_search_hero_when_no_item_selected(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase'));

        $response->assertOk();
        $response->assertSee('Item Purchase Report');
        $response->assertSee('Select a Product to View Item Purchase Report');
        $response->assertSee('name="search_product"', false);
    }

    public function test_product_search_by_name_returns_matching_results_or_selects_product(): void
    {
        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create(['name' => 'Bangalore Tomato', 'category_id' => $category->id, 'unit' => 'kg']);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'search_product' => 'Bangalore Tomato',
        ]));

        $response->assertOk();
        $response->assertSee('Bangalore Tomato');
        $response->assertSee('Vegetables');
        $response->assertSee('Total Purchased Qty');
    }

    public function test_product_url_uses_public_uuid_without_raw_numeric_id(): void
    {
        $product = Product::factory()->create(['name' => 'Safe UUID Tomato', 'unit' => 'kg']);
        $this->createPurchase($product, '2026-10-23', 'A', 25.0, 30.0);

        // Access via public_uuid (non-numeric URL)
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Safe UUID Tomato');
        // Form hidden input uses product public_uuid
        $response->assertSee('name="product" value="'.$product->public_uuid.'"', false);
        // Does not output raw product_id in newly generated visible form
        $response->assertDontSee('name="product_id"', false);
    }

    public function test_legacy_product_id_url_remains_backward_compatible(): void
    {
        $product = Product::factory()->create(['name' => 'Legacy Tomato', 'unit' => 'kg']);
        $this->createPurchase($product, '2026-10-23', 'A', 20.0, 25.0);

        // Access via legacy product_id parameter
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product_id' => $product->id,
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Legacy Tomato');
        $this->assertEquals(20.0, $response->viewData('summary')->total_quantity);
    }

    public function test_cash_and_credit_purchases_reconcile_to_total_amount(): void
    {
        $product = Product::factory()->create(['name' => 'Cucumber', 'unit' => 'kg']);

        // Cash Purchase: 40 kg @ 25 = 1000
        $this->createPurchase($product, '2026-10-23', 'A', 40.0, 25.0, null, null, 'Cash');
        // Credit Purchase: 60 kg @ 25 = 1500
        $this->createPurchase($product, '2026-10-23', 'A', 60.0, 25.0, null, null, 'Credit');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'period' => 'today',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');

        // Total qty: 100 kg
        $this->assertEquals(100.0, $summary->total_quantity);
        // Total amount: 2500
        $this->assertEquals(2500.0, $summary->total_amount);
        // Cash: 1000
        $this->assertEquals(1000.0, $summary->cash_purchase_amount);
        // Credit: 1500
        $this->assertEquals(1500.0, $summary->credit_purchase_amount);
        // Cash + Credit == Total Amount
        $this->assertEquals($summary->total_amount, $summary->cash_purchase_amount + $summary->credit_purchase_amount);
    }

    public function test_single_product_credit_bill_calculates_exact_attributable_pending(): void
    {
        $product = Product::factory()->create(['name' => 'Green Chilli', 'unit' => 'kg']);
        $vendor = Supplier::factory()->create(['name' => 'Chilli Vendor']);

        // Single product credit bill: 50 kg @ 40 = 2000
        $invoice = $this->createPurchase($product, '2026-10-23', 'A', 50.0, 40.0, null, $vendor, 'Credit');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'period' => 'today',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');

        // Since invoice has 0 payments yet, entire 2000 is pending and 100% attributable
        $this->assertEquals(2000.0, $summary->associated_credit_bill_pending);
        $this->assertEquals(2000.0, $summary->exact_attributable_pending);
        $this->assertEquals(1, $summary->credit_invoice_count);

        $items = $response->viewData('items');
        $this->assertTrue($items->first()->is_single_product_bill);
        $this->assertEquals(2000.0, $items->first()->attributable_pending);
    }

    public function test_multi_product_credit_bill_clearly_labels_bill_pending_without_false_attribution(): void
    {
        $productA = Product::factory()->create(['name' => 'Cabbage', 'unit' => 'kg']);
        $productB = Product::factory()->create(['name' => 'Cauliflower', 'unit' => 'kg']);
        $vendor = Supplier::factory()->create(['name' => 'Shared Produce Vendor']);
        $purchaser = User::factory()->create(['name' => 'Shared Purchaser']);

        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $vendor->id,
            'business_date' => '2026-10-23',
            'status' => 'submitted',
            'cart_number' => 'PC-MULTI',
            'payment_method' => 'Credit',
        ]);

        // Product A line: 30 kg @ 20 = 600
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $productA->id,
            'grade' => 'A',
            'quantity' => 30.0,
            'unit_price' => 20.0,
            'line_total' => 600.0,
        ]);
        // Product B line: 20 kg @ 40 = 800
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $productB->id,
            'grade' => 'A',
            'quantity' => 20.0,
            'unit_price' => 40.0,
            'line_total' => 800.0,
        ]);

        $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);

        // Invoice total is 1400 (600 + 800)
        PurchaseInvoice::query()->create([
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $vendor->id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'INV-SHARED-1400',
            'amount' => 1400.0,
            'discount_amount' => 0,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
            'paid_amount' => 0,
            'status' => 'approved',
        ]);

        // View Report for Product A
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $productA->public_uuid,
            'period' => 'today',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');

        // Product A purchase line is only 600
        $this->assertEquals(600.0, $summary->total_amount);
        $this->assertEquals(600.0, $summary->credit_purchase_amount);
        // The associated credit bill has 1400 pending
        $this->assertEquals(1400.0, $summary->associated_credit_bill_pending);
        // Exact attributable pending is 0 because bill is shared across multiple products
        $this->assertEquals(0.0, $summary->exact_attributable_pending);
        $this->assertTrue($summary->has_multi_product_credit_bills);

        $items = $response->viewData('items');
        $itemRow = $items->first();
        $this->assertFalse($itemRow->is_single_product_bill);
        $this->assertNull($itemRow->attributable_pending);
        $this->assertEquals(1400.0, $itemRow->bill_pending);

        // Blade view renders Bill Pending with note
        $response->assertSee('Bill Pending*');
    }

    public function test_credit_bill_partially_and_fully_settled_via_vendor_settlement_allocations(): void
    {
        $product = Product::factory()->create(['name' => 'Garlic', 'unit' => 'kg']);
        $vendor = Supplier::factory()->create(['name' => 'Garlic Trader']);

        // Bill total 2000
        $invoice = $this->createPurchase($product, '2026-10-23', 'A', 20.0, 100.0, null, $vendor, 'Credit');

        // Create partial payment allocation of 1200
        $settlementId = DB::table('vendor_settlements')->insertGetId([
            'public_uuid' => (string) str()->uuid(),
            'supplier_id' => $vendor->id,
            'actual_payment_amount' => 1200.0,
            'payment_date' => '2026-10-23',
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vendor_settlement_allocations')->insert([
            'vendor_settlement_id' => $settlementId,
            'purchase_invoice_id' => $invoice->id,
            'cash_allocated' => 1200.0,
            'advance_allocated' => 0.0,
            'discount_allocated' => 0.0,
            'total_settled' => 1200.0,
            'is_reversed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'period' => 'today',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');
        // Total credit bill pending is now 2000 - 1200 = 800
        $this->assertEquals(800.0, $summary->associated_credit_bill_pending);

        $items = $response->viewData('items');
        $this->assertEquals(1200.0, $items->first()->invoice_paid);
        $this->assertEquals(800.0, $items->first()->invoice_outstanding);
        $this->assertEquals('Credit • Partially Paid', $items->first()->payment_status_badge);

        // Now settle the remaining 800 via advance allocation
        $settlement2Id = DB::table('vendor_settlements')->insertGetId([
            'public_uuid' => (string) str()->uuid(),
            'supplier_id' => $vendor->id,
            'actual_payment_amount' => 0.0,
            'payment_date' => '2026-10-23',
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vendor_settlement_allocations')->insert([
            'vendor_settlement_id' => $settlement2Id,
            'purchase_invoice_id' => $invoice->id,
            'cash_allocated' => 0.0,
            'advance_allocated' => 800.0,
            'discount_allocated' => 0.0,
            'total_settled' => 800.0,
            'is_reversed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $responseFullyPaid = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'period' => 'today',
        ]));

        $responseFullyPaid->assertOk();
        $summaryFull = $responseFullyPaid->viewData('summary');
        // Pending should now be 0.0
        $this->assertEquals(0.0, $summaryFull->associated_credit_bill_pending);
        $this->assertEquals('Credit • Settled', $responseFullyPaid->viewData('items')->first()->payment_status_badge);
    }

    public function test_cancelled_or_reversed_settlements_are_excluded(): void
    {
        $product = Product::factory()->create(['name' => 'Ginger', 'unit' => 'kg']);
        $vendor = Supplier::factory()->create(['name' => 'Ginger Vendor']);

        $invoice = $this->createPurchase($product, '2026-10-23', 'A', 10.0, 100.0, null, $vendor, 'Credit');

        $settlementId = DB::table('vendor_settlements')->insertGetId([
            'public_uuid' => (string) str()->uuid(),
            'supplier_id' => $vendor->id,
            'actual_payment_amount' => 500.0,
            'payment_date' => '2026-10-23',
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Reversed settlement allocation
        DB::table('vendor_settlement_allocations')->insert([
            'vendor_settlement_id' => $settlementId,
            'purchase_invoice_id' => $invoice->id,
            'cash_allocated' => 500.0,
            'advance_allocated' => 0.0,
            'discount_allocated' => 0.0,
            'total_settled' => 500.0,
            'is_reversed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'period' => 'today',
        ]));

        $response->assertOk();
        // The reversed allocation should not count as paid; pending remains 1000
        $summary = $response->viewData('summary');
        $this->assertEquals(1000.0, $summary->associated_credit_bill_pending);
        $this->assertEquals('Credit • Unpaid', $response->viewData('items')->first()->payment_status_badge);
    }

    public function test_purchase_type_filter_scopes_cash_and_credit(): void
    {
        $product = Product::factory()->create(['name' => 'Potato', 'unit' => 'kg']);

        // Cash purchase
        $this->createPurchase($product, '2026-10-23', 'A', 30.0, 20.0, null, null, 'Cash');
        // Credit purchase
        $this->createPurchase($product, '2026-10-23', 'A', 50.0, 20.0, null, null, 'Credit');

        // Filter Cash Only
        $respCash = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'payment' => 'cash',
            'period' => 'today',
        ]));
        $respCash->assertOk();
        $this->assertEquals(30.0, $respCash->viewData('summary')->total_quantity);
        $this->assertEquals(600.0, $respCash->viewData('summary')->total_amount);

        // Filter Credit Only
        $respCredit = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'payment' => 'credit',
            'period' => 'today',
        ]));
        $respCredit->assertOk();
        $this->assertEquals(50.0, $respCredit->viewData('summary')->total_quantity);
        $this->assertEquals(1000.0, $respCredit->viewData('summary')->total_amount);
    }

    public function test_vendor_details_and_credit_drill_down_rendered(): void
    {
        $product = Product::factory()->create(['name' => 'Capsicum', 'unit' => 'kg']);
        $vendor = Supplier::factory()->create([
            'name' => 'Super Green Suppliers',
            'mobile_number' => '9988776655',
            'location' => 'Block C Wholesale Yard',
        ]);

        $invoice = $this->createPurchase($product, '2026-10-23', 'A', 40.0, 50.0, null, $vendor, 'Credit');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Super Green Suppliers');
        $response->assertSee('9988776655');
        // Vendor link to canonical finance profile
        $response->assertSee(route('admin.cashbook.finance.purchase.vendors.show', $vendor->public_uuid));
        // Original bill link
        $response->assertSee(route('purchasing.invoices.show', $invoice->public_uuid));
        // Drill-down modal trigger exists
        $response->assertSee('openDrilldown', false);
    }

    public function test_table_headers_are_sortable_and_rows_contain_sort_attributes(): void
    {
        $product = Product::factory()->create(['name' => 'Broccoli', 'unit' => 'kg']);
        $purchaser = User::factory()->create(['name' => 'Alice Purchaser']);
        $vendor = Supplier::factory()->create(['name' => 'Bob Farm Vendor']);

        $this->createPurchase($product, '2026-10-23', 'A', 25.0, 40.0, $purchaser, $vendor, 'Cash');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'period' => 'today',
        ]));

        $response->assertOk();

        // Verify sortTable Alpine method and column triggers
        $response->assertSee("sortTable(colKey, type = 'text')", false);
        $response->assertSee("sortTable('date', 'date')", false);
        $response->assertSee("sortTable('bill', 'text')", false);
        $response->assertSee("sortTable('grade', 'text')", false);
        $response->assertSee("sortTable('qty', 'num')", false);
        $response->assertSee("sortTable('rate', 'num')", false);
        $response->assertSee("sortTable('total', 'num')", false);
        $response->assertSee("sortTable('type', 'text')", false);
        $response->assertSee("sortTable('purchaser', 'text')", false);
        $response->assertSee("sortTable('vendor', 'text')", false);
        $response->assertSee("sortTable('pending', 'num')", false);

        // Verify row has sorting attributes
        $response->assertSee('data-sort-row', false);
        $response->assertSee('data-date="2026-10-23"', false);
        $response->assertSee('data-qty="25"', false);
        $response->assertSee('data-rate="40"', false);
        $response->assertSee('data-total="1000"', false);
        $response->assertSee('data-purchaser="Alice Purchaser"', false);
        $response->assertSee('data-vendor="Bob Farm Vendor"', false);

        // Test optional server-side sorting works smoothly
        $sortedResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'period' => 'today',
            'sort_by' => 'qty',
            'sort_dir' => 'asc',
        ]));
        $sortedResponse->assertOk();
    }

    public function test_filter_dropdown_options_are_dynamic_to_available_data(): void
    {
        $productA = Product::factory()->create(['name' => 'Premium Tomato', 'unit' => 'kg']);
        $productB = Product::factory()->create(['name' => 'Other Vegetable', 'unit' => 'kg']);

        $purchaserTomato = User::factory()->create(['name' => 'Tomato Purchaser']);
        $purchaserOther = User::factory()->create(['name' => 'Other Purchaser']);

        $vendorTomato = Supplier::factory()->create(['name' => 'Tomato Farm Co']);
        $vendorOther = Supplier::factory()->create(['name' => 'Other Supplier']);

        // Only Grade A was purchased for Premium Tomato
        $this->createPurchase($productA, '2026-10-23', 'A', 50.0, 30.0, $purchaserTomato, $vendorTomato, 'Cash');
        // Grade B and other purchaser/vendor for Other Vegetable
        $this->createPurchase($productB, '2026-10-23', 'B', 10.0, 20.0, $purchaserOther, $vendorOther, 'Cash');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $productA->public_uuid,
            'period' => 'today',
        ]));

        $response->assertOk();

        $options = $response->viewData('options');
        $this->assertNotNull($options);

        // Grade options should dynamically contain Grade A, and NOT non-existent Grade B/C/D for this product
        $gradeKeys = collect($options['grades'])->pluck('key')->all();
        $this->assertContains('A', $gradeKeys);
        $this->assertNotContains('B', $gradeKeys);
        $this->assertNotContains('C', $gradeKeys);
        $this->assertNotContains('D', $gradeKeys);

        // Purchaser options should only contain purchasers who purchased this product
        $purchaserIds = collect($options['purchasers'])->pluck('id')->all();
        $this->assertContains($purchaserTomato->id, $purchaserIds);
        $this->assertNotContains($purchaserOther->id, $purchaserIds);

        // Vendor options should only contain vendors who supplied this product
        $vendorIds = collect($options['vendors'])->pluck('id')->all();
        $this->assertContains($vendorTomato->id, $vendorIds);
        $this->assertNotContains($vendorOther->id, $vendorIds);

        // Assert rendered HTML select does not have Grade C or D
        $response->assertSee('Grade A');
        $response->assertDontSee('Grade C');
        $response->assertDontSee('Grade D');
    }

    public function test_dropdown_options_and_counts_are_scoped_to_active_date_range_and_preserve_out_of_period_selection(): void
    {
        $product = Product::factory()->create(['name' => 'Scoped Date Item', 'unit' => 'kg']);
        $vendorA = Supplier::factory()->create(['name' => 'October Vendor']);
        $vendorB = Supplier::factory()->create(['name' => 'September Vendor']);

        // Vendor A supplied Grade A in October
        $this->createPurchase($product, '2026-10-15', 'A', 50.0, 20.0, null, $vendorA, 'Cash');
        // Vendor B supplied Grade B in September
        $this->createPurchase($product, '2026-09-12', 'B', 30.0, 25.0, null, $vendorB, 'Cash');

        // Request October 2026 without vendor filter: Vendor B should NOT appear in October options
        $responseOct = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'period' => 'custom',
        ]));

        $responseOct->assertOk();
        $optionsOct = $responseOct->viewData('options');

        $vendorOctIds = collect($optionsOct['vendors'])->pluck('id')->all();
        $this->assertContains($vendorA->id, $vendorOctIds);
        $this->assertNotContains($vendorB->id, $vendorOctIds);

        $gradeOctKeys = collect($optionsOct['grades'])->pluck('key')->all();
        $this->assertContains('A', $gradeOctKeys);
        $this->assertNotContains('B', $gradeOctKeys);

        // Request October with Vendor B explicitly selected in URL: Vendor B should be preserved with count 0
        $responseOutOfPeriod = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.item-purchase', [
            'product' => $product->public_uuid,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'period' => 'custom',
            'vendor_id' => $vendorB->id,
            'grade' => 'B',
        ]));

        $responseOutOfPeriod->assertOk();
        $optionsOutOfPeriod = $responseOutOfPeriod->viewData('options');
        $vendorBEntry = collect($optionsOutOfPeriod['vendors'])->firstWhere('id', $vendorB->id);
        $this->assertNotNull($vendorBEntry);
        $this->assertEquals(0, $vendorBEntry->purchase_count);

        // Diagnostic assistance explains out of period purchase date
        $responseOutOfPeriod->assertSee('No purchases found matching active filters');
        $responseOutOfPeriod->assertSee('September Vendor');
        $responseOutOfPeriod->assertSee('12 Sep 2026');
    }

    public function test_unauthorized_user_cannot_access_report(): void
    {
        $unauthorized = User::factory()->create();

        $response = $this->actingAs($unauthorized)->get(route('admin.cashbook.reports-v1.item-purchase'));

        $this->assertTrue(in_array($response->status(), [302, 403], true));
    }
}
