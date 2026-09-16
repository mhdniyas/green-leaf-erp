<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Cashbook;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseBusinessDay;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyInventoryComparisonService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPurchaserBusinessDayPhase4Test extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $purchaserUser;

    private User $unauthorizedUser;

    private Warehouse $vegWarehouse;

    private Warehouse $fruitWarehouse;

    private Supplier $supplier;

    private Product $tomato;

    private Product $bananaLeaf;

    private Product $apple;

    private PurchaserBusinessDayService $businessDayService;

    private DailyInventoryComparisonService $comparisonService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->purchaserUser = User::factory()->create(['name' => 'John Purchaser']);
        $this->purchaserUser->assignRole('purchase');
        $this->purchaserUser->assignRole('purchaser');

        $this->unauthorizedUser = User::factory()->create();

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
            'name' => 'Nilgiri Farms',
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

        $this->bananaLeaf = Product::create([
            'category_id' => $catVeg->id,
            'name' => 'Banana Leaf',
            'sku' => 'BL-01',
            'unit' => 'piece',
            'default_warehouse_id' => $this->vegWarehouse->id,
            'is_active' => true,
        ]);

        $this->apple = Product::create([
            'category_id' => $catFruit->id,
            'name' => 'Apple Royal Gala',
            'sku' => 'APL-01',
            'unit' => 'box',
            'default_warehouse_id' => $this->fruitWarehouse->id,
            'is_active' => true,
        ]);

        $this->businessDayService = app(PurchaserBusinessDayService::class);
        $this->comparisonService = app(DailyInventoryComparisonService::class);
    }

    public function test_admin_monthly_oversight_board_renders_latest_first_with_canonical_metrics(): void
    {
        $day1 = $this->businessDayService->open(
            (int) $this->vegWarehouse->id,
            '2026-09-14',
            (int) $this->purchaserUser->id
        );
        $this->businessDayService->close($day1, (int) $this->purchaserUser->id, 'Closing day 1');

        $day2 = $this->businessDayService->open(
            (int) $this->vegWarehouse->id,
            '2026-09-15',
            (int) $this->purchaserUser->id
        );

        $this->createGoodsReceipt($day2, $this->tomato, 450.0, 450.0);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.purchaser-business-days.index', [
                'month' => '2026-09',
            ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.purchaser-business-days.index');
        $response->assertSee('Purchaser Business Days');
        $response->assertSee('15 Sep 2026');
        $response->assertSee('14 Sep 2026');
        $response->assertSee('John Purchaser');

        // Check latest day ordering
        $daysData = $response->viewData('daysData');
        $this->assertCount(2, $daysData);
        $this->assertEquals($day2->id, $daysData->first()['day']->id);
    }

    public function test_admin_filters_by_month_warehouse_purchaser_status_and_reopened(): void
    {
        $vegDay = $this->businessDayService->open(
            (int) $this->vegWarehouse->id,
            '2026-09-10',
            (int) $this->purchaserUser->id
        );

        $fruitDay = $this->businessDayService->open(
            (int) $this->fruitWarehouse->id,
            '2026-09-10',
            (int) $this->purchaserUser->id
        );

        // Filter warehouse
        $responseVeg = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.purchaser-business-days.index', [
                'month' => '2026-09',
                'warehouse_id' => $this->vegWarehouse->id,
            ]));

        $responseVeg->assertOk();
        $daysData = $responseVeg->viewData('daysData');
        $this->assertCount(1, $daysData);
        $this->assertEquals($vegDay->id, $daysData->first()['day']->id);

        // Close fruit day and reopen it
        $this->businessDayService->close($fruitDay, (int) $this->purchaserUser->id, 'Closing test');
        $this->businessDayService->reopen($fruitDay, (int) $this->adminUser->id, 'Admin audit reopen');

        // Filter reopened only
        $responseReopened = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.purchaser-business-days.index', [
                'month' => '2026-09',
                'reopened_only' => 1,
            ]));

        $responseReopened->assertOk();
        $reopenedData = $responseReopened->viewData('daysData');
        $this->assertCount(1, $reopenedData);
        $this->assertEquals($fruitDay->id, $reopenedData->first()['day']->id);
    }

    public function test_admin_day_detail_displays_canonical_manager_summary_and_rows(): void
    {
        $day = $this->businessDayService->open(
            (int) $this->vegWarehouse->id,
            '2026-09-15',
            (int) $this->purchaserUser->id
        );

        $this->createGoodsReceipt($day, $this->tomato, 450.0, 450.0);
        $this->createGoodsReceipt($day, $this->bananaLeaf, 85.0, 85.0);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.purchaser-business-days.show', $day->uuid));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.purchaser-business-days.show');
        $response->assertSee('15 Sep 2026');
        $response->assertSee('Vegetable Warehouse');
        $response->assertSee('Tomato Local');
        $response->assertSee('Banana Leaf');
        $response->assertSee('450 kg');
        $response->assertSee('85 piece');

        // Check manager summary in viewData
        $managerSummary = $response->viewData('managerSummary');
        $this->assertEquals(2, $managerSummary['advance_receives']['count']);
    }

    public function test_admin_day_detail_preserves_first_close_pending_snapshot_when_reopened_and_closed_again(): void
    {
        $day = $this->businessDayService->open(
            (int) $this->vegWarehouse->id,
            '2026-09-15',
            (int) $this->purchaserUser->id
        );

        // Advance receive 450 kg Tomato
        $this->createGoodsReceipt($day, $this->tomato, 450.0, 450.0);

        // First close with 450 kg pending
        $this->businessDayService->close($day, (int) $this->purchaserUser->id, 'Missing bills for today');

        $day->refresh();
        $this->assertTrue($day->isClosed());
        $this->assertNotNull($day->first_close_pending_snapshot);
        $this->assertCount(1, $day->first_close_pending_snapshot);
        $this->assertEquals(450.0, (float) $day->first_close_pending_snapshot[0]['pending_qty']);
        $this->assertEquals('Missing bills for today', $day->close_note);

        // Admin reopens
        $this->businessDayService->reopen($day, (int) $this->adminUser->id, 'Bill arrived from farmer');
        $day->refresh();
        $this->assertTrue($day->isReopened());

        // Now add bill to clear pending
        $this->createPurchaseBillDirect($day, $this->tomato, 450.0, 30.0);

        // Close again
        $this->businessDayService->close($day, (int) $this->purchaserUser->id, 'All bills cleared now');
        $day->refresh();

        // Check that first_close_pending_snapshot is PRESERVED (still 450kg tomato)
        $this->assertNotNull($day->first_close_pending_snapshot);
        $this->assertCount(1, $day->first_close_pending_snapshot);
        $this->assertEquals(450.0, (float) $day->first_close_pending_snapshot[0]['pending_qty']);

        // Check latest close snapshot is now empty (0 pending)
        $this->assertEmpty($day->close_pending_snapshot);

        // Admin Day Detail should reflect both historical snapshot and current 0 pending
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.purchaser-business-days.show', $day->uuid));

        $response->assertOk();
        $response->assertSee('Historical Snapshot (At First Close)');
        $response->assertSee('All bills cleared now');
        $response->assertSee('Tomato Local');
        $response->assertSee('Current Realtime Pending Status');
    }

    public function test_admin_audit_timeline_contains_all_lifecycle_events(): void
    {
        $day = $this->businessDayService->open(
            (int) $this->vegWarehouse->id,
            '2026-09-15',
            (int) $this->purchaserUser->id
        );

        $this->createGoodsReceipt($day, $this->tomato, 450.0, 450.0);
        $this->businessDayService->close($day, (int) $this->purchaserUser->id, 'Initial close');
        $this->businessDayService->reopen($day, (int) $this->adminUser->id, 'Admin requested correction');
        $this->createPurchaseBillDirect($day, $this->tomato, 450.0, 30.0);
        $this->businessDayService->close($day, (int) $this->purchaserUser->id, 'Final close');
        $day->refresh();

        $timeline = $this->businessDayService->buildAuditTimeline($day);

        $titles = collect($timeline)->pluck('title')->all();

        $this->assertContains('Business Day Opened', $titles);
        $this->assertContains('Business Day Closed', $titles);
        $this->assertContains('Business Day Reopened', $titles);
        $this->assertTrue(collect($titles)->contains(fn ($t) => str_starts_with($t, 'Advance Received')));
        $this->assertTrue(collect($titles)->contains(fn ($t) => str_starts_with($t, 'Purchase Bill Recorded')));
        $this->assertTrue(collect($titles)->contains(fn ($t) => str_starts_with($t, 'Auto-Match')));
    }

    public function test_admin_can_reopen_closed_business_day_with_mandatory_reason(): void
    {
        $day = $this->businessDayService->open(
            (int) $this->vegWarehouse->id,
            '2026-09-15',
            (int) $this->purchaserUser->id
        );

        $this->businessDayService->close($day, (int) $this->purchaserUser->id, 'Closing end of day');

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.cashbook.purchaser-business-days.reopen', $day->uuid), [
                'reopen_reason' => 'Admin override: Supplier sent missing invoice late evening',
            ]);

        $response->assertRedirect(route('admin.cashbook.purchaser-business-days.show', $day->uuid));
        $response->assertSessionHas('success');

        $day->refresh();
        $this->assertTrue($day->isReopened());
        $this->assertEquals($this->adminUser->id, $day->reopened_by);
        $this->assertEquals('Admin override: Supplier sent missing invoice late evening', $day->reopen_reason);
    }

    public function test_admin_cannot_reopen_without_valid_reason(): void
    {
        $day = $this->businessDayService->open(
            (int) $this->vegWarehouse->id,
            '2026-09-15',
            (int) $this->purchaserUser->id
        );

        $this->businessDayService->close($day, (int) $this->purchaserUser->id, 'Closing end of day');

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.cashbook.purchaser-business-days.reopen', $day->uuid), [
                'reopen_reason' => '',
            ]);

        $response->assertSessionHasErrors(['reopen_reason']);
        $day->refresh();
        $this->assertTrue($day->isClosed());
    }

    public function test_admin_reports_hub_renders_all_tabs_with_consistent_data(): void
    {
        $day = $this->businessDayService->open(
            (int) $this->vegWarehouse->id,
            '2026-09-15',
            (int) $this->purchaserUser->id
        );

        $this->createGoodsReceipt($day, $this->tomato, 450.0, 450.0);

        // 1. Monthly Tab
        $responseMonthly = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.purchaser-business-days.reports', [
                'tab' => 'monthly',
                'month' => '2026-09',
            ]));
        $responseMonthly->assertOk();
        $responseMonthly->assertSee('Business Day Reporting Hub');
        $responseMonthly->assertSee('Monthly Summary');

        // 2. Pending Bills Tab
        $responsePending = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.purchaser-business-days.reports', [
                'tab' => 'pending',
            ]));
        $responsePending->assertOk();
        $responsePending->assertSee('Pending Purchase Bills Worklist');
        $responsePending->assertSee('Tomato Local');
        $responsePending->assertSee('450 kg');

        // 3. Reopened Tab
        $this->businessDayService->close($day, (int) $this->purchaserUser->id, 'Close note');
        $this->businessDayService->reopen($day, (int) $this->adminUser->id, 'Reason for reopen');

        $responseReopened = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.purchaser-business-days.reports', [
                'tab' => 'reopened',
                'month' => '2026-09',
            ]));
        $responseReopened->assertOk();
        $responseReopened->assertSee('Reason for reopen');

        // 4. Close-With-Pending Tab
        $responseCloseWithPending = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.purchaser-business-days.reports', [
                'tab' => 'close_with_pending',
                'month' => '2026-09',
            ]));
        $responseCloseWithPending->assertOk();
        $responseCloseWithPending->assertSee('Close-With-Pending');

        // 5. Vendor Pending Tab
        $responseVendor = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.purchaser-business-days.reports', [
                'tab' => 'vendor_pending',
            ]));
        $responseVendor->assertOk();
        $responseVendor->assertSee('Vendor Pending Aggregation');
    }

    public function test_admin_can_export_pending_bills_pdf(): void
    {
        $day = $this->businessDayService->open(
            (int) $this->vegWarehouse->id,
            '2026-09-15',
            (int) $this->purchaserUser->id
        );

        $this->createGoodsReceipt($day, $this->tomato, 450.0, 450.0);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.purchaser-business-days.reports.pending-pdf', [
                'warehouse_id' => $this->vegWarehouse->id,
            ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('content-disposition'));
    }

    public function test_unauthorized_user_cannot_access_admin_cashbook_oversight(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.cashbook.purchaser-business-days.index'));

        $this->assertTrue($response->isForbidden() || $response->isRedirect());
    }

    /**
     * Helper to create advance goods receipt linked to business day.
     */
    private function createGoodsReceipt(PurchaseBusinessDay $day, Product $product, float $receivedQty, float $acceptedQty): GoodsReceived
    {
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $day->warehouse_id,
            'business_day_id' => $day->id,
            'grn_number' => 'GRN-ADV-'.Str::upper(Str::random(6)),
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $day->opened_by,
            'approved_by' => $day->opened_by,
            'received_at' => $day->business_date->copy()->setTime(10, 0, 0),
            'approved_at' => $day->business_date->copy()->setTime(10, 0, 0),
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $product->id,
            'received_qty' => $receivedQty,
            'received_unit' => $product->unit,
            'unit_price' => 0.0,
            'total_amount' => 0.0,
            'billed_qty' => 0.0,
            'variance' => 0.0,
            'is_matched' => false,
        ]);

        return $advGrn;
    }

    /**
     * Helper to create direct purchase bill linked to business day.
     */
    private function createPurchaseBillDirect(PurchaseBusinessDay $day, Product $product, float $qty, float $rate): GoodsReceived
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-BILL-'.Str::upper(Str::random(6)),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $day->warehouse_id,
            'order_date' => $day->business_date,
            'status' => 'approved',
            'created_by' => $day->opened_by,
            'business_day_id' => $day->id,
        ]);

        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'grn_number' => 'GRN-BILL-'.Str::upper(Str::random(6)),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $day->warehouse_id,
            'received_by' => (int) $day->opened_by,
            'approved_by' => (int) $day->opened_by,
            'received_at' => $day->business_date->copy()->setTime(11, 0, 0),
            'approved_at' => $day->business_date->copy()->setTime(11, 0, 0),
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'direct_purchase',
            'bill_number' => 'INV-'.rand(1000, 9999),
            'business_day_id' => $day->id,
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'unit_price' => $rate,
            'total_amount' => $qty * $rate,
            'billed_qty' => $qty,
            'variance' => 0.0,
            'is_matched' => true,
        ]);

        $this->comparisonService->autoMatchForGrn($grn, (int) $day->opened_by);

        return $grn;
    }
}
