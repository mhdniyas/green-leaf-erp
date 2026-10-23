<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Actions\Inventory\ProcessBatchSortingAction;
use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\Domains\ShopOrder\Actions\ResolveDeliveryReviewAction;
use App\DTOs\Inventory\SortingData;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Enums\Purchasing\POStatus;
use App\Exceptions\Inventory\BatchAlreadySortedException;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Requisition\MultiGradeOrderGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseMultiGradePhase3Test extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $admin;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Category $category;

    private Product $product;

    private ShopPriceGroup $priceGroup;

    private Shop $shop;

    private string $businessDate;

    private StockLedgerService $stockLedgerService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);

        Permission::firstOrCreate(['name' => 'warehouse.receive.view']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.confirm']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.sort']);

        $receiverRole = Role::findByName('warehouse_receiver');
        $receiverRole->givePermissionTo([
            'warehouse.receive.view',
            'warehouse.receive.confirm',
            'warehouse.receive.sort',
        ]);

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MWH-01',
            'is_active' => true,
        ]);
        $this->receiver->warehouses()->attach($this->warehouse);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Fresh Farm Supplier',
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Tomato H',
            'sku' => 'TOM-H',
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
            'name' => 'Casio Grocery',
            'code' => 'CAS-01',
            'shop_price_group_id' => $this->priceGroup->id,
            'is_active' => true,
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $this->businessDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

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
            'price_b' => 30.0,
            'price_c' => 20.0,
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
            'price_b' => 20.0,
            'price_c' => 10.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->stockLedgerService = app(StockLedgerService::class);
    }

    // -------------------------------------------------------------------------
    // 1-6: Existing Grade A Workflow Backward Compatibility
    // -------------------------------------------------------------------------

    public function test_01_current_grade_a_purchase_and_warehouse_confirmation_flow_unaffected(): void
    {
        [$po, $poItem] = $this->createPurchaseOrderWithItem('A', 100.0);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: now()->toDateTimeString(),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Standard Grade A receipt',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 100.0,
                    'unit_price' => 50.0,
                    'grade' => 'A',
                ],
            ],
            warehouseId: $this->warehouse->id,
            purchaseGrade: 'A',
        );

        $grn = app(RecordGoodsReceiptAction::class)->execute($dto, (int) $this->receiver->id);
        $this->assertSame('A', $grn->purchase_grade);

        app(ApproveGoodsReceiptAction::class)->execute($grn, (int) $this->admin->id);
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();

        $this->assertSame('A', $batch->purchase_grade);
        $this->assertTrue((bool) $batch->warehouse_receive_pending);
        $this->assertSame(BatchStatus::Pending, $batch->status);

        // Warehouse receiver confirms receipt
        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ]);
        $response->assertRedirect();

        $batch->refresh();
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertSame(BatchStatus::Pending, $batch->status);
    }

    public function test_02_current_grade_a_sorting_flow_produces_grade_a_stock(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-A-01',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 100.0],
            ],
            notes: 'Sort Grade A batch',
        );

        $action = app(ProcessBatchSortingAction::class);
        $action->execute($batch, $sortingData, (int) $this->receiver->id);

        $batch->refresh();
        $this->assertSame(BatchStatus::Sorted, $batch->status);

        $movements = StockMovement::where('batch_id', $batch->id)->get();
        $this->assertCount(1, $movements);
        $this->assertSame('A', $movements->first()->grade->value);
        $this->assertSame(100.0, (float) $movements->first()->quantity);
        $this->assertSame(StockMovementType::In, $movements->first()->type);
    }

    public function test_03_current_grade_a_stock_ledger_calculations(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-A-LEDGER',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 80.0,
            'cost_per_kg' => 45.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'created_by' => $this->receiver->id,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In,
            'quantity' => 80.0,
            'cost_per_unit' => 45.0,
        ]);

        $availableA = $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA);
        $availableB = $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB);

        $this->assertSame(80.0, $availableA);
        $this->assertSame(0.0, $availableB);
    }

    public function test_04_current_grade_a_web_loadout_remains_backward_compatible(): void
    {
        $batch = $this->createSortedBatch('A', 50.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 500.0,
        ]);

        // Show endpoint
        $res = $this->actingAs($this->receiver)
            ->get(route('warehouse.loadout.show', $order));
        $res->assertOk();

        // Save loadout with classic product_id key
        $saveRes = $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => [(string) $this->product->id => 10.0],
            ]);
        $saveRes->assertRedirect();

        $item->refresh();
        $this->assertSame(10.0, (float) $item->loaded_qty);
        $this->assertSame('loaded', $item->sorting_status);

        // Check stock movement Out was created with Grade A
        $outMovement = StockMovement::where('product_id', $this->product->id)
            ->where('type', StockMovementType::Out->value)
            ->first();
        $this->assertNotNull($outMovement);
        $this->assertSame('A', $outMovement->grade->value);
        $this->assertSame(10.0, (float) $outMovement->quantity);
    }

    public function test_05_current_grade_a_api_loadout_remains_backward_compatible(): void
    {
        $this->createSortedBatch('A', 40.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 250.0,
        ]);

        $res = $this->actingAs($this->receiver)
            ->getJson("/api/v1/warehouse/loadout/{$order->id}");
        $res->assertOk();
        $res->assertJsonPath('product_groups.0.product_id', $this->product->id);
        $res->assertJsonPath('product_groups.0.product_grade', 'A');

        $saveRes = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [(string) $this->product->id => 5.0],
            ]);
        $saveRes->assertOk();

        $item->refresh();
        $this->assertSame(5.0, (float) $item->loaded_qty);

        $out = StockMovement::where('shop_order_item_id', $item->id)->first();
        $this->assertNotNull($out);
        $this->assertSame('A', $out->grade->value);
        $this->assertSame(5.0, (float) $out->quantity);
    }

    // -------------------------------------------------------------------------
    // 7-15: Additional Grades, Segregation & Safety
    // -------------------------------------------------------------------------

    public function test_06_fixed_grade_b_receiving_and_confirmation(): void
    {
        [$po, $poItem] = $this->createPurchaseOrderWithItem('B', 50.0);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: now()->toDateTimeString(),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Direct Grade B tomato purchase',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 50.0,
                    'unit_price' => 35.0,
                    'grade' => 'B',
                ],
            ],
            warehouseId: $this->warehouse->id,
            purchaseGrade: 'B',
        );

        $grn = app(RecordGoodsReceiptAction::class)->execute($dto, (int) $this->receiver->id);
        $this->assertSame('B', $grn->purchase_grade);

        app(ApproveGoodsReceiptAction::class)->execute($grn, (int) $this->admin->id);
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();

        $this->assertSame('B', $batch->purchase_grade);
        $this->assertSame('fixed_purchase_grade', $batch->grading_mode);
        $this->assertTrue((bool) $batch->warehouse_receive_pending);

        // Warehouse receiver confirms fixed-grade receipt
        $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ])
            ->assertRedirect();

        $batch->refresh();
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertSame(BatchStatus::Sorted, $batch->status);

        // Grade B stock movement In is created immediately
        $movement = StockMovement::where('batch_id', $batch->id)->first();
        $this->assertNotNull($movement);
        $this->assertSame('B', $movement->grade->value);
        $this->assertSame(50.0, (float) $movement->quantity);
        $this->assertSame(StockMovementType::In, $movement->type);

        $this->assertSame(50.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
    }

    public function test_07_fixed_grade_c_receiving_and_confirmation(): void
    {
        [$po, $poItem] = $this->createPurchaseOrderWithItem('C', 40.0);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: now()->toDateTimeString(),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Grade C receipt',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 40.0,
                    'unit_price' => 20.0,
                    'grade' => 'C',
                ],
            ],
            warehouseId: $this->warehouse->id,
            purchaseGrade: 'C',
        );

        $grn = app(RecordGoodsReceiptAction::class)->execute($dto, (int) $this->receiver->id);
        app(ApproveGoodsReceiptAction::class)->execute($grn, (int) $this->admin->id);
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();

        $this->assertSame('C', $batch->purchase_grade);
        $this->assertSame('fixed_purchase_grade', $batch->grading_mode);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ])
            ->assertRedirect();

        $batch->refresh();
        $this->assertSame(BatchStatus::Sorted, $batch->status);

        $movement = StockMovement::where('batch_id', $batch->id)->first();
        $this->assertSame('C', $movement->grade->value);
        $this->assertSame(40.0, (float) $movement->quantity);
        $this->assertSame(40.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeC));
    }

    public function test_08_mixed_grade_b_purchase_with_warehouse_segregation(): void
    {
        // Purchase 200 kg, physically arrives mixed
        $batch = StockBatch::create([
            'reference' => 'BATCH-MIXED-200',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 200.0,
            'cost_per_kg' => 30.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'B',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Unsorted stock is NOT sellable
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeC));

        // Sorting: A: 100 kg, B: 20 kg, C: 75 kg, Damage: 5 kg = 200 kg
        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 100.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 20.0],
                ['grade' => ProductGrade::GradeC, 'quantity' => 75.0],
                ['grade' => ProductGrade::Damage, 'quantity' => 5.0],
            ],
            notes: 'Segregate mixed B batch',
        );

        app(ProcessBatchSortingAction::class)->execute($batch, $sortingData, (int) $this->receiver->id);

        $batch->refresh();
        $this->assertSame(BatchStatus::Sorted, $batch->status);

        // Grade A, B, C stock movements
        $this->assertSame(100.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
        $this->assertSame(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
        $this->assertSame(75.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeC));

        // Damage recorded as wastage
        $wastage = WastageEntry::where('batch_id', $batch->id)->first();
        $this->assertNotNull($wastage);
        $this->assertSame(5.0, (float) $wastage->quantity);
        $gradeVal = is_object($wastage->grade) ? $wastage->grade->value : (string) $wastage->grade;
        $this->assertSame(ProductGrade::Damage->value, $gradeVal);
        $reasonVal = is_object($wastage->reason) ? $wastage->reason->value : (string) $wastage->reason;
        $this->assertSame(WastageReason::SortingDamage->value, $reasonVal);
    }

    public function test_09_existing_grade_a_purchase_with_mixed_sorting_segregation(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-A-MIXED',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 80.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 18.0],
                ['grade' => ProductGrade::Damage, 'quantity' => 2.0],
            ],
            notes: 'Sort Grade A into A and B',
        );

        app(ProcessBatchSortingAction::class)->execute($batch, $sortingData, (int) $this->receiver->id);

        $this->assertSame(80.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
        $this->assertSame(18.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
    }

    public function test_10_grade_isolation_prevents_cross_contamination(): void
    {
        $this->createSortedBatch('A', 100.0);
        $this->createSortedBatch('B', 20.0);
        $this->createSortedBatch('C', 75.0);

        // Deduct 15 kg from Grade B
        $consumed = $this->stockLedgerService->consumeSortedStockForProduct(
            productId: $this->product->id,
            quantity: 15.0,
            userId: (int) $this->receiver->id,
            movementType: StockMovementType::Out,
            notes: 'Test Grade B deduction',
            grade: ProductGrade::GradeB,
        );

        $this->assertSame(15.0, $consumed);
        $this->assertSame(100.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
        $this->assertSame(5.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
        $this->assertSame(75.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeC));
    }

    public function test_11_damage_wastage_conservation(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-CONSERVE',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'B',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 20.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 25.0],
                ['grade' => ProductGrade::Damage, 'quantity' => 5.0],
            ],
            notes: 'Wastage conservation check',
        );

        app(ProcessBatchSortingAction::class)->execute($batch, $sortingData, (int) $this->receiver->id);

        $sellableQty = (float) StockMovement::where('batch_id', $batch->id)
            ->where('type', StockMovementType::In->value)
            ->sum('quantity');
        $damageQty = (float) WastageEntry::where('batch_id', $batch->id)->sum('quantity');

        $this->assertSame(45.0, $sellableQty);
        $this->assertSame(5.0, $damageQty);
        $this->assertSame(50.0, $sellableQty + $damageQty);
    }

    public function test_12_partial_goods_receiving_with_grades(): void
    {
        [$po, $poItem] = $this->createPurchaseOrderWithItem('B', 100.0);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: now()->toDateTimeString(),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Partial Grade B receipt (60kg of 100kg ordered)',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 60.0,
                    'unit_price' => 30.0,
                    'grade' => 'B',
                ],
            ],
            warehouseId: $this->warehouse->id,
            purchaseGrade: 'B',
        );

        $grn = app(RecordGoodsReceiptAction::class)->execute($dto, (int) $this->receiver->id);
        app(ApproveGoodsReceiptAction::class)->execute($grn, (int) $this->admin->id);
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();

        $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ])
            ->assertRedirect();

        $this->assertSame(60.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
    }

    public function test_13_sorting_retry_prevented_no_duplicate_stock(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-RETRY-SORT',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 40.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 50.0],
            ],
            notes: 'Initial sort',
        );

        $action = app(ProcessBatchSortingAction::class);
        $action->execute($batch, $sortingData, (int) $this->receiver->id);

        $initialMovementCount = StockMovement::where('batch_id', $batch->id)->count();
        $this->assertSame(1, $initialMovementCount);

        // Retry sorting must throw exception
        $this->expectException(BatchAlreadySortedException::class);
        $action->execute($batch, $sortingData, (int) $this->receiver->id);

        $this->assertSame(1, StockMovement::where('batch_id', $batch->id)->count());
    }

    public function test_14_confirmation_retry_is_idempotent_no_duplicate_stock(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-RETRY-CONFIRM',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 30.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
            'received_at' => now(),
            'purchase_grade' => 'B',
            'grading_mode' => 'fixed_purchase_grade',
            'created_by' => $this->receiver->id,
        ]);

        // First confirmation
        $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ])
            ->assertRedirect();

        $batch->refresh();
        $this->assertSame(BatchStatus::Sorted, $batch->status);
        $this->assertSame(1, StockMovement::where('batch_id', $batch->id)->count());

        // Repeated confirmation call must not create duplicate StockMovement In
        $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ]);

        $this->assertSame(1, StockMovement::where('batch_id', $batch->id)->count());
    }

    // -------------------------------------------------------------------------
    // 16-24: Loadout Multi-Grade & Delivery Discrepancies
    // -------------------------------------------------------------------------

    public function test_15_one_shop_orders_tomato_grade_a_and_grade_b_remain_separate_operational_lines(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 500.0,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 200.0,
        ]);

        // Web loadout show
        $webRes = $this->actingAs($this->receiver)
            ->get(route('warehouse.loadout.show', $order));
        $webRes->assertOk();
        $productGroups = $webRes->viewData('productGroups');
        $this->assertCount(2, $productGroups);

        $keys = $productGroups->pluck('row_key')->toArray();
        $this->assertContains("{$this->product->id}|A", $keys);
        $this->assertContains("{$this->product->id}|B", $keys);

        // API loadout show
        $apiRes = $this->actingAs($this->receiver)
            ->getJson("/api/v1/warehouse/loadout/{$order->id}");
        $apiRes->assertOk();
        $groups = $apiRes->json('product_groups');
        $this->assertCount(2, $groups);
        $this->assertSame('A', $groups[0]['product_grade']);
        $this->assertSame('B', $groups[1]['product_grade']);
    }

    public function test_16_web_loadout_deducts_correct_inventory_grade(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 30.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 500.0,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 200.0,
        ]);

        // Save loadout with composite keys
        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => [
                    "{$this->product->id}|A" => 10.0,
                    "{$this->product->id}|B" => 5.0,
                ],
            ])
            ->assertRedirect();

        $itemA->refresh();
        $itemB->refresh();
        $this->assertSame(10.0, (float) $itemA->loaded_qty);
        $this->assertSame(5.0, (float) $itemB->loaded_qty);

        // Grade A stock reduced from 50 to 40
        $this->assertSame(40.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
        // Grade B stock reduced from 30 to 25
        $this->assertSame(25.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));

        // Out movements
        $movA = StockMovement::where('shop_order_item_id', $itemA->id)->first();
        $movB = StockMovement::where('shop_order_item_id', $itemB->id)->first();
        $this->assertSame('A', $movA->grade->value);
        $this->assertSame(10.0, (float) $movA->quantity);
        $this->assertSame('B', $movB->grade->value);
        $this->assertSame(5.0, (float) $movB->quantity);
    }

    public function test_17_updating_grade_b_loadout_does_not_alter_grade_a(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 30.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 500.0,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 200.0,
        ]);

        // Initial save: 10 kg A, 5 kg B
        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => [
                    "{$this->product->id}|A" => 10.0,
                    "{$this->product->id}|B" => 5.0,
                ],
            ]);

        // Update Grade B to 3 kg (reversing 2 kg of Grade B)
        $res = $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => [
                    "{$this->product->id}|A" => 10.0,
                    "{$this->product->id}|B" => 3.0,
                ],
            ]);
        $res->assertSessionHasNoErrors();

        $itemA->refresh();
        $itemB->refresh();
        $this->assertSame(10.0, (float) $itemA->loaded_qty);
        $this->assertSame(3.0, (float) $itemB->loaded_qty);

        // Grade A remains 40 kg
        $this->assertSame(40.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
        // Grade B restored to 27 kg (30 - 3)
        $this->assertSame(27.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
    }

    public function test_18_grade_specific_delivery_shortage_reversal_targets_correct_grade(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 30.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'in_transit',
            'delivery_review_status' => 'not_started',
            'business_date' => $this->businessDate,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 5.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'line_total' => 200.0,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $order->shop_id,
            'shop_order_id' => $order->id,
            'business_date' => $order->business_date->toDateString(),
            'status' => 'payment_pending',
            'delivery_status' => 'in_transit',
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $itemB->id,
            'product_id' => $itemB->product_id,
            'product_name' => $this->product->name,
            'unit' => 'KG',
            'price_unit' => 'KG',
            'approved_qty' => 5.0,
            'price_quantity' => 5.0,
            'delivered_qty' => 5.0,
            'unit_price' => 50.0,
            'line_subtotal' => 250.0,
        ]);

        $this->stockLedgerService->consumeSortedStockForProduct(
            $this->product->id,
            5.0,
            (int) $this->admin->id,
            StockMovementType::Out,
            "Loadout - Order: {$order->order_number}; Item: {$itemB->id}",
            $itemB->id,
            null,
            ProductGrade::GradeB,
        );

        // Grade B shortage: shop counted 3 kg (shortage 2 kg)
        $action = app(ResolveDeliveryReviewAction::class);
        $action->submit($order, [$itemB->id => 3.0], (int) $this->admin->id, 'Grade B shortage of 2kg');

        // Approve delivery review with add_back in parameter 4 (itemInventoryActions)
        $action->approve(
            $order->fresh(),
            [$itemB->id => 3.0],
            [],
            [$itemB->id => 'add_back'],
            [],
            [],
            (int) $this->admin->id,
            'Approved shortage returned to inventory',
        );

        // SaleReversal must be Grade B
        $reversal = StockMovement::where('type', StockMovementType::SaleReversal->value)
            ->where('shop_order_item_id', $itemB->id)
            ->first();
        $this->assertNotNull($reversal);
        $this->assertSame('B', $reversal->grade->value);
        $this->assertSame(2.0, (float) $reversal->quantity);

        // Stock balances: Grade A untouched at 50.0, Grade B is 27.0 (25 + 2)
        $this->assertSame(50.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
        $this->assertSame(27.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
    }

    public function test_19_grade_specific_delivery_review_wastage_records_correct_grade(): void
    {
        $this->createSortedBatch('B', 30.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'in_transit',
            'delivery_review_status' => 'not_started',
            'business_date' => $this->businessDate,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 5.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'line_total' => 200.0,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $order->shop_id,
            'shop_order_id' => $order->id,
            'business_date' => $order->business_date->toDateString(),
            'status' => 'payment_pending',
            'delivery_status' => 'in_transit',
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $itemB->id,
            'product_id' => $itemB->product_id,
            'product_name' => $this->product->name,
            'unit' => 'KG',
            'price_unit' => 'KG',
            'approved_qty' => 5.0,
            'price_quantity' => 5.0,
            'delivered_qty' => 5.0,
            'unit_price' => 50.0,
            'line_subtotal' => 250.0,
        ]);

        $this->stockLedgerService->consumeSortedStockForProduct(
            $this->product->id,
            5.0,
            (int) $this->admin->id,
            StockMovementType::Out,
            "Loadout - Order: {$order->order_number}; Item: {$itemB->id}",
            $itemB->id,
            null,
            ProductGrade::GradeB,
        );

        $action = app(ResolveDeliveryReviewAction::class);
        $action->submit($order, [$itemB->id => 4.0], (int) $this->admin->id, 'Transit damage 1kg');

        $action->approve(
            $order->fresh(),
            [$itemB->id => 4.0],
            [],
            [$itemB->id => 'wastage'],
            [$itemB->id => 'transit_damage'],
            [$itemB->id => 'Crushed in van'],
            (int) $this->admin->id,
            'Approved as wastage',
        );

        $wastage = WastageEntry::where('notes', 'like', "%Order: {$order->order_number}; Item: {$itemB->id}%")->first();
        $this->assertNotNull($wastage);
        $this->assertSame('B', is_object($wastage->grade) ? $wastage->grade->value : (string) $wastage->grade);
        $this->assertSame(1.0, (float) $wastage->quantity);
    }

    public function test_20_excess_delivery_consumes_correct_grade(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 30.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'in_transit',
            'delivery_review_status' => 'not_started',
            'business_date' => $this->businessDate,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 5.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'line_total' => 200.0,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $order->shop_id,
            'shop_order_id' => $order->id,
            'business_date' => $order->business_date->toDateString(),
            'status' => 'payment_pending',
            'delivery_status' => 'in_transit',
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $itemB->id,
            'product_id' => $itemB->product_id,
            'product_name' => $this->product->name,
            'unit' => 'KG',
            'price_unit' => 'KG',
            'approved_qty' => 5.0,
            'price_quantity' => 5.0,
            'delivered_qty' => 5.0,
            'unit_price' => 50.0,
            'line_subtotal' => 250.0,
        ]);

        $action = app(ResolveDeliveryReviewAction::class);
        $action->submit($order, [$itemB->id => 8.0], (int) $this->admin->id, 'Received 8kg instead of 5kg');

        $action->approve(
            $order->fresh(),
            [$itemB->id => 8.0],
            [],
            [$itemB->id => 'deduct_extra'],
            [],
            [],
            (int) $this->admin->id,
            'Approved extra 3kg deducted from inventory',
        );

        // Grade B stock reduced by 3 kg (from 30 to 27)
        $this->assertSame(27.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
        // Grade A stock remains 50.0
        $this->assertSame(50.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));

        $excessMovement = StockMovement::where('notes', 'like', "Delivery excess approved - Order: {$order->order_number}; Item: {$itemB->id}%")->first();
        $this->assertNotNull($excessMovement);
        $this->assertSame('B', $excessMovement->grade->value);
        $this->assertSame(3.0, (float) $excessMovement->quantity);
    }

    public function test_21_api_loadout_deducts_correct_grade_stock(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 30.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 500.0,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 200.0,
        ]);

        // API save with item IDs from a compatible client
        $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    (string) $itemA->id => 10.0,
                    (string) $itemB->id => 5.0,
                ],
            ], [
                'X-Client-Supports-Multi-Grade' => 'true',
            ])
            ->assertOk();

        $this->assertSame(40.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
        $this->assertSame(25.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
    }

    public function test_22_legacy_flutter_payload_compatibility(): void
    {
        $this->createSortedBatch('A', 30.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 7.0,
            'approved_qty' => 7.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 350.0,
        ]);

        // Legacy mobile client sends { items: { "productId": quantity } }
        $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    (string) $this->product->id => 7.0,
                ],
            ])
            ->assertOk();

        $item->refresh();
        $this->assertSame(7.0, (float) $item->loaded_qty);
        $this->assertSame(23.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
    }

    public function test_23_repeated_loadout_saves_do_not_double_deduct_stock(): void
    {
        $this->createSortedBatch('B', 30.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 200.0,
        ]);

        $payload = [
            'items' => ["{$this->product->id}|B" => 5.0],
        ];

        // First save
        $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $order), $payload)->assertRedirect();
        $this->assertSame(25.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));

        // Repeated save with same payload
        $this->actingAs($this->receiver)->post(route('warehouse.loadout.save', $order), $payload)->assertRedirect();
        $this->assertSame(25.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
    }

    public function test_24_duplicate_merging_candidate_preserves_grade_distinction(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        // Two distinct lines for the same product but different grades
        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 500.0,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 200.0,
        ]);

        $apiRes = $this->actingAs($this->receiver)
            ->getJson("/api/v1/warehouse/loadout/{$order->id}");
        $apiRes->assertOk();
        $this->assertFalse($apiRes->json('has_duplicates'));
        $this->assertEmpty($apiRes->json('merge_candidates'));

        $res = $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.merge-duplicates.all', $order));
        $res->assertRedirect();

        // Must NOT merge A and B into one row
        $this->assertSame(2, ShopOrderItem::where('shop_order_id', $order->id)->count());
        $this->assertDatabaseHas('shop_order_items', [
            'id' => $itemA->id,
            'product_grade' => 'A',
        ]);
        $this->assertDatabaseHas('shop_order_items', [
            'id' => $itemB->id,
            'product_grade' => 'B',
        ]);
    }

    // -------------------------------------------------------------------------
    // 25-27: Feature Safety & Flag Verification
    // -------------------------------------------------------------------------

    public function test_25_global_multi_grade_ordering_feature_remains_disabled(): void
    {
        $this->assertFalse(app(MultiGradeOrderGate::class)->isFeatureEnabled());
    }

    public function test_26_existing_grade_a_processing_continues_with_feature_disabled(): void
    {
        $this->assertFalse(app(MultiGradeOrderGate::class)->isFeatureEnabled());

        $batch = $this->createSortedBatch('A', 20.0);
        $this->assertSame(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
    }

    public function test_27_already_accepted_non_a_records_remain_processable_when_ordering_is_disabled(): void
    {
        $this->assertFalse(app(MultiGradeOrderGate::class)->isFeatureEnabled());

        // Even with global shop ordering flag disabled, existing Grade B receipt and loadout can be processed
        $batch = $this->createSortedBatch('B', 30.0);
        $this->assertSame(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 400.0,
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 10.0],
            ])
            ->assertRedirect();

        $itemB->refresh();
        $this->assertSame(10.0, (float) $itemB->loaded_qty);
        $this->assertSame(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createPurchaseOrderWithItem(string $grade = 'A', float $qty = 100.0): array
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-'.strtoupper($grade).'-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => $this->businessDate,
            'total_amount' => $qty * 30.0,
            'status' => POStatus::Approved->value,
            'purchase_grade' => $grade,
            'created_by' => $this->receiver->id,
        ]);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'unit_price' => 30.0,
            'line_total' => $qty * 30.0,
        ]);

        return [$po, $item];
    }

    private function createSortedBatch(string $grade, float $quantity): StockBatch
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-TEST-'.strtoupper($grade).'-'.uniqid(),
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
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
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => $grade,
            'type' => StockMovementType::In,
            'quantity' => $quantity,
            'cost_per_unit' => 40.0,
        ]);

        return $batch;
    }
}
