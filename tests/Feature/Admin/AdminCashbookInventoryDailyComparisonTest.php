<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCashbookInventoryDailyComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouseVeg;

    private Warehouse $warehouseFruit;

    private Product $tomato;

    private Product $potato;

    private Product $banana;

    private Product $apple;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouseVeg = Warehouse::factory()->create([
            'name' => 'Vegetable Warehouse',
            'code' => 'VEG',
            'is_active' => true,
        ]);

        $this->warehouseFruit = Warehouse::factory()->create([
            'name' => 'Fruit Warehouse',
            'code' => 'FRUIT',
            'is_active' => true,
        ]);

        $category = Category::factory()->create();

        $this->tomato = Product::create([
            'category_id' => $category->id,
            'name' => 'Tomato',
            'sku' => 'TOM-01',
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouseVeg->id,
            'base_price' => 10,
            'is_active' => true,
        ]);

        $this->potato = Product::create([
            'category_id' => $category->id,
            'name' => 'Potato',
            'sku' => 'POT-01',
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouseVeg->id,
            'base_price' => 15,
            'is_active' => true,
        ]);

        $this->banana = Product::create([
            'category_id' => $category->id,
            'name' => 'Banana Leaf',
            'sku' => 'BAN-01',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouseVeg->id,
            'base_price' => 20,
            'is_active' => true,
        ]);

        $this->apple = Product::create([
            'category_id' => $category->id,
            'name' => 'Apple Royal',
            'sku' => 'APP-01',
            'unit' => 'box',
            'default_warehouse_id' => $this->warehouseFruit->id,
            'base_price' => 50,
            'is_active' => true,
        ]);
    }

    public function test_same_unit_and_full_match_calculations(): void
    {
        $date = '2026-09-13';

        // 1. Advance Receipt (10 KG Potato)
        $grnAdv = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'received_at' => $date.' 10:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grnAdv->id,
            'product_id' => $this->potato->id,
            'received_qty' => 10.00,
            'received_unit' => 'KG',
        ]);

        // 2. Bill Receipt (10 KG Potato -> Full Match 100%, Diff 0)
        $grnBill = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'standard',
            'status' => 'received',
            'received_at' => $date.' 14:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grnBill->id,
            'product_id' => $this->potato->id,
            'received_qty' => 10.00,
            'received_unit' => 'KG',
        ]);

        $this->actingAs($this->admin);

        $response = $this->get('/admin/cashbook/inventory?date='.$date);

        $response->assertOk()
            ->assertSee('Daily Inventory Comparison')
            ->assertSee('Match %');

        $rows = $response->viewData('rows');
        $potatoRow = $rows->firstWhere('product_id', $this->potato->id);

        $this->assertFalse($potatoRow['unit_mismatch']);
        $this->assertEquals(0.0, $potatoRow['diff']);
        $this->assertEquals('0', $potatoRow['formatted_diff']);
        $this->assertEquals(0.0, $potatoRow['match_pct']);
        $this->assertEquals('0%', $potatoRow['formatted_match_pct']);

        // Execute match
        $matchRes = $this->postJson(route('admin.cashbook.inventory.match-day'), [
            'date' => $date,
            'warehouse_id' => $this->warehouseVeg->id,
            'product_id' => $this->potato->id,
            'unit' => 'KG',
        ]);
        $matchRes->assertOk();

        // After match -> 100%
        $refreshedRows = $this->get('/admin/cashbook/inventory?date='.$date)->viewData('rows');
        $matchedPotato = $refreshedRows->firstWhere('product_id', $this->potato->id);
        $this->assertEquals(100.0, $matchedPotato['match_pct']);
        $this->assertEquals('100%', $matchedPotato['formatted_match_pct']);
    }

    public function test_advance_smaller_than_bill_and_advance_larger_than_bill(): void
    {
        $date = '2026-09-13';

        $grnAdv = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'received_at' => $date.' 10:00:00',
        ]);

        // Potato: Advance 4 KG (smaller than bill 10 KG -> Diff -6 KG)
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grnAdv->id,
            'product_id' => $this->potato->id,
            'received_qty' => 4.00,
            'received_unit' => 'KG',
        ]);

        // Tomato: Advance 10 KG (larger than bill 4 KG -> Diff +6 KG)
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grnAdv->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 10.00,
            'received_unit' => 'KG',
        ]);

        $grnBill = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'standard',
            'status' => 'received',
            'received_at' => $date.' 14:00:00',
        ]);

        // Potato: Bill 10 KG
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grnBill->id,
            'product_id' => $this->potato->id,
            'received_qty' => 10.00,
            'received_unit' => 'KG',
        ]);

        // Tomato: Bill 4 KG
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grnBill->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 4.00,
            'received_unit' => 'KG',
        ]);

        $this->actingAs($this->admin);

        $response = $this->get('/admin/cashbook/inventory?date='.$date);

        $response->assertOk();
        $rows = $response->viewData('rows');

        $potatoRow = $rows->firstWhere('product_id', $this->potato->id);
        $this->assertEquals(-6.0, $potatoRow['diff']);
        $this->assertEquals('-6 KG', $potatoRow['formatted_diff']);

        $tomatoRow = $rows->firstWhere('product_id', $this->tomato->id);
        $this->assertEquals(6.0, $tomatoRow['diff']);
        $this->assertEquals('+6 KG', $tomatoRow['formatted_diff']);

        // Match all day inventory
        $this->postJson(route('admin.cashbook.inventory.match-all-day'), [
            'date' => $date,
            'warehouse_id' => $this->warehouseVeg->id,
        ])->assertOk();

        // After match: Potato matched 4 of 10 KG = 40%, Tomato matched 4 of 4 KG = 100%
        $matchedRows = $this->get('/admin/cashbook/inventory?date='.$date)->viewData('rows');
        $this->assertEquals(40.0, $matchedRows->firstWhere('product_id', $this->potato->id)['match_pct']);
        $this->assertEquals('40%', $matchedRows->firstWhere('product_id', $this->potato->id)['formatted_match_pct']);
        $this->assertEquals(100.0, $matchedRows->firstWhere('product_id', $this->tomato->id)['match_pct']);
        $this->assertEquals('100%', $matchedRows->firstWhere('product_id', $this->tomato->id)['formatted_match_pct']);
    }

    public function test_unit_mismatch_detection_and_fix_unit_action(): void
    {
        $date = '2026-09-13';

        // Advance: 85 piece
        $grnAdv = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'received_at' => $date.' 10:00:00',
        ]);

        $advItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grnAdv->id,
            'product_id' => $this->banana->id,
            'received_qty' => 85.00,
            'received_unit' => 'piece',
        ]);

        // Bill: 80 KG
        $grnBill = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'standard',
            'status' => 'received',
            'received_at' => $date.' 14:00:00',
        ]);

        $billItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grnBill->id,
            'product_id' => $this->banana->id,
            'received_qty' => 80.00,
            'received_unit' => 'KG',
        ]);

        $this->actingAs($this->admin);

        $response = $this->get('/admin/cashbook/inventory?date='.$date);

        $response->assertOk()
            ->assertSee('Daily Inventory Comparison');

        $rows = $response->viewData('rows');
        $bananaRow = $rows->firstWhere('product_id', $this->banana->id);

        $this->assertTrue($bananaRow['unit_mismatch']);
        $this->assertNull($bananaRow['diff']);
        $this->assertEquals('Unit Mismatch', $bananaRow['formatted_diff']);
        $this->assertNull($bananaRow['match_pct']);
        $this->assertEquals('--', $bananaRow['formatted_match_pct']);

        // Test fixing the unit on the Bill item from KG to piece
        $updateResponse = $this->postJson('/admin/cashbook/inventory/update-item-unit', [
            'goods_received_item_id' => $billItem->id,
            'new_unit' => 'piece',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertEquals('piece', $billItem->fresh()->received_unit);

        // After fixing unit: refresh and recalculate
        $refreshed = $this->get('/admin/cashbook/inventory?date='.$date);
        $refreshedRows = $refreshed->viewData('rows');
        $fixedRow = $refreshedRows->firstWhere('product_id', $this->banana->id);

        $this->assertFalse($fixedRow['unit_mismatch']);
        $this->assertEquals(5.0, $fixedRow['diff']);
        $this->assertEquals('+5 piece', $fixedRow['formatted_diff']);
    }

    public function test_warehouse_filter_isolates_warehouse_data_and_persists_in_query_string(): void
    {
        $date = '2026-09-13';

        // Veg warehouse receipt (Tomato)
        $grnVeg = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'received_at' => $date.' 10:00:00',
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grnVeg->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 10.00,
            'received_unit' => 'KG',
        ]);

        // Fruit warehouse receipt (Apple)
        $grnFruit = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseFruit->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'received_at' => $date.' 10:00:00',
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grnFruit->id,
            'product_id' => $this->apple->id,
            'received_qty' => 50.00,
            'received_unit' => 'box',
        ]);

        $this->actingAs($this->admin);

        // 1. All warehouses view
        $allResponse = $this->get('/admin/cashbook/inventory?date='.$date);
        $allRows = $allResponse->viewData('rows');
        $this->assertCount(2, $allRows);

        // 2. Filter to Vegetable Warehouse only
        $vegResponse = $this->get('/admin/cashbook/inventory?date='.$date.'&warehouse_id='.$this->warehouseVeg->id);
        $vegRows = $vegResponse->viewData('rows');
        $this->assertCount(1, $vegRows);
        $this->assertEquals($this->tomato->id, $vegRows->first()['product_id']);

        // 3. Filter to Fruit Warehouse only
        $fruitResponse = $this->get('/admin/cashbook/inventory?date='.$date.'&warehouse_id='.$this->warehouseFruit->id);
        $fruitRows = $fruitResponse->viewData('rows');
        $this->assertCount(1, $fruitRows);
        $this->assertEquals($this->apple->id, $fruitRows->first()['product_id']);
    }

    public function test_unit_fix_changes_only_metadata_and_does_not_modify_inventory_or_stock_batches(): void
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'received_at' => '2026-09-13 10:00:00',
        ]);

        $item = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 25.00,
            'received_unit' => 'KG',
        ]);

        $batch = StockBatch::factory()->create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 25.00,
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson('/admin/cashbook/inventory/update-item-unit', [
            'goods_received_item_id' => $item->id,
            'new_unit' => 'box',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertEquals('box', $item->fresh()->received_unit);
        $this->assertEquals(25.00, $item->fresh()->received_qty);

        // Verify stock batch total_kg is NOT modified
        $this->assertEquals(25.00, $batch->fresh()->total_kg);
    }

    public function test_inventory_page_defaults_to_latest_data_date_when_no_date_is_provided(): void
    {
        $yesterday = now()->subDay()->toDateString();

        // Yesterday: create an advance receipt
        $grnYesterday = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'received_at' => $yesterday.' 10:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grnYesterday->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 12.00,
            'received_unit' => 'KG',
        ]);

        $this->actingAs($this->admin);

        // Access /admin/cashbook/inventory without ?date=
        $response = $this->get('/admin/cashbook/inventory');

        $response->assertOk();
        $this->assertEquals($yesterday, $response->viewData('date'));
        $rows = $response->viewData('rows');
        $this->assertNotEmpty($rows);
        $this->assertEquals($this->tomato->id, $rows->first()['product_id']);
        $this->assertEquals(12.00, $rows->first()['advance_qty']);
    }

    public function test_inventory_page_warehouse_dropdown_includes_all_authorized_warehouses_when_one_warehouse_selected(): void
    {
        $this->actingAs($this->admin);

        // Access with warehouse_id=2 (Fruit Warehouse)
        $response = $this->get('/admin/cashbook/inventory?date=2026-09-01&warehouse_id='.$this->warehouseFruit->id);

        $response->assertOk();
        $warehouses = $response->viewData('warehouses');

        // Both Vegetable and Fruit warehouses should be available in dropdown
        $this->assertCount(2, $warehouses);
        $this->assertTrue($warehouses->contains('id', $this->warehouseVeg->id));
        $this->assertTrue($warehouses->contains('id', $this->warehouseFruit->id));
    }

    public function test_inventory_page_rows_contain_stock_balance(): void
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseFruit->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'received_at' => '2026-09-01 10:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 10.00,
            'received_unit' => 'box',
        ]);

        $this->actingAs($this->admin);

        $response = $this->get('/admin/cashbook/inventory?date=2026-09-01&warehouse_id='.$this->warehouseFruit->id);

        $response->assertOk();
        $rows = $response->viewData('rows');
        $this->assertNotEmpty($rows);
        $row = $rows->first();
        $this->assertArrayHasKey('stock_balance', $row);
        $this->assertArrayHasKey('formatted_stock_balance', $row);
    }
}
