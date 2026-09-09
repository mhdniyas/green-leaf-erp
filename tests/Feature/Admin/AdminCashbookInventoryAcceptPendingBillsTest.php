<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Purchasing\POStatus;
use App\Models\Category;
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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminCashbookInventoryAcceptPendingBillsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $unauthorizedUser;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Category $category;

    private Product $tomato;

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
            'name' => 'Vegetable Warehouse',
            'code' => 'WH-VEG',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Fruit Warehouse',
            'code' => 'WH-FRUIT',
            'is_active' => true,
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Main Retail Shop',
            'code' => 'SH-MAIN',
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'code' => 'VEG',
        ]);

        $this->tomato = Product::create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
            'base_price' => 10.00,
            'status' => 'active',
        ]);

        $this->apple = Product::create([
            'name' => 'Apple',
            'sku' => 'APP-001',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseB->id,
            'unit' => 'kg',
            'base_price' => 20.00,
            'status' => 'active',
        ]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Test Supplier',
        ]);
    }

    public function test_pending_bills_days_summary_returns_grouped_counts_for_selected_warehouse(): void
    {
        $this->createPendingBillGrn($this->warehouseA, $this->tomato, 100.0, '2026-09-08');
        $this->createPendingBillGrn($this->warehouseA, $this->tomato, 50.0, '2026-09-08');
        $this->createPendingBillGrn($this->warehouseA, $this->tomato, 200.0, '2026-09-09');

        // Warehouse B bill - should not appear in Warehouse A query
        $this->createPendingBillGrn($this->warehouseB, $this->apple, 300.0, '2026-09-09');

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('admin.cashbook.inventory.pending-bills-days', [
                'warehouse_id' => $this->warehouseA->id,
            ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.warehouse_id', $this->warehouseA->id);
        $response->assertJsonPath('data.total_bills', 3);
        $this->assertEquals(350.0, $response->json('data.total_qty'));

        $days = $response->json('data.days');
        $this->assertCount(2, $days);

        $day09 = collect($days)->firstWhere('date', '2026-09-09');
        $this->assertNotNull($day09);
        $this->assertSame(1, $day09['bill_count']);
        $this->assertEquals(200.0, $day09['total_qty']);

        $day08 = collect($days)->firstWhere('date', '2026-09-08');
        $this->assertNotNull($day08);
        $this->assertSame(2, $day08['bill_count']);
        $this->assertEquals(150.0, $day08['total_qty']);
    }

    public function test_pending_bills_day_details_returns_line_items_for_expanded_date(): void
    {
        $grn = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 120.0, '2026-09-09');

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('admin.cashbook.inventory.pending-bills-day-details', [
                'warehouse_id' => $this->warehouseA->id,
                'date' => '2026-09-09',
            ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.date', '2026-09-09');

        $bills = $response->json('data.bills');
        $this->assertCount(1, $bills);
        $this->assertSame($grn->grn_number, $bills[0]['grn_number']);
        $this->assertSame('Test Supplier', $bills[0]['supplier_name']);
        $this->assertEquals(120.0, $bills[0]['qty']);
        $this->assertSame('Pending Approval', $bills[0]['status']);
    }

    public function test_accept_pending_bills_approves_selected_dates_and_returns_auto_match_preview(): void
    {
        // 1. Create open Advance in Warehouse A for Tomato (100 kg)
        $this->createConfirmedAdvanceGrn($this->warehouseA, $this->tomato, 100.0, '2026-09-05');

        // 2. Create pending bills: 2 on 2026-09-08 (50 kg each), 1 on 2026-09-09 (80 kg)
        $grn1 = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 50.0, '2026-09-08');
        $grn2 = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 50.0, '2026-09-08');
        $grn3 = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 80.0, '2026-09-09');

        // Accept only 2026-09-08 bills
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'dates' => ['2026-09-08'],
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.approved', 2);
        $response->assertJsonPath('data.skipped', 0);
        $response->assertJsonPath('data.failed', 0);

        // Verify GRN 1 and GRN 2 were approved canonically
        $this->assertSame('approved', $grn1->fresh()->status);
        $this->assertSame('bill_pending', $grn1->fresh()->bill_status);
        $this->assertSame('approved', $grn2->fresh()->status);
        $this->assertSame('bill_pending', $grn2->fresh()->bill_status);

        // Verify provisional StockBatches created with warehouse_receive_pending = true
        $batches1 = StockBatch::where('goods_received_id', $grn1->id)->get();
        $this->assertTrue($batches1->isNotEmpty());
        $this->assertTrue((bool) $batches1->first()->warehouse_receive_pending);

        // Verify GRN 3 on 2026-09-09 remained pending
        $this->assertSame('pending_approval', $grn3->fresh()->status);

        // Verify Auto Match Preview was computed and returned
        $preview = $response->json('data.auto_match_preview');
        $this->assertNotNull($preview);
        // The two 50kg bills on 2026-09-08 match the 100kg open advance exactly
        $this->assertSame(2, $preview['matchable_with_advances']);
        $this->assertSame(1, $preview['advances_that_can_fully_clear']);

        // Verify Auto Match did NOT execute automatically (Advance remains open)
        $this->assertSame('bill_pending', GoodsReceived::where('receipt_type', 'warehouse_advance')->first()->bill_status);
    }

    public function test_unauthorized_user_cannot_access_or_accept_pending_bills(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->getJson(route('admin.cashbook.inventory.pending-bills-days', [
                'warehouse_id' => $this->warehouseA->id,
            ]))
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'dates' => ['2026-09-08'],
            ])
            ->assertForbidden();
    }

    private function createPendingBillGrn(Warehouse $warehouse, Product $product, float $qty, string $date): GoodsReceived
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $warehouse->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::SentToSupplier,
            'order_date' => Carbon::parse($date),
            'total_amount' => $qty * 10,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => 10.0,
            'unit' => $product->unit,
            'purchase_unit' => $product->unit,
            'total_amount' => $qty * 10,
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-'.uniqid(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_by' => $this->adminUser->id,
            'received_at' => Carbon::parse($date),
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'variance' => 0.0,
            'grade' => 'A',
        ]);

        return $grn;
    }

    private function createConfirmedAdvanceGrn(Warehouse $warehouse, Product $product, float $qty, string $date): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->adminUser->id,
            'approved_by' => $this->adminUser->id,
            'received_at' => Carbon::parse($date),
            'approved_at' => Carbon::parse($date),
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'variance' => 0.0,
            'grade' => 'A',
        ]);

        StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->adminUser->id,
            'reference' => 'BAT-ADV-'.uniqid(),
            'received_at' => Carbon::parse($date),
            'total_kg' => $qty,
            'cost_per_kg' => 10.0,
            'status' => 'pending',
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->adminUser->id,
        ]);

        return $grn;
    }
}
