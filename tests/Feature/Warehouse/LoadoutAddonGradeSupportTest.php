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
            'name' => 'General Retail',
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
     * 8. Failed Add-on does not leave partial inventory movements.
     */
    public function test_failed_addon_does_not_leave_partial_inventory_movements(): void
    {
        // Batch with only 5 KG available
        $this->createSortedBatch('B', 5.0);
        $order = $this->createOrder();

        $movementCountBefore = StockMovement::count();
        $itemCountBefore = ShopOrderItem::count();

        // Attempt to add 20 KG (exceeds available 5 KG)
        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/addon", [
                'product_id' => $this->product->id,
                'quantity' => 20.0,
                'product_grade' => 'B',
            ]);

        $response->assertStatus(422);

        // Transaction rolled back: zero items and zero movements added
        $this->assertSame($movementCountBefore, StockMovement::count());
        $this->assertSame($itemCountBefore, ShopOrderItem::count());
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
}
