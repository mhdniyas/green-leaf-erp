<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoadoutPriceMatrixSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Shop $shop;

    private ShopPriceGroup $priceGroup;

    private Product $onion;

    private Product $potato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('warehouse_receiver');
        $this->user->givePermissionTo([
            'warehouse.loadout.all',
        ]);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Vegetable Warehouse', 'code' => 'WH1']);
        $this->user->warehouses()->attach($this->warehouse->id);
        $this->actingAs($this->user);
        $this->withHeader('X-Client-Supports-Multi-Grade', 'true');

        // Shop is assigned Price Group B (as reported in Hotel orders)
        $this->priceGroup = ShopPriceGroup::factory()->create([
            'name' => 'B',
            'is_active' => true,
        ]);
        $this->shop = Shop::factory()->create([
            'name' => 'TEST 1 HOTEL',
            'shop_price_group_id' => $this->priceGroup->id,
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->onion = Product::factory()->create([
            'name' => 'Onion',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'sku' => 'VEG-ONI-01',
        ]);
        $this->potato = Product::factory()->create([
            'name' => 'Potato Agra',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'sku' => 'VEG-POT-01',
        ]);
    }

    /**
     * Scenario 1: Correct price matrix -> loadout save, sync and invoice succeed.
     */
    public function test_scenario_1_correct_price_matrix_loadout_sync_and_bill_succeed(): void
    {
        $businessDate = '2026-10-28';

        // Set approved price matrix for Grade B on Onion & Potato Agra for Price Group B
        DailyPriceApproval::create([
            'product_id' => $this->onion->id,
            'business_date' => $businessDate,
            'grade' => 'B',
            'purchase_price' => 20.0,
            'price_unit' => 'kg',
            'price_a' => 25.0,
            'price_b' => 28.0, // Group B price
            'price_c' => 30.0,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->user->id,
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->potato->id,
            'business_date' => $businessDate,
            'grade' => 'B',
            'purchase_price' => 18.0,
            'price_unit' => 'kg',
            'price_a' => 22.0,
            'price_b' => 24.0, // Group B price
            'price_c' => 26.0,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->user->id,
        ]);

        // Stock available in warehouse
        $this->createStock($this->onion, 50.0, 'B', $businessDate);
        $this->createStock($this->potato, 50.0, 'B', $businessDate);

        // Shop order for Grade B
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $itemOnion = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->onion->id,
            'product_grade' => 'B',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        $itemPotato = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->potato->id,
            'product_grade' => 'B',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'loaded_qty' => 0.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        // Save loadout via API
        $response = $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'items' => [
                "{$this->onion->id}|B" => 10.0,
                "{$this->potato->id}|B" => 15.0,
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'loadout_saved' => true,
            'invoice_synced' => true,
        ]);

        // Verify order status updated to ready_for_dispatch
        $this->assertSame('ready_for_dispatch', $order->fresh()->delivery_status);

        // Verify invoice was generated with correct price calculations
        $this->assertNotNull($order->fresh()->invoice);
        $invoice = $order->fresh()->invoice;
        $this->assertSame(2, $invoice->items()->count());

        $onionInvoiceItem = $invoice->items()->where('product_id', $this->onion->id)->first();
        $this->assertNotNull($onionInvoiceItem);
        $this->assertEquals(28.0, (float) $onionInvoiceItem->unit_price);
        $this->assertEquals(280.0, (float) $onionInvoiceItem->line_subtotal);

        $potatoInvoiceItem = $invoice->items()->where('product_id', $this->potato->id)->first();
        $this->assertNotNull($potatoInvoiceItem);
        $this->assertEquals(24.0, (float) $potatoInvoiceItem->unit_price);
        $this->assertEquals(360.0, (float) $potatoInvoiceItem->line_subtotal);

        $this->assertEquals(640.0, (float) $invoice->subtotal);
    }

    /**
     * Scenario 2: Wrong/missing price -> accurate date/grade diagnostics returned,
     * loadout quantities saved independently, then successful recovery after price correction.
     */
    public function test_scenario_2_missing_price_accurate_diagnostics_and_recovery(): void
    {
        $businessDate = '2026-10-28';

        // Stock available in warehouse
        $this->createStock($this->onion, 50.0, 'B', $businessDate);

        // Shop order for Grade B Onion, but NO daily price exists for Grade B on 2026-10-28
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $itemOnion = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->onion->id,
            'product_grade' => 'B',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        // Save loadout with missing price matrix
        $response = $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'items' => [
                "{$this->onion->id}|B" => 10.0,
            ],
        ]);

        // CRITICAL CHECK: Physical loadout quantities ARE saved (not rolled back!)
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'loadout_saved' => true,
            'invoice_synced' => false,
        ]);

        $this->assertEquals(10.0, (float) $itemOnion->fresh()->loaded_qty);
        $this->assertSame('loaded', $itemOnion->fresh()->sorting_status);

        // Check pricing diagnostics in response
        $data = $response->json();
        $this->assertTrue($data['pricing_diagnostics']['has_unpriced']);
        $this->assertSame($businessDate, $data['pricing_diagnostics']['order_business_date']);
        $this->assertSame($businessDate, $data['pricing_diagnostics']['price_matrix_date']);
        $this->assertSame('B', $data['pricing_diagnostics']['shop_price_group']);
        $this->assertContains('Onion (Grade B)', $data['pricing_diagnostics']['unpriced_product_names']);

        // Check detailed reason
        $itemDiagnostic = $data['pricing_diagnostics']['unpriced_items'][0];
        $this->assertSame('Onion', $itemDiagnostic['product_name']);
        $this->assertSame('B', $itemDiagnostic['grade']);
        $this->assertSame('B', $itemDiagnostic['price_group']);
        $this->assertStringContainsString('Approved daily price is missing for Onion (Grade B)', $itemDiagnostic['reason']);

        // Verify GET show endpoint also reflects the accurate diagnostics and dates
        $showResponse = $this->getJson("/api/v1/warehouse/loadout/{$order->id}");
        $showResponse->assertOk();
        $showResponse->assertJsonPath('order.business_date', $businessDate);
        $showResponse->assertJsonPath('order.price_matrix_date', $businessDate);
        $showResponse->assertJsonPath('pricing_diagnostics.has_unpriced', true);

        $etagBefore = $showResponse->headers->get('ETag');
        $this->assertNotEmpty($etagBefore);

        // Now admin corrects the price matrix by approving Grade B price
        DailyPriceApproval::create([
            'product_id' => $this->onion->id,
            'business_date' => $businessDate,
            'grade' => 'B',
            'purchase_price' => 20.0,
            'price_unit' => 'kg',
            'price_a' => 25.0,
            'price_b' => 28.0,
            'price_c' => 30.0,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->user->id,
        ]);

        // Verify GET request with old ETag receives fresh 200 (NOT stale 304!) because ETag invalidated
        $refreshedResponse = $this->getJson("/api/v1/warehouse/loadout/{$order->id}", [
            'If-None-Match' => $etagBefore,
        ]);
        $refreshedResponse->assertStatus(200);
        $this->assertNotEquals($etagBefore, $refreshedResponse->headers->get('ETag'));
        $refreshedResponse->assertJsonPath('pricing_diagnostics.has_unpriced', false);
        $this->assertEmpty($refreshedResponse->json('pricing_diagnostics.unpriced_product_names'));

        // Re-saving / syncing invoice now succeeds completely
        $syncResponse = $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'items' => [
                "{$this->onion->id}|B" => 10.0,
            ],
        ]);

        $syncResponse->assertOk();
        $syncResponse->assertJson([
            'success' => true,
            'loadout_saved' => true,
            'invoice_synced' => true,
        ]);

        $this->assertNotNull($order->fresh()->invoice);
        $this->assertSame(280.0, (float) $order->fresh()->invoice->subtotal);
    }

    /**
     * Scenario 3: Retry/refresh -> no duplicate loadout stock deduction and no stale sync state.
     */
    public function test_scenario_3_retry_and_refresh_no_duplicate_stock_deduction(): void
    {
        $businessDate = '2026-10-28';

        DailyPriceApproval::create([
            'product_id' => $this->onion->id,
            'business_date' => $businessDate,
            'grade' => 'B',
            'purchase_price' => 20.0,
            'price_unit' => 'kg',
            'price_a' => 25.0,
            'price_b' => 28.0,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->user->id,
        ]);

        $this->createStock($this->onion, 50.0, 'B', $businessDate);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->onion->id,
            'product_grade' => 'B',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        // First save: 10 kg
        $res1 = $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'items' => ["{$this->onion->id}|B" => 10.0],
        ]);
        $res1->assertOk();

        $outMovements1 = StockMovement::where('product_id', $this->onion->id)
            ->where('type', StockMovementType::Out)
            ->get();
        $this->assertCount(1, $outMovements1);
        $this->assertEquals(10.0, (float) $outMovements1->sum('quantity'));

        // Second save (simulating Autosave buffer flush / Sync Now retry with same quantities)
        $res2 = $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'items' => ["{$this->onion->id}|B" => 10.0],
        ]);
        $res2->assertOk();

        // Third save (simulating another retry)
        $res3 = $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'items' => ["{$this->onion->id}|B" => 10.0],
        ]);
        $res3->assertOk();

        // CRITICAL CHECK: Exactly 1 stock deduction of 10 kg, NO duplicates created
        $outMovementsTotal = StockMovement::where('product_id', $this->onion->id)
            ->where('type', StockMovementType::Out)
            ->get();
        $this->assertCount(1, $outMovementsTotal);
        $this->assertEquals(10.0, (float) $outMovementsTotal->sum('quantity'));

        // No reversal movements created
        $this->assertSame(0, StockMovement::where('product_id', $this->onion->id)->where('type', StockMovementType::SaleReversal)->count());
    }

    private function createStock(Product $product, float $quantity, string $grade, string $date): StockBatch
    {
        $batch = StockBatch::factory()->create([
            'reference' => 'BATCH-TEST-'.uniqid(),
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => $quantity,
            'received_at' => $date,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'purchase_grade' => $grade,
            'grading_mode' => 'fixed_purchase_grade',
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'grade' => $grade,
            'type' => StockMovementType::In,
            'quantity' => $quantity,
            'cost_per_unit' => 20.0,
            'movement_date' => $date,
        ]);

        return $batch;
    }
}
