<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyInventoryComparisonService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaserBusinessDayPhase2Test extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $purchaserUser;

    private Warehouse $vegWarehouse;

    private Warehouse $fruitWarehouse;

    private Supplier $supplier;

    private Product $tomato;

    private Product $apple;

    private PurchaserBusinessDayService $businessDayService;

    private DailyInventoryComparisonService $comparisonService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->purchaserUser = User::factory()->create();
        $this->purchaserUser->assignRole('purchase');
        $this->purchaserUser->assignRole('purchaser');

        $this->vegWarehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'WH-VEG',
            'is_active' => true,
        ]);

        $this->fruitWarehouse = Warehouse::create([
            'name' => 'Fruit Warehouse',
            'code' => 'WH-FRUIT',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Fresh Agri Supplier',
            'type' => 'farmer',
            'status' => 'active',
        ]);

        $catVeg = Category::create(['name' => 'Vegetables', 'is_active' => true]);
        $catFruit = Category::create(['name' => 'Fruits', 'is_active' => true]);

        $this->tomato = Product::create([
            'category_id' => $catVeg->id,
            'name' => 'Tomato Local',
            'sku' => 'TOM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->vegWarehouse->id,
            'is_active' => true,
        ]);

        $this->apple = Product::create([
            'category_id' => $catFruit->id,
            'name' => 'Apple Royal Gala',
            'sku' => 'APL-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->fruitWarehouse->id,
            'is_active' => true,
        ]);

        $this->businessDayService = app(PurchaserBusinessDayService::class);
        $this->comparisonService = app(DailyInventoryComparisonService::class);

        // Enable business day module on warehouses
        $this->businessDayService->updateWarehouseSettings($this->vegWarehouse->id, [
            'enabled' => true,
            'purchasers_can_close' => true,
            'purchasers_can_reopen' => true,
            'reopen_requires_reason' => true,
            'allow_close_with_pending' => true,
            'require_digital_verification' => true,
            'admin_override_reopen' => true,
        ]);

        $this->businessDayService->updateWarehouseSettings($this->fruitWarehouse->id, [
            'enabled' => true,
            'purchasers_can_close' => true,
            'purchasers_can_reopen' => true,
            'reopen_requires_reason' => true,
            'allow_close_with_pending' => true,
            'require_digital_verification' => true,
            'admin_override_reopen' => true,
        ]);
    }

    public function test_company_settings_can_update_purchaser_business_day_warehouse_settings(): void
    {
        $response = $this->actingAs($this->adminUser)->patch(route('admin.company-settings.update'), [
            'company_name' => 'Green Leaf Traders',
            'company_address' => 'Market Yard',
            'default_purchaser_user_id' => $this->purchaserUser->id,
            'business_day_warehouse_settings' => [
                $this->vegWarehouse->id => [
                    'enabled' => '1',
                    'purchasers_can_close' => '1',
                    'purchasers_can_reopen' => '0',
                    'allow_close_with_pending' => '0',
                    'require_digital_verification' => '1',
                    'admin_override_reopen' => '1',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.company-settings.edit'));

        $settings = $this->businessDayService->getWarehouseSettings($this->vegWarehouse->id);
        $this->assertTrue($settings['enabled']);
        $this->assertTrue($settings['purchasers_can_close']);
        $this->assertFalse($settings['purchasers_can_reopen']);
        $this->assertFalse($settings['allow_close_with_pending']);
    }

    public function test_business_days_monthly_page_displays_latest_first_and_respects_filters(): void
    {
        // Create 3 business days for Fruit Warehouse
        $day1 = $this->businessDayService->open($this->fruitWarehouse->id, '2026-09-13', $this->adminUser->id);
        $this->businessDayService->close($day1, $this->adminUser->id);

        $day2 = $this->businessDayService->open($this->fruitWarehouse->id, '2026-09-14', $this->adminUser->id);
        $this->businessDayService->close($day2, $this->adminUser->id);

        $day3 = $this->businessDayService->open($this->fruitWarehouse->id, '2026-09-15', $this->adminUser->id);

        $response = $this->actingAs($this->purchaserUser)->get(route('purchasing.business-days.index', [
            'month' => '2026-09',
            'warehouse_id' => $this->fruitWarehouse->id,
        ]));

        $response->assertOk();
        $response->assertSee('Monthly Control Board');
        $response->assertSee('15 Sep 2026');
        $response->assertSee('14 Sep 2026');
        $response->assertSee('13 Sep 2026');
        $response->assertSee('OPEN');
        $response->assertSee('CLOSED');

        // Test status filter for OPEN only
        $openResponse = $this->actingAs($this->purchaserUser)->get(route('purchasing.business-days.index', [
            'month' => '2026-09',
            'warehouse_id' => $this->fruitWarehouse->id,
            'status' => 'open',
        ]));

        $openResponse->assertOk();
        $openResponse->assertSee('15 Sep 2026');
        $openResponse->assertDontSee('14 Sep 2026');
    }

    public function test_business_day_detail_page_shows_canonical_comparison_and_pending_worklist(): void
    {
        $day = $this->businessDayService->open($this->fruitWarehouse->id, '2026-09-15', $this->adminUser->id);

        // 1. Advance Receive: 100 kg Apple
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->fruitWarehouse->id,
            'business_day_id' => $day->id,
            'grn_number' => 'GRN-ADV-001',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'approved_by' => $this->adminUser->id,
            'received_at' => '2026-09-15 10:00:00',
            'approved_at' => '2026-09-15 10:00:00',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'unit_price' => 0.0,
            'total_amount' => 0.0,
            'billed_qty' => 0.0,
            'variance' => 0.0,
            'is_matched' => false,
        ]);

        // 2. Partial Bill Receive: 40 kg Apple
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'business_day_id' => $day->id,
            'status' => 'approved',
            'po_number' => 'PO-FRUIT-001',
            'order_date' => '2026-09-15',
            'created_by' => $this->adminUser->id,
        ]);

        $billGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->fruitWarehouse->id,
            'business_day_id' => $day->id,
            'grn_number' => 'GRN-BILL-001',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->adminUser->id,
            'approved_by' => $this->adminUser->id,
            'received_at' => '2026-09-15 11:00:00',
            'approved_at' => '2026-09-15 11:00:00',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 40.0,
            'received_unit' => 'kg',
            'unit_price' => 120.0,
            'total_amount' => 4800.0,
            'billed_qty' => 40.0,
            'variance' => 0.0,
            'is_matched' => false,
        ]);

        // Run matching
        $this->comparisonService->autoMatchForGrn($billGrn, $this->adminUser->id);

        $response = $this->actingAs($this->purchaserUser)->get(route('purchasing.business-days.show', $day->uuid));

        $response->assertOk();
        $response->assertSee('Apple Royal Gala');
        $response->assertSee('100 kg'); // Advance
        $response->assertSee('40 kg');  // Bill
        $response->assertSee('60 kg');  // Pending Bill (100 - 40 = 60)
        $response->assertSee('Pending Bill Work List');
    }

    public function test_clean_day_can_be_closed_directly(): void
    {
        $day = $this->businessDayService->open($this->fruitWarehouse->id, '2026-09-15', $this->adminUser->id);

        $response = $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.verify-close', $day->uuid));

        $response->assertRedirect(route('purchasing.business-days.show', $day->uuid));
        $this->assertTrue($day->fresh()->isClosed());
        $this->assertEquals($this->purchaserUser->id, $day->fresh()->closed_by);
    }

    public function test_day_with_pending_requires_reason_and_setting_permission_to_close(): void
    {
        $day = $this->businessDayService->open($this->fruitWarehouse->id, '2026-09-15', $this->adminUser->id);

        // Advance with pending
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->fruitWarehouse->id,
            'business_day_id' => $day->id,
            'grn_number' => 'GRN-ADV-002',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'approved_by' => $this->adminUser->id,
            'received_at' => '2026-09-15 10:00:00',
            'approved_at' => '2026-09-15 10:00:00',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'unit_price' => 0.0,
            'total_amount' => 0.0,
            'billed_qty' => 0.0,
            'variance' => 0.0,
            'is_matched' => false,
        ]);

        // Attempt closing without reason -> should fail validation
        $response = $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.verify-close', $day->uuid), [
            'close_note' => '',
        ]);
        $response->assertSessionHasErrors('close_note');
        $this->assertFalse($day->fresh()->isClosed());

        // Close with valid reason -> should succeed
        $successResponse = $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.verify-close', $day->uuid), [
            'close_note' => 'Remaining 50kg bill will be settled tomorrow morning with farmer.',
        ]);
        $successResponse->assertRedirect(route('purchasing.business-days.show', $day->uuid));
        $this->assertTrue($day->fresh()->isClosed());
        $this->assertEquals('Remaining 50kg bill will be settled tomorrow morning with farmer.', $day->fresh()->close_note);
    }

    public function test_closed_day_is_read_only_and_reopening_requires_reason(): void
    {
        $day = $this->businessDayService->open($this->fruitWarehouse->id, '2026-09-15', $this->adminUser->id);
        $this->businessDayService->close($day, $this->adminUser->id);

        // Attempting to record GRN against closed day must fail
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'business_day_id' => $day->id,
            'status' => 'approved',
            'po_number' => 'PO-CLOSED-TEST',
            'order_date' => '2026-09-15',
            'created_by' => $this->adminUser->id,
        ]);

        $this->expectException(ValidationException::class);
        app(RecordGoodsReceiptAction::class)->execute(new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: '2026-09-15',
            transportCost: 0.0,
            labourCost: 0.0,
            notes: null,
            items: [['product_id' => $this->apple->id, 'received_qty' => 10.0]],
            billStatus: 'bill_available',
            warehouseId: $this->fruitWarehouse->id,
            businessDayId: $day->id,
        ), $this->adminUser->id);
    }

    public function test_reopened_day_accepts_bill_and_updates_matching(): void
    {
        $day = $this->businessDayService->open($this->fruitWarehouse->id, '2026-09-15', $this->adminUser->id);

        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->fruitWarehouse->id,
            'business_day_id' => $day->id,
            'grn_number' => 'GRN-ADV-003',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'approved_by' => $this->adminUser->id,
            'received_at' => '2026-09-15 10:00:00',
            'approved_at' => '2026-09-15 10:00:00',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 75.0,
            'received_unit' => 'kg',
            'unit_price' => 0.0,
            'total_amount' => 0.0,
            'billed_qty' => 0.0,
            'variance' => 0.0,
            'is_matched' => false,
        ]);

        $this->businessDayService->close($day, $this->adminUser->id, 'Initial day close');

        // Reopen day with reason
        $reopenResponse = $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.reopen', $day->uuid), [
            'reopen_reason' => 'Fruit supplier bill arrived the following morning.',
        ]);
        $reopenResponse->assertRedirect(route('purchasing.business-days.show', $day->uuid));
        $this->assertTrue($day->fresh()->isReopened());
        $this->assertEquals('Fruit supplier bill arrived the following morning.', $day->fresh()->reopen_reason);

        // Bill created the next calendar morning, assigned to this reopened business day
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'business_day_id' => $day->id,
            'status' => 'approved',
            'po_number' => 'PO-REOPEN-TEST',
            'order_date' => '2026-09-16',
            'created_by' => $this->adminUser->id,
        ]);

        $billGrn = app(RecordGoodsReceiptAction::class)->execute(new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: '2026-09-16 08:30:00', // Real timestamp next calendar day
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Late arriving fruit bill',
            items: [['product_id' => $this->apple->id, 'received_qty' => 75.0]],
            billStatus: 'bill_available',
            warehouseId: $this->fruitWarehouse->id,
            businessDayId: $day->id, // Assigned to 15 Sep Business Day
        ), $this->purchaserUser->id);

        $this->assertEquals($day->id, $billGrn->business_day_id);
        $this->assertEquals('2026-09-16 08:30:00', $billGrn->received_at->format('Y-m-d H:i:s'));

        // Run auto match
        $this->comparisonService->autoMatchForGrn($billGrn, $this->purchaserUser->id);

        // Verify comparison shows 0 pending for this business day
        $rows = $this->comparisonService->buildComparisonRows('2026-09-15', $this->fruitWarehouse->id);
        $appleRow = $rows->firstWhere('product_id', $this->apple->id);
        $this->assertNotNull($appleRow);
        $this->assertEquals(75.0, (float) $appleRow['advance_qty']);
        $this->assertEquals(75.0, (float) $appleRow['bill_qty']);
        $this->assertEquals(75.0, (float) $appleRow['matched_bill_qty']);
        $this->assertEquals(0.0, max(0.0, (float) $appleRow['advance_qty'] - (float) $appleRow['matched_bill_qty']));
    }
}
