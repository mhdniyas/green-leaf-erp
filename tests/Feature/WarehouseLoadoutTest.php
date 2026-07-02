<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedgerService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tests for warehouse loadout flow:
 *  Save Loadout -> stock OUT is recorded immediately
 *  Move to Delivery / Partial Delivery -> status transition only
 *  Shop owner delivery check -> delivery completion
 */
class WarehouseLoadoutTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private Warehouse $warehouse;

    private Shop $shop;

    private Product $product;

    private StockBatch $batch;

    private ShopOrder $order;

    private ShopOrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');

        $this->warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'TW-001',
            'is_active' => true,
        ]);

        $this->shop = Shop::create([
            'code' => 'TEST_LOADOUT_SHOP',
            'name' => 'Test Loadout Shop',
            'status' => 'active',
        ]);

        $this->product = Product::factory()->create();

        $today = Carbon::today()->format('Y-m-d');

        // Create a stock batch for today (30 kg received)
        $this->batch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'total_kg' => 30.0,
            'received_at' => $today,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'sorted',
            'warehouse_receive_pending' => false,
        ]);

        StockMovement::create([
            'batch_id' => $this->batch->id,
            'product_id' => $this->product->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 30.0,
            'cost_per_unit' => 5.0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Create a shop order
        $this->order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'state' => 'approved',
            'business_date' => $today,
            'created_by' => $this->receiver->id,
        ]);

        // Create an order item (approved 20 kg)
        $this->item = ShopOrderItem::create([
            'shop_order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'approved_qty' => 20.0,
            'requested_qty' => 20.0,
            'loaded_qty' => null,
            'sorting_status' => 'allocated',
            'unit' => 'kg',
        ]);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_loadout_index_shows_pending_and_ready_orders(): void
    {
        $response = $this->actingAs($this->receiver)->get(route('warehouse.loadout.index'));
        $response->assertOk();
        $response->assertViewIs('warehouse.loadout.index');
        $response->assertSee($this->shop->name);
        $response->assertSee('Search by shop or order number');
        $response->assertSee('Waiting');
        $response->assertSee('Partial');
        $response->assertSee('Ready');
        $response->assertSee('In Transit');
        $response->assertSee('Delivered');
    }

    public function test_loadout_index_search_filters_orders(): void
    {
        $otherShop = Shop::create([
            'code' => 'OTHER_SHOP',
            'name' => 'Other Shop',
            'status' => 'active',
        ]);

        $otherOrder = ShopOrder::create([
            'shop_id' => $otherShop->id,
            'delivery_status' => 'pending_delivery',
            'state' => 'approved',
            'business_date' => Carbon::today()->format('Y-m-d'),
            'created_by' => $this->receiver->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $otherOrder->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'approved_qty' => 5.0,
            'requested_qty' => 5.0,
            'loaded_qty' => null,
            'sorting_status' => 'allocated',
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->receiver)->get(route('warehouse.loadout.index', ['search' => 'Loadout Shop']));

        $response->assertOk();
        $response->assertSee($this->shop->name);
        $response->assertDontSee('Other Shop');
    }

    public function test_loadout_index_is_forbidden_for_unauthorized_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole('purchaser');

        $this->actingAs($user)->get(route('warehouse.loadout.index'))->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_loadout_show_displays_grouped_products(): void
    {
        $response = $this->actingAs($this->receiver)->get(route('warehouse.loadout.show', $this->order));
        $response->assertOk();
        $response->assertViewIs('warehouse.loadout.show');
        $response->assertSee($this->product->name);
        $response->assertSee('Save Loadout');
        $response->assertSee('Start from');
        $response->assertSee('value="0.00"', false);
        $response->assertSee('Go to top');
        $response->assertSee('Go to bottom');
        $response->assertSee('Full quantities applied');
        $response->assertSee('Warehouse Desk');
        $response->assertSee('Receive');
        $response->assertSee('Inventory');
        $response->assertSee('Loadout');
    }

    public function test_loadout_show_displays_inventory_as_available_stock_for_pending_items(): void
    {
        $this->item->update([
            'sorting_status' => 'pending',
            'loaded_qty' => null,
        ]);
        $expectedAvailableStock = app(StockLedgerService::class)->availableStockForProduct($this->product->id);

        $response = $this->actingAs($this->receiver)->get(route('warehouse.loadout.show', $this->order));

        $response->assertOk();
        $response->assertSee('Available:');
        $response->assertSee(number_format($expectedAvailableStock, 2).' kg');
        $response->assertSee('Balance: 20.00 kg remaining');
    }

    public function test_loadout_show_displays_top_out_for_delivery_action_when_ready(): void
    {
        $this->item->update([
            'sorting_status' => 'loaded',
            'loaded_qty' => 20.0,
        ]);
        $this->order->update(['delivery_status' => 'ready_for_dispatch']);

        $response = $this->actingAs($this->receiver)->get(route('warehouse.loadout.show', $this->order));

        $response->assertOk();
        $response->assertSee('Out for Delivery');
        $response->assertSee('Move to Delivery');
    }

    public function test_loadout_show_displays_partial_delivery_action_when_balance_exists(): void
    {
        $this->item->update([
            'sorting_status' => 'loaded',
            'loaded_qty' => 15.0,
            'approved_qty' => 15.0,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'approved_qty' => 5.0,
            'requested_qty' => 5.0,
            'loaded_qty' => null,
            'sorting_status' => 'allocated',
            'unit' => 'kg',
        ]);

        $this->order->update(['delivery_status' => 'ready_for_dispatch']);

        $response = $this->actingAs($this->receiver)->get(route('warehouse.loadout.show', $this->order));

        $response->assertOk();
        $response->assertSee('Partial Delivery');
        $response->assertSee('Move to Partial Delivery');
    }

    public function test_loadout_show_does_not_allow_warehouse_to_mark_delivered(): void
    {
        $this->item->update([
            'sorting_status' => 'loaded',
            'loaded_qty' => 20.0,
        ]);
        $this->order->update(['delivery_status' => 'in_transit']);

        $response = $this->actingAs($this->receiver)->get(route('warehouse.loadout.show', $this->order));

        $response->assertOk();
        $response->assertSee('Order is out for delivery');
        $response->assertSee('Move to Loadout');
        $response->assertDontSee('Mark Delivered');
    }

    // ─── Save: Test 1 — Full Loadout ──────────────────────────────────────────

    public function test_save_full_loadout_marks_item_loaded_and_deducts_stock(): void
    {
        $initialStockCount = StockMovement::count();

        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $this->order), [
            'items' => [$this->product->id => 20.0],
        ]);

        $response->assertRedirect(route('warehouse.loadout.show', $this->order));
        $response->assertSessionHas('success');

        // Item is marked loaded
        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'loaded_qty' => 20.0,
            'sorting_status' => 'loaded',
        ]);

        // Order status updated to ready_for_dispatch
        $this->assertDatabaseHas('shop_orders', [
            'id' => $this->order->id,
            'delivery_status' => 'ready_for_dispatch',
        ]);

        // CRITICAL: Stock movement is created
        $this->assertEquals($initialStockCount + 1, StockMovement::count());
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => StockMovementType::Out->value,
            'quantity' => 20.0,
        ]);
    }

    public function test_save_full_loadout_creates_out_movement_for_confirmed_unsorted_stock(): void
    {
        StockMovement::query()->delete();
        $this->batch->update([
            'status' => 'pending',
            'warehouse_receive_pending' => false,
            'total_kg' => 30.0,
        ]);

        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $this->order), [
            'items' => [$this->product->id => 20.0],
        ]);

        $response->assertRedirect(route('warehouse.loadout.show', $this->order));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shop_orders', [
            'id' => $this->order->id,
            'delivery_status' => 'ready_for_dispatch',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'batch_id' => $this->batch->id,
            'product_id' => $this->product->id,
            'grade' => 'U',
            'type' => StockMovementType::Out->value,
            'quantity' => 20.0,
        ]);
    }

    // ─── Save: Test 2 — Partial Loadout ──────────────────────────────────────

    public function test_save_partial_loadout_creates_balance_row(): void
    {
        $initialStockCount = StockMovement::count();

        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $this->order), [
            'items' => [$this->product->id => 15.0],
        ]);

        $response->assertRedirect(route('warehouse.loadout.show', $this->order));

        // Loaded row
        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'loaded_qty' => 15.0,
            'approved_qty' => 15.0,
            'sorting_status' => 'loaded',
        ]);

        // Balance row
        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'approved_qty' => 5.0,
            'sorting_status' => 'allocated',
        ]);

        // Stock movement is created for loaded quantity
        $this->assertEquals($initialStockCount + 1, StockMovement::count());
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => StockMovementType::Out->value,
            'quantity' => 15.0,
        ]);
    }

    // ─── Save: Test 3 — Edit Before Delivery ─────────────────────────────────

    public function test_save_again_before_delivery_updates_loaded_qty(): void
    {
        // First save: 15 kg
        $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $this->order), [
            'items' => [$this->product->id => 15.0],
        ]);

        // Second save: 20 kg (full)
        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $this->order), [
            'items' => [$this->product->id => 20.0],
        ]);

        $response->assertRedirect(route('warehouse.loadout.show', $this->order));

        // Only one loaded row at full qty
        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'loaded_qty' => 20.0,
            'approved_qty' => 20.0,
            'sorting_status' => 'loaded',
        ]);

        // Balance row gone
        $this->assertEquals(1, ShopOrderItem::where('shop_order_id', $this->order->id)
            ->where('product_id', $this->product->id)->count());

        // Initial In (30.0) + First Out (15.0) + Second Out (5.0) = 3 movements
        $this->assertEquals(3, StockMovement::count());
        $outMovements = StockMovement::where('type', StockMovementType::Out->value)->orderBy('id')->get();
        $this->assertEquals(15.0, (float) $outMovements[0]->quantity);
        $this->assertEquals(5.0, (float) $outMovements[1]->quantity);
    }

    // ─── Save: No stock change if zero items loaded ───────────────────────────

    public function test_save_zero_qty_keeps_order_pending(): void
    {
        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $this->order), [
            'items' => [$this->product->id => 0.0],
        ]);

        $response->assertRedirect(route('warehouse.loadout.show', $this->order));

        $this->assertDatabaseHas('shop_orders', [
            'id' => $this->order->id,
            'delivery_status' => 'pending_delivery',
        ]);
    }

    // ─── Save: Validation — cannot exceed approved qty ───────────────────────

    public function test_save_rejects_qty_greater_than_approved(): void
    {
        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $this->order), [
            'items' => [$this->product->id => 25.0], // approved is only 20
        ]);

        $response->assertSessionHasErrors();
        $this->assertEquals(1, StockMovement::count());
    }

    // ─── Save: Validation — cannot exceed available stock ───────────────────

    public function test_save_rejects_qty_greater_than_available_stock(): void
    {
        // Only 5 kg in batch and stock ledger
        $this->batch->update(['total_kg' => 5.0]);
        StockMovement::where('batch_id', $this->batch->id)->update(['quantity' => 5.0]);

        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $this->order), [
            'items' => [$this->product->id => 15.0],
        ]);

        $response->assertSessionHasErrors();
        $this->assertEquals(1, StockMovement::count());
    }

    // ─── Save: Blocked when in_transit ────────────────────────────────────────

    public function test_save_blocked_when_order_is_in_transit(): void
    {
        $this->order->update(['delivery_status' => 'in_transit']);

        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $this->order), [
            'items' => [$this->product->id => 20.0],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_move_to_partial_delivery_sets_in_transit_without_additional_stock_change(): void
    {
        $initialStockCount = StockMovement::count();

        // Save first (this consumes 15 kg OUT)
        $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $this->order), [
            'items' => [$this->product->id => 15.0],
        ]);

        $this->assertEquals($initialStockCount + 1, StockMovement::count());

        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.move-to-partial-delivery', $this->order));

        $response->assertRedirect(route('warehouse.loadout.index'));
        $response->assertSessionHas('success');

        // Order is now in_transit
        $this->assertDatabaseHas('shop_orders', [
            'id' => $this->order->id,
            'delivery_status' => 'in_transit',
        ]);

        // Stock OUT remains 1, no duplicate/additional deduction occurred during dispatch
        $this->assertEquals($initialStockCount + 1, StockMovement::count());
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => StockMovementType::Out->value,
            'quantity' => 15.0,
        ]);
    }

    public function test_move_to_delivery_is_blocked_when_order_still_has_balance_items(): void
    {
        $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $this->order), [
            'items' => [$this->product->id => 15.0],
        ]);

        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.move-to-delivery', $this->order));

        $response->assertSessionHasErrors();
    }

    // ─── Move to Delivery: Test 6 — Duplicate protection ─────────────────────

    public function test_move_to_delivery_blocked_when_already_in_transit(): void
    {
        $this->order->update(['delivery_status' => 'in_transit']);

        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.move-to-delivery', $this->order));

        $response->assertSessionHasErrors();
    }

    public function test_move_to_loadout_returns_in_transit_order_to_ready_for_dispatch(): void
    {
        $this->order->update([
            'delivery_status' => 'in_transit',
            'is_allocation_completed' => true,
        ]);

        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.move-to-loadout', $this->order));

        $response->assertRedirect(route('warehouse.loadout.show', $this->order));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shop_orders', [
            'id' => $this->order->id,
            'delivery_status' => 'ready_for_dispatch',
            'is_allocation_completed' => false,
        ]);
    }

    public function test_move_to_loadout_is_blocked_for_delivered_order(): void
    {
        $this->order->update(['delivery_status' => 'delivered']);

        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.move-to-loadout', $this->order));

        $response->assertSessionHasErrors();
    }

    // ─── Move to Delivery: No loaded items ───────────────────────────────────

    public function test_move_to_delivery_blocked_without_loaded_items(): void
    {
        $this->order->update(['delivery_status' => 'ready_for_dispatch']);
        // item stays allocated with no loaded_qty

        $response = $this->actingAs($this->receiver)->post(route('warehouse.loadout.move-to-delivery', $this->order));

        $response->assertSessionHasErrors();
        $this->assertEquals(0, StockMovement::where('type', StockMovementType::Out->value)->count());
    }
}
