<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing\V2;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Queries\Purchasing\V2\PurchaserV2ReportQuery;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserV2ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Carbon $testDate;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 10:00:00');

        $this->seed(RolePermissionSeeder::class);
        Role::findOrCreate('purchaser');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->testDate = app(PurchaserBusinessDayService::class)->operationalDate();
        $this->category = Category::factory()->create(['name' => 'Fresh Greens', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createProduct(string $name, string $sku): Product
    {
        return Product::query()->create([
            'category_id' => $this->category->id,
            'name' => $name,
            'sku' => $sku,
            'unit' => 'kg',
            'base_price' => 40.00,
            'vendor_price' => 30.00,
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);
    }

    public function test_purchaser_can_access_v2_daily_report(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Royal Agritech']);
        $product = $this->createProduct('Palak Fresh', 'PAL-01');

        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $this->testDate,
            'cart_number' => 'PC-REP-01',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'payment_status' => 'paid',
            'payment_method' => 'Cash',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'grade' => 'A',
            'quantity' => 20.0,
            'unit_price' => 25.00,
            'line_total' => 500.00,
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser-v2.report', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertViewIs('purchasing.purchaser-v2.report');
        $response->assertSee('Daily Purchase Report');
        $response->assertSee('Palak Fresh');
        $response->assertSee('Royal Agritech');
        $response->assertSee('500.00');
    }

    public function test_unauthorized_user_cannot_access_v2_report(): void
    {
        $guest = User::factory()->create();

        $response = $this->actingAs($guest)->get(route('purchaser-v2.report', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_report_calculates_kpis_and_fulfilment_accurately(): void
    {
        $supplier1 = Supplier::factory()->create(['name' => 'Sunrise Produce']);
        $supplier2 = Supplier::factory()->create(['name' => 'Moonlight Agro']);
        $p1 = $this->createProduct('Tomato Hybrid', 'TOM-01');
        $p2 = $this->createProduct('Onion Local', 'ONI-01');

        // Shop demand: 50kg Tomato, 100kg Onion
        $shop = Shop::factory()->create();
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $this->testDate->toDateString(),
            'state' => 'approved',
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $p1->id,
            'product_grade' => 'A',
            'approved_qty' => 50.0,
            'requested_qty' => 50.0,
            'unit' => 'kg',
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $p2->id,
            'product_grade' => 'A',
            'approved_qty' => 100.0,
            'requested_qty' => 100.0,
            'unit' => 'kg',
        ]);

        // Cart 1: 50kg Tomato @ ₹20 from Sunrise (Paid)
        $grn1 = GoodsReceived::factory()->create();
        $cart1 = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $supplier1->id,
            'business_date' => $this->testDate,
            'cart_number' => 'PC-REP-C1',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'payment_status' => 'paid',
            'payment_method' => 'Cash',
        ]);
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart1->id,
            'product_id' => $p1->id,
            'grade' => 'A',
            'quantity' => 50.0,
            'unit_price' => 20.00,
            'line_total' => 1000.00,
        ]);
        PurchaseInvoice::query()->create([
            'goods_received_id' => $grn1->id,
            'purchaser_cart_id' => $cart1->id,
            'supplier_id' => $supplier1->id,
            'invoice_number' => 'INV-SUN-001',
            'amount' => 1000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'payment_method' => 'Cash',
        ]);

        // Cart 2: 60kg Onion @ ₹30 from Moonlight (Credit)
        $grn2 = GoodsReceived::factory()->create();
        $cart2 = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $supplier2->id,
            'business_date' => $this->testDate,
            'cart_number' => 'PC-REP-C2',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'payment_status' => 'pending',
            'payment_method' => 'Credit',
        ]);
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart2->id,
            'product_id' => $p2->id,
            'grade' => 'A',
            'quantity' => 60.0,
            'unit_price' => 30.00,
            'line_total' => 1800.00,
        ]);
        PurchaseInvoice::query()->create([
            'goods_received_id' => $grn2->id,
            'purchaser_cart_id' => $cart2->id,
            'supplier_id' => $supplier2->id,
            'invoice_number' => 'INV-MOON-002',
            'amount' => 1800.00,
            'discount_amount' => 100.00,
            'paid_amount' => 500.00, // Partial paid
            'payment_status' => 'partial',
            'payment_method' => 'Credit',
        ]);

        $response = $this->actingAs($this->purchaser)->getJson(route('purchaser-v2.report', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'metrics' => [
                'total_spend',
                'total_paid',
                'total_balance',
                'distinct_products_count',
                'total_quantity_bought',
                'total_bills_count',
                'paid_bills_count',
                'credit_bills_count',
            ],
            'items',
            'bills',
        ]);

        $data = $response->json();

        // Total spend: 1000 + (1800 - 100) = 2700
        $this->assertEquals(2700.00, $data['metrics']['total_spend']);
        // Total paid: 1000 + 500 = 1500
        $this->assertEquals(1500.00, $data['metrics']['total_paid']);
        // Total balance: 1200
        $this->assertEquals(1200.00, $data['metrics']['total_balance']);
        // Distinct items: 2
        $this->assertEquals(2, $data['metrics']['distinct_products_count']);
        // Total qty: 50 + 60 = 110
        $this->assertEquals(110.00, $data['metrics']['total_quantity_bought']);
        // Total bills: 2
        $this->assertEquals(2, $data['metrics']['total_bills_count']);
    }

    public function test_report_query_slope_remains_bounded(): void
    {
        $supplier = Supplier::factory()->create();

        // 1. Test with 1 cart
        $p1 = $this->createProduct('Product Single', 'SKU-RPT-1');
        $cart1 = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $this->testDate,
            'cart_number' => 'PC-REP-SINGLE',
            'status' => 'submitted',
            'purchase_grade' => 'A',
        ]);
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart1->id,
            'product_id' => $p1->id,
            'grade' => 'A',
            'quantity' => 10.0,
            'unit_price' => 20.00,
            'line_total' => 200.00,
        ]);

        /** @var PurchaserV2ReportQuery $reportQuery */
        $reportQuery = app(PurchaserV2ReportQuery::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $reportQuery->getDailyReport($this->testDate, 'A', $this->purchaser);
        $count1 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 2. Add 9 more carts
        for ($i = 2; $i <= 10; $i++) {
            $p = $this->createProduct("Product Batch {$i}", "SKU-RPT-{$i}");
            $cart = PurchaserCart::query()->create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $supplier->id,
                'business_date' => $this->testDate,
                'cart_number' => "PC-REP-{$i}",
                'status' => 'submitted',
                'purchase_grade' => 'A',
            ]);
            PurchaserCartItem::query()->create([
                'purchaser_cart_id' => $cart->id,
                'product_id' => $p->id,
                'grade' => 'A',
                'quantity' => 10.0,
                'unit_price' => 20.00,
                'line_total' => 200.00,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $reportQuery->getDailyReport($this->testDate, 'A', $this->purchaser);
        $count10 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Query count remains flat / bounded regardless of scale
        $this->assertSame($count1, $count10);
        $this->assertLessThanOrEqual(8, $count10);
    }
}
