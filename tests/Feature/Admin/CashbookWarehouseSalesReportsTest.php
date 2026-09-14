<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSale;
use App\Services\Warehouse\WarehouseSalesAccessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookWarehouseSalesReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $salesUser;

    private User $unauthorizedUser;

    private Warehouse $warehouse;

    private Product $product;

    private WarehouseSalesAccessService $accessService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->accessService = app(WarehouseSalesAccessService::class);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Vegetable Warehouse', 'is_active' => true]);

        $this->adminUser = User::factory()->create(['name' => 'Admin User']);
        $this->adminUser->assignRole('admin');

        $this->salesUser = User::factory()->create(['name' => 'Niyas']);
        $this->unauthorizedUser = User::factory()->create(['name' => 'Regular User']);
        $this->unauthorizedUser->assignRole('warehouse_receiver');

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato',
            'unit' => 'kg',
            'base_price' => 30.0,
            'is_active' => true,
        ]);

        $this->accessService->updateSettings([
            'enabled' => true,
            'allowed_user_ids' => [$this->salesUser->id, $this->adminUser->id],
            'user_warehouses' => [
                (string) $this->salesUser->id => [$this->warehouse->id],
                (string) $this->adminUser->id => [$this->warehouse->id],
            ],
        ]);
    }

    private function seedStock(float $quantity): StockBatch
    {
        $batch = StockBatch::query()->create([
            'reference' => 'BATCH-001',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->adminUser->id,
            'received_at' => now()->toDateString(),
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'total_kg' => $quantity,
            'sorted_kg' => $quantity,
            'initial_quantity' => $quantity,
            'cost_per_kg' => 20.0,
            'cost_per_unit' => 20.0,
        ]);

        StockMovement::query()->create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->adminUser->id,
            'grade' => ProductGrade::GradeA->value,
            'type' => StockMovementType::In->value,
            'quantity' => $quantity,
            'cost_per_unit' => 20.0,
            'notes' => 'Initial stock',
        ]);

        return $batch;
    }

    public function test_admin_can_view_cashbook_warehouse_sales_report_and_kpis(): void
    {
        $this->seedStock(200.0);

        $shop = Shop::factory()->create(['name' => 'Casio Shop', 'code' => 'CASIO']);

        // Create Sale 1: Cash Sales (₹600)
        $this->actingAs($this->salesUser);
        $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'cash_sales',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'user',
            'money_holder_user_id' => $this->salesUser->id,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 20.0, 'unit_price' => 30.0],
            ],
        ]);

        // Create Sale 2: Shop Sale - Casio Shop (₹300)
        $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'shop',
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'payment_method' => 'upi',
            'money_holder_type' => 'company',
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 10.0, 'unit_price' => 30.0],
            ],
        ]);

        // Create Sale 3: Walking Customer - Mohammed (₹150)
        $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'walking_customer',
            'customer_name' => 'Mohammed',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 5.0, 'unit_price' => 30.0],
            ],
        ]);

        // Admin checks Cashbook Warehouse Sales report
        $this->actingAs($this->adminUser);
        $response = $this->get(route('admin.cashbook.warehouse-sales'));
        $response->assertOk();
        $response->assertSee('Cash Sales');
        $response->assertSee('Casio Shop');
        $response->assertSee('Mohammed');
        $response->assertSee('1,050.00'); // Total sales

        // Test filtering by customer_type = shop
        $shopFilterResponse = $this->get(route('admin.cashbook.warehouse-sales', ['customer_type' => 'shop']));
        $shopFilterResponse->assertOk();
        $shopFilterResponse->assertSee('Casio Shop');
        $shopFilterResponse->assertDontSee('Mohammed');

        // Test filtering by shop_id
        $shopIdFilterResponse = $this->get(route('admin.cashbook.warehouse-sales', ['shop_id' => $shop->id]));
        $shopIdFilterResponse->assertOk();
        $shopIdFilterResponse->assertSee('Casio Shop');
    }

    public function test_unauthorized_user_is_forbidden_from_cashbook_warehouse_sales_report(): void
    {
        $this->actingAs($this->unauthorizedUser);

        $response = $this->get(route('admin.cashbook.warehouse-sales'));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');

        $jsonResponse = $this->getJson(route('admin.cashbook.warehouse-sales'));
        $jsonResponse->assertForbidden();
    }

    public function test_admin_can_view_complete_traceability_detail_of_warehouse_sale(): void
    {
        $this->seedStock(50.0);

        $shop = Shop::factory()->create(['name' => 'Casio']);

        $this->actingAs($this->salesUser);
        $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'shop',
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'user',
            'money_holder_user_id' => $this->salesUser->id,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 15.0, 'unit_price' => 30.0],
            ],
        ]);

        $sale = WarehouseSale::query()->first();
        $this->assertNotNull($sale);

        $this->actingAs($this->adminUser);
        $response = $this->get(route('admin.cashbook.warehouse-sales.show', $sale));

        $response->assertOk();
        $response->assertSee($sale->invoice_number);
        $response->assertSee('Casio');
        $response->assertSee('Tomato');
        $response->assertSee('Auditable Inventory Movements');
        $response->assertSee('SALE_OUT');
        $response->assertSee('Held by Niyas');
    }
}
