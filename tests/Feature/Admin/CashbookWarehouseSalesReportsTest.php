<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\DailyPriceApproval;
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
        $response->assertSee(route('admin.cashbook.warehouse-sales'), false);
        $response->assertSee(route('admin.cashbook.warehouse-sales.create'), false);
        $response->assertSee('+ New Warehouse Sale');
        $response->assertSee('Warehouse Sales');
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
        $response->assertSee(route('admin.cashbook.warehouse-sales.pdf', $sale), false);

        // Download PDF from Cashbook report & operational Warehouse Sales
        $cashbookPdf = $this->get(route('admin.cashbook.warehouse-sales.pdf', $sale));
        $cashbookPdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $cashbookPdf->headers->get('content-type'));

        $operationalPdf = $this->get(route('warehouse.sales.pdf', $sale));
        $operationalPdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $operationalPdf->headers->get('content-type'));
    }

    public function test_admin_can_complete_full_warehouse_sale_workflow_inside_cashbook(): void
    {
        $this->seedStock(100.0);

        $this->actingAs($this->adminUser);

        // 1. Open Cashbook Create page
        $createResponse = $this->get(route('admin.cashbook.warehouse-sales.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('New Warehouse Sale');
        $createResponse->assertSee(route('admin.cashbook.warehouse-sales.store'), false);

        // 2. Create customer via Cashbook endpoint
        $customerResponse = $this->postJson(route('admin.cashbook.warehouse-sales.customers.store'), [
            'name' => 'Cashbook Walkin Buyer',
            'phone' => '9876543210',
        ]);
        $customerResponse->assertOk()->assertJsonPath('status', 'success');
        $customerId = (int) $customerResponse->json('customer.id');

        // 3. Search products via Cashbook endpoint
        $productsResponse = $this->getJson(route('admin.cashbook.warehouse-sales.search-products', [
            'warehouse_id' => $this->warehouse->id,
        ]));
        $productsResponse->assertOk()->assertJsonPath('status', 'success');

        // 4. Store sale via Cashbook endpoint (JSON)
        $storeResponse = $this->postJson(route('admin.cashbook.warehouse-sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'walking_customer',
            'customer_id' => $customerId,
            'customer_name' => 'Cashbook Walkin Buyer',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'user',
            'money_holder_user_id' => $this->adminUser->id,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 25.0, 'unit_price' => 40.0],
            ],
        ]);

        $storeResponse->assertOk()->assertJsonPath('status', 'success');
        $sale = WarehouseSale::query()->first();
        $this->assertNotNull($sale);
        $this->assertSame(route('admin.cashbook.warehouse-sales.show', $sale), $storeResponse->json('redirect_url'));

        // Verify stock deduction
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_sale_item_id' => $sale->items->first()->id,
            'type' => StockMovementType::Sale->value,
            'quantity' => 25.0,
        ]);

        // 5. View detail page in Cashbook (no Operational Invoice redirect)
        $detailResponse = $this->get(route('admin.cashbook.warehouse-sales.show', $sale));
        $detailResponse->assertOk();
        $detailResponse->assertSee($sale->invoice_number);
        $detailResponse->assertDontSee('Operational Invoice');

        // 6. Cancel sale via Cashbook endpoint
        $cancelResponse = $this->post(route('admin.cashbook.warehouse-sales.cancel', $sale), [
            'reason' => 'Test Cashbook cancellation',
        ]);
        $cancelResponse->assertRedirect(route('admin.cashbook.warehouse-sales.show', $sale));

        $sale->refresh();
        $this->assertTrue($sale->isCancelled());

        // Verify stock reversal movement
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_sale_item_id' => $sale->items->first()->id,
            'type' => StockMovementType::SaleReversal->value,
            'quantity' => 25.0,
        ]);
    }

    public function test_cashbook_create_uses_selling_price_company_direct_default_and_validates_discount(): void
    {
        $this->seedStock(100.0);

        DailyPriceApproval::query()->create([
            'product_id' => $this->product->id,
            'business_date' => now()->toDateString(),
            'price_a' => 55.50,
            'price_b' => 50.00,
            'price_c' => 45.00,
            'status' => 'approved',
        ]);

        $purchaser = User::factory()->create(['name' => 'Zaid Purchaser']);
        $purchaser->assignRole('purchaser');

        $this->actingAs($this->adminUser);

        // 1. Create page defaults to Company Direct and includes Purchaser staff
        $createRes = $this->get(route('admin.cashbook.warehouse-sales.create'));
        $createRes->assertOk();
        $createRes->assertSee("moneyHolderType: 'company'", false);
        $createRes->assertSee('Zaid Purchaser');

        // 2. Search products returns approved selling price (55.50) rather than base_price/cost
        $searchRes = $this->getJson(route('admin.cashbook.warehouse-sales.search-products', [
            'warehouse_id' => $this->warehouse->id,
            'q' => 'Tomato',
        ]));
        $searchRes->assertOk();
        $this->assertEquals(55.50, $searchRes->json('data.0.price'));

        // 3. Discount exceeding subtotal is rejected
        $invalidDiscountRes = $this->postJson(route('admin.cashbook.warehouse-sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'cash_sales',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'discount' => 600.00,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 10.0, 'unit_price' => 55.50],
            ],
        ]);
        $invalidDiscountRes->assertUnprocessable()->assertJsonValidationErrors(['discount']);

        // 4. Valid discount is rounded and reflected without duplicate deduction
        $validRes = $this->postJson(route('admin.cashbook.warehouse-sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'cash_sales',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'discount' => 55.004,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 10.0, 'unit_price' => 55.50],
            ],
        ]);
        $validRes->assertOk();
        $sale = WarehouseSale::query()->first();
        $this->assertEquals(555.00, (float) $sale->subtotal);
        $this->assertEquals(55.00, (float) $sale->discount);
        $this->assertEquals(500.00, (float) $sale->total_amount);

        // Cashbook report shows net total and discount badge
        $reportRes = $this->get(route('admin.cashbook.warehouse-sales'));
        $reportRes->assertOk();
        $reportRes->assertSee('500.00');
        $reportRes->assertSee('Disc: -₹55.00');
    }

    public function test_cashbook_search_products_returns_positive_zero_and_negative_warehouse_stock(): void
    {
        $this->seedStock(25.5);

        $zeroProduct = Product::factory()->create([
            'category_id' => $this->product->category_id,
            'name' => 'Zero Stock Item',
            'unit' => 'kg',
            'base_price' => 10.0,
            'is_active' => true,
        ]);

        $negProduct = Product::factory()->create([
            'category_id' => $this->product->category_id,
            'name' => 'Negative Stock Item',
            'unit' => 'kg',
            'base_price' => 20.0,
            'is_active' => true,
        ]);

        $negBatch = StockBatch::query()->create([
            'reference' => 'BATCH-NEG-001',
            'product_id' => $negProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->adminUser->id,
            'received_at' => now()->toDateString(),
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'total_kg' => 0,
            'sorted_kg' => 0,
            'initial_quantity' => 0,
            'cost_per_kg' => 20.0,
            'cost_per_unit' => 20.0,
        ]);

        StockMovement::query()->create([
            'batch_id' => $negBatch->id,
            'product_id' => $negProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->adminUser->id,
            'grade' => ProductGrade::GradeA->value,
            'type' => StockMovementType::Sale->value,
            'quantity' => 12.5,
            'cost_per_unit' => 0.0,
            'notes' => 'Negative stock sale',
        ]);

        $this->actingAs($this->adminUser);

        $res = $this->getJson(route('admin.cashbook.warehouse-sales.search-products', [
            'warehouse_id' => $this->warehouse->id,
        ]));
        $res->assertOk();

        $byId = collect($res->json('data'))->keyBy('id');

        $this->assertEquals(25.5, $byId->get($this->product->id)['available_stock']);
        $this->assertEquals(0.0, $byId->get($zeroProduct->id)['available_stock']);
        $this->assertEquals(-12.5, $byId->get($negProduct->id)['available_stock']);

        // Product inventory popup endpoint returns the shared Warehouse Inventory card HTML
        $popupRes = $this->getJson(route('admin.cashbook.warehouse-sales.product-inventory', [
            'product' => $negProduct->id,
            'warehouse_id' => $this->warehouse->id,
        ]));
        $popupRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('product_name', 'Negative Stock Item');
        $html = (string) $popupRes->json('html');
        $this->assertStringContainsString('Current Total Stock', $html);
        $this->assertStringContainsString('Negative Stock', $html);
        $this->assertStringContainsString('-12.50', $html);
        $this->assertStringContainsString('Sorted', $html);
        $this->assertStringContainsString('Mixed', $html);
        $this->assertStringContainsString('Shop Allocations', $html);
    }

    public function test_sorted_plus_mixed_stock_matches_warehouse_inventory_for_potato_agra_and_tomato_h(): void
    {
        $potato = Product::factory()->create([
            'category_id' => $this->product->category_id,
            'name' => 'Potato Agra',
            'unit' => 'kg',
            'base_price' => 29.0,
            'is_active' => true,
        ]);

        // Sorted: -4061.30 kg, Mixed (Unsorted): +4125.00 kg => Total: +63.70 kg
        $sortedBatch = StockBatch::query()->create([
            'reference' => 'BATCH-POT-SORTED',
            'product_id' => $potato->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->adminUser->id,
            'received_at' => now()->toDateString(),
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'total_kg' => 0,
            'sorted_kg' => 0,
            'initial_quantity' => 0,
            'cost_per_kg' => 20.0,
            'cost_per_unit' => 20.0,
        ]);
        StockMovement::query()->create([
            'batch_id' => $sortedBatch->id,
            'product_id' => $potato->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->adminUser->id,
            'grade' => ProductGrade::GradeA->value,
            'type' => StockMovementType::Sale->value,
            'quantity' => 4061.30,
            'cost_per_unit' => 20.0,
            'notes' => 'Sorted deficit',
        ]);

        $mixedBatch = StockBatch::query()->create([
            'reference' => 'BATCH-POT-MIXED',
            'product_id' => $potato->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->adminUser->id,
            'received_at' => now()->toDateString(),
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'total_kg' => 4125.00,
            'sorted_kg' => 4125.00,
            'initial_quantity' => 4125.00,
            'cost_per_kg' => 20.0,
            'cost_per_unit' => 20.0,
        ]);
        StockMovement::query()->create([
            'batch_id' => $mixedBatch->id,
            'product_id' => $potato->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->adminUser->id,
            'grade' => ProductGrade::Unsorted->value,
            'type' => StockMovementType::In->value,
            'quantity' => 4125.00,
            'cost_per_unit' => 20.0,
            'notes' => 'Mixed stock in',
        ]);

        $this->actingAs($this->adminUser);

        // Search returns combined 63.70 kg
        $searchRes = $this->getJson(route('admin.cashbook.warehouse-sales.search-products', [
            'warehouse_id' => $this->warehouse->id,
            'q' => 'Potato Agra',
        ]));
        $searchRes->assertOk();
        $this->assertEquals(63.7, $searchRes->json('data.0.available_stock'));

        // Popup shows Sorted (-4,061.30), Mixed (4,125.00), and Total (63.70)
        $popupRes = $this->getJson(route('admin.cashbook.warehouse-sales.product-inventory', [
            'product' => $potato->id,
            'warehouse_id' => $this->warehouse->id,
        ]));
        $popupRes->assertOk();
        $html = (string) $popupRes->json('html');
        $this->assertStringContainsString('-4,061.30', $html);
        $this->assertStringContainsString('4,125.00', $html);
        $this->assertStringContainsString('63.70', $html);
    }
}
