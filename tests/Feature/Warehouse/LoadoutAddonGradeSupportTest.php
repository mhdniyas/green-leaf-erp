<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoadoutAddonGradeSupportTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private Warehouse $warehouse;

    private Shop $shop;

    private Product $product;

    private ShopPriceGroup $priceGroup;

    private Carbon $businessDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessDate = Carbon::parse('2026-10-17');
        $this->mock(PurchaserBusinessDayService::class, function ($mock): void {
            $mock->shouldReceive('operationalDate')->andReturn($this->businessDate);
        });

        Role::firstOrCreate(['name' => 'warehouse_receiver', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Central Warehouse', 'code' => 'WH-C']);
        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->warehouses()->attach($this->warehouse->id);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product = Product::factory()->create([
            'name' => 'Tomato Hybrid',
            'sku' => 'VEG-TOM-01',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'KG',
            'is_active' => true,
        ]);

        $this->priceGroup = ShopPriceGroup::create([
            'name' => 'A',
            'code' => 'A',
            'is_active' => true,
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Fresh Mart',
            'code' => 'SH-FM-01',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => null,
        ]);

        BusinessSetting::updateOrCreate(
            ['key' => 'allow_historical_invoice_repricing'],
            ['value' => '1']
        );

        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $this->businessDate,
            'grade' => 'A',
            'purchase_price' => 20.0,
            'price_unit' => 'KG',
            'price_a' => 50.0,
            'price_b' => 40.0,
            'price_c' => 30.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $this->businessDate,
            'grade' => 'B',
            'purchase_price' => 15.0,
            'price_unit' => 'KG',
            'price_a' => 40.0,
            'price_b' => 35.0,
            'price_c' => 25.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $this->businessDate,
            'grade' => 'C',
            'purchase_price' => 10.0,
            'price_unit' => 'KG',
            'price_a' => 30.0,
            'price_b' => 25.0,
            'price_c' => 20.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Seed DailyProductPrice for grades A, B, C so selling prices resolve deterministically
        DailyProductPrice::create([
            'product_id' => $this->product->id,
            'shop_price_group_id' => $this->priceGroup->id,
            'grade' => 'A',
            'selling_price' => 50.0,
            'effective_date' => $this->businessDate,
            'price_source' => 'approved_daily',
            'business_date' => $this->businessDate,
        ]);

        DailyProductPrice::create([
            'product_id' => $this->product->id,
            'shop_price_group_id' => $this->priceGroup->id,
            'grade' => 'B',
            'selling_price' => 40.0,
            'effective_date' => $this->businessDate,
            'price_source' => 'approved_daily',
            'business_date' => $this->businessDate,
        ]);

        DailyProductPrice::create([
            'product_id' => $this->product->id,
            'shop_price_group_id' => $this->priceGroup->id,
            'grade' => 'C',
            'selling_price' => 30.0,
            'effective_date' => $this->businessDate,
            'price_source' => 'approved_daily',
            'business_date' => $this->businessDate,
        ]);
    }

    private function createSortedBatch(string $grade, float $quantity): StockBatch
    {
        static $seq = 1;
        $batch = StockBatch::create([
            'reference' => 'BATCH-TEST-'.($seq++),
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => $quantity,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => $grade,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'created_by' => $this->receiver->id,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => $grade,
            'type' => StockMovementType::In,
            'quantity' => $quantity,
            'cost_per_unit' => 20.0,
            'notes' => "Test sorted batch grade {$grade}",
        ]);

        return $batch;
    }

    private function createOrder(): ShopOrder
    {
        return ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);
    }

    /**
     * 1. No grade supplied → defaults to Grade A.
     */
    public function test_no_grade_supplied_defaults_to_grade_a(): void
    {
        $this->createSortedBatch('A', 50.0);
        $order = $this->createOrder();

        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 10.0,
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);

        $item = ShopOrderItem::query()
            ->where('shop_order_id', $order->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertSame('A', $item->product_grade);
        $this->assertSame(10.0, (float) $item->loaded_qty);
        $this->assertSame(50.0, (float) $item->locked_selling_price);
    }

    /**
     * 2. Grade B Add-on creates a B order item.
     */
    public function test_grade_b_addon_creates_b_order_item(): void
    {
        $this->createSortedBatch('B', 40.0);
        $order = $this->createOrder();

        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 8.0,
                'product_grade' => 'B',
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);

        $item = ShopOrderItem::query()
            ->where('shop_order_id', $order->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertSame('B', $item->product_grade);
        $this->assertSame(8.0, (float) $item->loaded_qty);
        $this->assertSame(40.0, (float) $item->locked_selling_price);
        $this->assertSame(320.0, (float) $item->line_total);
    }

    /**
     * 3. Grade C Add-on creates a C order item.
     */
    public function test_grade_c_addon_creates_c_order_item(): void
    {
        $this->createSortedBatch('C', 30.0);
        $order = $this->createOrder();

        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 12.0,
                'product_grade' => 'C',
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);

        $item = ShopOrderItem::query()
            ->where('shop_order_id', $order->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertSame('C', $item->product_grade);
        $this->assertSame(12.0, (float) $item->loaded_qty);
        $this->assertSame(30.0, (float) $item->locked_selling_price);
        $this->assertSame(360.0, (float) $item->line_total);
    }

    /**
     * 4. Tomato A and B coexist in one order.
     */
    public function test_tomato_a_and_b_coexist_in_one_order(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 50.0);
        $order = $this->createOrder();

        // Existing Grade A item in order
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'loaded_qty' => 15.0,
            'unit' => 'KG',
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 50.0,
            'line_total' => 750.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'is_sorted' => true,
        ]);

        // Add Grade B addon for the same product
        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 7.0,
                'product_grade' => 'B',
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);

        $items = ShopOrderItem::query()
            ->where('shop_order_id', $order->id)
            ->where('product_id', $this->product->id)
            ->orderBy('product_grade')
            ->get();

        $this->assertCount(2, $items);
        $this->assertSame('A', $items[0]->product_grade);
        $this->assertSame(15.0, (float) $items[0]->loaded_qty);
        $this->assertSame('B', $items[1]->product_grade);
        $this->assertSame(7.0, (float) $items[1]->loaded_qty);
    }

    /**
     * 5. Duplicate Tomato B is rejected.
     */
    public function test_duplicate_tomato_b_is_rejected(): void
    {
        $this->createSortedBatch('B', 50.0);
        $order = $this->createOrder();

        // Existing Grade B item in order
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'KG',
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 40.0,
            'line_total' => 400.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'is_sorted' => true,
        ]);

        // Try to add another Grade B addon
        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 5.0,
                'product_grade' => 'B',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('already in this loadout order', (string) $response->json('message'));
        $this->assertStringContainsString('Grade B', (string) $response->json('message'));

        // Verify count remains 1
        $this->assertSame(1, ShopOrderItem::where('shop_order_id', $order->id)->where('product_id', $this->product->id)->count());
    }

    /**
     * 6. Inventory deduction uses the selected grade.
     */
    public function test_inventory_deduction_uses_the_selected_grade(): void
    {
        $batchA = $this->createSortedBatch('A', 50.0);
        $batchB = $this->createSortedBatch('B', 50.0);
        $order = $this->createOrder();

        $movementsBefore = StockMovement::count();

        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 15.0,
                'product_grade' => 'B',
            ]);

        $response->assertOk();

        // Check stock movements created
        $outMovement = StockMovement::where('product_id', $this->product->id)
            ->where('type', StockMovementType::Out)
            ->latest('id')
            ->first();

        $this->assertNotNull($outMovement);
        $this->assertSame($batchB->id, $outMovement->batch_id);
        $this->assertSame(15.0, (float) $outMovement->quantity);
        $this->assertSame(ProductGrade::GradeB, $outMovement->grade);

        // Grade A batch must remain untouched
        $batchAMovements = StockMovement::where('batch_id', $batchA->id)->count();
        $this->assertSame(1, $batchAMovements); // Only initial IN movement
    }

    /**
     * 7. Invalid grade is rejected.
     */
    public function test_invalid_grade_is_rejected(): void
    {
        $this->createSortedBatch('A', 50.0);
        $order = $this->createOrder();

        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 5.0,
                'product_grade' => 'X',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_grade']);
    }

    /**
     * 8. Add-on with insufficient stock allows negative stock.
     */
    public function test_addon_with_insufficient_stock_allows_negative_stock(): void
    {
        // Batch with only 5 KG available
        $this->createSortedBatch('B', 5.0);
        $order = $this->createOrder();

        // Attempt to add 20 KG (exceeds available 5 KG)
        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 20.0,
                'product_grade' => 'B',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Negative stock movement created for the 15 KG shortfall under Grade B
        $this->assertSame(1, StockMovement::where('notes', 'like', '%negative stock allowed%')->where('grade', 'B')->count());
        $this->assertSame(15.0, (float) StockMovement::where('notes', 'like', '%negative stock allowed%')->where('grade', 'B')->value('quantity'));
    }

    /**
     * 9. Existing Grade A Add-on continues working.
     */
    public function test_existing_grade_a_addon_continues_working(): void
    {
        $this->createSortedBatch('A', 30.0);
        $order = $this->createOrder();

        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 5.0,
                'product_grade' => 'A',
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);

        $item = ShopOrderItem::query()
            ->where('shop_order_id', $order->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertSame('A', $item->product_grade);
        $this->assertSame(5.0, (float) $item->loaded_qty);
    }

    /**
     * 10. Existing invoice locking is respected.
     */
    public function test_existing_invoice_locking_is_respected(): void
    {
        $this->createSortedBatch('A', 30.0);
        $order = $this->createOrder();

        // Create a finalized invoice for this order
        ShopInvoice::create([
            'shop_order_id' => $order->id,
            'shop_id' => $this->shop->id,
            'invoice_number' => 'INV-TEST-LOCKED',
            'business_date' => $this->businessDate,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'subtotal' => 500.0,
            'final_total' => 500.0,
        ]);

        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 5.0,
                'product_grade' => 'A',
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Cannot add addon to a delivered or finalized order', (string) $response->json('message'));
    }

    /**
     * 11. Web route storeAddon supports grade and redirects properly.
     */
    public function test_web_store_addon_supports_grade(): void
    {
        $this->createSortedBatch('B', 30.0);
        $order = $this->createOrder();

        $response = $this->actingAs($this->receiver)
            ->post("/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 6.0,
                'product_grade' => 'B',
            ]);

        $response->assertRedirect(route('warehouse.loadout.show', $order));
        $response->assertSessionHas('success');

        $item = ShopOrderItem::query()
            ->where('shop_order_id', $order->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertSame('B', $item->product_grade);
        $this->assertSame(6.0, (float) $item->loaded_qty);
    }

    /**
     * 12. Add-on catalog includes the exact default_warehouse_id for each product.
     */
    public function test_addon_catalog_includes_default_warehouse_id(): void
    {
        $fruitWarehouse = Warehouse::factory()->create(['name' => 'Fruit Warehouse', 'code' => 'WH-F']);
        $fruitProduct = Product::factory()->create([
            'name' => 'Apple Royal',
            'sku' => 'FRT-APP-01',
            'category_id' => $this->product->category_id,
            'default_warehouse_id' => $fruitWarehouse->id,
            'unit' => 'KG',
            'is_active' => true,
        ]);

        $order = $this->createOrder();

        $response = $this->actingAs($this->receiver)
            ->getJson("/api/v1/warehouse/loadout/{$order->id}/addons");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonFragment([
            'id' => $this->product->id,
            'name' => $this->product->name,
            'sku' => $this->product->sku,
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        $response->assertJsonFragment([
            'id' => $fruitProduct->id,
            'name' => $fruitProduct->name,
            'sku' => $fruitProduct->sku,
            'unit' => 'KG',
            'default_warehouse_id' => $fruitWarehouse->id,
            'occupied_grades' => [],
            'available_grades' => ['A', 'B', 'C'],
        ]);
    }

    /**
     * 13. Add-on catalog exposes occupied and available grades for products already in the order.
     */
    public function test_addon_catalog_exposes_occupied_and_available_grades(): void
    {
        $this->createSortedBatch('B', 50.0);
        $order = $this->createOrder();

        // Product currently has Grade A in the order
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'KG',
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 50.0,
            'line_total' => 500.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'is_sorted' => true,
        ]);

        $response = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->getJson("/api/v1/warehouse/loadout/{$order->id}/addons");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonFragment([
            'id' => $this->product->id,
            'name' => $this->product->name,
            'sku' => $this->product->sku,
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouse->id,
            'occupied_grades' => ['A'],
            'available_grades' => ['B', 'C'],
        ]);

        // Attempting to add Grade A again is rejected with 422 duplicate error
        $duplicateResponse = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 5.0,
                'product_grade' => 'A',
            ]);
        $duplicateResponse->assertStatus(422);
        $duplicateResponse->assertJsonFragment([
            'success' => false,
            'message' => "{$this->product->name} is already in this loadout order. Edit the existing line instead.",
        ]);

        // Attempting to add Grade B succeeds
        $validResponse = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 5.0,
                'product_grade' => 'B',
            ]);
        $validResponse->assertOk();
        $validResponse->assertJsonFragment(['success' => true]);
    }

    /**
     * 14. Delta autosave succeeds for uniquely identified order item ID on multi-grade order,
     * while ambiguous bare product ID remains rejected.
     */
    public function test_delta_autosave_succeeds_for_unambiguous_order_item_id_on_multi_grade_order(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 30.0);

        // Advance shop_order_items sequence so order item IDs are distinct from product ID (1)
        $anotherShop = Shop::factory()->create(['shop_price_group_id' => $this->priceGroup->id]);
        $dummyOrder = ShopOrder::factory()->approved()->create([
            'shop_id' => $anotherShop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);
        for ($i = 0; $i < 5; $i++) {
            ShopOrderItem::create([
                'shop_order_id' => $dummyOrder->id,
                'product_id' => $this->product->id,
                'product_grade' => 'A',
                'requested_qty' => 1.0,
                'approved_qty' => 1.0,
                'loaded_qty' => 1.0,
                'unit' => 'KG',
                'locked_price_group_id' => $this->priceGroup->id,
                'locked_selling_price' => 50.0,
                'line_total' => 50.0,
                'fulfillment_type' => 'warehouse',
                'sorting_status' => 'loaded',
                'is_sorted' => true,
            ]);
        }

        $order = $this->createOrder();

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 50.0,
            'line_total' => 500.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'is_sorted' => false,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 25.0,
            'line_total' => 125.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'is_sorted' => false,
        ]);

        $this->assertTrue($order->fresh()->hasMultiGradeItems());

        // Delta autosave submitting ONLY Item A's unique order item ID succeeds
        $responseA = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    (string) $itemA->id => 8.5,
                ],
                'item_status' => [
                    (string) $itemA->id => 'loaded',
                ],
            ]);

        $responseA->assertOk();
        $responseA->assertJsonPath('success', true);

        $itemA->refresh();
        $itemB->refresh();
        $this->assertSame(8.5, (float) $itemA->loaded_qty);
        $this->assertSame('loaded', $itemA->sorting_status);
        $this->assertSame(0.0, (float) $itemB->loaded_qty);
        $this->assertSame('allocated', $itemB->sorting_status);

        // Delta autosave submitting ONLY Item B's unique order item ID succeeds
        $responseB = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    (string) $itemB->id => 4.0,
                ],
                'item_status' => [
                    (string) $itemB->id => 'loaded',
                ],
            ]);

        $responseB->assertOk();
        $responseB->assertJsonPath('success', true);

        $itemA->refresh();
        $itemB->refresh();
        $this->assertSame(8.5, (float) $itemA->loaded_qty);
        $this->assertSame(4.0, (float) $itemB->loaded_qty);
        $this->assertSame('loaded', $itemB->sorting_status);

        // Ambiguous bare product-ID submission remains strictly rejected
        $responseAmbiguous = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    (string) $this->product->id => 5.0,
                ],
            ]);

        $responseAmbiguous->assertStatus(422);
        $responseAmbiguous->assertJson([
            'success' => false,
            'code' => 'AMBIGUOUS_MULTI_GRADE_PAYLOAD',
        ]);
    }

    /**
     * Helper to create a loaded regular order item.
     */
    private function createLoadedOrderItem(ShopOrder $order, string $grade, float $quantity): ShopOrderItem
    {
        $sellingPrice = match ($grade) {
            'A' => 50.0,
            'B' => 40.0,
            'C' => 30.0,
            default => 50.0,
        };

        return ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => $grade,
            'requested_qty' => $quantity,
            'approved_qty' => $quantity,
            'loaded_qty' => $quantity,
            'unit' => 'KG',
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => $sellingPrice,
            'line_total' => round($quantity * $sellingPrice, 2),
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'is_sorted' => true,
        ]);
    }

    /**
     * Reducing addon quantity updates approved_qty, reverses difference in stock, and allows delivery.
     */
    public function test_addon_quantity_reduction_reverses_stock_updates_approved_and_allows_delivery(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 50.0);

        $order = $this->createOrder();

        // 1. Regular item: Tomato Grade A, 10 KG loaded
        $itemA = $this->createLoadedOrderItem($order, 'A', 10.0);
        $order->update(['delivery_status' => 'ready_for_dispatch']);

        // 2. Add addon: Tomato Grade B, 10 KG
        $addonResponse = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 10.0,
                'grade' => 'B',
            ]);
        $addonResponse->assertOk();
        $addonItem = ShopOrderItem::where('shop_order_id', $order->id)->where('product_grade', 'B')->firstOrFail();
        $this->assertSame(10.0, (float) $addonItem->approved_qty);
        $this->assertSame(10.0, (float) $addonItem->loaded_qty);

        // Verify initial net deducted stock is 10.0
        $ledgerService = app(StockLedgerService::class);
        $this->assertEquals(10.0, $ledgerService->netDeductedQuantityForOrderItems([$addonItem->id]));

        // 3. Warehouse worker reduces addon loaded quantity to 6.0 KG via delta save
        $saveResponse = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    (string) $addonItem->id => 6.0,
                ],
                'item_status' => [
                    (string) $addonItem->id => 'loaded',
                ],
            ]);
        $saveResponse->assertOk();

        // 4. Verify item state: approved_qty and requested_qty now match reduced loaded_qty (6.0)
        $addonItem->refresh();
        $this->assertSame(6.0, (float) $addonItem->approved_qty);
        $this->assertSame(6.0, (float) $addonItem->loaded_qty);
        $this->assertSame(6.0, (float) $addonItem->requested_qty);
        $this->assertSame(240.0, (float) $addonItem->line_total); // 6.0 * 40.0

        // 5. Verify stock ledger: exactly 4.0 KG reversed, net deducted is 6.0 KG
        $this->assertEquals(6.0, $ledgerService->netDeductedQuantityForOrderItems([$addonItem->id]));
        $reversalMovements = StockMovement::where('shop_order_item_id', $addonItem->id)
            ->where('type', StockMovementType::SaleReversal)
            ->get();
        $this->assertCount(1, $reversalMovements);
        $this->assertEquals(4.0, (float) $reversalMovements->first()->quantity);

        // 6. Verify loadout show endpoint exposes 0 balance and allows delivery
        $showResponse = $this->actingAs($this->receiver)
            ->getJson("/api/v1/warehouse/loadout/{$order->id}");
        $showResponse->assertOk();
        $groups = collect($showResponse->json('product_groups'));
        $groupB = $groups->firstWhere('product_grade', 'B');
        $this->assertNotNull($groupB);
        $this->assertEquals(6.0, $groupB['total_approved']);
        $this->assertEquals(6.0, $groupB['total_loaded']);
        $this->assertEquals(0.0, $groupB['total_balance']);
        $this->assertTrue($showResponse->json('can_move_to_delivery'));

        // 7. Verify move-to-delivery succeeds without partial delivery error
        $deliveryResponse = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [
                'partial' => false,
            ]);
        $deliveryResponse->assertOk();
        $deliveryResponse->assertJsonPath('success', true);
        $this->assertSame('in_transit', $order->fresh()->delivery_status);

        // 8. Verify invoice has Grade B billed for 6.0 with zero shortage
        $invoice = $order->fresh()->invoice;
        $this->assertNotNull($invoice);
        $invoiceItemB = $invoice->items->firstWhere('product_grade', 'B');
        $this->assertNotNull($invoiceItemB);
        $this->assertEquals(6.0, (float) $invoiceItemB->approved_qty);
        $this->assertEquals(6.0, (float) $invoiceItemB->delivered_qty);
        $this->assertEquals(0.0, (float) $invoiceItemB->shortage_qty);
    }

    /**
     * Setting addon quantity to 0 reverses all deducted stock and does not block delivery.
     */
    public function test_addon_quantity_zeroing_reverses_all_stock_and_does_not_block_delivery(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 50.0);

        $order = $this->createOrder();

        // 1. Regular item: Tomato Grade A, 10 KG loaded
        $itemA = $this->createLoadedOrderItem($order, 'A', 10.0);
        $order->update(['delivery_status' => 'ready_for_dispatch']);

        // 2. Add addon: Tomato Grade B, 10 KG
        $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 10.0,
                'grade' => 'B',
            ])->assertOk();

        $addonItem = ShopOrderItem::where('shop_order_id', $order->id)->where('product_grade', 'B')->firstOrFail();

        // 3. Warehouse worker zeroes the addon line (sets quantity to 0)
        $saveResponse = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    (string) $addonItem->id => 0.0,
                ],
            ]);
        $saveResponse->assertOk();

        // 4. Verify item state: approved_qty, loaded_qty and requested_qty are all 0
        $addonItem->refresh();
        $this->assertSame(0.0, (float) $addonItem->approved_qty);
        $this->assertSame(0.0, (float) $addonItem->loaded_qty);
        $this->assertSame(0.0, (float) $addonItem->requested_qty);
        $this->assertSame(0.0, (float) $addonItem->line_total);

        // 5. Verify stock ledger: all 10.0 KG reversed, net deducted is 0.0 KG
        $ledgerService = app(StockLedgerService::class);
        $this->assertEquals(0.0, $ledgerService->netDeductedQuantityForOrderItems([$addonItem->id]));

        // 6. Verify loadout show endpoint exposes 0 balance and allows delivery
        $showResponse = $this->actingAs($this->receiver)
            ->getJson("/api/v1/warehouse/loadout/{$order->id}");
        $showResponse->assertOk();
        $this->assertTrue($showResponse->json('can_move_to_delivery'));

        // 7. Verify move-to-delivery succeeds without blocking
        $deliveryResponse = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [
                'partial' => false,
            ]);
        $deliveryResponse->assertOk();
        $this->assertSame('in_transit', $order->fresh()->delivery_status);

        // 8. Verify invoice has NO item for zeroed Grade B addon
        $invoice = $order->fresh()->invoice;
        $this->assertNotNull($invoice);
        $invoiceItemB = $invoice->items->firstWhere('product_grade', 'B');
        $this->assertNull($invoiceItemB);
    }

    /**
     * Marking addon as not_available reverses stock and does not block delivery.
     */
    public function test_addon_marked_not_available_reverses_stock_and_does_not_block_delivery(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 50.0);

        $order = $this->createOrder();

        // 1. Regular loaded item
        $this->createLoadedOrderItem($order, 'A', 10.0);
        $order->update(['delivery_status' => 'ready_for_dispatch']);

        // 2. Add addon: Grade B 10 KG
        $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 10.0,
                'grade' => 'B',
            ])->assertOk();

        $addonItem = ShopOrderItem::where('shop_order_id', $order->id)->where('product_grade', 'B')->firstOrFail();

        // 3. Mark addon as not_available
        $saveResponse = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'item_status' => [
                    (string) $addonItem->id => 'not_available',
                ],
                'item_notes' => [
                    (string) $addonItem->id => 'Out of Grade B stock at warehouse',
                ],
            ]);
        $saveResponse->assertOk();

        $addonItem->refresh();
        $this->assertSame(0.0, (float) $addonItem->approved_qty);
        $this->assertSame(0.0, (float) $addonItem->loaded_qty);
        $this->assertSame('not_available', $addonItem->sorting_status);

        // Stock reversed
        $ledgerService = app(StockLedgerService::class);
        $this->assertEquals(0.0, $ledgerService->netDeductedQuantityForOrderItems([$addonItem->id]));

        // Delivery succeeds
        $deliveryResponse = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [
                'partial' => false,
            ]);
        $deliveryResponse->assertOk();
        $this->assertSame('in_transit', $order->fresh()->delivery_status);
    }

    /**
     * Repeated saves and adjustments maintain stock and quantity consistency.
     */
    public function test_addon_repeated_autosaves_and_adjustments_maintain_stock_consistency(): void
    {
        $this->createSortedBatch('B', 100.0);

        $order = $this->createOrder();

        // Regular item loaded
        $this->createLoadedOrderItem($order, 'A', 5.0);
        $order->update(['delivery_status' => 'ready_for_dispatch']);

        // Add addon: 10 KG
        $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 10.0,
                'grade' => 'B',
            ])->assertOk();

        $addonItem = ShopOrderItem::where('shop_order_id', $order->id)->where('product_grade', 'B')->firstOrFail();
        $ledgerService = app(StockLedgerService::class);
        $this->assertEquals(10.0, $ledgerService->netDeductedQuantityForOrderItems([$addonItem->id]));

        // Edit 1: Save 8.0 KG
        $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [(string) $addonItem->id => 8.0],
                'item_status' => [(string) $addonItem->id => 'loaded'],
            ])->assertOk();
        $this->assertEquals(8.0, $ledgerService->netDeductedQuantityForOrderItems([$addonItem->id]));

        // Edit 2: Save 8.0 KG again (idempotent, no extra movements)
        $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [(string) $addonItem->id => 8.0],
                'item_status' => [(string) $addonItem->id => 'loaded'],
            ])->assertOk();
        $this->assertEquals(8.0, $ledgerService->netDeductedQuantityForOrderItems([$addonItem->id]));

        // Edit 3: Save 0.0 KG (unloaded)
        $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [(string) $addonItem->id => 0.0],
            ])->assertOk();
        $this->assertEquals(0.0, $ledgerService->netDeductedQuantityForOrderItems([$addonItem->id]));

        // Edit 4: Save 5.0 KG (reloaded)
        $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [(string) $addonItem->id => 5.0],
                'item_status' => [(string) $addonItem->id => 'loaded'],
            ])->assertOk();
        $this->assertEquals(5.0, $ledgerService->netDeductedQuantityForOrderItems([$addonItem->id]));

        $addonItem->refresh();
        $this->assertSame(5.0, (float) $addonItem->approved_qty);
        $this->assertSame(5.0, (float) $addonItem->loaded_qty);

        // Move to delivery succeeds
        $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [
                'partial' => false,
            ])->assertOk();
        $this->assertSame('in_transit', $order->fresh()->delivery_status);
    }

    /**
     * Multi-grade addon reduction leaves sister grade lines completely untouched.
     */
    public function test_multigrade_addon_reduction_leaves_sister_grade_lines_untouched(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 50.0);

        $order = $this->createOrder();

        // Regular item: Tomato Grade A, 15 KG approved, 15 KG loaded
        $itemA = $this->createLoadedOrderItem($order, 'A', 15.0);
        $order->update(['delivery_status' => 'ready_for_dispatch']);

        // Addon item: Tomato Grade B, 10 KG
        $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 10.0,
                'grade' => 'B',
            ])->assertOk();

        $addonItem = ShopOrderItem::where('shop_order_id', $order->id)->where('product_grade', 'B')->firstOrFail();

        // Delta autosave updating ONLY Grade B addon to 3.0 KG
        $saveResponse = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    (string) $addonItem->id => 3.0,
                ],
                'item_status' => [
                    (string) $addonItem->id => 'loaded',
                ],
            ]);
        $saveResponse->assertOk();

        // Verify Grade A sister line is 100% untouched
        $itemA->refresh();
        $this->assertSame(15.0, (float) $itemA->approved_qty);
        $this->assertSame(15.0, (float) $itemA->loaded_qty);
        $this->assertSame(15.0, (float) $itemA->requested_qty);
        $this->assertSame('loaded', $itemA->sorting_status);

        // Verify Grade B addon is updated
        $addonItem->refresh();
        $this->assertSame(3.0, (float) $addonItem->approved_qty);
        $this->assertSame(3.0, (float) $addonItem->loaded_qty);
        $this->assertSame(3.0, (float) $addonItem->requested_qty);
        $this->assertSame('loaded', $addonItem->sorting_status);

        // Verify full delivery succeeds
        $deliveryResponse = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [
                'partial' => false,
            ]);
        $deliveryResponse->assertOk();
        $this->assertSame('in_transit', $order->fresh()->delivery_status);
    }

    public function test_addon_creation_succeeds_when_invoice_has_unpaid_balance_and_not_financially_locked(): void
    {
        $this->createSortedBatch('A', 20.0);
        $this->createSortedBatch('B', 20.0);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
            'state' => 'confirmed',
            'delivery_status' => 'ready_for_dispatch',
            'delivery_review_status' => 'pending',
            'is_delivered' => false,
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 5.0,
            'loaded_order_unit_qty' => 5.0,
            'unit' => 'KG',
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 50.0,
            'locked_price_source' => 'board',
            'line_total' => 250.0,
            'sorting_status' => 'loaded',
            'fulfillment_type' => 'warehouse',
        ]);

        // Invoice exists with unpaid balance and no payment received
        $invoice = ShopInvoice::create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-TEST-UNPAID',
            'business_date' => $this->businessDate,
            'subtotal' => 250.0,
            'final_total' => 250.0,
            'balance_amount' => 250.0,
            'paid_amount' => 0.0,
            'status' => 'generated',
            'payment_status' => 'unpaid',
            'delivery_status' => 'pending',
            'generated_by' => $this->receiver->id,
        ]);

        // Unloaded relation check
        $freshOrder = ShopOrder::findOrFail($order->id);
        $this->assertFalse($freshOrder->isFinanciallyLocked());

        // Add Grade B addon product
        $response = $this->actingAs($this->receiver)
            ->withHeader('X-Client-Supports-Multi-Grade', 'true')
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 2.0,
                'product_grade' => 'B',
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'loaded_qty' => 2.0,
        ]);
    }
}
