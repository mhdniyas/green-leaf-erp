<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\ProcessBatchSortingAction;
use App\DTOs\Inventory\SortingData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\BusinessSetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOrderRevision;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\Cashbook\ShopFinancialReportService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\PurchaserDailyInitialQuery;
use App\Services\Requisition\MultiGradeOrderGate;
use App\Services\Requisition\ShopOrderItemSyncService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class StagingPilotOperationalFinancialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaserUser;

    private User $warehouseUser;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private ShopPriceGroup $priceGroupA;

    private ShopPriceGroup $priceGroupB;

    private Shop $shopPilot;

    private Shop $shopStandard;

    private Product $tomatoH;

    private string $businessDate;

    private MultiGradeOrderGate $multiGradeGate;

    private StockLedgerService $stockLedgerService;

    private ShopInvoiceService $shopInvoiceService;

    private ApprovedDailyPriceResolver $priceResolver;

    private InvoiceCashbookProjectionService $invoiceProjectionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('purchase', 'web');
        Role::findOrCreate('purchaser', 'web');
        Role::findOrCreate('shop', 'web');
        Role::findOrCreate('warehouse_receiver', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaserUser = User::factory()->create();
        $this->purchaserUser->assignRole('purchaser');

        $this->warehouseUser = User::factory()->create();
        $this->warehouseUser->assignRole('warehouse_receiver');

        $this->warehouse = Warehouse::create([
            'name' => 'Central Hub Warehouse',
            'code' => 'CHW-01',
            'is_active' => true,
        ]);
        $this->warehouseUser->warehouses()->attach($this->warehouse);

        $this->supplier = Supplier::factory()->create(['name' => 'Green Valley Farms']);

        $category = Category::create([
            'name' => 'Fresh Vegetables',
            'is_active' => true,
        ]);

        $this->tomatoH = Product::create([
            'name' => 'Tomato H',
            'sku' => 'TOM-H',
            'unit' => 'KG',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        $this->priceGroupA = ShopPriceGroup::create([
            'name' => 'A',
            'code' => 'A',
            'default_margin_percent' => 10.00,
            'is_active' => true,
        ]);

        $this->priceGroupB = ShopPriceGroup::create([
            'name' => 'B',
            'code' => 'B',
            'default_margin_percent' => 15.00,
            'is_active' => true,
        ]);

        // Pilot Shop: Opted-in for Grades A, B, C in Price Category A
        $this->shopPilot = Shop::create([
            'name' => 'Pilot Shop',
            'code' => 'SH-PILOT',
            'shop_price_group_id' => $this->priceGroupA->id,
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        // Standard Shop: Grade A only in Price Category B
        $this->shopStandard = Shop::create([
            'name' => 'Standard Shop',
            'code' => 'SH-STD',
            'shop_price_group_id' => $this->priceGroupB->id,
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'allowed_product_grades' => null, // Default Grade A only
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->businessDate = Carbon::parse('2026-10-17')->toDateString();

        $this->mock(PurchaserBusinessDayService::class, function ($mock): void {
            $mock->shouldReceive('operationalDate')->andReturn(Carbon::parse($this->businessDate));
            $mock->shouldReceive('cutoffLabel')->andReturn('12:00 PM');
        });

        $this->multiGradeGate = app(MultiGradeOrderGate::class);
        $this->stockLedgerService = app(StockLedgerService::class);
        $this->shopInvoiceService = app(ShopInvoiceService::class);
        $this->priceResolver = app(ApprovedDailyPriceResolver::class);
        $this->invoiceProjectionService = app(InvoiceCashbookProjectionService::class);

        // Seed provisional catalog prices
        foreach (['A' => 40.0, 'B' => 25.0, 'C' => 15.0] as $grade => $price) {
            DailyProductPrice::create([
                'product_id' => $this->tomatoH->id,
                'shop_price_group_id' => $this->priceGroupA->id,
                'grade' => $grade,
                'selling_price' => $price,
            ]);
            DailyProductPrice::create([
                'product_id' => $this->tomatoH->id,
                'shop_price_group_id' => $this->priceGroupB->id,
                'grade' => $grade,
                'selling_price' => $price + 2.0,
            ]);
        }
    }

    /**
     * 1. Staging Environment & Isolation Verification.
     */
    public function test_01_staging_environment_isolation_and_safety_checks(): void
    {
        // Confirm connection is isolated (testing / sqlite :memory: or local db)
        $defaultConn = Config::get('database.default');
        $this->assertContains($defaultConn, ['sqlite', 'mysql']);

        // Verify feature flag is disabled by default until staging activation
        $globalSetting = BusinessSetting::where('key', MultiGradeOrderGate::SETTING_KEY)->first();
        $this->assertTrue($globalSetting === null || $globalSetting->value === '0' || $globalSetting->value === 'false' || $globalSetting->value === null);
        $this->assertFalse($this->multiGradeGate->isFeatureEnabled());

        // Verify standard shop permissions default strictly to Grade A
        $this->assertFalse($this->multiGradeGate->canShopOrderGrade($this->shopStandard, 'B'));
        $this->assertFalse($this->multiGradeGate->canShopOrderGrade($this->shopStandard, 'C'));
        $this->assertTrue($this->multiGradeGate->canShopOrderGrade($this->shopStandard, 'A'));
    }

    /**
     * 2. Shop Ordering Interface: Pilot Shop vs Standard Shop & Order Revision.
     */
    public function test_02_shop_ordering_form_submission_and_revision(): void
    {
        // Activate staging pilot
        BusinessSetting::updateOrCreate(
            ['key' => MultiGradeOrderGate::SETTING_KEY],
            ['value' => '1']
        );
        $this->assertTrue($this->multiGradeGate->isFeatureEnabled());

        // Pilot Shop is authorized for A, B, and C
        $this->assertTrue($this->multiGradeGate->canShopOrderGrade($this->shopPilot, 'A'));
        $this->assertTrue($this->multiGradeGate->canShopOrderGrade($this->shopPilot, 'B'));
        $this->assertTrue($this->multiGradeGate->canShopOrderGrade($this->shopPilot, 'C'));

        // Standard Shop remains strictly Grade A only
        $this->assertTrue($this->multiGradeGate->canShopOrderGrade($this->shopStandard, 'A'));
        $this->assertFalse($this->multiGradeGate->canShopOrderGrade($this->shopStandard, 'B'));
        $this->assertFalse($this->multiGradeGate->canShopOrderGrade($this->shopStandard, 'C'));

        // Submit initial order for Pilot Shop: Tomato H Grade A (100 kg), Grade B (20 kg), Grade C (80 kg)
        $order = ShopOrder::create([
            'shop_id' => $this->shopPilot->id,
            'order_number' => 'ORD-PILOT-001',
            'business_date' => $this->businessDate,
            'state' => 'submitted',
            'status' => 'pending',
            'delivery_status' => 'pending',
            'order_source' => 'shop',
            'created_by' => $this->admin->id,
        ]);

        $revision = ShopOrderRevision::create([
            'shop_order_id' => $order->id,
            'revision_no' => 1,
            'status' => 'submitted',
            'requested_by' => $this->admin->id,
        ]);

        $syncService = app(ShopOrderItemSyncService::class);
        $syncService->syncShopOrderItems($order, [
            ['product' => $this->tomatoH, 'quantity' => 100.0, 'unit' => 'KG', 'grade' => 'A'],
            ['product' => $this->tomatoH, 'quantity' => 20.0, 'unit' => 'KG', 'grade' => 'B'],
            ['product' => $this->tomatoH, 'quantity' => 80.0, 'unit' => 'KG', 'grade' => 'C'],
        ]);

        $order->refresh();
        $this->assertCount(3, $order->items);
        $this->assertEquals(100.0, (float) $order->items->firstWhere('product_grade', 'A')->requested_qty);
        $this->assertEquals(20.0, (float) $order->items->firstWhere('product_grade', 'B')->requested_qty);
        $this->assertEquals(80.0, (float) $order->items->firstWhere('product_grade', 'C')->requested_qty);

        // Test Reopening and Revising Quantities (e.g. revise Grade B from 20 to 25 kg, then back to 20 kg)
        $syncService->syncShopOrderItems($order, [
            ['product' => $this->tomatoH, 'quantity' => 100.0, 'unit' => 'KG', 'grade' => 'A'],
            ['product' => $this->tomatoH, 'quantity' => 25.0, 'unit' => 'KG', 'grade' => 'B'],
            ['product' => $this->tomatoH, 'quantity' => 80.0, 'unit' => 'KG', 'grade' => 'C'],
        ]);
        $order->refresh();
        $this->assertEquals(25.0, (float) $order->items->firstWhere('product_grade', 'B')->requested_qty);

        // Restore to target pilot quantities: A: 100, B: 20, C: 80
        $syncService->syncShopOrderItems($order, [
            ['product' => $this->tomatoH, 'quantity' => 100.0, 'unit' => 'KG', 'grade' => 'A'],
            ['product' => $this->tomatoH, 'quantity' => 20.0, 'unit' => 'KG', 'grade' => 'B'],
            ['product' => $this->tomatoH, 'quantity' => 80.0, 'unit' => 'KG', 'grade' => 'C'],
        ]);
        $order->refresh();
        $this->assertEquals(20.0, (float) $order->items->firstWhere('product_grade', 'B')->requested_qty);

        // Verify Standard Shop is rejected if attempting Grade B
        $this->expectException(ValidationException::class);
        $this->multiGradeGate->validateOrderItems($this->shopStandard, [
            ['product' => $this->tomatoH, 'grade' => 'B', 'quantity' => 10],
        ]);
    }

    /**
     * 3. Purchaser Dashboard, Multi-Grade Carts, Warehouse Receiving & Sorting.
     */
    public function test_03_purchaser_demand_receiving_sorting_and_loadout(): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => MultiGradeOrderGate::SETTING_KEY],
            ['value' => '1']
        );

        // Create approved orders: Pilot (100 A, 20 B, 80 C = 200 kg) + Standard (50 A)
        $pilotOrder = ShopOrder::create([
            'shop_id' => $this->shopPilot->id,
            'order_number' => 'ORD-PILOT-002',
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'status' => 'pending',
            'delivery_status' => 'pending_delivery',
            'order_source' => 'shop',
            'created_by' => $this->admin->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $pilotOrder->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'A',
            'requested_qty' => 100.0,
            'approved_qty' => 100.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $pilotOrder->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'B',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $pilotOrder->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'C',
            'requested_qty' => 80.0,
            'approved_qty' => 80.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);

        $stdOrder = ShopOrder::create([
            'shop_id' => $this->shopStandard->id,
            'order_number' => 'ORD-STD-002',
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'status' => 'pending',
            'delivery_status' => 'pending_delivery',
            'order_source' => 'shop',
            'created_by' => $this->admin->id,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $stdOrder->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'A',
            'requested_qty' => 50.0,
            'approved_qty' => 50.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);

        // Purchaser Daily Demand Aggregation
        $purchaserQuery = app(PurchaserDailyInitialQuery::class);
        $demand = $purchaserQuery->getPendingDemand(Carbon::parse($this->businessDate), 'A', $this->purchaserUser);
        $tomatoDemand = $demand->firstWhere('product_id', $this->tomatoH->id);
        $this->assertNotNull($tomatoDemand);
        $this->assertEquals(250.0, (float) $tomatoDemand['total_approved_qty']);
        $this->assertTrue($tomatoDemand['has_non_a_grades']);

        $breakdown = collect($tomatoDemand['grade_breakdown'])->keyBy('grade');
        $this->assertEquals(150.0, (float) $breakdown['A']['approved_qty']);
        $this->assertEquals(20.0, (float) $breakdown['B']['approved_qty']);
        $this->assertEquals(80.0, (float) $breakdown['C']['approved_qty']);

        // Grade-Specific Purchaser Cart Items
        $cart = PurchaserCart::create([
            'cart_number' => 'CART-001',
            'user_id' => $this->purchaserUser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $this->businessDate,
            'status' => 'active',
        ]);

        $cartItemA = PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->tomatoH->id,
            'quantity' => 150.0,
            'unit_price' => 30.0,
            'line_total' => 4500.0,
            'grade' => 'A',
        ]);
        $this->assertSame('A', $cartItemA->grade);

        // Warehouse Receiving: Fixed batches for B (20 kg) and C (80 kg)
        $batchB = StockBatch::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->tomatoH->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'BAT-FIX-B',
            'total_kg' => 20.0,
            'cost_per_kg' => 18.00,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'B',
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'created_by' => $this->admin->id,
        ]);
        StockMovement::create([
            'batch_id' => $batchB->id,
            'product_id' => $this->tomatoH->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => StockMovementType::In,
            'grade' => 'B',
            'quantity' => 20.0,
            'cost_per_unit' => 18.00,
            'created_by' => $this->admin->id,
        ]);

        $batchC = StockBatch::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->tomatoH->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'BAT-FIX-C',
            'total_kg' => 80.0,
            'cost_per_kg' => 10.00,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'C',
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'created_by' => $this->admin->id,
        ]);
        StockMovement::create([
            'batch_id' => $batchC->id,
            'product_id' => $this->tomatoH->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => StockMovementType::In,
            'grade' => 'C',
            'quantity' => 80.0,
            'cost_per_unit' => 10.00,
            'created_by' => $this->admin->id,
        ]);

        // Mixed Batch: 200 kg -> 150 kg A, 20 kg B, 25 kg C, 5 kg Damaged
        $mixedBatch = StockBatch::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->tomatoH->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'BAT-MIX-200',
            'total_kg' => 200.0,
            'cost_per_kg' => 22.00,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $sortingAction = app(ProcessBatchSortingAction::class);
        $sortedBatch = $sortingAction->execute($mixedBatch, new SortingData(
            batchId: $mixedBatch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 150.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 20.0],
                ['grade' => ProductGrade::GradeC, 'quantity' => 25.0],
                ['grade' => ProductGrade::Damage, 'quantity' => 5.0],
            ],
            notes: 'Sorted 200kg mixed',
        ), $this->admin->id);
        $this->assertEquals(BatchStatus::Sorted, $sortedBatch->status);

        // Verify available stock per grade
        $this->assertEquals(150.0, $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, null, ProductGrade::GradeA));
        $this->assertEquals(40.0, $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, null, ProductGrade::GradeB));
        $this->assertEquals(105.0, $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, null, ProductGrade::GradeC));

        // Legacy mobile loadout write on Pilot Order (multi-grade) is safely blocked
        $response = $this->actingAs($this->warehouseUser)
            ->postJson("/api/v1/warehouse/loadout/{$pilotOrder->id}/save", [
                'items' => [(string) $this->tomatoH->id => 100.0],
            ]);
        $response->assertStatus(422);
        $response->assertJson(['code' => 'MULTI_GRADE_MOBILE_RESTRICTED']);

        // Web Warehouse Loadout saves multi-grade items with composite keys
        DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'A',
            'purchase_price' => 30.0,
            'price_unit' => 'KG',
            'price_a' => 40.0,
            'price_b' => 42.0,
            'price_c' => 45.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'B',
            'purchase_price' => 18.0,
            'price_unit' => 'KG',
            'price_a' => 25.0,
            'price_b' => 27.0,
            'price_c' => 30.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'C',
            'purchase_price' => 10.0,
            'price_unit' => 'KG',
            'price_a' => 15.0,
            'price_b' => 16.0,
            'price_c' => 18.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $webSaveResponse = $this->actingAs($this->warehouseUser)
            ->post(route('warehouse.loadout.save', $pilotOrder), [
                'items' => [
                    "{$this->tomatoH->id}|A" => 100.0,
                    "{$this->tomatoH->id}|B" => 20.0,
                    "{$this->tomatoH->id}|C" => 80.0,
                ],
            ]);
        $webSaveResponse->assertRedirect();

        // Grade A and Grade B/C inventory deductions verified
        $this->assertEquals(50.0, $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, null, ProductGrade::GradeA));
        $this->assertEquals(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, null, ProductGrade::GradeB));
        $this->assertEquals(25.0, $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, null, ProductGrade::GradeC));
    }

    /**
     * 4. Daily Price Matrix: Multi-Grade Approval, Manual Override & Missing Price Blocking.
     */
    public function test_04_daily_price_matrix_approval_override_and_safety(): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => MultiGradeOrderGate::SETTING_KEY],
            ['value' => '1']
        );

        // Grade A Approval: Purchase = ₹30.00; Category A = ₹40.00, Category B = ₹42.00
        $approvalA = DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'A',
            'purchase_price' => 30.00,
            'price_unit' => 'KG',
            'price_a' => 40.00,
            'price_b' => 42.00,
            'price_c' => 45.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Grade B Approval with manual price override: Purchase = ₹18.00; Category A override = ₹25.00
        $approvalB = DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'B',
            'purchase_price' => 18.00,
            'price_unit' => 'KG',
            'price_a' => 25.00,
            'price_b' => 27.00,
            'price_c' => 30.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Grade C Approval: Purchase = ₹10.00; Category A = ₹15.00
        $approvalC = DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'C',
            'purchase_price' => 10.00,
            'price_unit' => 'KG',
            'price_a' => 15.00,
            'price_b' => 16.00,
            'price_c' => 18.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Resolve selling prices for Pilot Shop (Category A)
        $priceA = $this->priceResolver->resolve($this->tomatoH, $this->shopPilot, $this->businessDate, 'A');
        $priceB = $this->priceResolver->resolve($this->tomatoH, $this->shopPilot, $this->businessDate, 'B');
        $priceC = $this->priceResolver->resolve($this->tomatoH, $this->shopPilot, $this->businessDate, 'C');

        $this->assertEquals(40.00, $priceA['price']);
        $this->assertEquals(25.00, $priceB['price']);
        $this->assertEquals(15.00, $priceC['price']);

        // Missing price safety: If Grade C approval is deleted, order synchronization blocks unsafe invoice generation
        $approvalC->delete();
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopPilot->id,
            'business_date' => $this->businessDate,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'C',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
            'loaded_qty' => 10.0,
        ]);

        $this->expectException(ValidationException::class);
        $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
    }

    /**
     * 5. Combined Invoice, Shortage Handling, Cashbook Posting & Post-Delivery Credit Adjustment.
     */
    public function test_05_combined_invoice_shortage_cashbook_and_credit_note(): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => MultiGradeOrderGate::SETTING_KEY],
            ['value' => '1']
        );

        // Daily Price Approvals
        DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'A',
            'purchase_price' => 30.00,
            'price_unit' => 'KG',
            'price_a' => 40.00,
            'price_b' => 42.00,
            'price_c' => 45.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'B',
            'purchase_price' => 18.00,
            'price_unit' => 'KG',
            'price_a' => 25.00,
            'price_b' => 27.00,
            'price_c' => 30.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'C',
            'purchase_price' => 10.00,
            'price_unit' => 'KG',
            'price_a' => 15.00,
            'price_b' => 16.00,
            'price_c' => 18.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopPilot->id,
            'business_date' => $this->businessDate,
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'A',
            'requested_qty' => 100.0,
            'approved_qty' => 100.0,
            'loaded_qty' => 100.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);
        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'B',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'loaded_qty' => 20.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);
        $itemC = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'C',
            'requested_qty' => 80.0,
            'approved_qty' => 80.0,
            'loaded_qty' => 80.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);

        // Generate combined invoice
        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $this->assertNotNull($invoice);
        $this->assertCount(3, $invoice->items);

        // Apply Delivery Checkin with 2 kg Grade B shortage:
        // Grade A: 100 kg delivered
        // Grade B: 18 kg delivered (2 kg shortage)
        // Grade C: 80 kg delivered
        $invoice = $this->shopInvoiceService->applyDeliveryCheckin($order, [
            $itemA->id => 100.0,
            $itemB->id => 18.0,
            $itemC->id => 80.0,
        ], $this->admin->id, 'Delivered with 2kg Grade B shortage');

        $lineA = $invoice->items->firstWhere('product_grade', 'A');
        $lineB = $invoice->items->firstWhere('product_grade', 'B');
        $lineC = $invoice->items->firstWhere('product_grade', 'C');

        // Verification of gross values
        // Grade A: 100 kg × ₹40 = ₹4,000
        $this->assertEquals(4000.00, (float) $lineA->line_subtotal);
        // Grade B: 20 kg approved × ₹25 = ₹500, shortage: 2 kg × ₹25 = ₹50, final_line_total = ₹450
        $this->assertEquals(500.00, (float) $lineB->line_subtotal);
        $this->assertEquals(50.00, (float) $lineB->shortage_amount);
        $this->assertEquals(450.00, (float) $lineB->final_line_total);
        // Grade C: 80 kg × ₹15 = ₹1,200
        $this->assertEquals(1200.00, (float) $lineC->line_subtotal);
        $this->assertEquals(1200.00, (float) $lineC->final_line_total);

        // Gross total: ₹4000 + ₹500 + ₹1200 = ₹5,700
        $grossTotal = (float) $invoice->items->sum('line_subtotal');
        $this->assertEquals(5700.00, $grossTotal);

        // Net payable amount after shortage deduction: ₹5,650
        $this->assertEquals(5650.00, (float) $invoice->final_total);
        $this->assertEquals(5650.00, (float) $invoice->balance_amount);

        // Finalize invoice and verify cashbook projection reflects ₹5,650
        $finalizedInvoice = $this->shopInvoiceService->markFinalized($invoice, $this->admin->id, 'finalization', 'Initial finalization');
        $this->assertEquals(5650.00, (float) $finalizedInvoice->final_total);

        $tx = ShopLedgerTransaction::where('reference_type', ShopInvoice::class)
            ->where('reference_id', $finalizedInvoice->id)
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals(5650.00, (float) $tx->amount);

        // Apply a separate authorized post-delivery 2 kg Grade B credit adjustment
        // Delivered quantity revised from 18 kg down to 16 kg @ ₹25.00
        $adjustedInvoice = $this->shopInvoiceService->updateItemFinalQuantityAndPrice(
            $finalizedInvoice,
            $lineB,
            16.0,
            25.00,
            $this->admin->id,
            'Authorized 2kg Grade B returned damaged post-delivery'
        );

        // Confirm invoice payable reconciles to ₹5,600
        $this->assertEquals(5600.00, (float) $adjustedInvoice->final_total);
        $this->assertEquals(5600.00, (float) $adjustedInvoice->balance_amount);

        // Verify Credit Note summary reflects -₹50.00 difference for Grade B
        $creditSummary = $adjustedInvoice->creditNoteSummary();
        $this->assertTrue($creditSummary['has_changes']);
        $this->assertEquals(-50.00, (float) $creditSummary['net_difference']);
        $creditRow = $creditSummary['rows']->first();
        $this->assertSame('B', $creditRow['product_grade']);
        $this->assertEquals(25.00, (float) $creditRow['rate']);
        $this->assertEquals(18.0, (float) $creditRow['previous_qty']);
        $this->assertEquals(16.0, (float) $creditRow['revised_qty']);
        $this->assertEquals(-50.00, (float) $creditRow['difference']);

        // Confirm cashbook projection transaction in-place updated to ₹5,600
        $txUpdated = ShopLedgerTransaction::where('reference_type', ShopInvoice::class)
            ->where('reference_id', $adjustedInvoice->id)
            ->first();
        $this->assertNotNull($txUpdated);
        $this->assertEquals(5600.00, (float) $txUpdated->amount);

        // Confirm NO duplicate transaction was created
        $txCount = ShopLedgerTransaction::where('reference_type', ShopInvoice::class)
            ->where('reference_id', $adjustedInvoice->id)
            ->count();
        $this->assertSame(1, $txCount);

        // Verify ShopFinancialReportService sums final_total accurately to ₹5,600
        $reportService = app(ShopFinancialReportService::class);
        $report = $reportService->generate($this->shopPilot->id, '2026-10-01', '2026-10-31', 'month');
        $this->assertEquals(5600.00, (float) $report['summary']['gl_bills']);
    }

    /**
     * 6. User Interface Rendering & Layout Presentation Verification.
     */
    public function test_06_ui_screen_rendering_verification(): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => MultiGradeOrderGate::SETTING_KEY],
            ['value' => '1']
        );

        // 6A. Shop Ordering Form Partial rendering:
        // Pilot Shop: shows Grade B & Grade C inputs
        $orderPilot = ShopOrder::factory()->create([
            'shop_id' => $this->shopPilot->id,
            'business_date' => $this->businessDate,
        ]);
        $viewPilot = view('shop-owner.orders.partials.product-selection-table', [
            'productsByCategory' => Category::with('products.orderUnits')->get(),
            'frequentProducts' => collect(),
            'tomorrowOrder' => $orderPilot,
            'yesterdayOrder' => null,
            'presets' => collect(),
            'multiGradeOrderingEnabled' => true,
            'allowedProductGrades' => ['A', 'B', 'C'],
        ])->render();

        $this->assertStringContainsString('Grade B', $viewPilot);
        $this->assertStringContainsString('Grade C', $viewPilot);
        $this->assertStringContainsString('item_grades[TOM-H|B]', $viewPilot);
        $this->assertStringContainsString('item_grades[TOM-H|C]', $viewPilot);

        // Standard Shop: does NOT show Grade B or Grade C inputs
        $orderStd = ShopOrder::factory()->create([
            'shop_id' => $this->shopStandard->id,
            'business_date' => $this->businessDate,
        ]);
        $viewStd = view('shop-owner.orders.partials.product-selection-table', [
            'productsByCategory' => Category::with('products.orderUnits')->get(),
            'frequentProducts' => collect(),
            'tomorrowOrder' => $orderStd,
            'yesterdayOrder' => null,
            'presets' => collect(),
            'multiGradeOrderingEnabled' => true,
            'allowedProductGrades' => ['A'],
        ])->render();

        $this->assertStringNotContainsString('item_grades[TOM-H|B]', $viewStd);
        $this->assertStringNotContainsString('item_grades[TOM-H|C]', $viewStd);

        // 6B. Invoice Web View Rendering (purchasing/shop-invoices/show)
        $invoice = ShopInvoice::create([
            'shop_id' => $this->shopPilot->id,
            'shop_order_id' => $orderPilot->id,
            'invoice_number' => 'INV-PILOT-001',
            'business_date' => $this->businessDate,
            'status' => 'generated',
            'subtotal' => 5650.00,
            'shortage_total' => 50.00,
            'final_total' => 5650.00,
            'balance_amount' => 5650.00,
        ]);
        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->tomatoH->id,
            'product_name' => 'Tomato H',
            'product_grade' => 'A',
            'unit' => 'KG',
            'price_unit' => 'KG',
            'approved_qty' => 100.0,
            'delivered_qty' => 100.0,
            'unit_price' => 40.00,
            'line_subtotal' => 4000.00,
            'final_line_total' => 4000.00,
        ]);
        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->tomatoH->id,
            'product_name' => 'Tomato H',
            'product_grade' => 'B',
            'unit' => 'KG',
            'price_unit' => 'KG',
            'approved_qty' => 20.0,
            'delivered_qty' => 18.0,
            'shortage_qty' => 2.0,
            'shortage_amount' => 50.00,
            'unit_price' => 25.00,
            'line_subtotal' => 500.00,
            'final_line_total' => 450.00,
        ]);
        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->tomatoH->id,
            'product_name' => 'Tomato H',
            'product_grade' => 'C',
            'unit' => 'KG',
            'price_unit' => 'KG',
            'approved_qty' => 80.0,
            'delivered_qty' => 80.0,
            'unit_price' => 15.00,
            'line_subtotal' => 1200.00,
            'final_line_total' => 1200.00,
        ]);

        $invoiceWebHtml = view('purchasing.shop-invoices.show', [
            'invoice' => $invoice->load(['shop.priceGroup', 'items.product', 'order']),
            'availableStatusTransitions' => [],
            'canEditPricing' => true,
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('Tomato H', $invoiceWebHtml);
        $this->assertStringContainsString('Grade B', $invoiceWebHtml);
        $this->assertStringContainsString('Grade C', $invoiceWebHtml);

        // 6C. Invoice PDF Rendering (purchasing/shop-invoices/dompdf)
        $invoicePdfHtml = view('purchasing.shop-invoices.dompdf', [
            'invoice' => $invoice->load(['shop.priceGroup', 'items.product', 'order']),
        ])->render();

        $this->assertStringContainsString('Tomato H', $invoicePdfHtml);
        $this->assertStringContainsString('Grade B', $invoicePdfHtml);
        $this->assertStringContainsString('Grade C', $invoicePdfHtml);

        // 6D. Shop Owner Invoice View (shop-owner/finance/partials/invoice-card)
        $shopOwnerHtml = view('shop-owner.finance.partials.invoice-card', [
            'invoice' => $invoice->load(['items.product']),
            'shop' => $this->shopPilot,
        ])->render();

        $this->assertStringContainsString('Tomato H', $shopOwnerHtml);
        $this->assertStringContainsString('Grade B', $shopOwnerHtml);
        $this->assertStringContainsString('Grade C', $shopOwnerHtml);
    }
}
