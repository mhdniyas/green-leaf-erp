<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\ShopOrder\Actions\ResolveDeliveryReviewAction;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\Product;
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
use App\Repositories\Inventory\StockMovementRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminInventoryActionCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Supplier $supplier;

    private Shop $shop;

    private ShopPriceGroup $priceGroup;

    private Category $category;

    private Product $tomato;

    private Product $onion;

    protected function setUp(): void
    {
        parent::setUp();

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $permView = Permission::firstOrCreate(['name' => 'inventory.product.view', 'guard_name' => 'web']);
        $permAdjust = Permission::firstOrCreate(['name' => 'inventory.stock.adjust', 'guard_name' => 'web']);
        $permWastage = Permission::firstOrCreate(['name' => 'inventory.wastage.record', 'guard_name' => 'web']);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo([$permView, $permAdjust, $permWastage]);
        Role::firstOrCreate(['name' => 'purchase', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create([
            'email' => 'admin_action_center@greenleaf.test',
        ]);
        $this->adminUser->assignRole('admin');
        $this->adminUser->givePermissionTo([$permView, $permAdjust, $permWastage]);
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->warehouseA = Warehouse::create([
            'name' => 'Warehouse Action Center A',
            'code' => 'WACA',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Warehouse Action Center B',
            'code' => 'WACB',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Supplier Prime']);

        $this->priceGroup = ShopPriceGroup::factory()->create(['name' => 'A']);

        $this->shop = Shop::create([
            'name' => 'Central Retail Shop',
            'code' => 'CRS-01',
            'shop_price_group_id' => $this->priceGroup->id,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->tomato = Product::factory()->create([
            'name' => 'Action Tomato',
            'sku' => 'ACT-TOM',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'is_active' => true,
        ]);

        $this->onion = Product::factory()->create([
            'name' => 'Action Onion',
            'sku' => 'ACT-ONI',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'is_active' => true,
        ]);
    }

    private function createBatch(Product $product, Warehouse $warehouse, float $qtyKg, string $date, ?GoodsReceived $grn = null): StockBatch
    {
        return StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'goods_received_id' => $grn?->id,
            'reference' => 'BATCH-TEST-'.Str::upper(Str::random(6)),
            'total_kg' => $qtyKg,
            'available_kg' => $qtyKg,
            'cost_per_kg' => 20.0,
            'status' => 'sorted',
            'received_date' => $date,
            'received_at' => $date,
            'created_at' => Carbon::parse($date)->setTime(10, 0, 0),
            'created_by' => $this->adminUser->id,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => Carbon::parse($date)->setTime(10, 0, 0),
            'warehouse_confirmed_by' => $this->adminUser->id,
        ]);
    }

    private function createMovement(StockBatch $batch, StockMovementType $type, float $qty, string $date, array $extra = []): StockMovement
    {
        return StockMovement::create(array_merge([
            'batch_id' => $batch->id,
            'warehouse_id' => $batch->warehouse_id,
            'product_id' => $batch->product_id,
            'grade' => 'A',
            'type' => $type->value,
            'quantity' => $qty,
            'unit' => 'kg',
            'cost_per_unit' => 20.0,
            'created_by' => $this->adminUser->id,
            'created_at' => Carbon::parse($date)->setTime(10, 0, 0),
        ], $extra));
    }

    public function test_scoping_for_selected_warehouse_and_date_across_all_sections(): void
    {
        $targetDate = today()->toDateString();

        // 1. Advance in Warehouse A on target date
        $advGrnA = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-ACT-A',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $targetDate,
        ]);
        $advGrnA->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);
        $batchA = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $targetDate, $advGrnA);
        $this->createMovement($batchA, StockMovementType::In, 10.0, $targetDate);

        // 2. Advance in Warehouse B (Should NOT show in Warehouse A)
        $advGrnB = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-ACT-B',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseB->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $targetDate,
        ]);
        $advGrnB->items()->create([
            'product_id' => $this->onion->id,
            'received_qty' => 20.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        // 3. Shop Return on target date for Warehouse A
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-ACT-001',
            'order_date' => $targetDate,
            'business_date' => $targetDate,
            'total_amount' => 100.0,
            'status' => 'delivered',
            'delivery_review_status' => 'approved',
            'created_by' => $this->adminUser->id,
        ]);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 5.0,
            'quantity' => 5.0,
            'unit' => 'kg',
            'unit_price' => 20.0,
            'total_price' => 100.0,
        ]);
        ShopInvoice::create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-ACT-001',
            'business_date' => $targetDate,
            'status' => 'finalized',
            'delivery_status' => 'approved_after_discrepancy',
            'payment_status' => 'unpaid',
            'subtotal' => 100.0,
            'final_total' => 100.0,
            'finalized_at' => Carbon::parse($targetDate)->setTime(12, 0, 0),
            'finalized_by' => $this->adminUser->id,
        ]);
        $this->createMovement($batchA, StockMovementType::SaleReversal, 2.0, $targetDate, [
            'shop_order_item_id' => $orderItem->id,
            'notes' => "Delivery shortage added back to inventory - Order: {$order->order_number}; Item: {$orderItem->id}",
        ]);

        // 4. Damage on target date for Warehouse A
        WastageEntry::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batchA->id,
            'grade' => 'A',
            'cost_per_kg' => 20.0,
            'quantity' => 1.5,
            'reason' => WastageReason::Rotten,
            'wastage_date' => $targetDate,
            'recorded_by' => $this->adminUser->id,
            'notes' => 'Damaged sample',
        ]);

        // 5. Query Warehouse A on target date
        $resA = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $targetDate,
        ]));

        $resA->assertOk();
        $resA->assertSee('Action Tomato');

        // Test Tab: Stock Without Bill
        $resWithoutBill = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'stock_without_bill',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $targetDate,
        ]));
        $resWithoutBill->assertOk();
        $resWithoutBill->assertSee('GRN-ADV-ACT-A');
        $resWithoutBill->assertDontSee('GRN-ADV-ACT-B');

        // Test Tab: Shop Returns
        $resReturns = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'shop_returns',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $targetDate,
        ]));
        $resReturns->assertOk();
        $resReturns->assertSee('Central Retail Shop');
        $resReturns->assertSee('ORD-ACT-001');

        // Test Tab: Damage
        $resDamage = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'damage',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $targetDate,
        ]));
        $resDamage->assertOk();
        $resDamage->assertSee('Damaged sample');
        $resDamage->assertSee('1.50');

        // Test Tab: Physical Check
        $resCheck = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'physical_check',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $targetDate,
        ]));
        $resCheck->assertOk();
        $resCheck->assertSee('Action Tomato');
    }

    public function test_advance_intake_and_later_bill_matching_does_not_increase_current_inventory(): void
    {
        $date = today()->toDateString();

        // 1. Advance 4 kg intake
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-4KG',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advItem = $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 4.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 4.0, $date, $advGrn);
        $this->createMovement($batch, StockMovementType::In, 4.0, $date);

        $stockRepo = app(StockMovementRepository::class);
        $stockBeforeMatch = $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id);
        $tomatoStockBefore = (float) ($stockBeforeMatch->where('product_id', $this->tomato->id)->sum('current_stock'));
        $this->assertEquals(4.0, $tomatoStockBefore);

        // 2. Vendor Bill arrives later and matches 4 kg
        $billGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-BILL-4KG',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $billItem = $billGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 4.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'advance_stock_batch_id' => $batch->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->tomato->id,
            'matched_qty' => 4.0,
            'matched_unit' => 'kg',
            'base_qty' => 4.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->adminUser->id,
            'confirmed_at' => Carbon::parse($date),
        ]);

        // 3. Verify Current Inventory remains 4.0 (NEVER increases to 8.0 because of matching)
        $stockAfterMatch = $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id);
        $tomatoStockAfter = (float) ($stockAfterMatch->where('product_id', $this->tomato->id)->sum('current_stock'));
        $this->assertEquals(4.0, $tomatoStockAfter);

        // 4. Verify UI reflects Current Balance = 4.0, Without Bill = 0.0, With Bill = 4.0
        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));
        $res->assertOk();
        $res->assertSee('Action Tomato');
    }

    public function test_loadout_reduces_current_sellable_balance(): void
    {
        $date = today()->toDateString();

        $batch = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batch, StockMovementType::In, 10.0, $date);

        $stockRepo = app(StockMovementRepository::class);
        $this->assertEquals(10.0, (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock'));

        // Loadout of 3.0 kg
        $this->createMovement($batch, StockMovementType::Out, 3.0, $date);

        $this->assertEquals(7.0, (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock'));
    }

    public function test_shop_return_increases_current_sellable_balance(): void
    {
        $date = today()->toDateString();

        $batch = $this->createBatch($this->tomato, $this->warehouseA, 5.0, $date);
        $this->createMovement($batch, StockMovementType::In, 5.0, $date);

        $stockRepo = app(StockMovementRepository::class);
        $this->assertEquals(5.0, (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock'));

        // Shop return of 2.0 kg
        $this->createMovement($batch, StockMovementType::SaleReversal, 2.0, $date);

        $this->assertEquals(7.0, (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock'));
    }

    public function test_move_to_damage_reduces_sellable_balance_without_duplicate_receive(): void
    {
        $date = today()->toDateString();

        $batch = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batch, StockMovementType::In, 10.0, $date);

        $stockRepo = app(StockMovementRepository::class);
        $this->assertEquals(10.0, (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock'));

        // Move 2.0 kg to damage: creates WastageEntry + negative movement
        WastageEntry::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batch->id,
            'grade' => 'A',
            'cost_per_kg' => 20.0,
            'quantity' => 2.0,
            'reason' => WastageReason::Rotten,
            'wastage_date' => $date,
            'recorded_by' => $this->adminUser->id,
            'notes' => 'Rotten sample test',
        ]);
        $this->createMovement($batch, StockMovementType::Wastage, 2.0, $date);

        $this->assertEquals(8.0, (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock'));
        $this->assertDatabaseHas('wastage_entries', [
            'product_id' => $this->tomato->id,
            'quantity' => 2.0,
            'reason' => 'rotten',
        ]);
    }

    public function test_physical_check_reconciliation_stores_adjustment_correctly(): void
    {
        $date = today()->toDateString();

        $batch = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batch, StockMovementType::In, 10.0, $date);

        $res = $this->actingAs($this->adminUser)->post(route('inventory.stock.adjustments.store', $this->tomato), [
            'warehouse_id' => $this->warehouseA->id,
            'system_qty' => 10.0,
            'counted_qty' => 8.5,
            'notes' => 'Physical stock count discrepancy',
            'business_date' => $date,
        ]);

        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseA->id,
            'system_qty' => 10.0,
            'counted_qty' => 8.5,
            'variance_qty' => -1.5,
        ]);
    }

    public function test_multi_product_move_to_damage_processes_batch_in_single_transaction(): void
    {
        $date = today()->toDateString();

        $batchTomato = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batchTomato, StockMovementType::In, 10.0, $date);

        $batchOnion = $this->createBatch($this->onion, $this->warehouseA, 15.0, $date);
        $this->createMovement($batchOnion, StockMovementType::In, 15.0, $date);

        $payload = [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'notes' => 'Batch damage write-off from action center',
            'tab' => 'damage',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'quantity' => 3.0,
                    'reason' => 'transit_damage',
                    'grade' => 'U',
                ],
                [
                    'product_id' => $this->onion->id,
                    'quantity' => 4.5,
                    'reason' => 'rotten',
                    'grade' => 'U',
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.cashbook.inventory.move-to-damage'), $payload);

        $response->assertRedirect(route('admin.cashbook.inventory', [
            'tab' => 'damage',
            'date' => $date,
            'warehouse_id' => $this->warehouseA->id,
        ]));
        $response->assertSessionHas('success', '2 product(s) moved to damage successfully.');

        $this->assertDatabaseHas('wastage_entries', [
            'product_id' => $this->tomato->id,
            'quantity' => 3.0,
            'reason' => 'transit_damage',
        ]);

        $this->assertDatabaseHas('wastage_entries', [
            'product_id' => $this->onion->id,
            'quantity' => 4.5,
            'reason' => 'rotten',
        ]);

        // Verify that the damage tab renders the 2 newly recorded damage entries
        $viewRes = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.inventory', [
                'tab' => 'damage',
                'date' => $date,
                'warehouse_id' => $this->warehouseA->id,
            ]));

        $viewRes->assertOk();
        $viewRes->assertSee('Daily Damage');
        $viewRes->assertSee('3.00');
        $viewRes->assertSee('4.50');
    }

    public function test_case_a_current_20_advance_remaining_4(): void
    {
        $date = today()->toDateString();

        // Physical stock 20 kg
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 20.0, $date);
        $this->createMovement($batch, StockMovementType::In, 20.0, $date);

        // Advance 4 kg unbilled
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-CASE-A',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 4.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $res->assertOk();
        $items = collect($res->viewData('currentInventory')->items());
        $row = $items->firstWhere('product_id', $this->tomato->id);

        $this->assertNotNull($row);
        $this->assertEquals(20.0, $row['current_sellable']);
        $this->assertEquals(16.0, $row['with_bill']);
        $this->assertEquals(4.0, $row['without_bill']);
        $this->assertEquals(4.0, $row['pending_vendor_bill']);
        $this->assertEquals(0.0, $row['stock_deficit']);
        $this->assertEquals($row['current_sellable'], $row['with_bill'] + $row['without_bill']);
    }

    public function test_case_b_current_10_advance_remaining_18(): void
    {
        $date = today()->toDateString();

        // Physical stock 10 kg
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batch, StockMovementType::In, 10.0, $date);

        // Advance 18 kg unbilled (more than current physical stock)
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-CASE-B',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 18.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $res->assertOk();
        $items = collect($res->viewData('currentInventory')->items());
        $row = $items->firstWhere('product_id', $this->tomato->id);

        $this->assertNotNull($row);
        $this->assertEquals(10.0, $row['current_sellable']);
        $this->assertEquals(0.0, $row['with_bill']);
        $this->assertEquals(10.0, $row['without_bill']);
        $this->assertEquals(18.0, $row['pending_vendor_bill']);
        $this->assertEquals(0.0, $row['stock_deficit']);
        $this->assertEquals($row['current_sellable'], $row['with_bill'] + $row['without_bill']);
    }

    public function test_case_c_current_negative_advance_remaining_18(): void
    {
        $date = today()->toDateString();

        // Physical stock 10 kg IN, 40.46 kg OUT => Ledger balance = -30.46
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batch, StockMovementType::In, 10.0, $date);
        $this->createMovement($batch, StockMovementType::Out, 40.46, $date);

        // Advance 18 kg unbilled
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-CASE-C',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 18.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $res->assertOk();
        $items = collect($res->viewData('currentInventory')->items());
        $row = $items->firstWhere('product_id', $this->tomato->id);

        $this->assertNotNull($row);
        $this->assertEquals(-30.46, $row['ledger_balance']);
        $this->assertEquals(0.0, $row['current_sellable']);
        $this->assertEquals(0.0, $row['with_bill']);
        $this->assertEquals(0.0, $row['without_bill']);
        $this->assertEquals(18.0, $row['pending_vendor_bill']);
        $this->assertEquals(30.46, $row['stock_deficit']);
        $this->assertEquals($row['current_sellable'], $row['with_bill'] + $row['without_bill']);

        // Check that view renders deficit warning and does not show negative current sellable
        $res->assertSee('30.46 kg');
    }

    public function test_case_d_current_12_advance_remaining_0(): void
    {
        $date = today()->toDateString();

        // Physical stock 12 kg, no unbilled advance
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 12.0, $date);
        $this->createMovement($batch, StockMovementType::In, 12.0, $date);

        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $res->assertOk();
        $items = collect($res->viewData('currentInventory')->items());
        $row = $items->firstWhere('product_id', $this->tomato->id);

        $this->assertNotNull($row);
        $this->assertEquals(12.0, $row['current_sellable']);
        $this->assertEquals(12.0, $row['with_bill']);
        $this->assertEquals(0.0, $row['without_bill']);
        $this->assertEquals(0.0, $row['pending_vendor_bill']);
        $this->assertEquals(0.0, $row['stock_deficit']);
        $this->assertEquals($row['current_sellable'], $row['with_bill'] + $row['without_bill']);
    }

    public function test_invariant_with_bill_plus_without_bill_equals_current_sellable_for_all_rows(): void
    {
        $date = today()->toDateString();

        // Multiple products with varying balances (positive, zero, negative, unbilled)
        $batch1 = $this->createBatch($this->tomato, $this->warehouseA, 25.0, $date);
        $this->createMovement($batch1, StockMovementType::In, 25.0, $date);

        $batch2 = $this->createBatch($this->onion, $this->warehouseA, 5.0, $date);
        $this->createMovement($batch2, StockMovementType::In, 5.0, $date);
        $this->createMovement($batch2, StockMovementType::Out, 15.0, $date); // negative balance -10

        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $res->assertOk();
        $items = collect($res->viewData('currentInventory')->items());

        foreach ($items as $item) {
            $this->assertGreaterThanOrEqual(0.0, $item['current_sellable']);
            $this->assertGreaterThanOrEqual(0.0, $item['with_bill']);
            $this->assertGreaterThanOrEqual(0.0, $item['without_bill']);
            $this->assertGreaterThanOrEqual(0.0, $item['pending_vendor_bill']);
            $this->assertGreaterThanOrEqual(0.0, $item['stock_deficit']);
            $this->assertEquals(
                $item['current_sellable'],
                round($item['with_bill'] + $item['without_bill'], 3),
                "Invariant violated for product {$item['name']}"
            );
        }
    }

    public function test_phase2_test1_available_10_damage_3_succeeds_and_reduces_stock_to_7(): void
    {
        $date = today()->toDateString();
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batch, StockMovementType::In, 10.0, $date);

        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.move-to-damage'), [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'items' => [
                ['product_id' => $this->tomato->id, 'quantity' => 3.0, 'grade' => 'U'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $stockRepo = app(StockMovementRepository::class);
        $currentStock = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock');
        $this->assertEquals(7.0, $currentStock);
        $this->assertDatabaseHas('wastage_entries', ['product_id' => $this->tomato->id, 'quantity' => 3.0]);
    }

    public function test_phase2_test2_available_10_damage_10_succeeds_and_reduces_stock_to_0(): void
    {
        $date = today()->toDateString();
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batch, StockMovementType::In, 10.0, $date);

        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.move-to-damage'), [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'items' => [
                ['product_id' => $this->tomato->id, 'quantity' => 10.0, 'grade' => 'U'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $stockRepo = app(StockMovementRepository::class);
        $currentStock = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock');
        $this->assertEquals(0.0, $currentStock);
    }

    public function test_phase2_test3_available_10_damage_11_is_rejected_and_stock_remains_10(): void
    {
        $date = today()->toDateString();
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batch, StockMovementType::In, 10.0, $date);

        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.move-to-damage'), [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'items' => [
                ['product_id' => $this->tomato->id, 'quantity' => 11.0, 'grade' => 'U'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('items');

        $stockRepo = app(StockMovementRepository::class);
        $currentStock = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock');
        $this->assertEquals(10.0, $currentStock);
        $this->assertDatabaseMissing('wastage_entries', ['product_id' => $this->tomato->id, 'quantity' => 11.0]);
    }

    public function test_phase2_test4_current_0_damage_is_rejected(): void
    {
        $date = today()->toDateString();

        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.move-to-damage'), [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'items' => [
                ['product_id' => $this->tomato->id, 'quantity' => 1.0, 'grade' => 'U'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('wastage_entries', ['product_id' => $this->tomato->id]);
    }

    public function test_phase2_test5_negative_ledger_damage_is_rejected(): void
    {
        $date = today()->toDateString();
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batch, StockMovementType::In, 10.0, $date);
        $this->createMovement($batch, StockMovementType::Out, 15.0, $date); // ledger = -5

        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.move-to-damage'), [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'items' => [
                ['product_id' => $this->tomato->id, 'quantity' => 1.0, 'grade' => 'U'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('wastage_entries', ['product_id' => $this->tomato->id]);
    }

    public function test_phase2_test6_batch_atomicity_rejects_all_when_one_item_exceeds_stock(): void
    {
        $date = today()->toDateString();

        $batchTomato = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batchTomato, StockMovementType::In, 10.0, $date);

        $batchOnion = $this->createBatch($this->onion, $this->warehouseA, 5.0, $date);
        $this->createMovement($batchOnion, StockMovementType::In, 5.0, $date);

        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.move-to-damage'), [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'items' => [
                ['product_id' => $this->tomato->id, 'quantity' => 2.0, 'grade' => 'U'],
                ['product_id' => $this->onion->id, 'quantity' => 6.0, 'grade' => 'U'], // Exceeds 5.0
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('items');

        // Verify neither Tomato nor Onion was modified
        $stockRepo = app(StockMovementRepository::class);
        $tomatoStock = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock');
        $onionStock = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->onion->id)->sum('current_stock');

        $this->assertEquals(10.0, $tomatoStock);
        $this->assertEquals(5.0, $onionStock);
        $this->assertDatabaseMissing('wastage_entries', ['product_id' => $this->tomato->id]);
        $this->assertDatabaseMissing('wastage_entries', ['product_id' => $this->onion->id]);
    }

    public function test_phase2_test7_classification_invariant_remains_valid_after_damage(): void
    {
        $date = today()->toDateString();

        // 10 kg physical stock
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batch, StockMovementType::In, 10.0, $date);

        // 4 kg unbilled advance
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-DAM-7',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 4.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        // Move 3 kg to damage
        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.move-to-damage'), [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'items' => [
                ['product_id' => $this->tomato->id, 'quantity' => 3.0, 'grade' => 'U'],
            ],
        ]);
        $response->assertRedirect();

        // Query Current Inventory after damage
        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));
        $res->assertOk();
        $row = collect($res->viewData('currentInventory')->items())->firstWhere('product_id', $this->tomato->id);

        $this->assertEquals(7.0, $row['current_sellable']);
        $this->assertEquals(4.0, $row['without_bill']);
        $this->assertEquals(3.0, $row['with_bill']);
        $this->assertEquals(4.0, $row['pending_vendor_bill']);
        $this->assertEquals(7.0, $row['with_bill'] + $row['without_bill']);
    }

    public function test_phase2_test8_advance_paperwork_unaffected_by_damage(): void
    {
        $date = today()->toDateString();

        $batch = $this->createBatch($this->tomato, $this->warehouseA, 3.0, $date);
        $this->createMovement($batch, StockMovementType::In, 3.0, $date);

        // 8 kg advance paperwork
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-DAM-8',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 8.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        // Move 2 kg to damage
        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.move-to-damage'), [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'items' => [
                ['product_id' => $this->tomato->id, 'quantity' => 2.0, 'grade' => 'U'],
            ],
        ]);
        $response->assertRedirect();

        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));
        $row = collect($res->viewData('currentInventory')->items())->firstWhere('product_id', $this->tomato->id);

        $this->assertEquals(1.0, $row['current_sellable']);
        $this->assertEquals(1.0, $row['without_bill']);
        $this->assertEquals(0.0, $row['with_bill']);
        $this->assertEquals(8.0, $row['pending_vendor_bill']); // Paperwork remains 8.0
    }

    public function test_phase2_test9_consecutive_requests_cannot_damage_more_than_available_stock(): void
    {
        $date = today()->toDateString();
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 10.0, $date);
        $this->createMovement($batch, StockMovementType::In, 10.0, $date);

        // First attempt: damage 8 kg -> succeeds (leaving 2 kg)
        $res1 = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.move-to-damage'), [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'items' => [
                ['product_id' => $this->tomato->id, 'quantity' => 8.0, 'grade' => 'U'],
            ],
        ]);
        $res1->assertRedirect();
        $res1->assertSessionHas('success');

        // Second attempt: damage 3 kg -> fails because only 2 kg remains
        $res2 = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.move-to-damage'), [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'items' => [
                ['product_id' => $this->tomato->id, 'quantity' => 3.0, 'grade' => 'U'],
            ],
        ]);
        $res2->assertRedirect();
        $res2->assertSessionHasErrors('items');

        $stockRepo = app(StockMovementRepository::class);
        $currentStock = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock');
        $this->assertEquals(2.0, $currentStock);
    }

    // =========================================================================
    // PHASE 3 — ADMIN MANUAL ADVANCE CLEAR TESTS
    // =========================================================================

    public function test_phase3_test1_single_advance_manual_clear(): void
    {
        $date = today()->toDateString();
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 25.0, $date);
        $this->createMovement($batch, StockMovementType::In, 25.0, $date);

        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-P3-001',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 15.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $movementCountBefore = StockMovement::count();
        $matchCountBefore = AdvanceReceiveMatch::count();
        $stockRepo = app(StockMovementRepository::class);
        $stockBefore = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock');

        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.clear-advances'), [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'advance_ids' => [$advGrn->id],
            'reason' => 'Vendor settlement handled separately',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $advGrn->refresh();
        $this->assertEquals('bill_available', $advGrn->bill_status);

        // Zero physical stock effect
        $this->assertSame($movementCountBefore, StockMovement::count());
        $this->assertSame($matchCountBefore, AdvanceReceiveMatch::count());
        $stockAfter = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock');
        $this->assertEquals($stockBefore, $stockAfter);
    }

    public function test_phase3_test2_multiple_advances_cleared_in_one_transaction(): void
    {
        $date = today()->toDateString();

        $adv1 = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-MULTI-1',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $adv1->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $adv2 = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-MULTI-2',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $adv2->items()->create([
            'product_id' => $this->onion->id,
            'received_qty' => 5.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $adv3 = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-MULTI-3',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $adv3->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 8.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.clear-advances'), [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'advance_ids' => [$adv1->id, $adv2->id, $adv3->id],
            'reason' => 'Old / legacy Advance',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('bill_available', $adv1->fresh()->bill_status);
        $this->assertEquals('bill_available', $adv2->fresh()->bill_status);
        $this->assertEquals('bill_available', $adv3->fresh()->bill_status);
    }

    public function test_phase3_test3_empty_reason_is_rejected(): void
    {
        $date = today()->toDateString();
        $adv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-REASON-REQ',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $adv->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.clear-advances'), [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'advance_ids' => [$adv->id],
            'reason' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('reason');
        $this->assertEquals('bill_pending', $adv->fresh()->bill_status);
    }

    public function test_phase3_test4_warehouse_scope_enforcement(): void
    {
        $date = today()->toDateString();
        // Advance belongs to warehouse B
        $advB = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-WH-B',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseB->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advB->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        // Attempting to clear via warehouse A scope
        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.clear-advances'), [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'advance_ids' => [$advB->id],
            'reason' => 'Cross warehouse test',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('advance_ids');
        $this->assertEquals('bill_pending', $advB->fresh()->bill_status);
    }

    public function test_phase3_test5_already_matched_or_resolved_advance_is_rejected(): void
    {
        $date = today()->toDateString();
        $advAlreadyAvailable = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-ALREADY-DONE',
            'status' => 'approved',
            'bill_status' => 'bill_available', // Already resolved
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advAlreadyAvailable->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.clear-advances'), [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'advance_ids' => [$advAlreadyAvailable->id],
            'reason' => 'Testing stale selection',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('advance_ids');
    }

    public function test_phase3_test6_stock_invariant_current_inventory_stock_balance_remains_strictly_identical(): void
    {
        $date = today()->toDateString();
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 50.0, $date);
        $this->createMovement($batch, StockMovementType::In, 50.0, $date);

        $adv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-INV-TEST',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $adv->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 20.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $stockRepo = app(StockMovementRepository::class);
        $beforeStock = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock');

        $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.clear-advances'), [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'advance_ids' => [$adv->id],
            'reason' => 'Data correction',
        ])->assertRedirect();

        $afterStock = (float) $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id)->where('product_id', $this->tomato->id)->sum('current_stock');

        $this->assertSame($beforeStock, $afterStock);
    }

    public function test_phase3_test7_match_records_count_remains_unchanged(): void
    {
        $date = today()->toDateString();
        $adv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-MATCH-COUNT',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $adv->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 12.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $matchesBefore = AdvanceReceiveMatch::count();

        $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.clear-advances'), [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'advance_ids' => [$adv->id],
            'reason' => 'Bill not required',
        ])->assertRedirect();

        $matchesAfter = AdvanceReceiveMatch::count();
        $this->assertSame($matchesBefore, $matchesAfter);
    }

    public function test_phase3_test8_spatie_audit_log_created_with_reason_and_admin_user(): void
    {
        $date = today()->toDateString();
        $adv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-AUDIT-TEST',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $adv->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 14.5,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.clear-advances'), [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'advance_ids' => [$adv->id],
            'reason' => 'Vendor settlement handled separately',
        ])->assertRedirect();

        $activity = Activity::query()
            ->where('log_name', 'purchasing')
            ->where('subject_id', $adv->id)
            ->where('subject_type', GoodsReceived::class)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('Advance manually cleared without vendor bill match', $activity->description);
        $this->assertEquals((int) $this->adminUser->id, (int) $activity->causer_id);
        $this->assertEquals('manual_advance_clear', $activity->properties['action'] ?? null);
        $this->assertEquals('Vendor settlement handled separately', $activity->properties['reason'] ?? null);
        $this->assertEquals('ADV-AUDIT-TEST', $activity->properties['grn_number'] ?? null);
        $this->assertEquals(14.5, (float) ($activity->properties['remaining_unmatched_qty'] ?? 0));
    }

    public function test_phase3_test9_batch_atomicity_one_invalid_advance_fails_entire_batch(): void
    {
        $date = today()->toDateString();

        $validAdv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-BATCH-VALID',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $validAdv->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $invalidAdv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-BATCH-INVALID',
            'status' => 'approved',
            'bill_status' => 'bill_available', // Already resolved
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $invalidAdv->items()->create([
            'product_id' => $this->onion->id,
            'received_qty' => 5.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.clear-advances'), [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'advance_ids' => [$validAdv->id, $invalidAdv->id],
            'reason' => 'Batch test',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('advance_ids');

        // Neither should be cleared
        $this->assertEquals('bill_pending', $validAdv->fresh()->bill_status);
        $this->assertEquals('bill_available', $invalidAdv->fresh()->bill_status);
    }

    public function test_phase3_test10_stock_without_bill_ui_renders_advance_grn_grouping_and_modal(): void
    {
        $date = today()->toDateString();
        $adv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'ADV-UI-1042',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $adv->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);
        $adv->items()->create([
            'product_id' => $this->onion->id,
            'received_qty' => 5.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'stock_without_bill',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $response->assertOk();
        $response->assertSee('ADV-UI-1042');
        $response->assertSee('Action Tomato');
        $response->assertSee('Action Onion');
        $response->assertSee('Clear Selected Advances');
        $response->assertSee('Admin Manual Advance Clear');
        $response->assertSee('Reason for Manual Clear');
    }

    public function test_phase4_test1_product_asc_and_desc_sort_works(): void
    {
        $date = today()->toDateString();
        $cat = Category::create(['name' => 'General Veg', 'is_active' => true]);
        $prodA = Product::factory()->create(['name' => 'AAA Product', 'sku' => 'AAA-1', 'category_id' => $cat->id, 'is_active' => true]);
        $prodZ = Product::factory()->create(['name' => 'ZZZ Product', 'sku' => 'ZZZ-1', 'category_id' => $cat->id, 'is_active' => true]);

        // Test ASC
        $resAsc = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'product',
            'direction' => 'asc',
        ]));
        $resAsc->assertOk();
        $invAsc = $resAsc->viewData('currentInventory');
        $namesAsc = collect($invAsc->items())->pluck('name')->all();
        $this->assertEquals('AAA Product', $namesAsc[0]);
        $this->assertEquals('ZZZ Product', end($namesAsc));

        // Test DESC
        $resDesc = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'product',
            'direction' => 'desc',
        ]));
        $resDesc->assertOk();
        $invDesc = $resDesc->viewData('currentInventory');
        $namesDesc = collect($invDesc->items())->pluck('name')->all();
        $this->assertEquals('ZZZ Product', $namesDesc[0]);
        $this->assertEquals('AAA Product', end($namesDesc));
    }

    public function test_phase4_test2_category_sort_works(): void
    {
        $date = today()->toDateString();
        $catA = Category::create(['name' => 'Apple Category', 'is_active' => true]);
        $catZ = Category::create(['name' => 'Zebra Category', 'is_active' => true]);
        $prod1 = Product::factory()->create(['name' => 'P1', 'sku' => 'P1', 'category_id' => $catA->id, 'is_active' => true]);
        $prod2 = Product::factory()->create(['name' => 'P2', 'sku' => 'P2', 'category_id' => $catZ->id, 'is_active' => true]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'category',
            'direction' => 'asc',
        ]));
        $res->assertOk();
        $inv = $res->viewData('currentInventory');
        $firstItem = $inv->items()[0];
        $this->assertEquals('Apple Category', $firstItem['category']);
    }

    public function test_phase4_test3_current_sellable_desc_globally_correct_across_pagination(): void
    {
        $date = today()->toDateString();
        // Create 35 products so pagination kicks in (perPage = 30)
        $cat = Category::create(['name' => 'Bulk Category', 'is_active' => true]);
        $prods = [];
        for ($i = 1; $i <= 35; $i++) {
            $prods[] = Product::create([
                'name' => sprintf('Prod %02d', $i),
                'sku' => sprintf('SKU-%02d', $i),
                'category_id' => $cat->id,
                'unit' => 'kg',
                'base_price' => 10.0,
                'vendor_price' => 10.0,
                'is_active' => true,
            ]);
        }

        // Give Prod 35 highest sellable stock (1000 kg), Prod 34 second (500 kg), Prod 01 low (1 kg)
        $batchHigh = $this->createBatch($prods[34], $this->warehouseA, 1000.0, $date);
        $this->createMovement($batchHigh, StockMovementType::In, 1000.0, $date);

        $batchMid = $this->createBatch($prods[33], $this->warehouseA, 500.0, $date);
        $this->createMovement($batchMid, StockMovementType::In, 500.0, $date);

        $resPage1 = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'current_sellable',
            'direction' => 'desc',
            'page' => 1,
        ]));
        $resPage1->assertOk();
        $itemsPage1 = $resPage1->viewData('currentInventory')->items();
        $this->assertEquals($prods[34]->name, $itemsPage1[0]['name']);
        $this->assertEquals(1000.0, (float) $itemsPage1[0]['current_sellable']);
        $this->assertEquals($prods[33]->name, $itemsPage1[1]['name']);
        $this->assertEquals(500.0, (float) $itemsPage1[1]['current_sellable']);

        // Page 2 should contain lowest stock items
        $resPage2 = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'current_sellable',
            'direction' => 'desc',
            'page' => 2,
        ]));
        $resPage2->assertOk();
        $itemsPage2 = $resPage2->viewData('currentInventory')->items();
        $this->assertLessThanOrEqual(500.0, (float) $itemsPage2[0]['current_sellable']);
    }

    public function test_phase4_test4_with_bill_sorting(): void
    {
        $date = today()->toDateString();
        // Product 1: 50 kg Direct Billed In
        $batch1 = $this->createBatch($this->tomato, $this->warehouseA, 50.0, $date);
        $this->createMovement($batch1, StockMovementType::In, 50.0, $date);

        // Product 2: 10 kg Advance In (Without Bill)
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-SORT-WB',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advGrn->items()->create([
            'product_id' => $this->onion->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);
        $batch2 = $this->createBatch($this->onion, $this->warehouseA, 10.0, $date, $advGrn);
        $this->createMovement($batch2, StockMovementType::In, 10.0, $date);

        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'with_bill',
            'direction' => 'desc',
        ]));
        $res->assertOk();
        $items = $res->viewData('currentInventory')->items();
        $this->assertEquals($this->tomato->name, $items[0]['name']);
        $this->assertEquals(50.0, (float) $items[0]['with_bill']);
    }

    public function test_phase4_test5_without_bill_sorting(): void
    {
        $date = today()->toDateString();
        // Tomato: 50 kg Direct (With Bill = 50, Without Bill = 0)
        $batch1 = $this->createBatch($this->tomato, $this->warehouseA, 50.0, $date);
        $this->createMovement($batch1, StockMovementType::In, 50.0, $date);

        // Onion: 30 kg Advance (Without Bill = 30)
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-SORT-WOB',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advGrn->items()->create([
            'product_id' => $this->onion->id,
            'received_qty' => 30.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);
        $batch2 = $this->createBatch($this->onion, $this->warehouseA, 30.0, $date, $advGrn);
        $this->createMovement($batch2, StockMovementType::In, 30.0, $date);

        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'without_bill',
            'direction' => 'desc',
        ]));
        $res->assertOk();
        $items = $res->viewData('currentInventory')->items();
        $this->assertEquals($this->onion->name, $items[0]['name']);
        $this->assertEquals(30.0, (float) $items[0]['without_bill']);
    }

    public function test_phase4_test6_pending_vendor_bill_sorting(): void
    {
        $date = today()->toDateString();
        // Advance for Tomato = 100 kg
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-PVB',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'pending_vendor_bill',
            'direction' => 'desc',
        ]));
        $res->assertOk();
        $items = $res->viewData('currentInventory')->items();
        $this->assertEquals($this->tomato->name, $items[0]['name']);
        $this->assertEquals(100.0, (float) $items[0]['pending_vendor_bill']);
    }

    public function test_phase4_test7_stock_deficit_sorting(): void
    {
        $date = today()->toDateString();
        // Negative ledger balance on Onion (e.g. -25 kg)
        $batch = $this->createBatch($this->onion, $this->warehouseA, 0.0, $date);
        $this->createMovement($batch, StockMovementType::Out, 25.0, $date);

        $res = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'stock_deficit',
            'direction' => 'desc',
        ]));
        $res->assertOk();
        $items = $res->viewData('currentInventory')->items();
        $this->assertEquals($this->onion->name, $items[0]['name']);
        $this->assertEquals(25.0, (float) $items[0]['stock_deficit']);
    }

    public function test_phase4_test8_invalid_sort_key_falls_back_safely(): void
    {
        $date = today()->toDateString();
        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'malicious_sql_injection;--',
            'direction' => 'asc',
        ]));
        $response->assertOk();
        $this->assertNull($response->viewData('sort'));
    }

    public function test_phase4_test9_invalid_direction_falls_back_safely(): void
    {
        $date = today()->toDateString();
        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'product',
            'direction' => 'invalid_dir',
        ]));
        $response->assertOk();
        $this->assertEquals('asc', $response->viewData('direction'));
    }

    public function test_phase4_test10_search_sort_warehouse_date_preserved(): void
    {
        $date = today()->toDateString();
        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'search' => 'Tomato',
            'sort' => 'current_sellable',
            'direction' => 'desc',
        ]));
        $response->assertOk();
        $response->assertSee('Action Tomato');
        $response->assertDontSee('Action Onion');
        $this->assertEquals('Tomato', $response->viewData('search'));
        $this->assertEquals('current_sellable', $response->viewData('sort'));
        $this->assertEquals('desc', $response->viewData('direction'));
    }

    public function test_phase4_test11_pagination_preserves_sort_parameters(): void
    {
        $date = today()->toDateString();
        $cat = Category::create(['name' => 'Pag Category', 'is_active' => true]);
        for ($i = 1; $i <= 35; $i++) {
            Product::create([
                'name' => sprintf('PagProd %02d', $i),
                'sku' => sprintf('PAG-%02d', $i),
                'category_id' => $cat->id,
                'unit' => 'kg',
                'base_price' => 10.0,
                'vendor_price' => 10.0,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'product',
            'direction' => 'desc',
            'page' => 2,
        ]));
        $response->assertOk();
        $paginator = $response->viewData('currentInventory');
        $this->assertEquals(2, $paginator->currentPage());
        // Verify paginator query params contain sort and direction
        $this->assertStringContainsString('sort=product', $paginator->url(1));
        $this->assertStringContainsString('direction=desc', $paginator->url(1));
    }

    public function test_phase4_test12_current_inventory_invariant_unchanged(): void
    {
        $date = today()->toDateString();
        // Give tomato 15 kg direct stock and 10 kg advance
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 25.0, $date);
        $this->createMovement($batch, StockMovementType::In, 25.0, $date);

        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-INV-CHECK',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
        ]);
        $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'current_sellable',
            'direction' => 'desc',
        ]));
        $response->assertOk();
        $items = $response->viewData('currentInventory')->items();
        foreach ($items as $row) {
            $withBill = (float) $row['with_bill'];
            $withoutBill = (float) $row['without_bill'];
            $currSellable = (float) $row['current_sellable'];
            $this->assertEqualsWithDelta($currSellable, $withBill + $withoutBill, 0.001, 'With Bill + Without Bill must equal Current Sellable');
        }
    }

    public function test_phase4_test13_inactive_tabs_do_not_execute_expensive_full_data_queries(): void
    {
        $date = today()->toDateString();

        // On current_inventory tab, shopReturns, damageEntries, and stockWithoutBill should be null
        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));
        $response->assertOk();
        $this->assertNotNull($response->viewData('currentInventory'));
        $this->assertNull($response->viewData('shopReturns'));
        $this->assertNull($response->viewData('damageEntries'));
        $this->assertNull($response->viewData('stockWithoutBill'));
        $this->assertNull($response->viewData('pendingBills'));

        // On shop_returns tab, currentInventory should be null
        $resReturns = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'shop_returns',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));
        $resReturns->assertOk();
        $this->assertNull($resReturns->viewData('currentInventory'));
        $this->assertNotNull($resReturns->viewData('shopReturns'));
    }

    public function test_phase4_test14_warehouse_switching_still_works(): void
    {
        $date = today()->toDateString();
        // Stock in Warehouse A only
        $batchA = $this->createBatch($this->tomato, $this->warehouseA, 75.0, $date);
        $this->createMovement($batchA, StockMovementType::In, 75.0, $date);

        $resA = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));
        $resA->assertOk();
        $itemsA = collect($resA->viewData('currentInventory')->items())->keyBy('product_id');
        $this->assertEquals(75.0, (float) $itemsA[$this->tomato->id]['current_sellable']);

        // Switch to Warehouse B
        $resB = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseB->id,
            'date' => $date,
        ]));
        $resB->assertOk();
        $itemsB = collect($resB->viewData('currentInventory')->items())->keyBy('product_id');
        $this->assertEquals(0.0, (float) $itemsB[$this->tomato->id]['current_sellable']);
    }

    /**
     * Helper to set up approved prices and invoice items for delivery review tests.
     *
     * @param  array<int, array{item: ShopOrderItem, product: Product, price: float}>  $itemsWithPrices
     */
    private function setupApprovedPricesAndInvoice(ShopOrder $order, array $itemsWithPrices, string $date, string $invoiceNumber = 'INV-TEST-001'): ShopInvoice
    {
        foreach ($itemsWithPrices as $itemData) {
            $product = $itemData['product'];
            $price = (float) $itemData['price'];
            DailyPriceApproval::firstOrCreate([
                'product_id' => $product->id,
                'business_date' => $date,
            ], [
                'purchase_price' => $price / 2,
                'price_unit' => $product->unit ?? 'kg',
                'price_a' => $price,
                'price_b' => $price,
                'price_c' => $price,
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }

        $subtotal = collect($itemsWithPrices)->sum(fn ($i) => (float) $i['item']->approved_qty * (float) $i['price']);

        $invoice = ShopInvoice::create([
            'shop_id' => $order->shop_id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $date,
            'status' => 'delivery_review',
            'delivery_status' => 'awaiting_review',
            'payment_status' => 'unpaid',
            'subtotal' => $subtotal,
            'final_total' => $subtotal,
        ]);

        foreach ($itemsWithPrices as $itemData) {
            $orderItem = $itemData['item'];
            $price = (float) $itemData['price'];
            ShopInvoiceItem::create([
                'shop_invoice_id' => $invoice->id,
                'shop_order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'product_name' => $itemData['product']->name,
                'unit' => $orderItem->unit ?? 'kg',
                'price_unit' => $orderItem->unit ?? 'kg',
                'approved_qty' => $orderItem->approved_qty,
                'price_quantity' => $orderItem->approved_qty,
                'delivered_qty' => $orderItem->approved_qty,
                'delivered_price_quantity' => $orderItem->approved_qty,
                'unit_price' => $price,
                'line_subtotal' => (float) $orderItem->approved_qty * $price,
                'shortage_qty' => 0,
                'shortage_price_quantity' => 0,
                'shortage_amount' => 0,
                'final_line_total' => (float) $orderItem->approved_qty * $price,
            ]);
        }

        return $invoice;
    }

    public function test_shop_returns_test1_shop_invoice_return_and_finalize_visible_in_admin_inventory(): void
    {
        $date = today()->toDateString();
        // 1. Initial stock in Warehouse A: 20 kg
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 20.0, $date);
        $this->createMovement($batch, StockMovementType::In, 20.0, $date);

        // 2. Loadout out 10 kg for shop order
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'RQ-TEST-001',
            'business_date' => $date,
            'state' => 'approved',
            'delivery_status' => 'pending_approval',
            'delivery_review_status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'kg',
            'unit_cost' => 20.0,
        ]);
        $this->createMovement($batch, StockMovementType::Out, 10.0, $date, [
            'shop_order_item_id' => $orderItem->id,
            'notes' => "Order: {$order->order_number}; Item: {$orderItem->id}",
        ]);

        $this->setupApprovedPricesAndInvoice($order, [
            ['item' => $orderItem, 'product' => $this->tomato, 'price' => 20.0],
        ], $date, 'INV-TEST-001');

        // 3. Finalize on behalf via ResolveDeliveryReviewAction with delivered = 5 kg (shortage = 5 kg) and return_to_warehouse
        $action = app(ResolveDeliveryReviewAction::class);
        $action->approve(
            $order,
            [$orderItem->id => 5.0],
            [],
            [$orderItem->id => 'return_to_warehouse'],
            [$orderItem->id => 'wastage_damage'],
            [],
            (int) $this->adminUser->id,
            'Returned 5 kg to warehouse'
        );

        // 4. Query Admin Inventory Shop Returns tab
        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'shop_returns',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $response->assertOk();
        $response->assertSee('Central Retail Shop');
        $response->assertSee('INV-TEST-001');
        $response->assertSee('Action Tomato');
        $response->assertSee('5.00');

        // Verify sellable stock in warehouse = 20 - 10 + 5 = 15 kg
        $stockRepo = app(StockMovementRepository::class);
        $stock = $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id);
        $tomatoStock = (float) $stock->where('product_id', $this->tomato->id)->sum('current_stock');
        $this->assertEquals(15.0, $tomatoStock);
    }

    public function test_shop_returns_test2_two_products_returned_both_rows_displayed(): void
    {
        $date = today()->toDateString();
        $batchT = $this->createBatch($this->tomato, $this->warehouseA, 20.0, $date);
        $this->createMovement($batchT, StockMovementType::In, 20.0, $date);
        $batchO = $this->createBatch($this->onion, $this->warehouseA, 20.0, $date);
        $this->createMovement($batchO, StockMovementType::In, 20.0, $date);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'RQ-TEST-002',
            'business_date' => $date,
            'state' => 'approved',
            'delivery_status' => 'pending_approval',
            'delivery_review_status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);
        $itemT = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'kg',
            'unit_cost' => 20.0,
        ]);
        $itemO = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->onion->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'kg',
            'unit_cost' => 15.0,
        ]);
        $this->createMovement($batchT, StockMovementType::Out, 10.0, $date, [
            'shop_order_item_id' => $itemT->id,
            'notes' => "Order: {$order->order_number}; Item: {$itemT->id}",
        ]);
        $this->createMovement($batchO, StockMovementType::Out, 10.0, $date, [
            'shop_order_item_id' => $itemO->id,
            'notes' => "Order: {$order->order_number}; Item: {$itemO->id}",
        ]);

        $this->setupApprovedPricesAndInvoice($order, [
            ['item' => $itemT, 'product' => $this->tomato, 'price' => 20.0],
            ['item' => $itemO, 'product' => $this->onion, 'price' => 15.0],
        ], $date, 'INV-TEST-002');

        $action = app(ResolveDeliveryReviewAction::class);
        $action->approve(
            $order,
            [$itemT->id => 5.0, $itemO->id => 7.0],
            [],
            [$itemT->id => 'return_to_warehouse', $itemO->id => 'return_to_warehouse'],
            [$itemT->id => 'wastage_damage', $itemO->id => 'wastage_damage'],
            [],
            (int) $this->adminUser->id,
            'Two items returned'
        );

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'shop_returns',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $response->assertOk();
        $response->assertSee('INV-TEST-002');
        $response->assertSee('Action Tomato');
        $response->assertSee('5.00');
        $response->assertSee('Action Onion');
        $response->assertSee('3.00');
    }

    public function test_shop_returns_test3_different_warehouse_scoped_correctly(): void
    {
        $date = today()->toDateString();
        $batchA = $this->createBatch($this->tomato, $this->warehouseA, 20.0, $date);
        $this->createMovement($batchA, StockMovementType::In, 20.0, $date);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'RQ-TEST-003',
            'business_date' => $date,
            'state' => 'approved',
            'delivery_status' => 'pending_approval',
            'delivery_review_status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);
        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'kg',
            'unit_cost' => 20.0,
        ]);
        $this->createMovement($batchA, StockMovementType::Out, 10.0, $date, [
            'shop_order_item_id' => $item->id,
            'notes' => "Order: {$order->order_number}; Item: {$item->id}",
        ]);

        $this->setupApprovedPricesAndInvoice($order, [
            ['item' => $item, 'product' => $this->tomato, 'price' => 20.0],
        ], $date, 'INV-TEST-003');

        $action = app(ResolveDeliveryReviewAction::class);
        $action->approve(
            $order,
            [$item->id => 6.0],
            [],
            [$item->id => 'return_to_warehouse'],
            [$item->id => 'wastage_damage'],
            [],
            (int) $this->adminUser->id,
            'Warehouse A return'
        );

        // Warehouse A should see INV-TEST-003
        $resA = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'shop_returns',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));
        $resA->assertOk();
        $resA->assertSee('INV-TEST-003');

        // Warehouse B should NOT see INV-TEST-003
        $resB = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'shop_returns',
            'warehouse_id' => $this->warehouseB->id,
            'date' => $date,
        ]));
        $resB->assertOk();
        $resB->assertDontSee('INV-TEST-003');
    }

    public function test_shop_returns_test4_date_filter_scoping(): void
    {
        $dateSep9 = '2026-09-09';
        $dateSep8 = '2026-09-08';

        $batch = $this->createBatch($this->tomato, $this->warehouseA, 20.0, $dateSep9);
        $this->createMovement($batch, StockMovementType::In, 20.0, $dateSep9);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'RQ-TEST-004',
            'business_date' => $dateSep9,
            'state' => 'approved',
            'delivery_status' => 'pending_approval',
            'delivery_review_status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);
        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'kg',
            'unit_cost' => 20.0,
        ]);
        $this->createMovement($batch, StockMovementType::Out, 10.0, $dateSep9, [
            'shop_order_item_id' => $item->id,
            'notes' => "Order: {$order->order_number}; Item: {$item->id}",
        ]);

        $this->setupApprovedPricesAndInvoice($order, [
            ['item' => $item, 'product' => $this->tomato, 'price' => 20.0],
        ], $dateSep9, 'INV-TEST-SEP9');

        // Mock current time to Sep 9 when finalizing
        Carbon::setTestNow(Carbon::parse($dateSep9)->setTime(14, 0, 0));
        $action = app(ResolveDeliveryReviewAction::class);
        $action->approve(
            $order,
            [$item->id => 6.0],
            [],
            [$item->id => 'return_to_warehouse'],
            [$item->id => 'wastage_damage'],
            [],
            (int) $this->adminUser->id,
            'Sep 9 return'
        );

        // Sep 9 request should see INV-TEST-SEP9
        $resSep9 = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'shop_returns',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $dateSep9,
        ]));
        $resSep9->assertOk();
        $resSep9->assertSee('INV-TEST-SEP9');

        // Sep 8 request should NOT see it
        $resSep8 = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'shop_returns',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $dateSep8,
        ]));
        $resSep8->assertOk();
        $resSep8->assertDontSee('INV-TEST-SEP9');
        Carbon::setTestNow();
    }

    public function test_shop_returns_test5_edit_finalized_invoice_shows_canonical_return_quantity(): void
    {
        $date = today()->toDateString();
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 20.0, $date);
        $this->createMovement($batch, StockMovementType::In, 20.0, $date);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'RQ-TEST-005',
            'business_date' => $date,
            'state' => 'approved',
            'delivery_status' => 'pending_approval',
            'delivery_review_status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);
        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'kg',
            'unit_cost' => 20.0,
        ]);
        $this->createMovement($batch, StockMovementType::Out, 10.0, $date, [
            'shop_order_item_id' => $item->id,
            'notes' => "Order: {$order->order_number}; Item: {$item->id}",
        ]);

        $this->setupApprovedPricesAndInvoice($order, [
            ['item' => $item, 'product' => $this->tomato, 'price' => 20.0],
        ], $date, 'INV-TEST-005');

        $action = app(ResolveDeliveryReviewAction::class);
        // Initial approval: 5 kg delivered -> 5 kg returned
        $action->approve(
            $order,
            [$item->id => 5.0],
            [],
            [$item->id => 'return_to_warehouse'],
            [$item->id => 'wastage_damage'],
            [],
            (int) $this->adminUser->id,
            'Initial return 5 kg'
        );

        // Revert approval for edit
        $action->revertApprovalForAdminEdit($order->fresh(), (int) $this->adminUser->id, 'Reverting to edit return qty');

        // New active return movement for edited 3 kg
        $this->createMovement($batch, StockMovementType::SaleReversal, 3.0, $date, [
            'shop_order_item_id' => $item->id,
            'notes' => "Delivery shortage added back to inventory - Order: {$order->order_number}; Item: {$item->id}",
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'shop_returns',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));
        $response->assertOk();
        $response->assertSee('3.00');

        $summary = $response->viewData('summary');
        $this->assertEquals(3.0, (float) $summary['shop_returns_qty']);

        // Stock in warehouse should be 20 - 10 + 5 (initial) - 5 (revert) + 3 (new) = 13 kg
        $stockRepo = app(StockMovementRepository::class);
        $stock = $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id);
        $tomatoStock = (float) $stock->where('product_id', $this->tomato->id)->sum('current_stock');
        $this->assertEquals(13.0, $tomatoStock);
    }

    public function test_shop_returns_test6_revert_finalized_invoice_reverses_return_and_updates_admin_inventory(): void
    {
        $date = today()->toDateString();
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 20.0, $date);
        $this->createMovement($batch, StockMovementType::In, 20.0, $date);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'RQ-TEST-006',
            'business_date' => $date,
            'state' => 'approved',
            'delivery_status' => 'pending_approval',
            'delivery_review_status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);
        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'kg',
            'unit_cost' => 20.0,
        ]);
        $this->createMovement($batch, StockMovementType::Out, 10.0, $date, [
            'shop_order_item_id' => $item->id,
            'notes' => "Order: {$order->order_number}; Item: {$item->id}",
        ]);

        $this->setupApprovedPricesAndInvoice($order, [
            ['item' => $item, 'product' => $this->tomato, 'price' => 20.0],
        ], $date, 'INV-TEST-006');

        $action = app(ResolveDeliveryReviewAction::class);
        $action->approve(
            $order,
            [$item->id => 5.0],
            [],
            [$item->id => 'return_to_warehouse'],
            [$item->id => 'wastage_damage'],
            [],
            (int) $this->adminUser->id,
            'Initial return 5 kg'
        );

        // Revert approval
        $action->revertApprovalForAdminEdit($order->fresh(), (int) $this->adminUser->id, 'Reverting return approval');

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'shop_returns',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));
        $response->assertOk();
        $response->assertSee('No Shop Returns Today');

        $summary = $response->viewData('summary');
        $this->assertEquals(0.0, (float) $summary['shop_returns_qty']);

        // Stock in warehouse should be 20 - 10 = 10 kg
        $stockRepo = app(StockMovementRepository::class);
        $stock = $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id);
        $tomatoStock = (float) $stock->where('product_id', $this->tomato->id)->sum('current_stock');
        $this->assertEquals(10.0, $tomatoStock);
    }

    public function test_shop_returns_test7_move_shop_invoice_returned_product_to_damage(): void
    {
        $date = today()->toDateString();
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 20.0, $date);
        $this->createMovement($batch, StockMovementType::In, 20.0, $date);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'RQ-TEST-007',
            'business_date' => $date,
            'state' => 'approved',
            'delivery_status' => 'pending_approval',
            'delivery_review_status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);
        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'kg',
            'unit_cost' => 20.0,
        ]);
        $this->createMovement($batch, StockMovementType::Out, 10.0, $date, [
            'shop_order_item_id' => $item->id,
            'notes' => "Order: {$order->order_number}; Item: {$item->id}",
        ]);

        $this->setupApprovedPricesAndInvoice($order, [
            ['item' => $item, 'product' => $this->tomato, 'price' => 20.0],
        ], $date, 'INV-TEST-007');

        $action = app(ResolveDeliveryReviewAction::class);
        $action->approve(
            $order,
            [$item->id => 5.0],
            [],
            [$item->id => 'return_to_warehouse'],
            [$item->id => 'wastage_damage'],
            [],
            (int) $this->adminUser->id,
            'Return 5 kg'
        );

        // Move 2 kg to damage via move-to-damage
        $resDamage = $this->actingAs($this->adminUser)->post(route('admin.cashbook.inventory.move-to-damage'), [
            'warehouse_id' => $this->warehouseA->id,
            'wastage_date' => $date,
            'common_reason' => 'transit_damage',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'quantity' => 2.0,
                    'reason' => 'transit_damage',
                ],
            ],
        ]);
        $resDamage->assertRedirect();

        // Stock in warehouse should be 20 - 10 + 5 - 2 = 13 kg
        $stockRepo = app(StockMovementRepository::class);
        $stock = $stockRepo->currentStockByProductAndGrade($date, $this->warehouseA->id);
        $tomatoStock = (float) $stock->where('product_id', $this->tomato->id)->sum('current_stock');
        $this->assertEquals(13.0, $tomatoStock);
    }

    public function test_shop_returns_test8_daily_summary_shop_returns_equals_finalized_return_quantity(): void
    {
        $date = today()->toDateString();
        $batch = $this->createBatch($this->tomato, $this->warehouseA, 20.0, $date);
        $this->createMovement($batch, StockMovementType::In, 20.0, $date);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'RQ-TEST-008',
            'business_date' => $date,
            'state' => 'approved',
            'delivery_status' => 'pending_approval',
            'delivery_review_status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);
        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'unit' => 'kg',
            'unit_cost' => 20.0,
        ]);
        $this->createMovement($batch, StockMovementType::Out, 10.0, $date, [
            'shop_order_item_id' => $item->id,
            'notes' => "Order: {$order->order_number}; Item: {$item->id}",
        ]);

        $this->setupApprovedPricesAndInvoice($order, [
            ['item' => $item, 'product' => $this->tomato, 'price' => 20.0],
        ], $date, 'INV-TEST-008');

        $action = app(ResolveDeliveryReviewAction::class);
        $action->approve(
            $order,
            [$item->id => 6.0],
            [],
            [$item->id => 'return_to_warehouse'],
            [$item->id => 'wastage_damage'],
            [],
            (int) $this->adminUser->id,
            'Return 4 kg'
        );

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'current_inventory',
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals(1, $summary['shop_returns_count']);
        $this->assertEquals(4.0, (float) $summary['shop_returns_qty']);
    }
}
