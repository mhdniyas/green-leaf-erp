<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\BillReconciliation;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairStaleReconciliationsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private User $user;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'VEG',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();

        $this->supplier = Supplier::factory()->create();

        $this->product = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'base_price' => 30.00,
            'is_active' => true,
        ]);
    }

    public function test_dry_run_identifies_stale_fully_matched_bills_without_modifying(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'po_number' => 'PO-STALE-001',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-08',
            'created_by' => $this->user->id,
        ]);
        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 100.0,
            'unit' => 'kg',
            'purchase_unit' => 'kg',
            'unit_price' => 30.0,
            'total_price' => 3000.0,
        ]);

        // Advance GRN
        $advGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-ADV-001',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'received_at' => '2026-09-08',
            'approved_at' => '2026-09-08',
            'received_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);
        $advItem = GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        // Bill GRN with 100 kg, bill_pending
        $billGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-BILL-001',
            'receipt_type' => null,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => '2026-09-08',
            'approved_at' => '2026-09-08',
            'received_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);
        $billItem = GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->user->id,
            'reference' => 'BATCH-BILL-001',
            'received_at' => '2026-09-08',
            'total_kg' => 100.0,
            'cost_per_kg' => 30.0,
            'warehouse_receive_pending' => true,
        ]);

        // Matches exist totaling 100 kg (100% matched)
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'matched_qty' => 100.0,
            'matched_unit' => 'kg',
            'base_qty' => 100.0,
            'matched_by' => $this->user->id,
            'matched_at' => now(),
            'confirmed_by' => $this->user->id,
            'confirmed_at' => now(),
        ]);

        // Dry run
        $this->artisan('inventory:repair-stale-reconciliations')
            ->expectsOutputToContain('Stale fully matched bills: 1')
            ->expectsOutputToContain('No changes made. Run with --apply to execute.')
            ->assertExitCode(0);

        // State remains untouched
        $this->assertEquals('bill_pending', $billGrn->fresh()->bill_status);
        $this->assertNull(BillReconciliation::where('goods_received_id', $billGrn->id)->first());
    }

    public function test_apply_mode_repairs_stale_fully_matched_bills(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'po_number' => 'PO-STALE-002',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-08',
            'created_by' => $this->user->id,
        ]);
        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 216.0,
            'unit' => 'kg',
            'purchase_unit' => 'kg',
            'unit_price' => 30.0,
            'total_price' => 6480.0,
        ]);

        $advGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-ADV-002',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'received_at' => '2026-09-08',
            'approved_at' => '2026-09-08',
            'received_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);
        $advItem = GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 216.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $billGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-BILL-002',
            'receipt_type' => null,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => '2026-09-08',
            'approved_at' => '2026-09-08',
            'received_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);
        $billItem = GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 216.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->user->id,
            'reference' => 'BATCH-BILL-002',
            'received_at' => '2026-09-08',
            'total_kg' => 216.0,
            'cost_per_kg' => 30.0,
            'warehouse_receive_pending' => true,
        ]);

        // 2 matches totaling 216 kg: 196 + 20
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'matched_qty' => 196.0,
            'matched_unit' => 'kg',
            'base_qty' => 196.0,
            'matched_by' => $this->user->id,
            'matched_at' => now(),
            'confirmed_by' => $this->user->id,
            'confirmed_at' => now(),
        ]);
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'matched_qty' => 20.0,
            'matched_unit' => 'kg',
            'base_qty' => 20.0,
            'matched_by' => $this->user->id,
            'matched_at' => now(),
            'confirmed_by' => $this->user->id,
            'confirmed_at' => now(),
        ]);

        // Apply mode
        $this->artisan('inventory:repair-stale-reconciliations --apply')
            ->expectsOutputToContain('Stale fully matched bills: 1')
            ->expectsOutputToContain('Successfully repaired 1 stale bill(s).')
            ->assertExitCode(0);

        // Assert database state
        $this->assertEquals('bill_available', $billGrn->fresh()->bill_status);

        $recon = BillReconciliation::where('goods_received_id', $billGrn->id)->firstOrFail();
        $this->assertEquals('confirmed', $recon->status);
        $this->assertEquals(216.0, (float) $recon->total_bill_base_qty);
        $this->assertEquals(216.0, (float) $recon->total_matched_base_qty);
        $this->assertEquals(0.0, (float) $recon->total_new_receive_base_qty);

        // Provisional batch is safely zeroed
        $this->assertEquals(0.0, (float) $batch->fresh()->total_kg);
        $this->assertFalse((bool) $batch->fresh()->warehouse_receive_pending);

        // Subsequent dry-run finds 0 stale bills
        $this->artisan('inventory:repair-stale-reconciliations')
            ->expectsOutputToContain('Stale fully matched bills: 0')
            ->assertExitCode(0);
    }
}
