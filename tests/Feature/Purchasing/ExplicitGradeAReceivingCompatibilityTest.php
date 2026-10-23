<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Inventory\ProcessBatchSortingAction;
use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Inventory\SortingData;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\POStatus;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExplicitGradeAReceivingCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $receiver;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Category $category;

    private Product $tomato;

    private ShopPriceGroup $priceGroup;

    private Shop $shop;

    private StockMovementRepository $stockRepo;

    private StockLedgerService $stockLedgerService;

    private string $businessDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Role::firstOrCreate(['name' => 'purchaser']);

        Permission::firstOrCreate(['name' => 'purchasing.grn.create']);
        Permission::firstOrCreate(['name' => 'purchasing.grn.edit']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.view']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.confirm']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.sort']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->receiver = User::factory()->create(['name' => 'Warehouse Receiver']);
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->givePermissionTo([
            'purchasing.grn.create',
            'purchasing.grn.edit',
            'warehouse.receive.view',
            'warehouse.receive.confirm',
            'warehouse.receive.sort',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Central Cold Storage',
            'code' => 'CCS-01',
            'is_active' => true,
        ]);

        $this->receiver->warehouses()->attach($this->warehouse->id, ['is_default' => true]);
        $this->admin->warehouses()->attach($this->warehouse->id);

        $this->supplier = Supplier::factory()->create(['name' => 'Prime Farms']);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->tomato = Product::create([
            'name' => 'Roma Tomato',
            'sku' => 'TOM-ROM',
            'unit' => 'KG',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);

        $this->priceGroup = ShopPriceGroup::create([
            'name' => 'Retail Group A',
            'is_active' => true,
        ]);

        $this->shop = Shop::create([
            'name' => 'Green Leaf Store 1',
            'code' => 'GLS-01',
            'shop_price_group_id' => $this->priceGroup->id,
            'is_active' => true,
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $this->stockRepo = app(StockMovementRepository::class);
        $this->stockLedgerService = app(StockLedgerService::class);
        $this->businessDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        BusinessSetting::updateOrCreate(
            ['key' => 'allow_historical_invoice_repricing'],
            ['value' => '1']
        );

        DailyPriceApproval::create([
            'product_id' => $this->tomato->id,
            'business_date' => $this->businessDate,
            'grade' => 'A',
            'purchase_price' => 20.0,
            'price_unit' => 'KG',
            'price_a' => 45.0,
            'price_b' => 35.0,
            'price_c' => 25.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    private function createPurchaseOrder(float $qty = 100.0): array
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => $this->businessDate,
            'purchase_grade' => 'A',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => $qty,
            'purchase_unit' => 'KG',
            'grade' => 'A',
        ]);

        return [$po, $poItem];
    }

    /** 1. New standard GRN defaults to Mixed */
    public function test_01_new_standard_grn_defaults_to_mixed(): void
    {
        [$po, $poItem] = $this->createPurchaseOrder(100.0);

        // View rendering test: inspect dropdown and hidden input in form partial
        $view = $this->actingAs($this->receiver)
            ->view('purchase-manager.grns.partials.form', [
                'sourceOrder' => $po,
                'grn' => null,
                'formAction' => route('purchasing.grns.store'),
                'formMethod' => 'POST',
                'cancelHref' => route('purchasing.orders.show', $po),
                'submitLabel' => 'Record GRN',
                'grnItems' => collect(),
            ]);

        // Quality grade dropdown must default to 'U' (Mixed / Unsegregated)
        $view->assertSee('<option value="U" selected>Mixed / Unsegregated (Default)</option>', false);
        // Hidden input for grading_mode must default to 'sort_required'
        $view->assertSee('name="items[0][grading_mode]" id="items_0_grading_mode" value="sort_required"', false);

        // API default: submitting without explicit grade / grading_mode defaults to Mixed / U
        Sanctum::actingAs($this->receiver);
        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => $this->businessDate,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                ],
            ],
        ]);
        $response->assertCreated();

        $grn = GoodsReceived::where('purchase_order_id', $po->id)->firstOrFail();
        $item = $grn->items()->firstOrFail();
        $this->assertSame('U', $item->grade);
    }

    /** 2. Explicit standard GRN Grade A increases Grade A stock */
    public function test_02_explicit_standard_grn_grade_a_increases_grade_a_stock(): void
    {
        [$po, $poItem] = $this->createPurchaseOrder(100.0);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: $this->businessDate,
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Explicit Grade A standard receipt',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
            ],
            warehouseId: $this->warehouse->id,
        );

        $grn = app(RecordGoodsReceiptAction::class)->execute($dto, (int) $this->receiver->id);
        $item = $grn->items()->firstOrFail();
        $this->assertSame('A', $item->grade);
        $this->assertSame('fixed_purchase_grade', $item->grading_mode);

        app(ApproveGoodsReceiptAction::class)->execute($grn, (int) $this->admin->id);
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();
        $this->assertSame('A', $batch->purchase_grade);
        $this->assertSame('fixed_purchase_grade', $batch->grading_mode);
        $this->assertTrue((bool) $batch->warehouse_receive_pending);

        // Before warehouse receive confirmation: physical stock is 0
        $this->assertEquals(0.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));

        // Warehouse receiver confirms receipt
        $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ])
            ->assertRedirect();

        $batch->refresh();
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertSame(BatchStatus::Sorted, $batch->status);

        // Grade A stock increased to 100 kg, Mixed is 0
        $this->assertEquals(100.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));
        $this->assertEquals(0.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouse->id));
        $this->assertEquals(100.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id));
    }

    /** 3. Explicit standard GRN Grade B increases Grade B stock */
    public function test_03_explicit_standard_grn_grade_b_increases_grade_b_stock(): void
    {
        [$po, $poItem] = $this->createPurchaseOrder(80.0);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: $this->businessDate,
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Explicit Grade B standard receipt',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 80.0,
                    'received_unit' => 'KG',
                    'grade' => 'B',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
            ],
            warehouseId: $this->warehouse->id,
        );

        $grn = app(RecordGoodsReceiptAction::class)->execute($dto, (int) $this->receiver->id);
        app(ApproveGoodsReceiptAction::class)->execute($grn, (int) $this->admin->id);
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();
        $this->assertSame('B', $batch->purchase_grade);
        $this->assertSame('fixed_purchase_grade', $batch->grading_mode);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ])
            ->assertRedirect();

        $this->assertEquals(80.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouse->id));
        $this->assertEquals(0.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));
    }

    /** 4. Explicit standard GRN Grade C increases Grade C stock */
    public function test_04_explicit_standard_grn_grade_c_increases_grade_c_stock(): void
    {
        [$po, $poItem] = $this->createPurchaseOrder(60.0);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: $this->businessDate,
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Explicit Grade C standard receipt',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'KG',
                    'grade' => 'C',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
            ],
            warehouseId: $this->warehouse->id,
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

        $this->assertEquals(60.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeC, $this->warehouse->id));
        $this->assertEquals(0.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));
    }

    /** 5. Explicit Advance Warehouse Grade A increases Grade A stock */
    public function test_05_explicit_advance_warehouse_grade_a_increases_grade_a_stock(): void
    {
        Sanctum::actingAs($this->receiver);
        $response = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse->id,
            'received_at' => $this->businessDate,
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 120.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
            ],
        ]);
        $response->assertCreated();

        $this->assertEquals(120.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));
        $this->assertEquals(0.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouse->id));
    }

    /** 6. Advance Warehouse Mixed increases Unsorted stock */
    public function test_06_advance_warehouse_mixed_increases_unsorted_stock(): void
    {
        Sanctum::actingAs($this->receiver);
        $response = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse->id,
            'received_at' => $this->businessDate,
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 150.0,
                    'received_unit' => 'KG',
                    'grade' => 'U',
                    'grading_mode' => 'sort_required',
                ],
            ],
        ]);
        $response->assertCreated();

        $this->assertEquals(0.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));
        $this->assertEquals(150.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouse->id));
    }

    /** 7. Legacy standard Grade A with NULL grading mode keeps old behavior */
    public function test_07_legacy_standard_grade_a_with_null_grading_mode_keeps_old_behavior(): void
    {
        [$po, $poItem] = $this->createPurchaseOrder(100.0);

        // Simulate legacy payload with grade 'A' and no grading_mode
        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: $this->businessDate,
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Legacy receipt test',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'grade' => 'A',
                    // grading_mode omitted / null
                ],
            ],
            warehouseId: $this->warehouse->id,
            purchaseGrade: 'A',
        );

        $grn = app(RecordGoodsReceiptAction::class)->execute($dto, (int) $this->receiver->id);
        $grnItem = $grn->items()->firstOrFail();
        $this->assertNull($grnItem->grading_mode);

        app(ApproveGoodsReceiptAction::class)->execute($grn, (int) $this->admin->id);
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();

        // Must retain sort_required and pending status under legacy fallback
        $this->assertSame('sort_required', $batch->grading_mode);
        $this->assertSame(BatchStatus::Pending, $batch->status);
        $this->assertTrue((bool) $batch->warehouse_receive_pending);

        // Confirming leaves it Pending (Unsorted)
        $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ]);

        $batch->refresh();
        $this->assertSame(BatchStatus::Pending, $batch->status);
        $this->assertEquals(0.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));
    }

    /** 8. Existing Grade B/C with NULL grading mode keeps old behavior */
    public function test_08_existing_grade_b_c_with_null_grading_mode_keeps_old_behavior(): void
    {
        [$po, $poItem] = $this->createPurchaseOrder(50.0);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: $this->businessDate,
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Legacy B receipt',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 50.0,
                    'grade' => 'B',
                ],
            ],
            warehouseId: $this->warehouse->id,
            purchaseGrade: 'B',
        );

        $grn = app(RecordGoodsReceiptAction::class)->execute($dto, (int) $this->receiver->id);
        app(ApproveGoodsReceiptAction::class)->execute($grn, (int) $this->admin->id);
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();

        // B direct grading legacy fallback: fixed_purchase_grade
        $this->assertSame('fixed_purchase_grade', $batch->grading_mode);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ]);

        $batch->refresh();
        $this->assertSame(BatchStatus::Sorted, $batch->status);
        $this->assertEquals(50.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouse->id));
    }

    /** 9. Editing a legacy receipt without changing grade does not reclassify inventory */
    public function test_09_editing_a_legacy_receipt_without_changing_grade_does_not_reclassify_inventory(): void
    {
        [$po, $poItem] = $this->createPurchaseOrder(100.0);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: $this->businessDate,
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Legacy pre-edit',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'grade' => 'A',
                ],
            ],
            warehouseId: $this->warehouse->id,
            purchaseGrade: 'A',
        );

        $grn = app(RecordGoodsReceiptAction::class)->execute($dto, (int) $this->receiver->id);
        $grnItem = $grn->items()->firstOrFail();
        $this->assertNull($grnItem->grading_mode);

        $grn->update(['status' => 'recheck_required']);

        // View edit form: legacy receipt must display Grade A, but hidden input grading_mode is empty/null
        $view = $this->actingAs($this->receiver)
            ->view('purchase-manager.grns.partials.form', [
                'sourceOrder' => $po,
                'grn' => $grn,
                'formAction' => route('purchasing.grns.update', $grn),
                'formMethod' => 'PUT',
                'cancelHref' => route('purchasing.grns.show', $grn),
                'submitLabel' => 'Update GRN',
                'grnItems' => $grn->items->keyBy('purchase_order_item_id'),
            ]);

        // Quality grade shows Grade A, but grading_mode remains empty (null)
        $view->assertSee('<option value="A" selected>Grade A — Premium</option>', false);
        $view->assertSee('name="items[0][grading_mode]" id="items_0_grading_mode" value=""', false);

        // Simulate form update submitting grade: 'A' and empty/null grading_mode
        $response = $this->actingAs($this->admin)
            ->put(route('purchasing.grns.update', ['grn' => $grn->grn_number]), [
                'purchase_order_id' => $po->id,
                'received_at' => $this->businessDate,
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $this->tomato->id,
                        'received_qty' => 100.0,
                        'grade' => 'A',
                        'grading_mode' => '',
                    ],
                ],
            ]);
        $response->assertRedirect();

        $grnItem->refresh();
        $this->assertNull($grnItem->grading_mode);

        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();
        $this->assertSame('sort_required', $batch->grading_mode);
    }

    /** 10. Invalid grade/mode combinations are rejected */
    public function test_10_invalid_grade_mode_combinations_are_rejected(): void
    {
        [$po, $poItem] = $this->createPurchaseOrder(100.0);
        Sanctum::actingAs($this->receiver);

        // Grade A with sort_required is contradictory
        $resA = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => $this->businessDate,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                    'grading_mode' => 'sort_required',
                ],
            ],
        ]);
        $resA->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.grading_mode']);

        // Mixed with fixed_purchase_grade is contradictory
        $resU = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => $this->businessDate,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                    'grade' => 'U',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
            ],
        ]);
        $resU->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.grading_mode']);
    }

    /** 11. Duplicate receipt confirmation adds no extra stock */
    public function test_11_duplicate_receipt_confirmation_adds_no_extra_stock(): void
    {
        [$po, $poItem] = $this->createPurchaseOrder(50.0);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: $this->businessDate,
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Duplicate confirmation test',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
            ],
            warehouseId: $this->warehouse->id,
        );

        $grn = app(RecordGoodsReceiptAction::class)->execute($dto, (int) $this->receiver->id);
        app(ApproveGoodsReceiptAction::class)->execute($grn, (int) $this->admin->id);
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();

        // Confirm receipt 1st time
        $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ]);

        $this->assertEquals(50.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));

        // Confirm receipt 2nd time (retry)
        $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ]);

        // Must still be 50.0, exactly 1 In movement
        $this->assertEquals(50.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));
        $this->assertSame(1, StockMovement::where('batch_id', $batch->id)->where('type', StockMovementType::In->value)->count());
    }

    /** 12. Advance matching adds no duplicate stock */
    public function test_12_advance_matching_adds_no_duplicate_stock(): void
    {
        // 1. Advance receipt of 100 kg Grade A
        Sanctum::actingAs($this->receiver);
        $advRes = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse->id,
            'received_at' => $this->businessDate,
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
            ],
        ]);
        $advRes->assertCreated();
        $advGrn = GoodsReceived::where('receipt_type', 'warehouse_advance')->latest('id')->firstOrFail();
        $advItem = $advGrn->items->firstOrFail();

        $this->assertEquals(100.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));

        // 2. Standard bill arrives matching 100 kg against the advance receipt
        [$po, $poItem] = $this->createPurchaseOrder(100.0);

        $billRes = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => $this->businessDate,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advGrn->id,
                    'advance_goods_received_item_id' => $advItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'matched_qty' => 100.0,
                ],
            ],
        ]);
        $billRes->assertCreated();

        // Total physical stock must remain 100 kg (no duplicate stock created from matched quantity)
        $this->assertEquals(100.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));
        $this->assertEquals(100.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id));
    }

    /** 13. Existing full-batch sorting still works */
    public function test_13_existing_full_batch_sorting_still_works(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-TEST-MIX-01',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 70.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 30.0],
            ],
            notes: 'Sort mixed batch into A and B',
        );

        app(ProcessBatchSortingAction::class)->execute($batch, $sortingData, (int) $this->receiver->id);

        $batch->refresh();
        $this->assertSame(BatchStatus::Sorted, $batch->status);

        $this->assertEquals(70.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));
        $this->assertEquals(30.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouse->id));
        $this->assertEquals(0.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouse->id));
    }

    /** 14. Automatic Load All still uses Grade First, Mixed Second */
    public function test_14_automatic_load_all_still_uses_grade_first_mixed_second(): void
    {
        $batchA = StockBatch::create([
            'reference' => 'BATCH-TEST-A',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 40.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'A',
            'grading_mode' => 'fixed_purchase_grade',
            'created_by' => $this->admin->id,
        ]);

        StockMovement::create([
            'batch_id' => $batchA->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => StockMovementType::In,
            'grade' => ProductGrade::GradeA,
            'quantity' => 40.0,
            'cost_per_unit' => 20.0,
            'notes' => 'Grade A stock',
            'created_by' => $this->admin->id,
        ]);

        $batchMixed = StockBatch::create([
            'reference' => 'BATCH-TEST-UNSORTED',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 60.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->admin->id,
        ]);

        StockMovement::create([
            'batch_id' => $batchMixed->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => StockMovementType::In,
            'grade' => ProductGrade::Unsorted,
            'quantity' => 60.0,
            'cost_per_unit' => 20.0,
            'notes' => 'Mixed stock',
            'created_by' => $this->admin->id,
        ]);

        $breakdown = $this->stockLedgerService->availableLoadoutStockBreakdown($this->tomato->id, $this->warehouse->id, ProductGrade::GradeA);

        $this->assertEquals(40.0, $breakdown['grade_stock']);
        $this->assertEquals(60.0, $breakdown['mixed_stock']);
        $this->assertEquals(100.0, $breakdown['available_stock']);
    }

    /** 15. Legacy payload omission preserves compatibility */
    public function test_15_legacy_payload_omission_preserves_compatibility(): void
    {
        Sanctum::actingAs($this->receiver);
        $response = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse->id,
            'received_at' => $this->businessDate,
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                    // grading_mode omitted
                ],
            ],
        ]);
        $response->assertCreated();

        $grn = GoodsReceived::latest('id')->firstOrFail();
        $item = $grn->items->firstOrFail();
        $this->assertNull($item->grading_mode);

        // Advance warehouse receipt with legacy Grade A defaults to fixed_purchase_grade
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();
        $this->assertSame('fixed_purchase_grade', $batch->grading_mode);
        $this->assertEquals(50.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse->id));
    }

    /** 16. Invoice prices remain unchanged */
    public function test_16_invoice_prices_remain_unchanged(): void
    {
        [$po, $poItem] = $this->createPurchaseOrder(100.0);

        Sanctum::actingAs($this->receiver);
        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => $this->businessDate,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
            ],
        ]);
        $response->assertCreated();

        $priceApproval = DailyPriceApproval::where('product_id', $this->tomato->id)
            ->where('grade', 'A')
            ->firstOrFail();

        // Selling prices must remain unchanged
        $this->assertEquals(45.0, (float) $priceApproval->price_a);
        $this->assertEquals(35.0, (float) $priceApproval->price_b);
        $this->assertEquals(25.0, (float) $priceApproval->price_c);
    }
}
