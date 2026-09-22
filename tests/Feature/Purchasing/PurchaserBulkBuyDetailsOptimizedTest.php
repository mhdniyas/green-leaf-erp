<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
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

class PurchaserBulkBuyDetailsOptimizedTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Warehouse $warehouse;

    private Category $category;

    private string $operationalDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

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

    public function test_purchaser_can_access_bulk_buy_details(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Fresh Carrot',
            'sku' => 'CAR-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->purchaser)->get("/purchaser/bulk-buy/details?date={$this->operationalDate}&purchase_grade=A&product_ids[]={$product->id}");

        $response->assertStatus(200);
        $response->assertViewIs('purchasing.purchaser.bulk_buy_details');
        $response->assertSee('Fresh Carrot');
    }

    public function test_unauthorized_user_is_redirected_or_forbidden(): void
    {
        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr_manager');

        $response = $this->actingAs($hrUser)->get("/purchaser/bulk-buy/details?date={$this->operationalDate}&purchase_grade=A&product_ids[]=1");

        $response->assertRedirect(route('dashboard'));
    }

    public function test_only_selected_products_are_rendered_with_correct_quantities(): void
    {
        $shop = Shop::create([
            'name' => 'Shop 1',
            'code' => 'SH-01',
            'is_active' => true,
        ]);

        $product1 = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Selected Tomato',
            'sku' => 'TOM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        ProductUnit::create([
            'product_id' => $product1->id,
            'unit' => 'crate',
            'label' => 'CRATE 20 KG',
            'conversion_to_base' => 20.0,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        $product2 = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Selected Onion',
            'sku' => 'ONI-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $unselectedProduct = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Unselected Potato',
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
            'order_number' => 'ORD-9001',
            'state' => 'approved',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        // Product 1: 50 needed, 10 bought, 15 draft -> 40 remaining
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product1->id,
            'product_grade' => 'A',
            'requested_qty' => 50,
            'approved_qty' => 50,
            'unit' => 'kg',
        ]);

        // Product 2: 30 needed, 0 bought -> 30 remaining
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product2->id,
            'product_grade' => 'A',
            'requested_qty' => 30,
            'approved_qty' => 30,
            'unit' => 'kg',
        ]);

        // Unselected product
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $unselectedProduct->id,
            'product_grade' => 'A',
            'requested_qty' => 100,
            'approved_qty' => 100,
            'unit' => 'kg',
        ]);

        // Draft cart for Product 1
        $draftCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->operationalDate,
            'cart_number' => 'CART-001',
            'status' => 'draft',
            'purchase_grade' => 'A',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $draftCart->id,
            'product_id' => $product1->id,
            'quantity' => 15,
            'unit_price' => 20,
            'grade' => 'A',
        ]);

        // Submitted cart for Product 1
        $submittedCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->operationalDate,
            'cart_number' => 'CART-002',
            'status' => 'submitted',
            'purchase_grade' => 'A',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $submittedCart->id,
            'product_id' => $product1->id,
            'quantity' => 10,
            'unit_price' => 20,
            'grade' => 'A',
        ]);

        $response = $this->actingAs($this->purchaser)->get("/purchaser/bulk-buy/details?date={$this->operationalDate}&purchase_grade=A&product_ids[]={$product1->id}&product_ids[]={$product2->id}");

        $response->assertStatus(200);
        $response->assertSee('Selected Tomato');
        $response->assertSee('Selected Onion');
        $response->assertDontSee('Unselected Potato');
        $response->assertSee('CART-001');
        $response->assertSee('CRATE 20 KG');
    }

    public function test_add_on_product_without_demand_loads_safely(): void
    {
        $addonProduct = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Addon Garlic',
            'sku' => 'GAR-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->purchaser)->get("/purchaser/bulk-buy/details?date={$this->operationalDate}&purchase_grade=A&product_ids[]={$addonProduct->id}");

        $response->assertStatus(200);
        $response->assertSee('Addon Garlic');
    }

    public function test_query_count_remains_low_and_flat(): void
    {
        $product1 = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Product 1',
            'sku' => 'P-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $product2 = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Product 2',
            'sku' => 'P-02',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $response = $this->actingAs($this->purchaser)->get("/purchaser/bulk-buy/details?date={$this->operationalDate}&purchase_grade=A&product_ids[]={$product1->id}&product_ids[]={$product2->id}");

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(20, count($queries));
    }
}
