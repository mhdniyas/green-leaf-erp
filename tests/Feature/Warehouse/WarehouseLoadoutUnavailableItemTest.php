<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Warehouse\WarehouseLoadoutCompletionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseLoadoutUnavailableItemTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Warehouse $warehouse;

    private Product $bunchProduct;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->operator = User::factory()->create(['name' => 'Loadout Operator']);
        $this->operator->assignRole('warehouse_receiver');

        $this->warehouse = Warehouse::factory()->create(['code' => 'WH1', 'name' => 'Main Warehouse']);
        $this->operator->warehouses()->attach($this->warehouse->id);

        $this->bunchProduct = Product::factory()->create([
            'name' => 'Mint Leaves (Bunch)',
            'sku' => 'MINT-BCH',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $batch = StockBatch::factory()->sorted()->create([
            'product_id' => $this->bunchProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->operator->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 10.0,
            'purchase_grade' => 'A',
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->bunchProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->operator->id,
            'grade' => 'A',
            'type' => 'in',
            'quantity' => 100.0,
            'cost_per_unit' => 10.0,
            'notes' => 'Opening stock',
        ]);

        $priceGroup = ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);
        $this->shop = Shop::factory()->create(['shop_price_group_id' => $priceGroup->id]);

        $businessDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();
        DailyPriceApproval::query()->create([
            'product_id' => $this->bunchProduct->id,
            'business_date' => $businessDate,
            'purchase_price' => 10,
            'price_unit' => 'kg',
            'price_a' => 15,
            'price_b' => 15,
            'price_c' => 15,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->operator->id,
        ]);

        Sanctum::actingAs($this->operator);
    }

    private function createOrderWithBunchItem(float $requestedBunches = 10.0, float $conversionToBase = 0.2): array
    {
        $businessDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
        ]);

        $expectedKg = round($requestedBunches * $conversionToBase, 3);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->bunchProduct->id,
            'product_grade' => 'A',
            'unit' => 'bunch',
            'requested_unit' => 'bunch',
            'requested_unit_quantity' => $requestedBunches,
            'requested_unit_conversion_to_base' => $conversionToBase,
            'requested_qty' => $expectedKg,
            'approved_qty' => $expectedKg,
            'locked_selling_price' => 15.0,
            'sorting_status' => 'allocated',
        ]);

        return [$order, $item];
    }

    private function netLedgerDeduction(ShopOrder $order): float
    {
        $out = (float) StockMovement::where('product_id', $this->bunchProduct->id)
            ->where('type', 'out')
            ->where('notes', 'like', "%Order: {$order->order_number}%")
            ->sum('quantity');

        $reversal = (float) StockMovement::where('product_id', $this->bunchProduct->id)
            ->where('type', 'sale_reversal')
            ->where('notes', 'like', "%Order: {$order->order_number}%")
            ->sum('quantity');

        return round($out - $reversal, 3);
    }

    public function test_web_loadout_saving_bunch_item_then_marking_not_available_reverses_ledger_to_zero(): void
    {
        // 10 bunches with conversion 0.2 = 2.0 kg
        [$order, $item] = $this->createOrderWithBunchItem(10.0, 0.2);

        // 1. Initial save with 10 bunches (2.0 kg)
        $this->postJson(route('warehouse.loadout.save', $order), [
            'item_unit_qtys' => [(string) $item->id => '10'],
        ])->assertSuccessful();

        $this->assertEquals(2.0, $this->netLedgerDeduction($order));
        $this->assertEquals(2.0, (float) $item->fresh()->loaded_qty);

        // 2. Mark not available
        $this->postJson(route('warehouse.loadout.save', $order), [
            'item_status' => [(string) $item->id => 'not_available'],
        ])->assertSuccessful();

        // Must reverse ledger deduction to 0.0 kg!
        $this->assertEquals(0.0, $this->netLedgerDeduction($order));
        $this->assertEquals(0.0, (float) $item->fresh()->loaded_qty);
        $this->assertEquals('not_available', $item->fresh()->sorting_status);
        $this->assertEquals('not_available', $item->fresh()->loadout_discrepancy_type);
    }

    public function test_web_loadout_repeated_not_available_saves_do_not_produce_movements(): void
    {
        [$order, $item] = $this->createOrderWithBunchItem(10.0, 0.2);

        // Mark not available immediately
        $this->postJson(route('warehouse.loadout.save', $order), [
            'item_status' => [(string) $item->id => 'not_available'],
        ])->assertSuccessful();

        $this->assertEquals(0.0, $this->netLedgerDeduction($order));

        $movementsBefore = StockMovement::where('product_id', $this->bunchProduct->id)
            ->where('notes', 'like', "%Order: {$order->order_number}%")
            ->count();

        // Save again as not_available
        $this->postJson(route('warehouse.loadout.save', $order), [
            'item_status' => [(string) $item->id => 'not_available'],
        ])->assertSuccessful();

        $movementsAfter = StockMovement::where('product_id', $this->bunchProduct->id)
            ->where('notes', 'like', "%Order: {$order->order_number}%")
            ->count();

        // Exactly zero new movements created on retry
        $this->assertSame($movementsBefore, $movementsAfter);
        $this->assertEquals(0.0, $this->netLedgerDeduction($order));
    }

    public function test_quantity_progression_10_to_12_to_8_to_not_available(): void
    {
        // 10 bunches @ 0.2 kg = 2.0 kg
        [$order, $item] = $this->createOrderWithBunchItem(10.0, 0.2);

        // Step 1: Save 10 bunches -> Net OUT = 2.0 kg
        $this->postJson(route('warehouse.loadout.save', $order), [
            'item_unit_qtys' => [(string) $item->id => '10'],
        ])->assertSuccessful();
        $this->assertEquals(2.0, $this->netLedgerDeduction($order));

        // Step 2: Increase to 12 bunches (2.4 kg) -> Net OUT = 2.4 kg
        $this->postJson(route('warehouse.loadout.save', $order), [
            'item_unit_qtys' => [(string) $item->id => '12'],
        ])->assertSuccessful();
        $this->assertEquals(2.4, $this->netLedgerDeduction($order));

        // Step 3: Decrease to 8 bunches (1.6 kg) -> Net OUT = 1.6 kg
        $this->postJson(route('warehouse.loadout.save', $order), [
            'item_unit_qtys' => [(string) $item->id => '8'],
        ])->assertSuccessful();
        $this->assertEquals(1.6, $this->netLedgerDeduction($order));

        // Step 4: Mark not available -> Net OUT = 0.0 kg
        $this->postJson(route('warehouse.loadout.save', $order), [
            'item_status' => [(string) $item->id => 'not_available'],
        ])->assertSuccessful();
        $this->assertEquals(0.0, $this->netLedgerDeduction($order));
        $this->assertEquals(0.0, (float) $item->fresh()->loaded_qty);
    }

    public function test_api_loadout_save_with_not_available_has_full_parity_with_web(): void
    {
        [$order, $item] = $this->createOrderWithBunchItem(10.0, 0.2);

        // 1. Initial save via API: 10 bunches (2.0 kg)
        $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'item_unit_qtys' => [(string) $item->id => '10'],
        ])->assertSuccessful();

        $this->assertEquals(2.0, $this->netLedgerDeduction($order));

        // 2. Mark not available via API
        $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'item_status' => [(string) $item->id => 'not_available'],
        ])->assertSuccessful();

        // Must reverse ledger deduction to 0.0 kg!
        $this->assertEquals(0.0, $this->netLedgerDeduction($order));
        $this->assertEquals(0.0, (float) $item->fresh()->loaded_qty);
        $this->assertEquals('not_available', $item->fresh()->sorting_status);
    }

    public function test_completion_reverses_orphaned_stock_for_unavailable_item(): void
    {
        [$order, $item] = $this->createOrderWithBunchItem(10.0, 0.2);

        // Simulate an item that had 2.0 kg deducted earlier, but worker marked it not_available
        // and loaded_qty is 0.0
        $this->postJson(route('warehouse.loadout.save', $order), [
            'item_unit_qtys' => [(string) $item->id => '10'],
        ])->assertSuccessful();
        $this->assertEquals(2.0, $this->netLedgerDeduction($order));

        // Manually simulate state where item is marked not_available in DB but ledger wasn't cleared
        $item->update([
            'loaded_qty' => 0.0,
            'sorting_status' => 'not_available',
            'loadout_discrepancy_type' => 'not_available',
        ]);

        // Execute completion service
        $completionService = app(WarehouseLoadoutCompletionService::class);
        $completionService->complete(
            $this->operator,
            $this->warehouse,
            $order,
            collect([$item->fresh()]),
        );

        // Completion must reverse any orphaned stock!
        $this->assertEquals(0.0, $this->netLedgerDeduction($order));
    }

    public function test_fractional_precision_and_three_decimals(): void
    {
        // 7 bunches @ 0.333 kg/bunch = 2.331 kg
        [$order, $item] = $this->createOrderWithBunchItem(7.0, 0.333);

        $this->postJson(route('warehouse.loadout.save', $order), [
            'item_unit_qtys' => [(string) $item->id => '7'],
        ])->assertSuccessful();

        // Stock movement ledger records 3-decimal precision (2.331 kg)
        $this->assertEquals(2.331, $this->netLedgerDeduction($order));
        // loaded_qty in shop_order_items is stored as decimal(10, 2)
        $this->assertEquals(2.33, (float) $item->fresh()->loaded_qty);

        // Mark unavailable
        $this->postJson(route('warehouse.loadout.save', $order), [
            'item_status' => [(string) $item->id => 'not_available'],
        ])->assertSuccessful();

        $this->assertEquals(0.0, $this->netLedgerDeduction($order));
        $this->assertEquals(0.0, (float) $item->fresh()->loaded_qty);
    }
}
