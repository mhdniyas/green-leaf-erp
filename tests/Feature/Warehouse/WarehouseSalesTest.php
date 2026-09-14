<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\Product;
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
        $this->assertEquals(WarehouseSale::STATUS_CONFIRMED, $sale->status);
        $this->assertEquals($this->salesUser->id, $sale->sold_by_user_id);

        // Check payment record
        $this->assertCount(1, $sale->payments);
        $payment = $sale->payments->first();
        $this->assertEquals('cash', $payment->payment_method);
        $this->assertEquals('user', $payment->money_holder_type);
        $this->assertEquals($this->salesUser->id, $payment->money_holder_user_id);
        $this->assertTrue($payment->isHeldByUser());
        $this->assertFalse($payment->isHeldByCompany());

        // Check inventory movements
        $saleMovement = StockMovement::query()
            ->where('warehouse_sale_item_id', $sale->items->first()->id)
            ->where('type', StockMovementType::Sale->value)
            ->first();

        $this->assertNotNull($saleMovement);
        $this->assertEquals(25.0, (float) $saleMovement->quantity);
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

    public function test_cancelling_confirmed_sale_restores_inventory_exactly_once(): void
    {
        $this->seedStock($this->productA, 50.0, $this->warehouse);

        $this->actingAs($this->salesUser);

        $this->post(route('warehouse.sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => 'Test Hotel',
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
