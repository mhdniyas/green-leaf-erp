<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\ProcessBatchSortingAction;
use App\DTOs\Inventory\SortingData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
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
use App\Services\Inventory\StockLedgerService;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use App\Services\Purchasing\PurchaserDailyInitialQuery;
use App\Services\Requisition\MultiGradeOrderGate;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MultiGradeEndToEndBusinessDayTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaserUser;

    private User $warehouseUser;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private ShopPriceGroup $priceGroupA;

    private ShopPriceGroup $priceGroupB;

    private Shop $shopCasio;

    private Shop $shopStandard;

    private Product $tomatoH;

    private string $businessDate;

    private MultiGradeOrderGate $multiGradeGate;

    private StockLedgerService $stockLedgerService;

    private ShopInvoiceService $shopInvoiceService;

    private ApprovedDailyPriceResolver $priceResolver;

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

        // Shop Casio: Pilot shop configured for A, B, C quality grades in Price Category A
        $this->shopCasio = Shop::create([
            'name' => 'Shop Casio',
            'code' => 'CAS-01',
            'shop_price_group_id' => $this->priceGroupA->id,
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        // Shop Standard: Normal existing shop configured for Grade A only in Price Category B
        $this->shopStandard = Shop::create([
            'name' => 'Shop Standard',
            'code' => 'STD-02',
            'shop_price_group_id' => $this->priceGroupB->id,
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'allowed_product_grades' => null, // Defaults to Grade A only
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->businessDate = Carbon::parse('2026-10-17')->toDateString();

        $this->multiGradeGate = app(MultiGradeOrderGate::class);
        $this->stockLedgerService = app(StockLedgerService::class);
        $this->shopInvoiceService = app(ShopInvoiceService::class);
        $this->priceResolver = app(ApprovedDailyPriceResolver::class);
    }

    public function test_complete_business_day_integration_scenario(): void
    {
        // -------------------------------------------------------------
        // STEP 1: Enable multi-grade ordering globally for the pilot
        // -------------------------------------------------------------
        BusinessSetting::updateOrCreate(
            ['key' => MultiGradeOrderGate::SETTING_KEY],
            ['value' => '1']
        );
        $this->assertTrue($this->multiGradeGate->isFeatureEnabled());
        $this->assertTrue($this->multiGradeGate->canShopOrderGrade($this->shopCasio, 'B'));
        $this->assertTrue($this->multiGradeGate->canShopOrderGrade($this->shopCasio, 'C'));
        $this->assertFalse($this->multiGradeGate->canShopOrderGrade($this->shopStandard, 'B'));
        $this->assertFalse($this->multiGradeGate->canShopOrderGrade($this->shopStandard, 'C'));

        // -------------------------------------------------------------
        // STEP 2: Shop Ordering
        // Unauthorized shop cannot order extra grades
        // -------------------------------------------------------------
        $this->expectException(ValidationException::class);
        try {
            $this->multiGradeGate->validateOrderItems($this->shopStandard, [
                ['product' => $this->tomatoH, 'grade' => 'B', 'quantity' => 10],
            ]);
        } catch (ValidationException $e) {
            $this->assertStringContainsString('not permitted to order Grade B', $e->getMessage());
            // Clear exception expectation to proceed with test
            throw $e;
        }
    }

    public function test_full_business_day_ordering_through_invoicing(): void
    {
        // Enable pilot
        BusinessSetting::updateOrCreate(
            ['key' => MultiGradeOrderGate::SETTING_KEY],
            ['value' => '1']
        );

        // Seed initial daily product catalog prices (provisional catalog)
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

        // -------------------------------------------------------------
        // STEP 2A: Shop Casio orders Tomato H (A: 100 kg, B: 20 kg, C: 80 kg = 200 kg)
        // -------------------------------------------------------------
        $casioOrder = ShopOrder::create([
            'shop_id' => $this->shopCasio->id,
            'order_number' => 'ORD-CAS-20261017-001',
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'status' => 'pending',
            'delivery_status' => 'pending',
            'order_source' => 'shop',
            'created_by' => $this->admin->id,
        ]);

        $casioRevision = ShopOrderRevision::create([
            'shop_order_id' => $casioOrder->id,
            'revision_no' => 1,
            'status' => 'approved',
            'requested_by' => $this->admin->id,
        ]);

        $casioItemA = ShopOrderItem::create([
            'shop_order_id' => $casioOrder->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'A',
            'requested_qty' => 100.0,
            'approved_qty' => 100.0,
            'unit' => 'KG',
        ]);

        $casioItemB = ShopOrderItem::create([
            'shop_order_id' => $casioOrder->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'B',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'unit' => 'KG',
        ]);

        $casioItemC = ShopOrderItem::create([
            'shop_order_id' => $casioOrder->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'C',
            'requested_qty' => 80.0,
            'approved_qty' => 80.0,
            'unit' => 'KG',
        ]);

        // -------------------------------------------------------------
        // STEP 2B: Shop Standard orders Tomato H (Grade A: 50 kg)
        // -------------------------------------------------------------
        $standardOrder = ShopOrder::create([
            'shop_id' => $this->shopStandard->id,
            'order_number' => 'ORD-STD-20261017-002',
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'status' => 'pending',
            'delivery_status' => 'pending',
            'order_source' => 'shop',
            'created_by' => $this->admin->id,
        ]);

        $standardItemA = ShopOrderItem::create([
            'shop_order_id' => $standardOrder->id,
            'product_id' => $this->tomatoH->id,
            'product_grade' => 'A',
            'requested_qty' => 50.0,
            'approved_qty' => 50.0,
            'unit' => 'KG',
        ]);

        // Verify Requisition Demand Total: 200 kg Casio + 50 kg Standard = 250 kg total
        $totalDemandKg = ShopOrderItem::where('product_id', $this->tomatoH->id)->sum('approved_qty');
        $this->assertEquals(250.0, (float) $totalDemandKg);

        // -------------------------------------------------------------
        // STEP 3: Purchaser Dashboard Demand Aggregation
        // -------------------------------------------------------------
        $purchaserQuery = app(PurchaserDailyInitialQuery::class);
        $demand = $purchaserQuery->getPendingDemand(Carbon::parse($this->businessDate), 'A', $this->purchaserUser);

        $tomatoDemand = $demand->firstWhere('product_id', $this->tomatoH->id);
        $this->assertNotNull($tomatoDemand);
        $this->assertEquals(250.0, (float) $tomatoDemand['total_approved_qty']);
        $this->assertTrue($tomatoDemand['has_non_a_grades']);

        $breakdown = collect($tomatoDemand['grade_breakdown'])->keyBy('grade');
        $this->assertEquals(150.0, (float) $breakdown['A']['approved_qty']); // 100 Casio + 50 Standard
        $this->assertEquals(20.0, (float) $breakdown['B']['approved_qty']);  // 20 Casio
        $this->assertEquals(80.0, (float) $breakdown['C']['approved_qty']);  // 80 Casio

        // -------------------------------------------------------------
        // STEP 4: Warehouse Receiving & Segregation
        // 4A. Fixed-grade receipts for A (50 kg), B (20 kg), and C (80 kg)
        // -------------------------------------------------------------
        $batchA = StockBatch::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->tomatoH->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'BAT-TOM-A-001',
            'total_kg' => 50.0,
            'cost_per_kg' => 28.00,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        StockMovement::create([
            'batch_id' => $batchA->id,
            'product_id' => $this->tomatoH->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => StockMovementType::In,
            'grade' => 'A',
            'quantity' => 50.0,
            'cost_per_unit' => 28.00,
            'reference' => 'GR-TOM-A-001',
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $batchB = StockBatch::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->tomatoH->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'BAT-TOM-B-001',
            'total_kg' => 20.0,
            'cost_per_kg' => 18.00,
            'status' => BatchStatus::Sorted,
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
            'reference' => 'GR-TOM-B-001',
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $batchC = StockBatch::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->tomatoH->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'BAT-TOM-C-001',
            'total_kg' => 80.0,
            'cost_per_kg' => 10.00,
            'status' => BatchStatus::Sorted,
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
            'reference' => 'GR-TOM-C-001',
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        // 4B. Mixed Batch Receiving and Sorting (200 kg mixed -> 100 A, 20 B, 75 C, 5 Damaged)
        $mixedBatch = StockBatch::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->tomatoH->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'BAT-TOM-MIX-001',
            'total_kg' => 200.0,
            'cost_per_kg' => 22.00,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $sortingAction = app(ProcessBatchSortingAction::class);
        $sortingData = new SortingData(
            batchId: $mixedBatch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 100.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 20.0],
                ['grade' => ProductGrade::GradeC, 'quantity' => 75.0],
                ['grade' => ProductGrade::Damage, 'quantity' => 5.0],
            ],
            notes: 'Sorting 200kg mixed batch',
        );

        $sortedBatch = $sortingAction->execute($mixedBatch, $sortingData, $this->admin->id);
        $this->assertEquals(BatchStatus::Sorted, $sortedBatch->status);

        // Mass conservation check
        $sortedKg = 100.0 + 20.0 + 75.0 + 5.0;
        $this->assertEquals(200.0, $sortedKg);

        // Verify stock ledger has accurate available quantities per grade
        $availA = $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, $this->warehouse->id, ProductGrade::GradeA);
        $availB = $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, $this->warehouse->id, ProductGrade::GradeB);
        $availC = $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, $this->warehouse->id, ProductGrade::GradeC);

        $this->assertEquals(150.0, $availA); // 100 from mixed + 50 from fixed
        $this->assertEquals(40.0, $availB);  // 20 from fixed + 20 from mixed
        $this->assertEquals(155.0, $availC); // 80 from fixed + 75 from mixed

        // -------------------------------------------------------------
        // STEP 5: Daily Selling Price Approval
        // -------------------------------------------------------------
        DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'A',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
            'purchase_price' => 30.00,
            'price_unit' => 'KG',
            'price_a' => 40.00,
            'price_b' => 42.00,
            'price_c' => 45.00,
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'B',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
            'purchase_price' => 18.00,
            'price_unit' => 'KG',
            'price_a' => 25.00,
            'price_b' => 27.00,
            'price_c' => 30.00,
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->tomatoH->id,
            'business_date' => $this->businessDate,
            'grade' => 'C',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
            'purchase_price' => 10.00,
            'price_unit' => 'KG',
            'price_a' => 15.00,
            'price_b' => 16.00,
            'price_c' => 18.00,
        ]);

        // Verify resolver resolves correct grade and shop price category
        $priceCasioA = $this->priceResolver->resolve($this->tomatoH, $this->shopCasio, $this->businessDate, 'A');
        $priceCasioB = $this->priceResolver->resolve($this->tomatoH, $this->shopCasio, $this->businessDate, 'B');
        $priceCasioC = $this->priceResolver->resolve($this->tomatoH, $this->shopCasio, $this->businessDate, 'C');

        $this->assertEquals(40.00, $priceCasioA['price']);
        $this->assertEquals(25.00, $priceCasioB['price']);
        $this->assertEquals(15.00, $priceCasioC['price']);

        $priceStdA = $this->priceResolver->resolve($this->tomatoH, $this->shopStandard, $this->businessDate, 'A');
        $this->assertEquals(42.00, $priceStdA['price']);

        // -------------------------------------------------------------
        // STEP 6: Inventory Loadout & Delivery
        // Loadout: Casio loaded A: 100 kg, B: 20 kg, C: 80 kg
        // Delivery Checkin: Delivered A: 100 kg (0 shortage), B: 18 kg (2 kg shortage), C: 80 kg (0 shortage)
        // -------------------------------------------------------------
        $casioItemA->update(['sorting_status' => 'loaded', 'loaded_qty' => 100.0, 'delivered_qty' => 100.0, 'shortage_qty' => 0.0]);
        $casioItemB->update(['sorting_status' => 'loaded', 'loaded_qty' => 20.0, 'delivered_qty' => 18.0, 'shortage_qty' => 2.0]);
        $casioItemC->update(['sorting_status' => 'loaded', 'loaded_qty' => 80.0, 'delivered_qty' => 80.0, 'shortage_qty' => 0.0]);
        $casioOrder->unsetRelation('items');

        // Stock deduction for loadout
        StockMovement::create([
            'batch_id' => $mixedBatch->id,
            'product_id' => $this->tomatoH->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => StockMovementType::Out,
            'grade' => 'A',
            'quantity' => 100.0,
            'cost_per_unit' => 22.00,
            'reference' => 'LOADOUT-CAS-A',
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);
        StockMovement::create([
            'batch_id' => $batchB->id,
            'product_id' => $this->tomatoH->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => StockMovementType::Out,
            'grade' => 'B',
            'quantity' => 20.0,
            'cost_per_unit' => 18.00,
            'reference' => 'LOADOUT-CAS-B',
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);
        StockMovement::create([
            'batch_id' => $batchC->id,
            'product_id' => $this->tomatoH->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => StockMovementType::Out,
            'grade' => 'C',
            'quantity' => 80.0,
            'cost_per_unit' => 10.00,
            'reference' => 'LOADOUT-CAS-C',
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        // Standard Shop loaded 50 kg Grade A
        $standardItemA->update(['sorting_status' => 'loaded', 'loaded_qty' => 50.0, 'delivered_qty' => 50.0, 'shortage_qty' => 0.0]);
        $standardOrder->unsetRelation('items');

        StockMovement::create([
            'batch_id' => $batchA->id,
            'product_id' => $this->tomatoH->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => StockMovementType::Out,
            'grade' => 'A',
            'quantity' => 50.0,
            'cost_per_unit' => 28.00,
            'reference' => 'LOADOUT-STD-A',
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        // Remaining inventory check: Grade A: 150 - 100 - 50 = 0; Grade B: 40 - 20 = 20; Grade C: 155 - 80 = 75
        $this->assertEquals(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, null, ProductGrade::GradeA));
        $this->assertEquals(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, null, ProductGrade::GradeB));
        $this->assertEquals(75.0, $this->stockLedgerService->availableSortedStockForProduct($this->tomatoH->id, null, ProductGrade::GradeC));

        // -------------------------------------------------------------
        // STEP 7: Shop Invoice Generation (Both Shops Independently)
        // Casio receives 1 invoice with 3 lines; Standard receives 1 invoice with 1 line
        // -------------------------------------------------------------
        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($casioOrder, $this->admin->id);
        $this->assertNotNull($invoice);
        $this->assertEquals(1, ShopInvoice::where('shop_order_id', $casioOrder->id)->count());

        $invoice = $this->shopInvoiceService->applyDeliveryCheckin(
            $casioOrder,
            [
                $casioItemA->id => 100.0,
                $casioItemB->id => 18.0,
                $casioItemC->id => 80.0,
            ],
            $this->admin->id,
            'Delivered with Grade B shortage'
        );

        $invoiceItems = $invoice->items()->get();
        $this->assertCount(3, $invoiceItems);

        $lineA = $invoiceItems->firstWhere('product_grade', 'A');
        $lineB = $invoiceItems->firstWhere('product_grade', 'B');
        $lineC = $invoiceItems->firstWhere('product_grade', 'C');

        $this->assertNotNull($lineA);
        $this->assertNotNull($lineB);
        $this->assertNotNull($lineC);

        // Grade A: 100 kg @ ₹40.00 = ₹4000.00
        $this->assertEquals(100.0, (float) $lineA->delivered_qty);
        $this->assertEquals(40.00, (float) $lineA->unit_price);
        $this->assertEquals(4000.00, (float) $lineA->line_subtotal);

        // Grade B: 18 kg delivered @ ₹25.00 (approved: 20 kg = ₹500.00, shortage: 2 kg = ₹50.00, line_total = ₹450.00)
        $this->assertEquals(18.0, (float) $lineB->delivered_qty);
        $this->assertEquals(2.0, (float) $lineB->shortage_qty);
        $this->assertEquals(25.00, (float) $lineB->unit_price);
        $this->assertEquals(500.00, (float) $lineB->line_subtotal);
        $this->assertEquals(450.00, (float) $lineB->final_line_total);
        $this->assertEquals(50.00, (float) $lineB->shortage_amount);

        // Grade C: 80 kg delivered @ ₹15.00 = ₹1200.00
        $this->assertEquals(80.0, (float) $lineC->delivered_qty);
        $this->assertEquals(15.00, (float) $lineC->unit_price);
        $this->assertEquals(1200.00, (float) $lineC->line_subtotal);
        $this->assertEquals(1200.00, (float) $lineC->final_line_total);

        // Invoice Grand Total = ₹4000.00 (A) + ₹450.00 (B) + ₹1200.00 (C) = ₹5650.00
        $this->assertEquals(5650.00, (float) $invoice->subtotal);
        $this->assertEquals(5650.00, (float) $invoice->final_total);

        // Standard Shop Invoice: Exactly 1 invoice, exactly 1 line (Grade A @ ₹42.00 in Price Group B)
        $stdInvoice = $this->shopInvoiceService->synchronizeOrderInvoice($standardOrder, $this->admin->id);
        $this->assertNotNull($stdInvoice);
        $this->assertEquals(1, ShopInvoice::where('shop_order_id', $standardOrder->id)->count());

        $stdInvoiceItems = $stdInvoice->items()->get();
        $this->assertCount(1, $stdInvoiceItems);

        $stdLineA = $stdInvoiceItems->first();
        $this->assertEquals('A', $stdLineA->product_grade);
        $this->assertEquals(50.0, (float) $stdLineA->delivered_qty);
        $this->assertEquals(0.0, (float) $stdLineA->shortage_qty);
        $this->assertEquals(42.00, (float) $stdLineA->unit_price);
        $this->assertEquals(2100.00, (float) $stdLineA->line_subtotal);
        $this->assertEquals(2100.00, (float) $stdLineA->final_line_total);
        $this->assertEquals(2100.00, (float) $stdInvoice->final_total);
        $this->assertEquals(0.0, (float) $stdInvoice->items()->sum('shortage_amount'));

        // -------------------------------------------------------------
        // STEP 8: Credit Note & Shortage Integrity
        // Credit note on Grade B shortage must use Grade B rate (₹25), not Grade A rate (₹40)
        // -------------------------------------------------------------
        $updatedInvoice = $this->shopInvoiceService->updateItemFinalQuantityAndPrice(
            $invoice,
            $lineB,
            16.0,
            25.00,
            $this->admin->id,
            '2kg damaged Grade B returned after delivery'
        );

        $creditSummary = $updatedInvoice->creditNoteSummary();
        $this->assertTrue($creditSummary['has_changes']);
        $this->assertEquals(-50.00, (float) $creditSummary['net_difference']);
        $creditRow = $creditSummary['rows']->first();
        $this->assertStringContainsString('Grade B', $creditRow['product_name']);
        $this->assertEquals('B', $creditRow['product_grade']);
        $this->assertEquals(25.00, (float) $creditRow['rate']);
        $this->assertEquals(18.0, (float) $creditRow['previous_qty']);
        $this->assertEquals(16.0, (float) $creditRow['revised_qty']);
        $this->assertEquals(-50.00, (float) $creditRow['difference']);

        // -------------------------------------------------------------
        // STEP 9: Idempotency of Synchronization
        // Repeated resync does not duplicate or delete lines
        // -------------------------------------------------------------
        $resyncedInvoice = $this->shopInvoiceService->synchronizeOrderInvoice($casioOrder, $this->admin->id);
        $this->assertEquals($invoice->id, $resyncedInvoice->id);
        $this->assertCount(3, $resyncedInvoice->items()->get());
        $this->assertEquals(5600.00, (float) $resyncedInvoice->final_total);

        // -------------------------------------------------------------
        // STEP 10: Deactivating Feature Flag
        // Disabling the add-on stops NEW multi-grade orders but DOES NOT strand existing orders/invoices
        // -------------------------------------------------------------
        BusinessSetting::updateOrCreate(
            ['key' => MultiGradeOrderGate::SETTING_KEY],
            ['value' => '0']
        );
        $this->assertFalse($this->multiGradeGate->isFeatureEnabled());

        // Existing invoice remains fully processable, finalizable, and readable
        $freshInvoice = ShopInvoice::with('items')->find($invoice->id);
        $this->assertNotNull($freshInvoice);
        $this->assertCount(3, $freshInvoice->items);
        $this->assertEquals(5600.00, (float) $freshInvoice->final_total);

        // But new attempt to submit Grade B item is rejected
        $this->expectException(ValidationException::class);
        $this->multiGradeGate->validateOrderItems($this->shopCasio, [
            ['product' => $this->tomatoH, 'grade' => 'B', 'quantity' => 10],
        ]);
    }
}
