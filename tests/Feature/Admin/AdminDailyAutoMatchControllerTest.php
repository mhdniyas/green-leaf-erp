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

    public function test_admin_can_view_daily_auto_match_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.auto-match', [
            'warehouse_id' => $this->warehouseA->id,
            'date' => '2026-09-11',
        ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.reports.daily-auto-match');
        $response->assertViewHas('availableWarehouses');
        $response->assertViewHas('plan');
    }

    public function test_preview_json_endpoint(): void
    {
        $date = '2026-09-11';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 50.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 50.0, $date);

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.auto-match.preview', [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
        ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.summary.ready_bills', 1);
        $this->assertEquals(50.0, $response->json('data.summary.matched_base_qty'));
    }

    public function test_execute_json_endpoint(): void
    {
        $date = '2026-09-11';
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 50.0, $date);
        $this->createBillGrn($this->warehouseA, $this->apple, 50.0, $date);

        $planningService = app(DailyAdvanceMatchPlanningService::class);
        $plan = $planningService->buildDailyPlan($this->warehouseA->id, $date, null, 100, $this->adminUser->id);

        $response = $this->actingAs($this->adminUser)->postJson(route('admin.cashbook.auto-match.execute'), [
            'warehouse_id' => $this->warehouseA->id,
            'date' => $date,
            'plan_hash' => $plan['plan_hash'],
            'client_submission_id' => (string) Str::uuid(),
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.summary.processed', 1);
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
            'warehouse_id' => $warehouse->id,
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
            'unit' => $product->unit,
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
