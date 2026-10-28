<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\TransferGradeStockAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\DailyInventoryCloseLine;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
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
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\DamageLossReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinalInventoryWorkflowVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse1;

    private Warehouse $warehouse2;

    private Category $category;

    private Shop $shop;

    private Supplier $supplier;

    private ShopPriceGroup $priceGroup;

    private StockMovementRepository $stockMovementRepository;

    private StockLedgerService $stockLedgerService;

    private TransferGradeStockAction $transferAction;

    private DamageLossReportService $damageLossReportService;

    private StockAdjustmentService $stockAdjustmentService;

    private AdvanceReceiveReconciliationService $advanceReconciliationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
        $this->user->givePermissionTo(['warehouse.loadout.all']);

        $this->warehouse1 = Warehouse::factory()->create(['name' => 'North Hub Warehouse', 'code' => 'NHW']);
        $this->warehouse2 = Warehouse::factory()->create(['name' => 'South Hub Warehouse', 'code' => 'SHW']);
        $this->user->warehouses()->attach([$this->warehouse1->id, $this->warehouse2->id]);
        $this->actingAs($this->user);

        $this->category = Category::factory()->create(['name' => 'Fresh Vegetables']);
        $this->supplier = Supplier::factory()->create(['name' => 'Prime Green Farms']);

        $this->priceGroup = ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);
        $this->shop = Shop::factory()->create([
            'name' => 'Royal Green Hotel',
            'shop_price_group_id' => $this->priceGroup->id,
            'status' => 'active',
        ]);

        $this->stockMovementRepository = app(StockMovementRepository::class);
        $this->stockLedgerService = app(StockLedgerService::class);
        $this->transferAction = app(TransferGradeStockAction::class);
        $this->damageLossReportService = app(DamageLossReportService::class);
        $this->stockAdjustmentService = app(StockAdjustmentService::class);
        $this->advanceReconciliationService = app(AdvanceReceiveReconciliationService::class);

        // Header for multi-grade API loadout
        $this->withHeader('X-Client-Supports-Multi-Grade', 'true');
    }

    /**
     * Test 1 — Complete Warehouse Lifecycle:
     * 1. Receive 100 KG Mixed/U.
     * 2. Receive 20 KG directly as Grade A.
     * 3. Match advance receipt to purchase bill without double stock.
     * 4. Load out 50 KG Grade A through API.
     * 5. Automatically segregate/consume necessary quantity from U.
     * 6. Edit loadout to 40 KG, retry saving.
     * 7. Complete loadout via web controller.
     * 8. Validate invoice pricing and synchronization.
     */
    public function test_1_complete_warehouse_lifecycle(): void
    {
        $businessDate = '2026-10-28';
        Carbon::setTestNow($businessDate.' 10:00:00');

        $cabbage = Product::factory()->create([
            'name' => 'Green Cabbage',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse1->id,
            'unit' => 'kg',
            'carryover_enabled' => true,
        ]);

        // Daily price approval: Grade A price = Rs 30.00
        DailyPriceApproval::create([
            'product_id' => $cabbage->id,
            'business_date' => $businessDate,
            'grade' => 'A',
            'purchase_price' => 20.0,
            'price_unit' => 'kg',
            'price_a' => 30.0,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->user->id,
        ]);

        // 1. Receive 100 KG Mixed/U through warehouse receiving
        $batchMixed = StockBatch::factory()->create([
            'product_id' => $cabbage->id,
            'warehouse_id' => $this->warehouse1->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 18.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => false,
            'received_at' => $businessDate,
        ]);

        // 2. Receive another 20 KG directly as Grade A
        $batchA = StockBatch::factory()->create([
            'product_id' => $cabbage->id,
            'warehouse_id' => $this->warehouse1->id,
            'total_kg' => 20.0,
            'cost_per_kg' => 22.0,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $businessDate,
        ]);

        StockMovement::create([
            'product_id' => $cabbage->id,
            'batch_id' => $batchA->id,
            'warehouse_id' => $this->warehouse1->id,
            'created_by' => $this->user->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 20.0,
            'cost_per_unit' => 22.0,
            'notes' => 'Direct Grade A receiving',
            'created_at' => $businessDate.' 10:30:00',
        ]);

        // Check stock before matching: 20 kg Grade A + 100 kg Mixed = 120 kg total
        $this->assertSame(20.0, $this->stockMovementRepository->stockForProductAndGrade($cabbage->id, ProductGrade::GradeA, $this->warehouse1->id, $businessDate));
        $this->assertSame(100.0, $this->stockMovementRepository->stockForProductAndGrade($cabbage->id, ProductGrade::Unsorted, $this->warehouse1->id, $businessDate));
        $this->assertSame(120.0, $this->stockMovementRepository->currentStockForProduct($cabbage->id, $this->warehouse1->id, $businessDate));

        // 3. Match an advance receipt to a purchase bill without double stock
        // Advance receipt: 25 KG received
        $grn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse1->id,
            'grn_number' => 'GRN-ADV-001',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->user->id,
            'approved_by' => $this->user->id,
            'received_at' => $businessDate,
            'approved_at' => now(),
        ]);

        $grnItem = $grn->items()->create([
            'product_id' => $cabbage->id,
            'received_qty' => 25.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'grade' => 'U',
        ]);

        $batchAdvance = StockBatch::factory()->create([
            'product_id' => $cabbage->id,
            'warehouse_id' => $this->warehouse1->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $grnItem->id,
            'total_kg' => 25.0,
            'cost_per_kg' => 20.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => false,
            'received_at' => $businessDate,
        ]);

        // Stock now: 120 + 25 = 145 kg
        $this->assertSame(145.0, $this->stockMovementRepository->currentStockForProduct($cabbage->id, $this->warehouse1->id, $businessDate));

        // Reconcile/Match advance receipt to purchase bill
        $billPayload = [
            'warehouse_id' => $this->warehouse1->id,
            'receipt_type' => 'bill_received',
            'bill_number' => 'BILL-CAB-001',
            'received_at' => $businessDate,
            'items' => [
                [
                    'product_id' => $cabbage->id,
                    'received_qty' => 25.0,
                    'received_unit' => 'kg',
                    'grade' => 'U',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $grn->id,
                    'advance_goods_received_item_id' => $grnItem->id,
                    'product_id' => $cabbage->id,
                    'matched_qty' => 25.0,
                    'unit' => 'kg',
                ],
            ],
        ];

        $goodsReceivedData = GoodsReceivedData::fromRequest(new Request($billPayload));
        $billGrn = $this->advanceReconciliationService->reconcileAndExecute($goodsReceivedData, $this->user->id);

        $this->assertSame('bill_available', $billGrn->bill_status);

        // Verify stock is STILL 145.0 kg (not double-counted to 170 kg!)
        $this->assertSame(145.0, $this->stockMovementRepository->currentStockForProduct($cabbage->id, $this->warehouse1->id, $businessDate));

        // 4. Load out 50 KG Grade A through Flutter/API
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'delivery_status' => 'pending_delivery',
        ]);

        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $cabbage->id,
            'product_grade' => 'A',
            'requested_qty' => 50.0,
            'approved_qty' => 50.0,
            'loaded_qty' => 0.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        $saveResponse = $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'items' => ["{$cabbage->id}|A" => 50.0],
        ]);

        $saveResponse->assertOk();
        $saveResponse->assertJson(['success' => true, 'loadout_saved' => true, 'invoice_synced' => true]);

        // 5. Verify auto-segregation: 20 KG Grade A consumed, 30 KG consumed from Mixed
        // Grade A remaining = 0 kg
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($cabbage->id, $this->warehouse1->id, ProductGrade::GradeA));
        // Total physical remaining = 145 - 50 = 95 kg!
        $this->assertSame(95.0, $this->stockMovementRepository->currentStockForProduct($cabbage->id, $this->warehouse1->id, $businessDate));

        // 6. Edit loadout to 40 KG, then retry saving
        $editResponse = $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'items' => ["{$cabbage->id}|A" => 40.0],
        ]);

        $editResponse->assertOk();
        // 10 KG restored back to available stock: total physical remaining is now 145 - 40 = 105 kg!
        $this->assertSame(105.0, $this->stockMovementRepository->currentStockForProduct($cabbage->id, $this->warehouse1->id, $businessDate));
        $this->assertSame(40.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($orderItem->id));

        // 7. Complete loadout via the web controller
        $webSaveResponse = $this->post(route('warehouse.loadout.save', $order), [
            'items' => ["{$cabbage->id}|A" => 40.0],
        ]);
        $webSaveResponse->assertRedirect();

        // 8. Validate invoice pricing and synchronization
        $order->refresh();
        $this->assertNotNull($order->invoice);
        $invoice = $order->invoice;
        $this->assertSame(1200.0, (float) $invoice->subtotal); // 40 kg * Rs 30.00
        $this->assertSame(1200.0, (float) $invoice->final_total);
        $this->assertCount(1, $invoice->items);
        $invItem = $invoice->items->first();
        $this->assertEquals(40.0, (float) $invItem->delivered_qty);
        $this->assertEquals(30.0, (float) $invItem->unit_price);
        $this->assertEquals('A', $invItem->product_grade);

        Carbon::setTestNow(); // Reset clock
    }

    /**
     * Test 2 — Daily Clear, Damage & Loss:
     * 1. Begin with known graded quantities (40 KG A, 30 KG B).
     * 2. Move 10 KG to DMG.
     * 3. Verify physical total remains unchanged (70 KG).
     * 4. Dispose 5 KG from DMG.
     * 5. Verify physical total decreases exactly 5 KG (65 KG).
     * 6. Check Damage & Loss Report.
     * 7. Repeat Daily Clear and verify no duplicate deduction.
     * 8. Correct previously submitted wastage downward and check reversal behavior.
     * 9. Verify warehouse-specific Daily Close persistence and unique keys.
     */
    public function test_2_daily_clear_damage_and_loss_workflow(): void
    {
        $date = '2026-10-28';
        Carbon::setTestNow($date.' 18:00:00');

        $tomato = Product::factory()->create([
            'name' => 'Roma Tomato',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse1->id,
            'carryover_enabled' => true,
        ]);

        // 1. Begin with known graded quantities: 40 KG Grade A, 30 KG Grade B. Total = 70 KG
        $batchA = $this->createBatchWithStock($tomato, $this->warehouse1, 40.0, ProductGrade::GradeA, 25.0, $date);
        $batchB = $this->createBatchWithStock($tomato, $this->warehouse1, 30.0, ProductGrade::GradeB, 20.0, $date);

        $this->assertSame(40.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::GradeA, $this->warehouse1->id, $date));
        $this->assertSame(30.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::GradeB, $this->warehouse1->id, $date));
        $this->assertSame(70.0, $this->stockMovementRepository->currentStockForProduct($tomato->id, $this->warehouse1->id, $date));

        // 2. Move 10 KG from Grade A to DMG (quarantine damage)
        $transferRes = $this->transferAction->execute(
            product: $tomato,
            warehouse: $this->warehouse1,
            sourceGrade: ProductGrade::GradeA,
            targetGrade: ProductGrade::Damage,
            quantity: 10.0,
            userId: $this->user->id,
            sourceBatch: $batchA,
            notes: 'Quarantining 10 kg damaged tomatoes'
        );

        $this->assertInstanceOf(StockBatch::class, $transferRes['destination_batch']);
        $this->assertSame('DMG', $transferRes['destination_batch']->purchase_grade);

        // 3. Verify physical total remains unchanged
        $this->assertSame(30.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::GradeA, $this->warehouse1->id, $date));
        $this->assertSame(10.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::Damage, $this->warehouse1->id, $date));
        $this->assertSame(30.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::GradeB, $this->warehouse1->id, $date));
        // Total physical inventory is still exactly 70 KG!
        $this->assertSame(70.0, $this->stockMovementRepository->currentStockForProduct($tomato->id, $this->warehouse1->id, $date));

        // 4. Dispose 5 KG from DMG via Daily Close: Closing = 10, Wastage = 5, Carryover = 5
        $this->post(route('inventory.daily-close.store'), [
            'date' => $date,
            'lines' => [
                [
                    'product_id' => $tomato->id,
                    'warehouse_id' => $this->warehouse1->id,
                    'grade' => 'DMG',
                    'closing_qty' => 10.0,
                    'wastage_qty' => 5.0,
                    'carryover_qty' => 5.0,
                    'carryover_enabled' => 1,
                ],
            ],
        ])->assertRedirect();

        // 5. Verify physical total decreases exactly 5 KG
        $this->assertSame(5.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::Damage, $this->warehouse1->id, $date));
        // Total is now 30 + 30 + 5 = 65 KG!
        $this->assertSame(65.0, $this->stockMovementRepository->currentStockForProduct($tomato->id, $this->warehouse1->id, $date));

        // 6. Check Damage & Loss Report
        $reportData = $this->damageLossReportService->getReportData([
            'product_id' => $tomato->id,
            'warehouse_id' => $this->warehouse1->id,
            'start_date' => $date,
            'end_date' => $date,
        ]);
        $this->assertSame(5.0, $reportData['summary']->total_damaged_kg);
        $this->assertSame(125.0, $reportData['summary']->total_damaged_cost); // 5 kg * Rs 25/kg

        // 7. Repeat Daily Clear and verify no duplicate deduction
        $this->post(route('inventory.daily-close.store'), [
            'date' => $date,
            'lines' => [
                [
                    'product_id' => $tomato->id,
                    'warehouse_id' => $this->warehouse1->id,
                    'grade' => 'DMG',
                    'closing_qty' => 10.0,
                    'wastage_qty' => 5.0,
                    'carryover_qty' => 5.0,
                    'carryover_enabled' => 1,
                ],
            ],
        ])->assertRedirect();

        // Stock must still be 5 KG (no duplicate deduction!)
        $this->assertSame(5.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::Damage, $this->warehouse1->id, $date));
        $this->assertSame(65.0, $this->stockMovementRepository->currentStockForProduct($tomato->id, $this->warehouse1->id, $date));

        // 8. Correct previously submitted wastage downward (from 5 KG to 2 KG) and check reversal behavior
        $this->post(route('inventory.daily-close.store'), [
            'date' => $date,
            'lines' => [
                [
                    'product_id' => $tomato->id,
                    'warehouse_id' => $this->warehouse1->id,
                    'grade' => 'DMG',
                    'closing_qty' => 10.0,
                    'wastage_qty' => 2.0, // Reduced from 5 to 2 kg (reversing 3 kg)
                    'carryover_qty' => 8.0,
                    'carryover_enabled' => 1,
                ],
            ],
        ])->assertRedirect();

        // Verify: 3 KG restored to DMG stock (5 + 3 = 8 KG)
        $this->assertSame(8.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::Damage, $this->warehouse1->id, $date));
        // Total physical inventory is now 30 + 30 + 8 = 68 KG!
        $this->assertSame(68.0, $this->stockMovementRepository->currentStockForProduct($tomato->id, $this->warehouse1->id, $date));

        // Verify Damage & Loss Report now reports 2 KG (Rs 50.00 loss)
        $reportDataReversed = $this->damageLossReportService->getReportData([
            'product_id' => $tomato->id,
            'warehouse_id' => $this->warehouse1->id,
            'start_date' => $date,
            'end_date' => $date,
        ]);
        $this->assertSame(2.0, $reportDataReversed['summary']->total_damaged_kg);
        $this->assertSame(50.0, $reportDataReversed['summary']->total_damaged_cost);

        // 9. Verify warehouse-specific Daily Close persistence and unique keys
        $closeLine = DailyInventoryCloseLine::query()
            ->whereDate('business_date', $date)
            ->where('product_id', $tomato->id)
            ->where('grade', 'DMG')
            ->first();
        $this->assertNotNull($closeLine);
        $this->assertEquals(2.0, (float) $closeLine->wastage_qty);
        $this->assertEquals(8.0, (float) $closeLine->carryover_qty);

        Carbon::setTestNow();
    }

    /**
     * Test 3 — Future Warehouse Reset (Simulation Only — Isolated Test Database):
     * 1. Create old positive, negative, mixed, graded and pending stock records across both warehouses.
     * 2. Simulate authoritative zero baseline for both warehouses.
     * 3. Verify all current stock balances become zero.
     * 4. Confirm old physical batches cannot be consumed by new loadouts.
     * 5. Receive 100 KG new stock after reset.
     * 6. Load out 30 KG.
     * 7. Confirm remaining stock is exactly 70 KG.
     * 8. Repeat baseline operation and verify idempotency.
     */
    public function test_3_future_warehouse_reset_simulation(): void
    {
        $oldDate = '2026-10-25';
        $resetDate = '2026-10-30';
        $newStockDate = '2026-10-31';

        Carbon::setTestNow($oldDate.' 10:00:00');

        $carrot = Product::factory()->create([
            'name' => 'Ooty Carrot',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse1->id,
            'carryover_enabled' => true,
        ]);

        $beetroot = Product::factory()->create([
            'name' => 'Red Beetroot',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse2->id,
            'carryover_enabled' => true,
        ]);

        // 1. Create old records in Warehouse 1:
        // - Positive sorted Grade A: 50 kg
        $this->createBatchWithStock($carrot, $this->warehouse1, 50.0, ProductGrade::GradeA, 30.0, $oldDate);
        // - Positive pending Mixed: 40 kg
        StockBatch::factory()->create([
            'product_id' => $carrot->id,
            'warehouse_id' => $this->warehouse1->id,
            'total_kg' => 40.0,
            'cost_per_kg' => 25.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => false,
            'received_at' => $oldDate,
        ]);
        // - Legacy negative stock on Beetroot in Warehouse 1: -15 kg
        $negBatch = StockBatch::factory()->create([
            'product_id' => $beetroot->id,
            'warehouse_id' => $this->warehouse1->id,
            'total_kg' => 0.0,
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $oldDate,
        ]);
        StockMovement::create([
            'product_id' => $beetroot->id,
            'batch_id' => $negBatch->id,
            'warehouse_id' => $this->warehouse1->id,
            'created_by' => $this->user->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 15.0,
            'cost_per_unit' => 20.0,
            'notes' => 'Old negative historical movement',
        ]);

        // In Warehouse 2:
        // - Positive sorted Grade B: 60 kg Beetroot
        $this->createBatchWithStock($beetroot, $this->warehouse2, 60.0, ProductGrade::GradeB, 22.0, $oldDate);

        // Pre-reset verification
        $this->assertSame(90.0, $this->stockMovementRepository->currentStockForProduct($carrot->id, $this->warehouse1->id, $oldDate));
        $this->assertSame(-15.0, $this->stockMovementRepository->currentStockForProduct($beetroot->id, $this->warehouse1->id, $oldDate));
        $this->assertSame(60.0, $this->stockMovementRepository->currentStockForProduct($beetroot->id, $this->warehouse2->id, $oldDate));

        // 2. Simulate the new authoritative zero baseline for BOTH warehouses
        Carbon::setTestNow($resetDate.' 18:00:00');
        $adjCountWh1 = $this->stockAdjustmentService->emptyWarehouse($this->warehouse1, $resetDate, $this->user->id);
        $adjCountWh2 = $this->stockAdjustmentService->emptyWarehouse($this->warehouse2, $resetDate, $this->user->id);

        $this->assertGreaterThan(0, $adjCountWh1);
        $this->assertGreaterThan(0, $adjCountWh2);

        // 3. Verify all current stock balances become zero
        $this->assertSame(0.0, $this->stockMovementRepository->currentStockForProduct($carrot->id, $this->warehouse1->id, $resetDate));
        $this->assertSame(0.0, $this->stockMovementRepository->currentStockForProduct($beetroot->id, $this->warehouse1->id, $resetDate));
        $this->assertSame(0.0, $this->stockMovementRepository->currentStockForProduct($beetroot->id, $this->warehouse2->id, $resetDate));

        // Confirm historical transactions were NOT deleted
        $this->assertTrue(StockMovement::where('product_id', $beetroot->id)->where('type', StockMovementType::Out->value)->exists());
        $this->assertTrue(StockMovement::where('product_id', $carrot->id)->where('type', StockMovementType::In->value)->exists());

        // 4. Confirm old physical batches cannot be consumed by new loadouts
        $availableCarrotLots = $this->stockLedgerService->availableSortedStockForProduct($carrot->id, $this->warehouse1->id, ProductGrade::GradeA);
        $this->assertSame(0.0, $availableCarrotLots);

        // 5. Receive 100 KG new stock after reset in Warehouse 1 on 2026-10-31
        Carbon::setTestNow($newStockDate.' 08:00:00');
        $newBatch = $this->createBatchWithStock($carrot, $this->warehouse1, 100.0, ProductGrade::GradeA, 28.0, $newStockDate);

        // Stock is now exactly 100 KG
        $this->assertSame(100.0, $this->stockMovementRepository->currentStockForProduct($carrot->id, $this->warehouse1->id, $newStockDate));

        // 6. Load out 30 KG
        DailyPriceApproval::create([
            'product_id' => $carrot->id,
            'business_date' => $newStockDate,
            'grade' => 'A',
            'purchase_price' => 28.0,
            'price_unit' => 'kg',
            'price_a' => 45.0,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->user->id,
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $newStockDate,
            'delivery_status' => 'pending_delivery',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $carrot->id,
            'product_grade' => 'A',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'loaded_qty' => 0.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        $response = $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
            'items' => ["{$carrot->id}|A" => 30.0],
        ]);
        $response->assertOk();

        // 7. Confirm remaining stock is exactly 70 KG
        $this->assertSame(70.0, $this->stockMovementRepository->currentStockForProduct($carrot->id, $this->warehouse1->id, $newStockDate));

        // 8. Repeat baseline operation and verify idempotency
        // Emptying an already emptied warehouse on the reset date produces 0 new adjustments
        $repeatedAdjCount = $this->stockAdjustmentService->emptyWarehouse($this->warehouse2, $resetDate, $this->user->id);
        $this->assertSame(0, $repeatedAdjCount);

        Carbon::setTestNow();
    }

    private function createBatchWithStock(Product $product, Warehouse $warehouse, float $quantity, ProductGrade $grade, float $costPerKg, string $date): StockBatch
    {
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'total_kg' => $quantity,
            'cost_per_kg' => $costPerKg,
            'purchase_grade' => $grade->value,
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->user->id,
            'grade' => $grade->value,
            'type' => StockMovementType::In->value,
            'quantity' => $quantity,
            'cost_per_unit' => $costPerKg,
            'notes' => 'Batch stock creation',
        ]);

        return $batch;
    }
}
