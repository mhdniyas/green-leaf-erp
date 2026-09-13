<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyAdvanceMatchPlanningService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDailyAutoMatchControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $unauthorizedUser;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Product $apple;

    private Product $banana;

    private Supplier $supplier;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->unauthorizedUser = User::factory()->create();

        $this->warehouseA = Warehouse::create([
            'name' => 'Alpha Main WH',
            'code' => 'WH-ALPHA',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Beta Secondary WH',
            'code' => 'WH-BETA',
            'is_active' => true,
        ]);

        $this->apple = Product::factory()->create([
            'name' => 'Fresh Apple',
            'sku' => 'APP-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
        ]);

        $this->banana = Product::factory()->create([
            'name' => 'Fresh Banana',
            'sku' => 'BAN-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Global Farms Ltd']);
        $this->shop = Shop::factory()->create([
            'name' => 'Downtown Retail Shop',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.cashbook.auto-match'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->getJson(route('admin.cashbook.auto-match'));
        $response->assertForbidden();
    }

    public function test_1_default_view_is_one_day(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.auto-match', [
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.reports.daily-auto-match');
        $response->assertViewHas('selectedFromDate', today()->toDateString());
        $response->assertViewHas('selectedToDate', today()->toDateString());
        $response->assertViewHas('isDateRange', false);
    }

    public function test_2_old_date_url_still_works(): void
    {
        $date = '2026-09-12';
        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.auto-match', [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $response->assertOk();
        $response->assertViewHas('selectedFromDate', $date);
        $response->assertViewHas('selectedToDate', $date);
        $response->assertViewHas('isDateRange', false);
    }

    public function test_3_from_to_range_works(): void
    {
        $fromDate = '2026-09-10';
        $toDate = '2026-09-12';

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.auto-match', [
            'warehouse_id' => $this->warehouseA->id,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]));

        $response->assertOk();
        $response->assertViewHas('selectedFromDate', $fromDate);
        $response->assertViewHas('selectedToDate', $toDate);
        $response->assertViewHas('isDateRange', true);
    }

    public function test_4_warehouse_plus_range_works(): void
    {
        $fromDate = '2026-09-10';
        $toDate = '2026-09-12';

        $this->createAdvanceGrn($this->warehouseA, $this->apple, 20.0, '2026-09-10');
        $this->createBillGrn($this->warehouseA, $this->apple, 20.0, '2026-09-10');

        $this->createAdvanceGrn($this->warehouseB, $this->apple, 50.0, '2026-09-10');
        $this->createBillGrn($this->warehouseB, $this->apple, 50.0, '2026-09-10');

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.auto-match', [
            'warehouse_id' => $this->warehouseA->id,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]));

        $response->assertOk();
        $plan = $response->viewData('plan');
        $this->assertSame($this->warehouseA->id, $plan['warehouse_id']);
        $this->assertSame(20.0, (float) $plan['summary']['matched_base_qty']);
    }

    public function test_5_range_never_creates_cross_date_matches(): void
    {
        // Sep 10: Advance only (50kg)
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 50.0, '2026-09-10');

        // Sep 12: Bill only (50kg)
        $this->createBillGrn($this->warehouseA, $this->apple, 50.0, '2026-09-12');

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.auto-match.preview', [
            'warehouse_id' => $this->warehouseA->id,
            'from_date' => '2026-09-10',
            'to_date' => '2026-09-12',
        ]));

        $response->assertOk();
        $data = $response->json('data');

        // Total matched must be 0 because advances on Sep 10 CANNOT match bills on Sep 12!
        $this->assertSame(0, $data['summary']['ready_bills']);
        $this->assertSame(0, $data['summary']['partial_bills']);
        $this->assertSame(1, $data['summary']['blocked_bills']);
        $this->assertSame(0.0, (float) $data['summary']['matched_base_qty']);
        $this->assertSame('NO_ADVANCE', $data['blocked_bills'][0]['blocked_reason']);
    }

    public function test_6_business_date_sorting_asc_desc(): void
    {
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 10.0, '2026-09-10');
        $this->createBillGrn($this->warehouseA, $this->apple, 10.0, '2026-09-10');

        $this->createAdvanceGrn($this->warehouseA, $this->apple, 20.0, '2026-09-12');
        $this->createBillGrn($this->warehouseA, $this->apple, 20.0, '2026-09-12');

        // Sort DESC (newest first: Sep 12, Sep 10)
        $respDesc = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.auto-match.preview', [
            'warehouse_id' => $this->warehouseA->id,
            'from_date' => '2026-09-10',
            'to_date' => '2026-09-12',
            'sort' => 'business_date',
            'direction' => 'desc',
        ]));
        $respDesc->assertOk();
        $this->assertSame('2026-09-12', $respDesc->json('data.ready_bills.0.business_date'));
        $this->assertSame('2026-09-10', $respDesc->json('data.ready_bills.1.business_date'));

        // Sort ASC (oldest first: Sep 10, Sep 12)
        $respAsc = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.auto-match.preview', [
            'warehouse_id' => $this->warehouseA->id,
            'from_date' => '2026-09-10',
            'to_date' => '2026-09-12',
            'sort' => 'business_date',
            'direction' => 'asc',
        ]));
        $respAsc->assertOk();
        $this->assertSame('2026-09-10', $respAsc->json('data.ready_bills.0.business_date'));
        $this->assertSame('2026-09-12', $respAsc->json('data.ready_bills.1.business_date'));
    }

    public function test_7_product_sorting_asc_desc(): void
    {
        $date = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->banana, 10.0, $date);
        $this->createBillGrn($this->warehouseA, $this->banana, 10.0, $date);

        $this->createAdvanceGrn($this->warehouseA, $this->apple, 10.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 10.0, $date);

        // Sort product ASC (Apple before Banana)
        $respAsc = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.auto-match.preview', [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'product',
            'direction' => 'asc',
        ]));
        $respAsc->assertOk();
        $this->assertSame('Fresh Apple', $respAsc->json('data.ready_bills.0.product_name'));
        $this->assertSame('Fresh Banana', $respAsc->json('data.ready_bills.1.product_name'));

        // Sort product DESC (Banana before Apple)
        $respDesc = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.auto-match.preview', [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'product',
            'direction' => 'desc',
        ]));
        $respDesc->assertOk();
        $this->assertSame('Fresh Banana', $respDesc->json('data.ready_bills.0.product_name'));
        $this->assertSame('Fresh Apple', $respDesc->json('data.ready_bills.1.product_name'));
    }

    public function test_8_qty_sorting_works_numerically(): void
    {
        $date = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 200.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 4.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 20.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 100.0, $date);

        // Numerically ASC: 4.0, 20.0, 100.0 (string sorting would incorrectly put 100 before 20)
        $respAsc = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.auto-match.preview', [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'bill_qty',
            'direction' => 'asc',
        ]));
        $respAsc->assertOk();
        $this->assertEquals(4.0, $respAsc->json('data.ready_bills.0.bill_qty'));
        $this->assertEquals(20.0, $respAsc->json('data.ready_bills.1.bill_qty'));
        $this->assertEquals(100.0, $respAsc->json('data.ready_bills.2.bill_qty'));
    }

    public function test_9_invalid_sort_falls_back_safely(): void
    {
        $date = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 10.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 10.0, $date);

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.auto-match', [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'sort' => 'UNION SELECT password FROM users',
            'direction' => 'SLEEP(5)',
        ]));

        $response->assertOk();
        $response->assertViewHas('plan');
    }

    public function test_10_pagination_preserves_filters_and_sort(): void
    {
        $date = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 500.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 10.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 20.0, $date);

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.auto-match', [
            'warehouse_id' => $this->warehouseA->id,
            'from_date' => $date,
            'to_date' => $date,
            'sort' => 'product',
            'direction' => 'asc',
            'cursor' => 1,
        ]));

        $response->assertOk();
        $response->assertViewHas('cursor', 1);
        $response->assertViewHas('sort', 'product');
        $response->assertViewHas('direction', 'asc');
    }

    public function test_11_same_day_full_match_unchanged(): void
    {
        $date = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 50.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 50.0, $date);

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.auto-match.preview', [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $response->assertOk();
        $this->assertSame(1, $response->json('data.summary.ready_bills'));
        $this->assertSame(0, $response->json('data.summary.partial_bills'));
        $this->assertEquals(50.0, $response->json('data.summary.matched_base_qty'));
    }

    public function test_12_same_day_partial_match_unchanged(): void
    {
        $date = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 20.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 50.0, $date);

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.auto-match.preview', [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $response->assertOk();
        $this->assertSame(0, $response->json('data.summary.ready_bills'));
        $this->assertSame(1, $response->json('data.summary.partial_bills'));
        $this->assertEquals(20.0, $response->json('data.summary.matched_base_qty'));
        $this->assertEquals(30.0, $response->json('data.partial_bills.0.remaining_base_qty'));
    }

    public function test_execute_multi_date_range_endpoint(): void
    {
        $day1 = '2026-09-10';
        $day2 = '2026-09-11';

        $this->createAdvanceGrn($this->warehouseA, $this->apple, 30.0, $day1);
        $this->createBillGrn($this->warehouseA, $this->apple, 30.0, $day1);

        $this->createAdvanceGrn($this->warehouseA, $this->apple, 40.0, $day2);
        $this->createBillGrn($this->warehouseA, $this->apple, 40.0, $day2);

        $planningService = app(DailyAdvanceMatchPlanningService::class);
        $rangePlan = $planningService->buildRangePlan($this->warehouseA->id, $day1, $day2, null, 100, $this->adminUser->id);

        $response = $this->actingAs($this->adminUser)->postJson(route('admin.cashbook.auto-match.execute'), [
            'warehouse_id' => $this->warehouseA->id,
            'from_date' => $day1,
            'to_date' => $day2,
            'plan_hash' => $rangePlan['plan_hash'],
            'client_submission_id' => (string) Str::uuid(),
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.summary.processed', 2);
        $this->assertEquals(70.0, $response->json('data.summary.matched_base_qty'));
    }

    public function test_single_day_range_plan_hash_equals_daily_plan_hash(): void
    {
        $date = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 25.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 25.0, $date);

        $planningService = app(DailyAdvanceMatchPlanningService::class);
        $dailyPlan = $planningService->buildDailyPlan($this->warehouseA->id, $date, null, 100, $this->adminUser->id);
        $rangePlan = $planningService->buildRangePlan($this->warehouseA->id, $date, $date, null, 100, $this->adminUser->id);

        $this->assertSame($dailyPlan['plan_hash'], $rangePlan['plan_hash']);
    }

    public function test_single_day_preview_then_execute_succeeds_without_concurrency_error(): void
    {
        $date = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 25.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 25.0, $date);

        $previewResponse = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.auto-match.preview', [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $previewResponse->assertOk();
        $planHash = $previewResponse->json('data.plan_hash');
        $this->assertNotEmpty($planHash);

        $executeResponse = $this->actingAs($this->adminUser)->postJson(route('admin.cashbook.auto-match.execute'), [
            'warehouse_id' => $this->warehouseA->id,
            'from_date' => $date,
            'to_date' => $date,
            'plan_hash' => $planHash,
            'client_submission_id' => (string) Str::uuid(),
        ]);

        $executeResponse->assertOk();
        $executeResponse->assertJsonPath('status', 'success');
        $executeResponse->assertJsonPath('data.summary.processed', 1);
        $this->assertEquals(25.0, $executeResponse->json('data.summary.matched_base_qty'));
    }

    public function test_single_day_preview_then_real_data_change_returns_409(): void
    {
        $date = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 25.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 25.0, $date);

        $previewResponse = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.auto-match.preview', [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));
        $planHash = $previewResponse->json('data.plan_hash');

        // Real data modification: Add another advance so match allocations change
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 10.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 10.0, $date);

        $executeResponse = $this->actingAs($this->adminUser)->postJson(route('admin.cashbook.auto-match.execute'), [
            'warehouse_id' => $this->warehouseA->id,
            'from_date' => $date,
            'to_date' => $date,
            'plan_hash' => $planHash,
            'client_submission_id' => (string) Str::uuid(),
        ]);

        $executeResponse->assertStatus(409);
        $executeResponse->assertJsonPath('status', 'conflict');
    }

    public function test_ui_sorting_does_not_change_plan_hash(): void
    {
        $date = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 25.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 25.0, $date);

        $planningService = app(DailyAdvanceMatchPlanningService::class);
        $planAsc = $planningService->buildRangePlan($this->warehouseA->id, $date, $date, null, 100, $this->adminUser->id, 'product', 'asc');
        $planDesc = $planningService->buildRangePlan($this->warehouseA->id, $date, $date, null, 100, $this->adminUser->id, 'product', 'desc');

        $this->assertSame($planAsc['plan_hash'], $planDesc['plan_hash']);
    }

    public function test_legacy_single_day_range_wrapper_hash_backward_compatibility(): void
    {
        $date = '2026-09-12';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 25.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 25.0, $date);

        $planningService = app(DailyAdvanceMatchPlanningService::class);
        $dailyPlan = $planningService->buildDailyPlan($this->warehouseA->id, $date, null, 100, $this->adminUser->id);
        $legacyRangeWrapperHash = hash('sha256', (string) json_encode([$date => $dailyPlan['plan_hash']]));

        $executeResponse = $this->actingAs($this->adminUser)->postJson(route('admin.cashbook.auto-match.execute'), [
            'warehouse_id' => $this->warehouseA->id,
            'from_date' => $date,
            'to_date' => $date,
            'plan_hash' => $legacyRangeWrapperHash,
            'client_submission_id' => (string) Str::uuid(),
        ]);

        $executeResponse->assertOk();
        $executeResponse->assertJsonPath('status', 'success');
        $executeResponse->assertJsonPath('data.summary.processed', 1);
    }

    private function createAdvanceGrn(Warehouse $warehouse, Product $product, float $qty, string $date): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'ADV-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $this->supplier->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => $date,
            'received_by' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'reference' => 'BATCH-'.uniqid(),
            'total_kg' => $qty,
            'cost_per_kg' => 50.0,
            'quantity' => $qty,
            'current_quantity' => $qty,
            'received_at' => $date,
            'warehouse_receive_pending' => false,
            'status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        return $grn;
    }

    private function createBillGrn(Warehouse $warehouse, Product $product, float $qty, string $date): GoodsReceived
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-'.uniqid(),
            'destination_shop_id' => $this->shop->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'order_date' => $date,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'purchase_unit' => $product->unit,
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-'.uniqid(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $this->supplier->id,
            'receipt_type' => 'normal',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => $date,
            'received_by' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'reference' => 'BATCH-'.uniqid(),
            'total_kg' => $qty,
            'cost_per_kg' => 50.0,
            'quantity' => $qty,
            'current_quantity' => $qty,
            'received_at' => $date,
            'warehouse_receive_pending' => false,
            'status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        return $grn;
    }
}
