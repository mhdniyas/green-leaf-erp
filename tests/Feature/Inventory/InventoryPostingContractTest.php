<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryPostingContractTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private Warehouse $warehouse;

    private Product $product;

    private StockMovementRepository $movementRepo;

    private StockLedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create([
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'base_price' => 50.0,
        ]);

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->warehouses()->attach($this->warehouse);
        $this->receiver->givePermissionTo([
            'inventory.stock.view',
            'inventory.product.view',
            'purchasing.order.view',
            'purchasing.grn.view',
            'purchasing.grn.create',
            'warehouse.loadout.all',
        ]);

        Sanctum::actingAs($this->receiver);
        $this->movementRepo = app(StockMovementRepository::class);
        $this->ledgerService = app(StockLedgerService::class);
    }

    public function test_pending_normal_receipt_does_not_increase_available_inventory(): void
    {
        $order = PurchaseOrder::factory()->create(['status' => 'received']);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
        ]);
        $receipt = GoodsReceived::factory()->create([
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'received_at' => now()->toDateString(),
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 100,
        ]);

        // StockBatch created with warehouse_receive_pending = true
        $batch = StockBatch::factory()->create([
            'goods_received_id' => $receipt->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
            'received_at' => now()->toDateString(),
        ]);

        // 1. Current stock calculation must be 0
        $currentStock = $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id);
        $this->assertSame(0.0, $currentStock);

        // 2. Ledger service available stock must be 0
        $availableStock = $this->ledgerService->availableStockForProduct($this->product->id, $this->warehouse->id);
        $this->assertSame(0.0, $availableStock);

        // 3. Batch computed remaining qty must be 0
        $this->assertSame(0.0, $batch->remaining_qty);
        $this->assertSame(0.0, $batch->allocated_qty);
        $this->assertFalse($batch->canBeSorted());

        // 4. API inventory endpoints must return 0
        $this->getJson('/api/v1/inventory/stock?warehouse_id='.$this->warehouse->id)
            ->assertOk()
            ->assertJsonMissing(['product_id' => $this->product->id]);
    }

    public function test_confirmed_normal_receipt_increases_available_inventory_exactly_once(): void
    {
        $batch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 75.5,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
            'received_at' => now()->toDateString(),
        ]);

        $currentStock = $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id);
        $this->assertSame(75.5, $currentStock);

        $availableStock = $this->ledgerService->availableStockForProduct($this->product->id, $this->warehouse->id);
        $this->assertSame(75.5, $availableStock);

        $this->assertSame(75.5, $batch->remaining_qty);
        $this->assertTrue($batch->canBeSorted());

        // API reflects confirmed stock
        $response = $this->getJson('/api/v1/inventory/stock?warehouse_id='.$this->warehouse->id)->assertOk();
        $this->assertEquals(75.5, (float) $response->json('data.0.current_stock'));
    }

    public function test_pending_to_confirmed_transition_makes_stock_available_exactly_once(): void
    {
        $batch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
            'received_at' => now()->toDateString(),
        ]);

        // Before confirmation
        $this->assertSame(0.0, $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id));

        // Warehouse confirms
        $batch->update([
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
        ]);

        // After confirmation
        $this->assertSame(50.0, $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id));
        $this->assertSame(50.0, $this->ledgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
    }

    public function test_multiple_reads_and_page_refreshes_produce_no_duplicate_stock(): void
    {
        StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 60.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
            'received_at' => now()->toDateString(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(60.0, $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id));
            $this->assertSame(60.0, $this->ledgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
            $this->getJson('/api/v1/inventory/stock?warehouse_id='.$this->warehouse->id)->assertOk();
        }

        $this->assertSame(1, StockBatch::count());
    }

    public function test_advance_receive_unconfirmed_is_not_available_and_confirmed_is_available(): void
    {
        $advanceGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'bill_status' => 'bill_pending',
            'status' => 'approved',
            'received_at' => now()->toDateString(),
        ]);

        $advanceBatch = StockBatch::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 80.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
            'received_at' => now()->toDateString(),
        ]);

        // Unconfirmed Advance: 0 available stock
        $this->assertSame(0.0, $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id));

        // Warehouse operator confirms Advance physical receipt
        $advanceBatch->update([
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
        ]);

        // Confirmed Advance: available exactly once
        $this->assertSame(80.0, $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id));
        $this->assertSame(80.0, $this->ledgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
    }

    public function test_direct_purchase_receipt_is_available_immediately(): void
    {
        $directBatch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 45.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
            'received_at' => now()->toDateString(),
            'notes' => 'Direct purchase received from admin order',
        ]);

        $this->assertSame(45.0, $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id));
        $this->assertSame(45.0, $this->ledgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
    }

    public function test_addon_purchase_shares_identical_confirmation_eligibility(): void
    {
        $addonGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'is_extra' => true,
            'status' => 'approved',
            'received_at' => now()->toDateString(),
        ]);

        $addonBatch = StockBatch::factory()->create([
            'goods_received_id' => $addonGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 30.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
            'received_at' => now()->toDateString(),
        ]);

        $this->assertSame(0.0, $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id));

        $addonBatch->update([
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
        ]);

        $this->assertSame(30.0, $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id));
    }

    public function test_grade_b_receipt_creates_movement_and_is_available_after_confirmation(): void
    {
        $gradeBGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_grade' => 'B',
            'status' => 'approved',
            'received_at' => now()->toDateString(),
        ]);

        $batch = StockBatch::factory()->create([
            'goods_received_id' => $gradeBGrn->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_grade' => 'B',
            'grading_mode' => 'fixed_purchase_grade',
            'total_kg' => 40.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
            'sorted_at' => now(),
            'received_at' => now()->toDateString(),
        ]);

        StockMovement::factory()->create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'in',
            'grade' => 'B',
            'quantity' => 40.0,
        ]);

        $currentStock = $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id);
        $this->assertSame(40.0, $currentStock);
        $this->assertSame(40.0, $this->ledgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
    }

    public function test_loadout_dispatch_and_stock_consumption_exclude_unconfirmed_batches(): void
    {
        // Batch 1: Unconfirmed (50kg)
        StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
            'received_at' => now()->toDateString(),
        ]);

        // Batch 2: Confirmed (20kg)
        $confirmedBatch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
            'received_at' => now()->toDateString(),
        ]);

        $shop = Shop::factory()->create();
        $shopOrder = ShopOrder::factory()->create(['shop_id' => $shop->id, 'business_date' => now()->toDateString()]);
        $shopOrderItem = ShopOrderItem::create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 15.0,
            'requested_unit_conversion_to_base' => 1,
            'locked_selling_price' => 10,
        ]);

        // Consume 15kg from ledger
        $consumed = $this->ledgerService->consumeStockForProductAllowingNegative(
            $this->product->id,
            15.0,
            $this->receiver->id,
            StockMovementType::Out,
            'Loadout test',
            $shopOrderItem->id,
            $this->warehouse->id,
        );

        $this->assertSame(15.0, $consumed);

        // Verify consumption came from the confirmed batch, NOT the pending batch
        $movement = StockMovement::where('product_id', $this->product->id)->where('type', 'out')->first();
        $this->assertNotNull($movement);
        $this->assertSame($confirmedBatch->id, $movement->batch_id);

        // Remaining available stock should be 5kg (20 - 15), NOT 55kg
        $this->assertSame(5.0, $this->movementRepo->currentStockForProduct($this->product->id, $this->warehouse->id));
        $this->assertSame(5.0, $this->ledgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
    }
}
