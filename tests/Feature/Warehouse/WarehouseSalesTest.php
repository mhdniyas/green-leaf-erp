<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

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
use App\Models\WarehouseCustomer;
use App\Models\WarehouseSale;
use App\Services\Warehouse\WarehouseSalesAccessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseSalesTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $salesUser;

    private User $otherUser;

    private Warehouse $warehouse;

    private Category $category;

    private Product $productA;

    private Product $productB;

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
        $this->otherUser = User::factory()->create(['name' => 'Ahmed']);

        $this->category = Category::factory()->create(['name' => 'Vegetables']);
        $this->productA = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Tomato',
            'unit' => 'kg',
            'base_price' => 32.0,
            'is_active' => true,
        ]);
        $this->productB = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Onion',
            'unit' => 'kg',
            'base_price' => 28.0,
            'is_active' => true,
        ]);

        // Enable warehouse sales in company settings for $this->salesUser
        $this->accessService->updateSettings([
            'enabled' => true,
            'allowed_user_ids' => [$this->salesUser->id, $this->adminUser->id],
            'user_warehouses' => [
                (string) $this->salesUser->id => [$this->warehouse->id],
                (string) $this->adminUser->id => [$this->warehouse->id],
            ],
        ]);
    }

    private function seedStock(Product $product, float $quantity, Warehouse $warehouse, ProductGrade $grade = ProductGrade::GradeA): StockBatch
    {
        $batch = StockBatch::query()->create([
            'reference' => 'BATCH-001',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
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
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->adminUser->id,
            'grade' => $grade->value,
            'type' => StockMovementType::In->value,
            'quantity' => $quantity,
            'cost_per_unit' => 20.0,
            'notes' => 'Initial test stock',
        ]);

        return $batch;
    }

    public function test_new_sale_defaults_to_cash_sales_and_lists_existing_shops(): void
    {
        $shop1 = Shop::factory()->create(['name' => 'Casio Shop', 'code' => 'CS01']);
        $shop2 = Shop::factory()->create(['name' => 'Lulu Budhigere', 'code' => 'LB02']);

        $this->actingAs($this->salesUser);

        $response = $this->get(route('warehouse.sales.create'));
        $response->assertOk();
        $response->assertSee('Cash Sales');
        $response->assertSee('Casio Shop');
        $response->assertSee('Lulu Budhigere');
        $response->assertSee('Walking Customer');
    }

    public function test_cash_sales_can_be_confirmed_without_creating_another_customer(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $initialCustomerCount = WarehouseCustomer::query()->count();

        $this->actingAs($this->salesUser);

        $response = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'cash_sales',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 5, 'unit_price' => 30.0],
            ],
        ]);

        $response->assertRedirect();

        $sale = WarehouseSale::query()->first();
        $this->assertNotNull($sale);
        $this->assertEquals(WarehouseSale::CUSTOMER_TYPE_CASH_SALES, $sale->customer_type);
        $this->assertEquals('Cash Sales', $sale->customer_name_snapshot);
        $this->assertNull($sale->shop_id);
        $this->assertNull($sale->warehouse_customer_id);

        // No new warehouse_customers row created for anonymous cash sale
        $this->assertEquals($initialCustomerCount, WarehouseCustomer::query()->count());
    }

    public function test_multiple_cash_sales_create_separate_invoices_without_duplicate_customers(): void
    {
        $this->seedStock($this->productA, 100.0, $this->warehouse);

        $this->actingAs($this->salesUser);

        // Sale 1
        $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'cash_sales',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 10, 'unit_price' => 30.0],
            ],
        ]);

        // Sale 2
        $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'cash_sales',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 20, 'unit_price' => 30.0],
            ],
        ]);

        $sales = WarehouseSale::query()->orderBy('id')->get();
        $this->assertCount(2, $sales);
        $this->assertNotEquals($sales[0]->invoice_number, $sales[1]->invoice_number);
        $this->assertEquals(300.0, (float) $sales[0]->total_amount);
        $this->assertEquals(600.0, (float) $sales[1]->total_amount);
        $this->assertEquals('Cash Sales', $sales[0]->customer_name_snapshot);
        $this->assertEquals('Cash Sales', $sales[1]->customer_name_snapshot);

        $this->assertEquals(0, WarehouseCustomer::query()->count());
    }

    public function test_shop_sale_references_correct_existing_shop_without_creating_warehouse_customer(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $shop = Shop::factory()->create([
            'name' => 'Grandcity Supermarket',
            'code' => 'GC01',
            'contact_phone' => '9876500000',
        ]);

        $this->actingAs($this->salesUser);

        $response = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'shop',
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'payment_method' => 'credit',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 15, 'unit_price' => 30.0],
            ],
        ]);

        $response->assertRedirect();

        $sale = WarehouseSale::query()->first();
        $this->assertNotNull($sale);
        $this->assertEquals(WarehouseSale::CUSTOMER_TYPE_SHOP, $sale->customer_type);
        $this->assertEquals($shop->id, $sale->shop_id);
        $this->assertEquals('Grandcity Supermarket', $sale->customer_name_snapshot);
        $this->assertEquals('9876500000', $sale->customer_phone_snapshot);
        $this->assertEquals($shop->id, $sale->shop->id);

        // Shop is NOT duplicated in warehouse_customers
        $this->assertEquals(0, WarehouseCustomer::query()->count());
    }

    public function test_unauthorized_shop_or_warehouse_manipulation_rejected(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);
        $unauthorizedWarehouse = Warehouse::factory()->create(['name' => 'Secret Warehouse', 'is_active' => true]);

        $this->actingAs($this->salesUser);

        // Attempt sale on unauthorized warehouse
        $response = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $unauthorizedWarehouse->id,
            'customer_type' => 'cash_sales',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 5, 'unit_price' => 30.0],
            ],
        ]);

        $response->assertForbidden();

        // Attempt non-existent shop_id
        $badShopResponse = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'shop',
            'shop_id' => 999999,
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 5, 'unit_price' => 30.0],
            ],
        ]);

        $badShopResponse->assertSessionHasErrors(['shop_id']);
    }

    public function test_walking_customer_works_without_name(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $this->actingAs($this->salesUser);

        $response = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'walking_customer',
            'customer_name' => '',
            'customer_phone' => '',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 5, 'unit_price' => 30.0],
            ],
        ]);

        $response->assertRedirect();

        $sale = WarehouseSale::query()->first();
        $this->assertNotNull($sale);
        $this->assertEquals(WarehouseSale::CUSTOMER_TYPE_WALKING, $sale->customer_type);
        $this->assertEquals('Walking Customer', $sale->customer_name_snapshot);
    }

    public function test_walking_customer_works_with_custom_name_and_phone(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $this->actingAs($this->salesUser);

        $response = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'walking_customer',
            'customer_name' => 'Mohammed',
            'customer_phone' => '9876543210',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 5, 'unit_price' => 30.0],
            ],
        ]);

        $response->assertRedirect();

        $sale = WarehouseSale::query()->first();
        $this->assertNotNull($sale);
        $this->assertEquals(WarehouseSale::CUSTOMER_TYPE_WALKING, $sale->customer_type);
        $this->assertEquals('Mohammed', $sale->customer_name_snapshot);
        $this->assertEquals('9876543210', $sale->customer_phone_snapshot);
    }

    public function test_customer_type_does_not_force_payment_method_cash_sales_with_upi(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $this->actingAs($this->salesUser);

        // Customer: Cash Sales, Payment: UPI
        $response = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'cash_sales',
            'business_date' => now()->toDateString(),
            'payment_method' => 'upi',
            'money_holder_type' => 'company',
            'payment_reference' => 'UPI-REF-12345',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 5, 'unit_price' => 30.0],
            ],
        ]);

        $response->assertRedirect();

        $sale = WarehouseSale::query()->first();
        $this->assertNotNull($sale);
        $this->assertEquals(WarehouseSale::CUSTOMER_TYPE_CASH_SALES, $sale->customer_type);
        $payment = $sale->primaryPayment();
        $this->assertEquals('upi', $payment->payment_method);
        $this->assertEquals('UPI-REF-12345', $payment->reference);
    }

    public function test_customer_type_does_not_force_payment_method_shop_with_cash(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $shop = Shop::factory()->create(['name' => 'Casio']);

        $this->actingAs($this->salesUser);

        // Customer: Shop - Casio, Payment: Cash
        $response = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'shop',
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'user',
            'money_holder_user_id' => $this->salesUser->id,
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 5, 'unit_price' => 30.0],
            ],
        ]);

        $response->assertRedirect();

        $sale = WarehouseSale::query()->first();
        $this->assertNotNull($sale);
        $this->assertEquals(WarehouseSale::CUSTOMER_TYPE_SHOP, $sale->customer_type);
        $this->assertEquals($shop->id, $sale->shop_id);
        $payment = $sale->primaryPayment();
        $this->assertEquals('cash', $payment->payment_method);
        $this->assertTrue($sale->isUserHeldCash());
    }

    public function test_confirmed_sale_creates_invoice_items_and_inventory_movements(): void
    {
        $this->seedStock($this->productA, 100.0, $this->warehouse);

        $this->actingAs($this->salesUser);

        $response = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'ABC Hotel',
            'customer_phone' => '9876543210',
            'business_date' => now()->toDateString(),
            'discount' => 50,
            'payment_method' => 'cash',
            'money_holder_type' => 'user',
            'money_holder_user_id' => $this->salesUser->id,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'grade' => 'A',
                    'qty' => 25.0,
                    'unit' => 'kg',
                    'unit_price' => 32.0,
                ],
            ],
        ]);

        $response->assertRedirect();

        $sale = WarehouseSale::query()->where('customer_name_snapshot', 'ABC Hotel')->first();
        $this->assertNotNull($sale);
        $this->assertStringStartsWith('WS-', $sale->invoice_number);
        $this->assertEquals(800.0, (float) $sale->subtotal);
        $this->assertEquals(50.0, (float) $sale->discount);
        $this->assertEquals(750.0, (float) $sale->total_amount);
        $this->assertEquals(750.0, (float) $sale->paid_amount);
        $this->assertTrue($sale->isConfirmed());

        // Verify items
        $this->assertCount(1, $sale->items);
        $item = $sale->items->first();
        $this->assertEquals($this->productA->id, $item->product_id);
        $this->assertEquals(25.0, (float) $item->entered_qty);
        $this->assertEquals(32.0, (float) $item->unit_price);
        $this->assertEquals(800.0, (float) $item->line_total);

        // Verify payment
        $this->assertCount(1, $sale->payments);
        $payment = $sale->payments->first();
        $this->assertEquals('cash', $payment->payment_method);
        $this->assertEquals(750.0, (float) $payment->amount);
        $this->assertEquals('user', $payment->money_holder_type);
        $this->assertEquals($this->salesUser->id, $payment->money_holder_user_id);

        // Verify stock movement
        $movements = StockMovement::query()
            ->where('warehouse_sale_item_id', $item->id)
            ->where('type', StockMovementType::Sale->value)
            ->get();

        $this->assertCount(1, $movements);
        $movement = $movements->first();
        $this->assertEquals(25.0, (float) $movement->quantity);
        $this->assertEquals($this->warehouse->id, $movement->warehouse_id);
        $this->assertEquals($this->productA->id, $movement->product_id);
    }

    public function test_sale_cannot_exceed_available_inventory_and_blocks_negative_stock(): void
    {
        $this->seedStock($this->productA, 20.0, $this->warehouse);

        $this->actingAs($this->salesUser);

        // Attempt to sell 25 kg when only 20 kg available
        $response = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'Over Buyer',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'grade' => 'A',
                    'qty' => 25.0,
                    'unit' => 'kg',
                    'unit_price' => 32.0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors();

        // Ensure no sale or movement was created
        $this->assertDatabaseCount('warehouse_sales', 0);
        $this->assertDatabaseMissing('stock_movements', [
            'type' => StockMovementType::Sale->value,
        ]);
    }

    public function test_cash_held_by_company_is_distinguishable_from_user_holding(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $this->actingAs($this->salesUser);

        $response = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'Walk-in Cash Customer',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'company',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'qty' => 10.0,
                    'unit_price' => 30.0,
                ],
            ],
        ]);

        $response->assertRedirect();

        $sale = WarehouseSale::query()->first();
        $this->assertNotNull($sale);
        $payment = $sale->payments->first();
        $this->assertEquals('company', $payment->money_holder_type);
        $this->assertNull($payment->money_holder_user_id);
        $this->assertTrue($payment->isHeldByCompany());
    }

    public function test_sale_cancellation_reverses_inventory_and_is_idempotent(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $this->actingAs($this->salesUser);

        $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'To Be Cancelled',
            'business_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'money_holder_type' => 'user',
            'money_holder_user_id' => $this->salesUser->id,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'qty' => 20.0,
                    'unit_price' => 30.0,
                ],
            ],
        ]);

        $sale = WarehouseSale::query()->first();
        $this->assertNotNull($sale);
        $this->assertTrue($sale->isConfirmed());

        // Cancel the sale
        $cancelResponse = $this->post(route('warehouse.sales.cancel', $sale), [
            'reason' => 'Customer changed mind',
        ]);

        $cancelResponse->assertRedirect();
        $sale->refresh();
        $this->assertTrue($sale->isCancelled());
        $this->assertEquals('Customer changed mind', $sale->cancellation_reason);
        $this->assertEquals($this->salesUser->id, $sale->cancelled_by);

        // Check stock reversal movement exists
        $reversalMovements = StockMovement::query()
            ->where('warehouse_sale_item_id', $sale->items->first()->id)
            ->where('type', StockMovementType::SaleReversal->value)
            ->get();

        $this->assertCount(1, $reversalMovements);
        $this->assertEquals(20.0, (float) $reversalMovements->first()->quantity);

        // Second cancellation attempt should fail and not double reverse
        $secondCancelResponse = $this->post(route('warehouse.sales.cancel', $sale), [
            'reason' => 'Try again',
        ]);

        $secondCancelResponse->assertForbidden();

        $this->assertCount(1, StockMovement::query()
            ->where('warehouse_sale_item_id', $sale->items->first()->id)
            ->where('type', StockMovementType::SaleReversal->value)
            ->get());
    }

    public function test_zero_and_negative_quantity_are_rejected(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $this->actingAs($this->salesUser);

        // Zero quantity
        $resZero = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'Zero Buyer',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 0, 'unit_price' => 30.0],
            ],
            'payment_method' => 'cash',
        ]);
        $resZero->assertSessionHasErrors();

        // Negative quantity
        $resNeg = $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'Neg Buyer',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => -5, 'unit_price' => 30.0],
            ],
            'payment_method' => 'cash',
        ]);
        $resNeg->assertSessionHasErrors();
    }

    public function test_customer_snapshot_preserves_historical_invoice_data_after_customer_edit(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $customer = WarehouseCustomer::query()->create([
            'name' => 'Original Customer Name',
            'phone' => '1122334455',
            'is_active' => true,
        ]);

        $this->actingAs($this->salesUser);

        $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'existing',
            'customer_id' => $customer->id,
            'business_date' => now()->toDateString(),
            'money_holder_type' => 'company',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 5, 'unit_price' => 30.0],
            ],
            'payment_method' => 'cash',
        ]);

        $sale = WarehouseSale::query()->first();
        $this->assertEquals('Original Customer Name', $sale->customer_name_snapshot);

        // Later customer is updated in master table
        $customer->update(['name' => 'Updated Customer Name']);

        $sale->refresh();
        // Historical snapshot on sale remains untouched
        $this->assertEquals('Original Customer Name', $sale->customer_name_snapshot);
    }

    public function test_html_in_customer_name_is_escaped(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $this->actingAs($this->salesUser);

        $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_type' => 'walking_customer',
            'customer_name' => '<script>alert("xss")</script> Safe Hotel',
            'business_date' => now()->toDateString(),
            'money_holder_type' => 'company',
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => 5, 'unit_price' => 30.0],
            ],
            'payment_method' => 'cash',
        ]);

        $sale = WarehouseSale::query()->first();
        $this->assertNotNull($sale);

        $response = $this->get(route('warehouse.sales.show', $sale));
        $response->assertOk();
        $response->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt; Safe Hotel', false);
        $response->assertDontSee('<script>alert("xss")</script>', false);
    }
}
