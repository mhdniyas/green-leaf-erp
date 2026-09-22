<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaserDailyOptimizedTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $nonPurchaser;

    private Warehouse $warehouse;

    private Category $category;

    private string $operationalDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->nonPurchaser = User::factory()->create();

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->operationalDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();
    }

    public function test_purchaser_can_access_daily_page(): void
    {
        $response = $this->actingAs($this->purchaser)->get("/purchaser/daily?date={$this->operationalDate}&purchase_grade=A");

        $response->assertStatus(200);
        $response->assertViewIs('purchasing.purchaser.daily');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get("/purchaser/daily?date={$this->operationalDate}&purchase_grade=A");

        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr_manager');

        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Forbidden Test Product',
            'sku' => 'FORB-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        // Web request gets redirected to dashboard per application exception handler
        $response = $this->actingAs($hrUser)->get("/purchaser/daily?date={$this->operationalDate}&purchase_grade=A");
        $response->assertRedirect(route('dashboard'));

        // JSON / API request receives 403 Forbidden
        $jsonResponse = $this->actingAs($hrUser)->getJson("/purchaser/daily/products/{$product->id}/demand?date={$this->operationalDate}&purchase_grade=A");
        $jsonResponse->assertStatus(403);
    }

    public function test_daily_page_renders_only_pending_products_with_exact_quantities(): void
    {
        $shop = Shop::create([
            'name' => 'Shop 1',
            'code' => 'SH-01',
            'is_active' => true,
        ]);

        $pendingProduct = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Pending Tomato',
            'sku' => 'TOM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $completedProduct = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Completed Potato',
            'sku' => 'POT-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => $this->operationalDate,
            'order_date' => $this->operationalDate,
            'order_number' => 'ORD-1001',
            'state' => 'approved',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        // Pending product: 50 needed, 10 bought, 15 in cart -> 40 remaining
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $pendingProduct->id,
            'product_grade' => 'A',
            'requested_qty' => 50,
            'approved_qty' => 50,
            'unit' => 'kg',
        ]);

        // Completed product: 20 needed, 20 bought -> 0 remaining
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $completedProduct->id,
            'product_grade' => 'A',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'kg',
        ]);

        // Draft cart for pending product
        $draftCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->operationalDate,
            'cart_number' => 'CART-001',
            'status' => 'draft',
            'purchase_grade' => 'A',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $draftCart->id,
            'product_id' => $pendingProduct->id,
            'quantity' => 15,
            'unit_price' => 20,
            'grade' => 'A',
        ]);

        // Submitted cart with bought items
        $submittedCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->operationalDate,
            'cart_number' => 'CART-002',
            'status' => 'submitted',
            'purchase_grade' => 'A',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $submittedCart->id,
            'product_id' => $pendingProduct->id,
            'quantity' => 10,
            'unit_price' => 20,
            'grade' => 'A',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $submittedCart->id,
            'product_id' => $completedProduct->id,
            'quantity' => 20,
            'unit_price' => 15,
            'grade' => 'A',
        ]);

        $response = $this->actingAs($this->purchaser)->get("/purchaser/daily?date={$this->operationalDate}&purchase_grade=A");

        $response->assertStatus(200);
        $response->assertSee('Pending Tomato');
        $response->assertDontSee('Completed Potato');
        $response->assertDontSee('window.purchaserDailyDemandData', false);
        $response->assertDontSee('id="section-completed"', false);
    }

    public function test_view_demand_endpoint_returns_json_details(): void
    {
        $shop = Shop::create([
            'name' => 'Shop 1',
            'code' => 'SH-01',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Onion Red',
            'sku' => 'ONI-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => $this->operationalDate,
            'order_date' => $this->operationalDate,
            'order_number' => 'ORD-2001',
            'state' => 'approved',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 30,
            'approved_qty' => 30,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->purchaser)->getJson("/purchaser/daily/products/{$product->id}/demand?date={$this->operationalDate}&purchase_grade=A");

        $response->assertStatus(200);
        $response->assertJsonPath('product_id', $product->id);
        $response->assertJsonPath('total_approved_qty', 30);
        $response->assertJsonPath('shop_details.0.shop_name', 'Shop 1');
    }

    public function test_purchase_options_endpoint_returns_units(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Carrot Fresh',
            'sku' => 'CAR-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->purchaser)->getJson("/purchaser/daily/products/{$product->id}/purchase-options");

        $response->assertStatus(200);
        $response->assertJsonPath('product_id', $product->id);
        $response->assertJsonPath('base_unit', 'kg');
        $response->assertJsonPath('step', '0.5');
    }

    public function test_query_count_remains_low_and_flat(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $response = $this->actingAs($this->purchaser)->get("/purchaser/daily?date={$this->operationalDate}&purchase_grade=A");

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(20, count($queries));
    }
}
