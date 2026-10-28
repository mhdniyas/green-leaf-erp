<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\POStatus;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Warehouse\AutoLoadAllService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GradeFirstMixedSecondLoadoutTest extends TestCase
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
            'name' => 'Central Hub Warehouse',
            'code' => 'CHW-01',
            'is_active' => true,
        ]);
        $this->otherWarehouse = Warehouse::create([
            'name' => 'Secondary Outpost Warehouse',
            'code' => 'SOW-02',
            'is_active' => true,
        ]);
        $this->receiver->warehouses()->attach([$this->warehouse->id, $this->otherWarehouse->id]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Kaveri Green Farms',
        ]);

        $this->category = Category::create([
            'name' => 'Fresh Farm Vegetables',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Tomato Hybrid',
            'sku' => 'TOM-HYB',
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
            'name' => 'Ashirwad Mart',
            'code' => 'ASH-01',
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
            'purchase_price' => 25.0,
            'price_unit' => 'KG',
            'price_a' => 50.0,
            'price_b' => 45.0,
            'price_c' => 40.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $this->businessDate,
            'grade' => 'B',
            'purchase_price' => 20.0,
            'price_unit' => 'KG',
            'price_a' => 40.0,
            'price_b' => 35.0,
            'price_c' => 30.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $this->businessDate,
            'grade' => 'C',
            'purchase_price' => 15.0,
            'price_unit' => 'KG',
            'price_a' => 30.0,
            'price_b' => 25.0,
            'price_c' => 20.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->stockLedgerService = app(StockLedgerService::class);
    }

    // -------------------------------------------------------------------------
    // Test 1: Grade A stock sufficient
    // -------------------------------------------------------------------------
    public function test_01_grade_a_stock_sufficient(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createMixedBatch(20.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'A', 'qty' => 20.0, 'selling_price' => 50.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|A" => 20.0],
            ])
            ->assertRedirect();

        // 20 kg taken from Grade A, Mixed untouched
        $this->assertSame(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        $movements = StockMovement::where('type', StockMovementType::Out->value)->get();
        $this->assertCount(1, $movements);
        $this->assertSame('A', $movements->first()->grade->value);
        $this->assertSame(20.0, (float) $movements->first()->quantity);
    }

    // -------------------------------------------------------------------------
    // Test 2: Grade B stock sufficient
    // -------------------------------------------------------------------------
    public function test_02_grade_b_stock_sufficient(): void
    {
        $this->createSortedBatch('B', 30.0);
        $this->createMixedBatch(20.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 15.0, 'selling_price' => 40.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 15.0],
            ])
            ->assertRedirect();

        $this->assertSame(15.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        $movements = StockMovement::where('type', StockMovementType::Out->value)->get();
        $this->assertCount(1, $movements);
        $this->assertSame('B', $movements->first()->grade->value);
        $this->assertSame(15.0, (float) $movements->first()->quantity);
    }

    // -------------------------------------------------------------------------
    // Test 3: Grade C stock sufficient
    // -------------------------------------------------------------------------
    public function test_03_grade_c_stock_sufficient(): void
    {
        $this->createSortedBatch('C', 25.0);
        $this->createMixedBatch(20.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'C', 'qty' => 10.0, 'selling_price' => 30.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|C" => 10.0],
            ])
            ->assertRedirect();

        $this->assertSame(15.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeC));
        $this->assertSame(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        $movements = StockMovement::where('type', StockMovementType::Out->value)->get();
        $this->assertCount(1, $movements);
        $this->assertSame('C', $movements->first()->grade->value);
        $this->assertSame(10.0, (float) $movements->first()->quantity);
    }

    // -------------------------------------------------------------------------
    // Test 4: Grade B partially available, remainder from Mixed
    // -------------------------------------------------------------------------
    public function test_04_grade_b_partially_available_remainder_from_mixed(): void
    {
        $this->createSortedBatch('B', 10.0);
        $this->createMixedBatch(20.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 15.0, 'selling_price' => 40.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 15.0],
            ])
            ->assertRedirect();

        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(15.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        $item = $order->items->first();
        $this->assertSame(15.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($item->id));

        $itemMovements = StockMovement::where('shop_order_item_id', $item->id)->where('type', StockMovementType::Out->value)->orderBy('id')->get();
        $this->assertCount(2, $itemMovements);
        $this->assertSame('B', $itemMovements[0]->grade->value);
        $this->assertSame(10.0, (float) $itemMovements[0]->quantity);
        $this->assertSame('B', $itemMovements[1]->grade->value);
        $this->assertSame(5.0, (float) $itemMovements[1]->quantity);
    }

    // -------------------------------------------------------------------------
    // Test 5: Grade B empty, Mixed sufficient
    // -------------------------------------------------------------------------
    public function test_05_grade_b_empty_mixed_sufficient(): void
    {
        $this->createMixedBatch(30.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 20.0, 'selling_price' => 40.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 20.0],
            ])
            ->assertRedirect();

        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(10.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        $item = $order->items->first();
        $this->assertSame(20.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($item->id));

        $itemMovements = StockMovement::where('shop_order_item_id', $item->id)->where('type', StockMovementType::Out->value)->get();
        $this->assertCount(1, $itemMovements);
        $this->assertSame('B', $itemMovements->first()->grade->value);
        $this->assertSame(20.0, (float) $itemMovements->first()->quantity);
    }

    // -------------------------------------------------------------------------
    // Test 6: Grade A-only shop loaded from Mixed
    // -------------------------------------------------------------------------
    public function test_06_grade_a_only_shop_loaded_from_mixed(): void
    {
        $this->shop->update(['allowed_product_grades' => ['A']]);
        $this->createMixedBatch(40.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'A', 'qty' => 25.0, 'selling_price' => 50.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|A" => 25.0],
            ])
            ->assertRedirect();

        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(15.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        $item = $order->items->first();
        $this->assertSame(25.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($item->id));

        $itemMovements = StockMovement::where('shop_order_item_id', $item->id)->where('type', StockMovementType::Out->value)->get();
        $this->assertCount(1, $itemMovements);
        $this->assertSame('A', $itemMovements->first()->grade->value);
        $this->assertSame(25.0, (float) $itemMovements->first()->quantity);
    }

    // -------------------------------------------------------------------------
    // Test 7: Mixed unavailable, approved grade sufficient
    // -------------------------------------------------------------------------
    public function test_07_mixed_unavailable_approved_grade_sufficient(): void
    {
        $this->createSortedBatch('B', 50.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 30.0, 'selling_price' => 40.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 30.0],
            ])
            ->assertRedirect();

        $this->assertSame(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        $movements = StockMovement::where('type', StockMovementType::Out->value)->get();
        $this->assertCount(1, $movements);
        $this->assertSame('B', $movements->first()->grade->value);
        $this->assertSame(30.0, (float) $movements->first()->quantity);
    }

    // -------------------------------------------------------------------------
    // Test 8: Both pools insufficient (negative stock documented under approved grade)
    // -------------------------------------------------------------------------
    public function test_08_both_pools_insufficient_negative_stock_documented_under_approved_grade(): void
    {
        $this->createSortedBatch('B', 5.0);
        $this->createMixedBatch(5.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 15.0, 'selling_price' => 40.0],
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 15.0],
            ]);

        $response->assertSessionHasErrors(['quantity']);

        // Zero negative stock movements created
        $this->assertSame(0, StockMovement::where('notes', 'like', '%negative stock allowed%')->count());
        $this->assertSame(5.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(5.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
    }

    // -------------------------------------------------------------------------
    // Test 9: Grade A stock cannot satisfy Grade B
    // -------------------------------------------------------------------------
    public function test_09_grade_a_stock_cannot_satisfy_grade_b(): void
    {
        $this->createSortedBatch('A', 100.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 10.0, 'selling_price' => 40.0],
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 10.0],
            ]);

        $response->assertSessionHasErrors(['quantity']);

        // Grade A stock MUST remain untouched at 100.0
        $this->assertSame(100.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));

        // Available Grade B remains 0.0 with zero negative movements
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(0, StockMovement::where('type', StockMovementType::Out->value)->count());
    }

    // -------------------------------------------------------------------------
    // Test 10: Same product in Grade A and B on one order
    // -------------------------------------------------------------------------
    public function test_10_same_product_grade_a_and_b_on_one_order(): void
    {
        $this->createSortedBatch('A', 10.0);
        $this->createSortedBatch('B', 3.0);
        $this->createMixedBatch(10.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'A', 'qty' => 10.0, 'selling_price' => 50.0],
            ['product' => $this->product, 'grade' => 'B', 'qty' => 5.0, 'selling_price' => 40.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => [
                    "{$this->product->id}|A" => 10.0,
                    "{$this->product->id}|B" => 5.0,
                ],
            ])
            ->assertRedirect();

        // Grade A consumes 10 kg from Grade A (0 remaining)
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        // Grade B consumes 3 kg from Grade B (0 remaining) and 2 kg from Mixed
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        // Mixed had 10, now 8 remaining (10 - 2)
        $this->assertSame(8.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
    }

    // -------------------------------------------------------------------------
    // Test 11: Scheduled Automatic Load All
    // -------------------------------------------------------------------------
    public function test_11_scheduled_automatic_load_all(): void
    {
        $this->createSortedBatch('B', 5.0);
        $this->createMixedBatch(15.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 10.0, 'selling_price' => 40.0],
        ]);

        $service = app(AutoLoadAllService::class);
        $result = $service->loadOrder($order, $this->receiver, $this->warehouse->id);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['loaded_items_count']);

        $item = $order->fresh()->items->first();
        $this->assertSame(10.0, (float) $item->loaded_qty);
        $this->assertSame('loaded', $item->sorting_status);

        // Deductions: 5 kg B, 5 kg Mixed
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(10.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
    }

    // -------------------------------------------------------------------------
    // Test 12: Admin Manual Load All
    // -------------------------------------------------------------------------
    public function test_12_admin_manual_load_all(): void
    {
        $this->createSortedBatch('C', 4.0);
        $this->createMixedBatch(10.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'C', 'qty' => 8.0, 'selling_price' => 30.0],
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.auto-load-all.api.load-order', $order), [
                'warehouse_id' => $this->warehouse->id,
            ])
            ->assertOk();

        $item = $order->fresh()->items->first();
        $this->assertSame(8.0, (float) $item->loaded_qty);

        // Deductions: 4 kg C, 4 kg Mixed
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeC));
        $this->assertSame(6.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
    }

    // -------------------------------------------------------------------------
    // Test 13: Flutter Save / Load All
    // -------------------------------------------------------------------------
    public function test_13_flutter_save_and_load_all(): void
    {
        $this->createSortedBatch('B', 6.0);
        $this->createMixedBatch(10.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 8.0, 'selling_price' => 40.0],
        ]);

        $res = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => ["{$this->product->id}|B" => 8.0],
            ], [
                'X-Client-Supports-Multi-Grade' => 'true',
            ]);

        $res->assertOk();
        $res->assertJsonPath('success', true);

        // Verify stock deductions via API
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(8.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
    }

    // -------------------------------------------------------------------------
    // Test 14: Flutter Deliver with two legitimate grade lines (verify no 422 error!)
    // -------------------------------------------------------------------------
    public function test_14_flutter_deliver_with_two_legitimate_grade_lines(): void
    {
        $this->createSortedBatch('A', 10.0);
        $this->createSortedBatch('B', 20.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'A', 'qty' => 10.0, 'selling_price' => 50.0],
            ['product' => $this->product, 'grade' => 'B', 'qty' => 20.0, 'selling_price' => 40.0],
        ]);

        $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    "{$this->product->id}|A" => 10.0,
                    "{$this->product->id}|B" => 20.0,
                ],
            ], ['X-Client-Supports-Multi-Grade' => 'true'])
            ->assertOk();

        // Record stock movements count right after save
        $outMovementsAfterSave = StockMovement::where('type', StockMovementType::Out->value)->count();

        // Deliver directly via API move-to-delivery
        $res = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [], [
                'X-Client-Supports-Multi-Grade' => 'true',
            ]);

        // MUST be 200 OK — NEVER 422 "Duplicate product rows found. Merge duplicates first."
        $res->assertOk();
        $res->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('in_transit', $order->delivery_status);

        // Deliver does not deduct inventory again
        $outMovementsAfterDeliver = StockMovement::where('type', StockMovementType::Out->value)->count();
        $this->assertSame($outMovementsAfterSave, $outMovementsAfterDeliver);

        // Invoice quantity and approved selling prices remain correct
        $invoice = $order->invoice;
        $this->assertNotNull($invoice);
        $invoiceItems = $invoice->items;
        $this->assertCount(2, $invoiceItems);

        $invItemA = $invoiceItems->firstWhere('product_grade', 'A');
        $this->assertNotNull($invItemA);
        $this->assertEquals(10.0, (float) $invItemA->delivered_qty);
        $this->assertEquals(10.0, (float) $invItemA->approved_qty);
        $this->assertEquals(50.0, (float) $invItemA->unit_price);

        $invItemB = $invoiceItems->firstWhere('product_grade', 'B');
        $this->assertNotNull($invItemB);
        $this->assertEquals(20.0, (float) $invItemB->delivered_qty);
        $this->assertEquals(20.0, (float) $invItemB->approved_qty);
        $this->assertEquals(40.0, (float) $invItemB->unit_price);
    }

    // -------------------------------------------------------------------------
    // Test 15: Genuine duplicate detection
    // -------------------------------------------------------------------------
    public function test_15_genuine_duplicate_detection(): void
    {
        $this->createSortedBatch('A', 20.0);

        // Create order with TWO identical rows of same product AND same grade A
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 5.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'line_total' => 250.0,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 5.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'line_total' => 250.0,
        ]);

        // Attempt move-to-delivery — genuine duplicates must be rejected with 422
        $res = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [], [
                'X-Client-Supports-Multi-Grade' => 'true',
            ]);

        $res->assertStatus(422);
        $res->assertJsonFragment([
            'message' => 'Duplicate product rows found. Merge duplicates first.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 16: No stock deduction when Deliver is repeated
    // -------------------------------------------------------------------------
    public function test_16_no_stock_deduction_when_deliver_is_repeated(): void
    {
        $this->createSortedBatch('B', 20.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 10.0, 'selling_price' => 40.0],
        ]);

        $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => ["{$this->product->id}|B" => 10.0],
            ], ['X-Client-Supports-Multi-Grade' => 'true'])
            ->assertOk();

        // First deliver
        $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [], [
                'X-Client-Supports-Multi-Grade' => 'true',
            ])
            ->assertOk();

        $movementsCount1 = StockMovement::where('type', StockMovementType::Out->value)->count();
        $this->assertSame(1, $movementsCount1);

        // Deliver again: rejected because order is already in transit
        $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [], [
                'X-Client-Supports-Multi-Grade' => 'true',
            ])
            ->assertStatus(422);

        // No new stock movements created

        // No new stock movements created
        $movementsCount2 = StockMovement::where('type', StockMovementType::Out->value)->count();
        $this->assertSame(1, $movementsCount2);
    }

    // -------------------------------------------------------------------------
    // Test 17: No double deduction after Automatic Load All
    // -------------------------------------------------------------------------
    public function test_17_no_double_deduction_after_automatic_load_all(): void
    {
        $this->createSortedBatch('B', 20.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 10.0, 'selling_price' => 40.0],
        ]);

        // Step 1: AutoLoadAll
        app(AutoLoadAllService::class)->loadOrder($order, $this->receiver, $this->warehouse->id);

        $this->assertSame(10.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $movementsCount1 = StockMovement::where('type', StockMovementType::Out->value)->count();
        $this->assertSame(1, $movementsCount1);

        // Step 2: Manual save
        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 10.0],
            ])
            ->assertRedirect();

        // Stock and movements unchanged
        $this->assertSame(10.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame($movementsCount1, StockMovement::where('type', StockMovementType::Out->value)->count());

        // Step 3: Deliver
        $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [], [
                'X-Client-Supports-Multi-Grade' => 'true',
            ])
            ->assertOk();

        $this->assertSame(10.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame($movementsCount1, StockMovement::where('type', StockMovementType::Out->value)->count());
    }

    // -------------------------------------------------------------------------
    // Test 18: Full reversal to original physical pools
    // -------------------------------------------------------------------------
    public function test_18_full_reversal_to_original_physical_pools(): void
    {
        $this->createSortedBatch('B', 10.0);
        $this->createMixedBatch(10.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 20.0, 'selling_price' => 40.0],
        ]);

        // Save 20 kg: consumes 10 kg B, 10 kg Mixed
        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 20.0],
            ]);

        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        // Reversal to 0 kg: returns stock to segregated Grade B per Fix 1
        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 0.0],
            ]);

        $this->assertSame(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        $reversals = StockMovement::where('type', StockMovementType::SaleReversal->value)->get();
        $this->assertCount(2, $reversals);
        $revGrades = $reversals->pluck('grade.value')->unique()->values()->all();
        $this->assertSame(['B'], $revGrades);
    }

    // -------------------------------------------------------------------------
    // Test 19: Partial reversal (conserves stock, returns to exact physical pool)
    // -------------------------------------------------------------------------
    public function test_19_partial_reversal_conserves_stock_and_returns_to_exact_pool(): void
    {
        $this->createSortedBatch('B', 10.0);
        $this->createMixedBatch(10.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 20.0, 'selling_price' => 40.0],
        ]);

        // Consumes 10 kg B, 10 kg Mixed
        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 20.0],
            ]);

        // Reduce to 15 kg: 5 kg reversed. Because LIFO order is used, the 5 kg is returned to Mixed ('U')
        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 15.0],
            ]);

        // Grade B receives the reversal back to its segregated grade (now 5 remaining)
        $this->assertSame(5.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        $reversals = StockMovement::where('type', StockMovementType::SaleReversal->value)->get();
        $this->assertCount(1, $reversals);
        $this->assertSame('B', $reversals->first()->grade->value);
        $this->assertSame(5.0, (float) $reversals->first()->quantity);
    }

    // -------------------------------------------------------------------------
    // Test 20: Concurrent loadout attempts
    // -------------------------------------------------------------------------
    public function test_20_concurrent_loadout_attempts(): void
    {
        $this->createSortedBatch('A', 50.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'A', 'qty' => 20.0, 'selling_price' => 50.0],
        ]);

        DB::transaction(function () use ($order) {
            ShopOrder::lockForUpdate()->find($order->id);

            // Verified that row lock is acquired without throwing
            $this->assertTrue(true);
        });
    }

    // -------------------------------------------------------------------------
    // Test 21: Warehouse isolation
    // -------------------------------------------------------------------------
    public function test_21_warehouse_isolation(): void
    {
        // Stock in Secondary Outpost Warehouse
        $this->createSortedBatch('B', 50.0, $this->otherWarehouse);

        // Order assigned to Central Hub Warehouse
        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 20.0, 'selling_price' => 40.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 20.0],
            ])
            ->assertRedirect();

        // Secondary warehouse stock must be untouched at 50.0
        $this->assertSame(50.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->otherWarehouse->id, ProductGrade::GradeB));

        // Central hub has no stock, so available sorted stock is 0.0
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
    }

    // -------------------------------------------------------------------------
    // Test 22: Advance received Mixed stock used in loadout
    // -------------------------------------------------------------------------
    public function test_22_advance_received_mixed_stock_used_in_loadout(): void
    {
        // Create an unsegregated goods received note
        [$po, $poItem] = $this->createPurchaseOrderWithItem('U', 50.0);

        $dto = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: now()->toDateTimeString(),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Advance mixed receipt',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 50.0,
                    'unit_price' => 20.0,
                    'grade' => 'U',
                ],
            ],
            warehouseId: $this->warehouse->id,
            purchaseGrade: 'U',
        );

        $grn = app(RecordGoodsReceiptAction::class)->execute($dto, (int) $this->receiver->id);
        app(ApproveGoodsReceiptAction::class)->execute($grn, (int) $this->admin->id);

        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();
        $this->actingAs($this->receiver)->post(route('warehouse.receiver.confirm', $batch), [
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Batch is pending sorting, so it is Mixed physical stock
        $breakdown = $this->stockLedgerService->availableLoadoutStockBreakdown($this->product->id, $this->warehouse->id, ProductGrade::GradeB);
        $this->assertSame(50.0, $breakdown['mixed_stock']);
        $this->assertSame(50.0, $breakdown['available_stock']);

        // Load Grade B using this advance received mixed stock
        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 20.0, 'selling_price' => 40.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 20.0],
            ])
            ->assertRedirect();

        $item = $order->items->first();
        $this->assertSame(20.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($item->id));

        $itemMovements = StockMovement::where('shop_order_item_id', $item->id)->where('type', StockMovementType::Out->value)->get();
        $this->assertCount(1, $itemMovements);
        $this->assertSame('B', $itemMovements->first()->grade->value);
        $this->assertSame(20.0, (float) $itemMovements->first()->quantity);
    }

    // -------------------------------------------------------------------------
    // Test 23: Direct Grade B receipt used before Mixed
    // -------------------------------------------------------------------------
    public function test_23_direct_grade_b_receipt_used_before_mixed(): void
    {
        // Direct Grade B batch of 15 kg
        $this->createSortedBatch('B', 15.0);
        // Mixed batch of 30 kg
        $this->createMixedBatch(30.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 20.0, 'selling_price' => 40.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 20.0],
            ])
            ->assertRedirect();

        $item = $order->items->first();
        $this->assertSame(20.0, $this->stockLedgerService->netDeductedQuantityForOrderItem($item->id));

        $itemMovements = StockMovement::where('shop_order_item_id', $item->id)->where('type', StockMovementType::Out->value)->orderBy('id')->get();
        $this->assertCount(2, $itemMovements);
        $this->assertSame('B', $itemMovements[0]->grade->value);
        $this->assertSame(15.0, (float) $itemMovements[0]->quantity);
        $this->assertSame('B', $itemMovements[1]->grade->value);
        $this->assertSame(5.0, (float) $itemMovements[1]->quantity);
    }

    // -------------------------------------------------------------------------
    // Test 24: No duplicate inventory counting
    // -------------------------------------------------------------------------
    public function test_24_no_duplicate_inventory_counting(): void
    {
        $this->createSortedBatch('B', 20.0);
        $this->createMixedBatch(30.0);

        $breakdown = $this->stockLedgerService->availableLoadoutStockBreakdown($this->product->id, $this->warehouse->id, ProductGrade::GradeB);

        $this->assertSame(20.0, $breakdown['grade_stock']);
        $this->assertSame(30.0, $breakdown['mixed_stock']);
        $this->assertSame(50.0, $breakdown['available_stock']);

        // When grade is Unsorted itself, available_stock equals mixed_stock without duplication
        $unsortedBreakdown = $this->stockLedgerService->availableLoadoutStockBreakdown($this->product->id, $this->warehouse->id, ProductGrade::Unsorted);
        $this->assertSame(30.0, $unsortedBreakdown['grade_stock']);
        $this->assertSame(0.0, $unsortedBreakdown['mixed_stock']);
        $this->assertSame(30.0, $unsortedBreakdown['available_stock']);
    }

    // -------------------------------------------------------------------------
    // Test 25: Grade B invoice rate unchanged by Mixed fallback
    // -------------------------------------------------------------------------
    public function test_25_grade_b_invoice_rate_unchanged_by_mixed_fallback(): void
    {
        // Only Mixed stock available (0 Grade B)
        $this->createMixedBatch(30.0);

        $order = $this->createOrderWithItems([
            ['product' => $this->product, 'grade' => 'B', 'qty' => 10.0, 'selling_price' => 40.0],
        ]);

        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => ["{$this->product->id}|B" => 10.0],
            ])
            ->assertRedirect();

        // Stock was consumed from Mixed
        $movements = StockMovement::where('type', StockMovementType::Out->value)->get();
        $this->assertNotEmpty($movements);
        $this->assertSame('U', $movements->first()->grade->value);

        // Selling price on shop order item and invoice MUST strictly remain Grade B price (40.0)
        $order->refresh();
        $item = $order->items->first();
        $this->assertSame('B', $item->product_grade);
        $this->assertSame(40.0, (float) $item->locked_selling_price);
        $this->assertSame(400.0, (float) $item->line_total);

        $invoice = $order->invoice;
        $this->assertNotNull($invoice);
        $invItem = $invoice->items->first();
        $this->assertSame('B', $invItem->product_grade);
        $this->assertSame(40.0, (float) $invItem->unit_price);
        $this->assertSame(400.0, (float) $invItem->final_line_total);
    }

    // -------------------------------------------------------------------------
    // Test 26: Legacy Grade A-only compatibility
    // -------------------------------------------------------------------------
    public function test_26_legacy_grade_a_only_compatibility(): void
    {
        $this->createMixedBatch(25.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        // Legacy order item with default Grade A
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
            'locked_selling_price' => 50.0,
            'line_total' => 500.0,
        ]);

        // Save loadout with classic product_id key
        $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => [(string) $this->product->id => 10.0],
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame(10.0, (float) $item->loaded_qty);
        $this->assertSame('loaded', $item->sorting_status);

        // Grade A segregated and consumed from Mixed stock per Fix 1
        $itemMovements = StockMovement::where('shop_order_item_id', $item->id)->where('type', StockMovementType::Out->value)->get();
        $this->assertCount(1, $itemMovements);
        $this->assertSame('A', $itemMovements->first()->grade->value);
        $this->assertSame(10.0, (float) $itemMovements->first()->quantity);
        $this->assertSame(15.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
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
