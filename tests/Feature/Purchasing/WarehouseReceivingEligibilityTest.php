<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Web\Admin\AdminCashbookReportsController;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaserCart;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\WarehouseReceiptStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseReceivingEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $admin;

    private Warehouse $warehouse;

    private Shop $shop;

    private Supplier $supplier;

    private Product $papaya;

    private Product $mixedProduct;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Role::firstOrCreate(['name' => 'admin']);
        Permission::firstOrCreate(['name' => 'purchasing.grn.create']);
        Permission::firstOrCreate(['name' => 'cashbook.access']);
        Permission::firstOrCreate(['name' => 'cashbook.inventory.manage']);
        Permission::firstOrCreate(['name' => 'purchasing.grn.approve']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.view']);

        $this->receiver = User::factory()->create(['name' => 'Warehouse Receiver']);
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->givePermissionTo(['purchasing.grn.create', 'warehouse.receive.view']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo(['purchasing.grn.approve', 'warehouse.receive.view', 'cashbook.access', 'cashbook.inventory.manage']);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Central Depot', 'code' => 'DEP1', 'is_active' => true]);
        $this->receiver->warehouses()->attach($this->warehouse->id, ['is_default' => true]);
        $this->admin->warehouses()->attach($this->warehouse->id, ['is_default' => true]);

        $this->shop = Shop::factory()->create(['name' => 'Families Jindal City']);
        $this->supplier = Supplier::factory()->create(['name' => 'Local Fruit Vendor']);

        $this->papaya = Product::factory()->create([
            'name' => 'Papaya',
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->mixedProduct = Product::factory()->create([
            'name' => 'Tomato Mixed Grade',
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
    }

    /**
     * Scenario 1: Synthetic test of October 4 Papaya record.
     * Direct shop purchase (fulfillment_type = shop) must NEVER appear in warehouse pending receiving queue.
     */
    public function test_direct_shop_purchase_is_excluded_from_warehouse_pending_receiving(): void
    {
        Sanctum::actingAs($this->receiver);

        $cart = PurchaserCart::create([
            'business_day_id' => null,
            'user_id' => $this->receiver->id,
            'supplier_id' => $this->supplier->id,
            'cart_number' => 'CART-20261004-TEST',
            'purchase_source' => 'shop',
            'business_date' => '2026-10-04',
            'status' => 'confirmed',
        ]);

        $shopPo = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'purchaser_cart_id' => $cart->id,
            'po_number' => 'SPO-20261004-D8JBW3',
            'status' => POStatus::Received,
            'fulfillment_type' => 'shop',
            'order_date' => '2026-10-04',
            'created_by' => $this->receiver->id,
            'purchase_grade' => 'A',
            'notes' => 'Shop Purchase Order',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $shopPo->id,
            'product_id' => $this->papaya->id,
            'quantity' => 30.00,
            'unit_price' => 51.67,
            'price_basis' => 'weight',
            'subtotal' => 1550.00,
            'grade' => 'A',
            'purchase_unit' => 'KG',
        ]);

        $shopGrn = GoodsReceived::create([
            'purchase_order_id' => $shopPo->id,
            'purchaser_cart_id' => $cart->id,
            'grn_number' => 'SGRN-20261004-UCS50L',
            'status' => 'approved',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->receiver->id,
            'received_at' => '2026-10-04',
            'notes' => 'Shop Goods Received',
            'purchase_grade' => 'A',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $shopGrn->id,
            'product_id' => $this->papaya->id,
            'purchase_order_item_id' => $poItem->id,
            'received_qty' => 30.00,
            'received_unit' => 'KG',
            'variance' => 0.00,
            'grade' => 'A',
        ]);

        // Model helper assertions
        $this->assertTrue($shopPo->isDirectShopPurchase());
        $this->assertFalse($shopPo->isWarehousePurchase());
        $this->assertTrue($shopGrn->isDirectShopPurchase());
        $this->assertFalse($shopGrn->isWarehousePurchase());

        // State resolver assertions
        $state = app(WarehouseReceiptStateResolver::class)->forOrder($shopPo);
        $this->assertEquals('not_applicable', $state['receipt_status']);
        $this->assertFalse($state['is_warehouse_receivable']);
        $this->assertFalse($state['can_create_receipt']);
        $this->assertFalse($state['can_confirm_receipt']);

        // Warehouse Pending API call must NOT include this PO
        $pendingResp = $this->getJson('/api/v1/purchasing/orders?status=pending&date=2026-10-04');
        $pendingResp->assertOk();
        $pendingIds = collect($pendingResp->json('data'))->pluck('id')->all();
        $this->assertNotContains($shopPo->id, $pendingIds);

        // General history query with explicit fulfillment_type=all DOES preserve it for reporting
        Sanctum::actingAs($this->admin);
        $allResp = $this->getJson('/api/v1/purchasing/orders?fulfillment_type=all&date=2026-10-04');
        $allResp->assertOk();
        $allIds = collect($allResp->json('data'))->pluck('id')->all();
        $this->assertContains($shopPo->id, $allIds);
    }

    /**
     * Scenario 2: Security guard test.
     * Attempting to call POST /api/v1/purchasing/grns with a direct shop PO ID must be rejected with 422.
     */
    public function test_direct_shop_po_cannot_be_received_via_api(): void
    {
        Sanctum::actingAs($this->receiver);

        $shopPo = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'SPO-20261004-SECURITY',
            'status' => POStatus::Approved,
            'fulfillment_type' => 'shop',
            'order_date' => now()->toDateString(),
            'created_by' => $this->receiver->id,
            'purchase_grade' => 'A',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $shopPo->id,
            'product_id' => $this->papaya->id,
            'quantity' => 20.00,
            'unit_price' => 50.00,
            'price_basis' => 'weight',
            'subtotal' => 1000.00,
            'grade' => 'A',
            'purchase_unit' => 'KG',
        ]);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'client_submission_id' => 'sec-attempt-1',
            'purchase_order_id' => $shopPo->id,
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->papaya->id,
                    'received_qty' => 20.00,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Direct shop purchase', $response->json('message'));
        $this->assertDatabaseMissing('stock_batches', ['product_id' => $this->papaya->id]);
    }

    /**
     * Scenario 3: Admin Cashbook Receive All protection.
     * Admin bulk receiving must exclude direct-shop purchases and never create stock batches for them.
     */
    public function test_admin_receive_all_excludes_direct_shop_purchases(): void
    {
        Sanctum::actingAs($this->admin);

        // Create direct shop PO
        $shopPo = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'SPO-20261004-ADMIN1',
            'status' => POStatus::Received,
            'fulfillment_type' => 'shop',
            'order_date' => '2026-10-04',
            'created_by' => $this->admin->id,
        ]);

        $shopGrn = GoodsReceived::create([
            'purchase_order_id' => $shopPo->id,
            'grn_number' => 'SGRN-20261004-ADMIN1',
            'status' => 'approved',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->admin->id,
            'received_at' => '2026-10-04',
        ]);

        // Create genuine warehouse PO
        $whPo = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20261004-WH1',
            'status' => POStatus::Approved,
            'fulfillment_type' => 'warehouse',
            'order_date' => '2026-10-04',
            'created_by' => $this->admin->id,
        ]);

        $whPoItem = PurchaseOrderItem::create([
            'purchase_order_id' => $whPo->id,
            'product_id' => $this->papaya->id,
            'quantity' => 100.00,
            'unit_price' => 45.00,
            'price_basis' => 'weight',
            'subtotal' => 4500.00,
            'grade' => 'A',
            'purchase_unit' => 'KG',
        ]);

        // Test reflection on getPendingBillsForDate
        $controller = app(AdminCashbookReportsController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getPendingBillsForDate');
        $method->setAccessible(true);
        $candidates = $method->invoke($controller, '2026-10-04', $this->warehouse->id, [$this->warehouse->id]);

        $containsShopGrn = $candidates->contains(fn ($c) => $c['type'] === 'grn' && $c['record']->id === $shopGrn->id);
        $containsShopPo = $candidates->contains(fn ($c) => $c['type'] === 'po' && $c['record']->id === $shopPo->id);
        $containsWhPo = $candidates->contains(fn ($c) => $c['type'] === 'po' && $c['record']->id === $whPo->id);

        $this->assertFalse($containsShopGrn, 'Shop GRN must not be in pending bills');
        $this->assertFalse($containsShopPo, 'Shop PO must not be in pending bills');
        $this->assertTrue($containsWhPo, 'Genuine warehouse PO must be in pending bills');

        // Execute bulk receive
        $receiveAllResp = $this->postJson('/admin/cashbook/inventory/receive-all-pending-bills', [
            'date' => '2026-10-04',
            'warehouse_id' => $this->warehouse->id,
        ]);
        $receiveAllResp->assertOk();

        // Warehouse PO has batch created
        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $this->papaya->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Shop GRN has NO batches created
        $this->assertDatabaseMissing('stock_batches', [
            'goods_received_id' => $shopGrn->id,
        ]);
    }

    /**
     * Scenario 4: Genuine warehouse purchase lifecycle & Mixed grade sorting status.
     * - Genuine warehouse bill appears in Pending
     * - Can be confirmed via API
     * - Becomes Received
     * - Duplicate confirmation does not recreate stock
     * - Mixed purchase becomes Received in warehouse while remaining sort_required in inventory.
     */
    public function test_genuine_warehouse_mixed_purchase_lifecycle(): void
    {
        Sanctum::actingAs($this->receiver);

        $whPo = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-WH-MIXED-101',
            'status' => POStatus::Approved,
            'fulfillment_type' => 'warehouse',
            'order_date' => now()->toDateString(),
            'created_by' => $this->receiver->id,
            'purchase_grade' => 'MIXED',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $whPo->id,
            'product_id' => $this->mixedProduct->id,
            'quantity' => 10.00,
            'packet_qty' => 10,
            'unit_price' => 200.00,
            'price_basis' => 'per_unit',
            'subtotal' => 2000.00,
            'grade' => 'MIXED',
            'purchase_unit' => 'BAG',
        ]);

        // 1. Appears in Pending
        $pendingResp = $this->getJson('/api/v1/purchasing/orders?status=pending');
        $pendingResp->assertOk();
        $pendingIds = collect($pendingResp->json('data'))->pluck('id')->all();
        $this->assertContains($whPo->id, $pendingIds);

        // 2. Receive the bill with physical KG
        $receiveResp = $this->postJson('/api/v1/purchasing/grns', [
            'client_submission_id' => 'wh-mixed-sub-1',
            'purchase_order_id' => $whPo->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->mixedProduct->id,
                    'received_qty' => 220.00,
                    'received_unit' => 'KG',
                    'grade' => 'MIXED',
                ],
            ],
        ]);
        $receiveResp->assertCreated();
        $grnId = $receiveResp->json('data.id');

        // 3. Stock batch created with sorting required
        $batch = StockBatch::where('goods_received_id', $grnId)->first();
        $this->assertNotNull($batch);
        $this->assertEquals(220.00, (float) $batch->total_kg);
        $this->assertEquals('MIXED', $batch->purchase_grade);
        $this->assertEquals('sort_required', $batch->grading_mode);
        $this->assertEquals(BatchStatus::Pending, $batch->status); // Needs sorting

        // 4. Shows as Received in warehouse orders
        $orderDetail = $this->getJson("/api/v1/purchasing/orders/{$whPo->id}");
        $orderDetail->assertOk()
            ->assertJsonPath('data.warehouse_receipt_status', 'received')
            ->assertJsonPath('data.can_create_receipt', false);

        // 5. Duplicate confirmation does not recreate stock
        $grn = GoodsReceived::find($grnId);
        $action = app(ApproveGoodsReceiptAction::class);
        $action->executeAndConfirmReceive($grn, $this->receiver->id, $this->warehouse->id);
        $this->assertEquals(1, StockBatch::where('goods_received_id', $grnId)->count());
    }
}
