<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
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

/**
 * Five core inventory workflow tests:
 *
 *  1. Pending Bill   → approve → inventory +qty
 *  2. Bill + Advance → match   → advance clears, inventory no double-add
 *  3. Partial Match  → adv 4, bill 10 → net +6 new stock
 *  4. Advance No Bill → remains as "Pending Vendor Bill" (unbilled_inventory_count > 0)
 *  5. Loadout No Bill or Advance → product in stock with 0 advance coverage
 */
class InventoryCoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Product $product;

    private Supplier $supplier;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH-TEST',
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Produce', 'code' => 'PRD']);

        $this->product = Product::create([
            'name' => 'Carrot',
            'sku' => 'CAR-001',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'base_price' => 5.00,
            'status' => 'active',
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Core Supplier']);
        $this->shop = Shop::factory()->create(['name' => 'Core Shop', 'code' => 'SH-CORE']);
    }

    // ─────────────────────────────────────────────────────────
    // Flow 1: Pending Bill → Approve → Inventory increases
    // ─────────────────────────────────────────────────────────

    public function test_flow1_pending_bill_approves_and_inventory_increases_by_bill_qty(): void
    {
        $grn = $this->makePendingBillGrn(20.0, '2026-09-09');

        $this->assertEquals(0.0, $this->confirmedStock(), 'Precondition: no stock before approval');

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouse->id,
                'grn_id' => $grn->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.approved', 1);

        $this->assertSame('approved', $grn->fresh()->status);

        $batch = StockBatch::where('goods_received_id', $grn->id)->first();
        $this->assertNotNull($batch, 'StockBatch must be created');
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertNotNull($batch->warehouse_confirmed_at);
        $this->assertEquals(20.0, $batch->total_kg);

        $this->assertEquals(20.0, $this->confirmedStock(), 'Inventory must increase by 20 kg');
    }

    // ─────────────────────────────────────────────────────────
    // Flow 2: Bill + Existing Advance → Full match → no double-add
    // ─────────────────────────────────────────────────────────

    public function test_flow2_bill_with_full_advance_match_clears_advance_and_adds_zero_new_stock(): void
    {
        $advGrn = $this->makeConfirmedAdvance(15.0, '2026-09-05');
        $advItem = $advGrn->items->first();

        $this->assertEquals(15.0, $this->confirmedStock(), 'Precondition: 15 kg advance in stock');

        $billGrn = $this->makePendingBillGrn(15.0, '2026-09-09');
        $billItem = $billGrn->items->first();

        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->product->id,
            'matched_qty' => 15.0,
            'matched_unit' => 'kg',
            'base_qty' => 15.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouse->id,
                'grn_id' => $billGrn->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.approved', 1);

        // Bill batch = 15 - 15 = 0 kg (fully covered by advance)
        $billBatch = StockBatch::where('goods_received_id', $billGrn->id)->first();
        $this->assertNotNull($billBatch);
        $this->assertEquals(0.0, $billBatch->total_kg, 'Bill batch must be 0 kg (fully matched)');
        $this->assertFalse((bool) $billBatch->warehouse_receive_pending);

        // Advance remains approved with bill_pending (not double-shipped)
        $this->assertSame('approved', $advGrn->fresh()->status);

        // Total inventory still 15 kg — no double-add
        $this->assertEquals(15.0, $this->confirmedStock(), 'No double-add: inventory stays 15 kg');
    }

    // ─────────────────────────────────────────────────────────
    // Flow 3: Partial Match — Advance 4, Bill 10 → net +6 new stock
    // ─────────────────────────────────────────────────────────

    public function test_flow3_partial_match_advance4_bill10_clears_4_and_adds_6_new_stock(): void
    {
        $advGrn = $this->makeConfirmedAdvance(4.0, '2026-09-05');
        $advItem = $advGrn->items->first();

        $this->assertEquals(4.0, $this->confirmedStock(), 'Precondition: 4 kg advance in stock');

        $billGrn = $this->makePendingBillGrn(10.0, '2026-09-09');
        $billItem = $billGrn->items->first();

        // Partial match: 4 kg of 10 kg bill covered by the advance
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->product->id,
            'matched_qty' => 4.0,
            'matched_unit' => 'kg',
            'base_qty' => 4.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouse->id,
                'grn_id' => $billGrn->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.approved', 1);

        // Bill batch = 10 - 4 = 6 kg (only unmatched qty enters as new stock)
        $billBatch = StockBatch::where('goods_received_id', $billGrn->id)->first();
        $this->assertNotNull($billBatch);
        $this->assertEquals(6.0, $billBatch->total_kg, 'Bill batch must be 6 kg (10 - 4 matched)');
        $this->assertFalse((bool) $billBatch->warehouse_receive_pending);

        // 4 kg (advance) + 6 kg (bill net) = 10 kg total
        $this->assertEquals(10.0, $this->confirmedStock(), 'Total must be 10 kg (4 advance + 6 new bill)');
    }

    // ─────────────────────────────────────────────────────────
    // Flow 4: Advance With No Bill → Pending Vendor Bill
    // ─────────────────────────────────────────────────────────

    public function test_flow4_unmatched_advance_remains_as_pending_vendor_bill(): void
    {
        $advGrn = $this->makeConfirmedAdvance(8.0, '2026-09-09');

        // No bill, no AdvanceReceiveMatch created
        $this->assertFalse(
            AdvanceReceiveMatch::where('advance_goods_received_id', $advGrn->id)->exists(),
            'No match must exist'
        );

        // Advance GRN remains approved with bill_status = bill_pending
        $fresh = $advGrn->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame('bill_pending', $fresh->bill_status, 'Advance must remain as Pending Vendor Bill');

        // Stock is in warehouse (the advance stock is real)
        $batch = StockBatch::where('goods_received_id', $advGrn->id)->first();
        $this->assertNotNull($batch);
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertEquals(8.0, $batch->total_kg);

        // Controller query for "Stock Without Bill" picks up this GRN
        $openAdvanceCount = GoodsReceived::where('receipt_type', 'warehouse_advance')
            ->where('status', '!=', 'cancelled')
            ->where('bill_status', 'bill_pending')
            ->where('warehouse_id', $this->warehouse->id)
            ->count();

        $this->assertEquals(1, $openAdvanceCount, 'One advance must appear as Pending Vendor Bill');
    }

    // ─────────────────────────────────────────────────────────
    // Flow 5: Loadout With No Bill or Advance → No Bill Coverage
    // ─────────────────────────────────────────────────────────

    public function test_flow5_loadout_with_no_bill_or_advance_shows_zero_advance_coverage(): void
    {
        // A StockBatch with no GRN (simulates stock loaded out without a bill or advance)
        StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => null,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->admin->id,
            'reference' => 'BAT-LOAD-'.uniqid(),
            'received_at' => Carbon::parse('2026-09-09'),
            'total_kg' => 12.0,
            'cost_per_kg' => 5.0,
            'status' => 'pending',
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
            'notes' => 'Loadout stock without bill',
        ]);

        // Physical stock exists
        $this->assertEquals(12.0, $this->confirmedStock(), 'Loadout stock must be 12 kg');

        // No open advance for this product
        $openAdvance = GoodsReceived::where('receipt_type', 'warehouse_advance')
            ->where('status', '!=', 'cancelled')
            ->where('bill_status', 'bill_pending')
            ->whereHas('items', fn ($q) => $q->where('product_id', $this->product->id))
            ->count();
        $this->assertEquals(0, $openAdvance, 'No advance exists for this product');

        // No pending bill for this product
        $pendingBill = GoodsReceived::where('status', 'pending_approval')
            ->whereHas('items', fn ($q) => $q->where('product_id', $this->product->id))
            ->count();
        $this->assertEquals(0, $pendingBill, 'No bill exists for this product');

        // Advance coverage for this product = 0 kg
        // The controller's unbilledByProductId will NOT include this product
        // (only products linked to open advances are tracked)
        $advanceCoverageKg = (float) GoodsReceived::where('receipt_type', 'warehouse_advance')
            ->where('status', '!=', 'cancelled')
            ->where('bill_status', 'bill_pending')
            ->join('goods_received_items as gri', 'goods_received.id', '=', 'gri.goods_received_id')
            ->where('gri.product_id', $this->product->id)
            ->sum('gri.received_qty');

        $this->assertEquals(
            0.0,
            $advanceCoverageKg,
            'Product has 0 kg advance coverage — it is stock with No Bill or Advance (No Bill Created)'
        );
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    private function confirmedStock(): float
    {
        return (float) StockBatch::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
    }

    private function makePendingBillGrn(float $qty, string $date): GoodsReceived
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-WF-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::SentToSupplier,
            'order_date' => Carbon::parse($date),
            'total_amount' => $qty * 5,
            'created_by' => $this->admin->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'unit_price' => 5.0,
            'unit' => $this->product->unit,
            'purchase_unit' => $this->product->unit,
            'total_amount' => $qty * 5,
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-WF-'.uniqid(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => Carbon::parse($date),
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => $qty,
            'received_unit' => $this->product->unit,
            'variance' => 0.0,
            'grade' => 'A',
        ]);

        return $grn->load('items');
    }

    private function makeConfirmedAdvance(float $qty, string $date): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-'.uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'received_at' => Carbon::parse($date),
            'approved_at' => Carbon::parse($date),
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => $qty,
            'received_unit' => $this->product->unit,
            'variance' => 0.0,
            'grade' => 'A',
        ]);

        StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->admin->id,
            'reference' => 'BAT-ADV-'.uniqid(),
            'received_at' => Carbon::parse($date),
            'total_kg' => $qty,
            'cost_per_kg' => 5.0,
            'status' => 'pending',
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);

        return $grn->load('items');
    }
}
