<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

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
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserBulkBuyOptimizedTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Category $category;

    private Product $productA;

    private Product $productB;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 10:00:00');
        Role::findOrCreate('purchaser');
        Role::findOrCreate('admin');

        $this->purchaser = User::factory()->create(['name' => 'John Purchaser']);
        $this->purchaser->assignRole('purchaser');

        $this->category = Category::factory()->create(['name' => 'VEG', 'is_active' => true]);
        $this->supplier = Supplier::factory()->create(['name' => 'Fresh Farm', 'type' => 'Vendor']);

        $this->productA = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Tomato A',
            'sku' => 'VEG-TOM-A',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        $this->productB = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Carrot B',
            'sku' => 'VEG-CAR-B',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        $shop = Shop::factory()->create(['name' => 'Main Branch']);

        // Create approved shop orders for business date 2026-08-24
        $shopOrder = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-24',
            'state' => 'approved',
            'order_number' => 'SO-20260824-001',
        ]);
        DB::table('shop_orders')->where('id', $shopOrder->id)->update(['business_date' => '2026-08-24']);

        ShopOrderItem::query()->create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $this->productA->id,
            'product_grade' => 'A',
            'approved_qty' => 50.0,
            'requested_qty' => 50.0,
            'unit' => 'kg',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $this->productB->id,
            'product_grade' => 'A',
            'approved_qty' => 30.0,
            'requested_qty' => 30.0,
            'unit' => 'kg',
        ]);

        // Create draft cart for Tomato A (10 kg)
        $draftCart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'business_date' => '2026-08-24',
            'status' => 'draft',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-DRAFT-001',
        ]);
        DB::table('purchaser_carts')->where('id', $draftCart->id)->update(['business_date' => '2026-08-24']);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $draftCart->id,
            'product_id' => $this->productA->id,
            'grade' => 'A',
            'quantity' => 10.0,
            'unit_price' => 5.0,
            'line_total' => 50.0,
        ]);

        // Create submitted cart for Tomato A (20 kg)
        $submittedCart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-24',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-SUB-001',
        ]);
        DB::table('purchaser_carts')->where('id', $submittedCart->id)->update(['business_date' => '2026-08-24']);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $submittedCart->id,
            'product_id' => $this->productA->id,
            'grade' => 'A',
            'quantity' => 20.0,
            'unit_price' => 5.0,
            'line_total' => 100.0,
        ]);

        // Create historical overdue cart from 2026-08-20 with GRN and Invoice
        $overdueCart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-20',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-OVERDUE-001',
        ]);
        DB::table('purchaser_carts')->where('id', $overdueCart->id)->update(['business_date' => '2026-08-20']);

        $grn = GoodsReceived::query()->create([
            'supplier_id' => $this->supplier->id,
            'purchaser_cart_id' => $overdueCart->id,
            'received_by' => $this->purchaser->id,
            'grn_number' => 'GRN-OD-001',
            'received_at' => '2026-08-20 12:00:00',
            'status' => 'pending',
            'bill_status' => 'pending',
        ]);
        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'purchaser_cart_id' => $overdueCart->id,
            'invoice_number' => 'INV-OD-001',
            'invoice_date' => '2026-08-20',
            'amount' => 500.0,
            'payment_status' => 'unpaid',
            'payment_method' => 'Credit',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_bulk_buy_initial_page_loads_with_correct_quantities(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', ['date' => '2026-08-24']));

        $response->assertOk();
        $response->assertViewIs('purchasing.purchaser.bulk_buy');

        // Check products rendered
        $response->assertSee('Tomato A');
        $response->assertSee('Carrot B');

        // Tomato A: Approved = 50, Bought = 20, Draft = 10, Left = 30
        $response->assertSee('Need: 50.0 kg');
        $response->assertSee('Bought: 20.0');
        $response->assertSee('In Cart: 10.0 kg');
        $response->assertSee('Left: 30.0');

        // Carrot B: Approved = 30, Bought = 0, Draft = 0, Left = 30
        $response->assertSee('Need: 30.0 kg');
    }

    public function test_bulk_buy_initial_page_does_not_execute_l3_historical_queries(): void
    {
        $executedQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$executedQueries): void {
            $executedQueries[] = $query->sql;
        });

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', ['date' => '2026-08-24']));
        $response->assertOk();

        // Must NOT query vendor_settlement_allocations or historical overdue carts
        foreach ($executedQueries as $sql) {
            $this->assertStringNotContainsString('vendor_settlement_allocations', $sql);
            $this->assertStringNotContainsString('GRN-OD-001', $sql);
            $this->assertStringNotContainsString('VC-OVERDUE-001', $sql);
        }

        // Query count should be minimal (< 20)
        $this->assertLessThan(20, count($executedQueries));
    }

    public function test_bulk_buy_fulfilled_tab_endpoint_works(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.tabs.fulfilled', [
            'date' => '2026-08-24',
            'purchase_grade' => 'A',
        ]));

        $response->assertOk();
    }

    public function test_bulk_buy_product_search_addon_endpoint_works(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.product-search', [
            'q' => 'Tomato',
            'purchase_grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $this->productA->id,
            'name' => 'Tomato A',
        ]);
    }
}
