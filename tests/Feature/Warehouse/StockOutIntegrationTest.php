<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\DTOs\Inventory\WastageEntryData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockLedgerService;
use App\Services\Inventory\WastageService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Warehouse\WarehouseLoadoutCompletionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockOutIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $admin;

    private Warehouse $warehouse;

    private Warehouse $otherWarehouse;

    private Supplier $supplier;

    private Category $category;

    private Product $product;

    private ShopPriceGroup $priceGroup;

    private Shop $shop;

    private string $businessDate;

    private StockLedgerService $stockLedgerService;

    private StockMovementRepository $movementRepository;

    private WarehouseLoadoutCompletionService $completionService;

    private WastageService $wastageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->receiver = User::factory()->create(['name' => 'Warehouse Receiver']);
        $this->receiver->assignRole('warehouse_receiver');

        $this->admin = User::factory()->create(['name' => 'System Admin']);
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MW-01',
            'is_active' => true,
        ]);

        $this->otherWarehouse = Warehouse::create([
            'name' => 'Remote Depot',
            'code' => 'RD-02',
            'is_active' => true,
        ]);

        $this->receiver->warehouses()->attach([$this->warehouse->id, $this->otherWarehouse->id]);
        $this->admin->warehouses()->attach([$this->warehouse->id, $this->otherWarehouse->id]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Agri Farms',
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'code' => 'VEG',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Green Capsicum',
            'sku' => 'GCAP-01',
            'unit' => 'KG',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);

        $this->priceGroup = ShopPriceGroup::create([
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->shop = Shop::create([
            'name' => 'City Fresh Supermarket',
            'code' => 'CFS-01',
            'shop_price_group_id' => $this->priceGroup->id,
            'is_active' => true,
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $this->businessDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        BusinessSetting::updateOrCreate(
            ['key' => 'allow_historical_invoice_repricing'],
            ['value' => '1']
        );

        foreach (['A' => 60.0, 'B' => 50.0, 'C' => 40.0] as $g => $p) {
            DailyPriceApproval::query()->create([
                'product_id' => $this->product->id,
                'business_date' => $this->businessDate,
                'grade' => $g,
                'purchase_price' => 30.0,
                'price_unit' => 'KG',
                'price_a' => $p,
                'price_b' => $p,
                'price_c' => $p,
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }

        $this->stockLedgerService = app(StockLedgerService::class);
        $this->movementRepository = app(StockMovementRepository::class);
        $this->completionService = app(WarehouseLoadoutCompletionService::class);
        $this->wastageService = app(WastageService::class);
    }

    /**
     * Test 1: 100 KG U → Grade A loadout 30 KG → edit to 20 KG. Verify U=70, A=10, total=80.
     */
    public function test_scenario_1_automatic_grade_segregation_and_loadout_reduction_restores_segregated_grade(): void
    {
        // 1. Initial state: 100 KG U in warehouse
        $this->createMixedBatch(100.0);

        $initialBreakdown = $this->stockLedgerService->availableLoadoutStockBreakdown($this->product->id, $this->warehouse->id, ProductGrade::GradeA);
        $this->assertSame(0.0, $initialBreakdown['grade_stock']);
        $this->assertSame(100.0, $initialBreakdown['mixed_stock']);
        $this->assertSame(100.0, $initialBreakdown['available_stock']);

        // 2. Create loadout order for 30 KG Grade A
        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'A', 'qty' => 30.0, 'selling_price' => 60.0],
        ]);
        $item = $order->items->first();

        // 3. Save loadout of 30 KG Grade A (Flutter API)
        $res30 = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => ["{$this->product->id}|A" => 30.0],
            ], ['X-Client-Supports-Multi-Grade' => 'true']);

        $res30->assertOk();
        $res30->assertJsonPath('success', true);

        // Verify net deducted: 30 KG
        $this->assertSame(30.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($item->id));

        // Verify child batch was created for segregation
        $childBatches = StockBatch::where('product_id', $this->product->id)
            ->where('purchase_grade', 'A')
            ->where('status', BatchStatus::Sorted)
            ->get();
        $this->assertCount(1, $childBatches);
        $this->assertSame(30.0, (float) $childBatches->first()->total_kg);

        // Verify stock after 30 KG loadout: U=70, A=0, Total=70
        $stockAfter30A = $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA);
        $stockAfter30U = $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted);
        $this->assertSame(0.0, $stockAfter30A);
        $this->assertSame(70.0, $stockAfter30U);
        $this->assertSame(70.0, round($stockAfter30A + $stockAfter30U, 3));

        // 4. Edit loadout to 20 KG
        $res20 = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => ["{$this->product->id}|A" => 20.0],
            ], ['X-Client-Supports-Multi-Grade' => 'true']);

        $res20->assertOk();
        $res20->assertJsonPath('success', true);

        // Verify net deducted is now 20 KG
        $this->assertSame(20.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($item->id));

        // 5. Verify final stock balance: U=70, A=10, Total=80!
        $finalStockA = $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA);
        $finalStockU = $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted);
        $totalStock = round($finalStockA + $finalStockU, 3);

        $this->assertSame(70.0, $finalStockU, 'Unsorted stock must remain 70 KG');
        $this->assertSame(10.0, $finalStockA, 'Grade A stock must be 10 KG from restored segregation');
        $this->assertSame(80.0, $totalStock, 'Total physical stock on-hand must be 80 KG');

        // Verify SaleReversal movement was recorded on the Grade A child batch
        $reversalMovements = StockMovement::where('type', StockMovementType::SaleReversal->value)
            ->where('shop_order_item_id', $item->id)
            ->get();
        $this->assertCount(1, $reversalMovements);
        $this->assertSame(10.0, (float) $reversalMovements->first()->quantity);
        $this->assertSame('A', $reversalMovements->first()->grade->value);
        $this->assertSame($childBatches->first()->id, $reversalMovements->first()->batch_id);
    }

    /**
     * Test 2: Flutter autosave + web completion + repeated/concurrent requests. Verify exactly one net stock deduction.
     */
    public function test_scenario_2_flutter_autosave_web_completion_and_repeated_requests_deduct_net_stock_exactly_once(): void
    {
        // 1. Initial state: 100 KG Grade A stock in warehouse
        $this->createSortedBatch('A', 100.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'A', 'qty' => 25.0, 'selling_price' => 60.0],
        ]);
        $item = $order->items->first();

        // 2. Flutter autosave 1: 25 KG
        $res1 = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => ["{$this->product->id}|A" => 25.0],
            ], ['X-Client-Supports-Multi-Grade' => 'true']);
        $res1->assertOk();

        $this->assertSame(25.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($item->id));
        $this->assertSame(75.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));

        // 3. Repeated Flutter autosave (retry / identical payload): 25 KG
        $res2 = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => ["{$this->product->id}|A" => 25.0],
            ], ['X-Client-Supports-Multi-Grade' => 'true']);
        $res2->assertOk();

        // Net deducted must STILL be 25 KG (no second deduction!)
        $this->assertSame(25.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($item->id));
        $this->assertSame(75.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));

        // 4. Web completion runs
        $item->refresh();
        $this->assertSame(25.0, (float) $item->loaded_qty);
        $order->update(['delivery_status' => 'pending_delivery']);

        $this->completionService->complete($this->receiver, $this->warehouse, $order);

        // Net deducted must STILL be 25 KG (web completion did not double deduct!)
        $this->assertSame(25.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($item->id));
        $this->assertSame(75.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));

        // Count total Out movements for this item: must be exactly 1 with quantity 25.0
        $outMovements = StockMovement::where('shop_order_item_id', $item->id)
            ->where('type', StockMovementType::Out->value)
            ->get();
        $this->assertCount(1, $outMovements);
        $this->assertSame(25.0, (float) $outMovements->first()->quantity);
    }

    /**
     * Test 3: Wastage once, insufficient inventory rejection, and correct grade/warehouse balance.
     */
    public function test_scenario_3_single_wastage_insufficient_stock_rejection_and_warehouse_isolation(): void
    {
        // ---------------------------------------------------------------------
        // Part A: Single Wastage Deduction on Pending Batch
        // ---------------------------------------------------------------------
        $pendingBatch = StockBatch::create([
            'reference' => 'BATCH-TEST-PENDING-'.uniqid(),
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 30.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'commercial_grading',
            'created_by' => $this->receiver->id,
        ]);

        // Record 10 KG wastage
        $this->wastageService->record(new WastageEntryData(
            productId: $this->product->id,
            batchId: $pendingBatch->id,
            grade: 'U',
            quantity: 10.0,
            costPerKg: 30.0,
            reason: WastageReason::Rotten,
            wastageDate: now()->toDateString(),
            notes: 'Test wastage single deduction',
        ), $this->receiver->id);

        // Verify remaining qty on batch is 40 KG
        $this->assertSame(40.0, (float) $pendingBatch->fresh()->remaining_qty);
        $this->assertSame(40.0, (float) $pendingBatch->fresh()->available_mixed_qty);

        // Verify StockMovementRepository reports exactly 40.0 KG (NOT 30 KG!)
        $stockRows = $this->movementRepository->currentStockByProductAndGrade(
            productId: $this->product->id,
            warehouseId: $this->warehouse->id,
        );
        $uStockRow = $stockRows->firstWhere('grade', 'U');
        $this->assertNotNull($uStockRow);
        $this->assertSame(40.0, (float) data_get($uStockRow, 'current_stock'), 'Single wastage event must deduct stock exactly once');

        // ---------------------------------------------------------------------
        // Part B: Insufficient Inventory Rejection (Fix 4: No Negative Stock)
        // ---------------------------------------------------------------------
        // Promote remaining 40 KG to sorted U stock
        $pendingBatch->update(['status' => BatchStatus::Sorted, 'total_kg' => 40.0]);
        StockMovement::create([
            'batch_id' => $pendingBatch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'U',
            'type' => StockMovementType::In,
            'quantity' => 40.0,
            'cost_per_unit' => 30.0,
        ]);

        // Total available is 40 KG (0 A + 40 U). Attempt loadout of 50 KG Grade A.
        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'A', 'qty' => 50.0, 'selling_price' => 60.0],
        ]);
        $item = $order->items->first();

        $resFailed = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => ["{$this->product->id}|A" => 50.0],
            ], ['X-Client-Supports-Multi-Grade' => 'true']);

        $resFailed->assertStatus(422);
        $resFailed->assertJsonValidationErrors(['quantity']);
        $errorMsg = $resFailed->json('errors.quantity.0');
        $this->assertStringContainsString('Insufficient stock', $errorMsg);
        $this->assertStringContainsString('50', $errorMsg);
        $this->assertStringContainsString('40', $errorMsg);

        // Verify zero stock movements or child batches were committed
        $this->assertSame(0.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($item->id));
        $this->assertDatabaseMissing('stock_movements', [
            'notes' => 'Loadout dispatch to delivery — Order: '.$order->order_number,
        ]);
        $this->assertDatabaseMissing('stock_batches', [
            'reference' => 'NEG-%',
        ]);
        $this->assertSame(40.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        // ---------------------------------------------------------------------
        // Part C: Warehouse Isolation
        // ---------------------------------------------------------------------
        // Other warehouse has 0 KG stock. Loadout scoped to other warehouse must fail.
        $otherShop = Shop::create([
            'name' => 'Secondary Shop',
            'code' => 'SEC-02',
            'shop_price_group_id' => $this->priceGroup->id,
            'is_active' => true,
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $otherOrder = ShopOrder::factory()->approved()->create([
            'shop_id' => $otherShop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);
        $otherItem = ShopOrderItem::create([
            'shop_order_id' => $otherOrder->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'locked_selling_price' => 60.0,
            'line_total' => 600.0,
        ]);

        $this->expectException(ValidationException::class);
        $this->stockLedgerService->consumeStockForProductAllowingNegative(
            productId: $this->product->id,
            quantity: 10.0,
            userId: $this->receiver->id,
            movementType: StockMovementType::Out,
            notes: 'Test other warehouse deduction',
            shopOrderItemId: $otherItem->id,
            warehouseId: $this->otherWarehouse->id,
            grade: ProductGrade::GradeA,
        );
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    private function createSortedBatch(string $grade, float $quantity, ?Warehouse $wh = null): StockBatch
    {
        $warehouse = $wh ?? $this->warehouse;

        $batch = StockBatch::create([
            'reference' => 'BATCH-TEST-'.strtoupper($grade).'-'.uniqid(),
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => $quantity,
            'cost_per_kg' => 40.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => $grade,
            'grading_mode' => 'fixed_purchase_grade',
            'created_by' => $this->receiver->id,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => $grade,
            'type' => StockMovementType::In,
            'quantity' => $quantity,
            'cost_per_unit' => 40.0,
        ]);

        return $batch;
    }

    private function createMixedBatch(float $quantity, ?Warehouse $wh = null): StockBatch
    {
        $warehouse = $wh ?? $this->warehouse;

        $batch = StockBatch::create([
            'reference' => 'BATCH-TEST-U-'.uniqid(),
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => $quantity,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'fixed_purchase_grade',
            'created_by' => $this->receiver->id,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'U',
            'type' => StockMovementType::In,
            'quantity' => $quantity,
            'cost_per_unit' => 20.0,
        ]);

        return $batch;
    }

    private function createOrderWithItems(array $itemDefs): ShopOrder
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        foreach ($itemDefs as $def) {
            $product = $def['product'];
            $grade = $def['grade'];
            $qty = (float) $def['qty'];
            $price = (float) ($def['selling_price'] ?? 40.0);

            ShopOrderItem::create([
                'shop_order_id' => $order->id,
                'product_id' => $product->id,
                'product_grade' => $grade,
                'requested_qty' => $qty,
                'approved_qty' => $qty,
                'loaded_qty' => 0.0,
                'unit' => 'KG',
                'fulfillment_type' => 'warehouse',
                'sorting_status' => 'allocated',
                'locked_selling_price' => $price,
                'line_total' => round($qty * $price, 2),
            ]);
        }

        return $order;
    }
}
