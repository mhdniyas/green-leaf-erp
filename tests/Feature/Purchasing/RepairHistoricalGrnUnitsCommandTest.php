<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RepairHistoricalGrnUnitsCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Category $category;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->supplier = Supplier::factory()->create(['credit_approved' => true]);
        $this->category = Category::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
    }

    public function test_dry_run_mode_does_not_modify_database(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Anar / Pomegranate',
            'unit' => 'box',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $po = PurchaseOrder::query()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-DRY-01',
            'status' => POStatus::Received,
            'fulfillment_type' => 'warehouse',
            'order_date' => now(),
            'created_by' => $this->admin->id,
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'purchase_unit' => 'box',
            'quantity' => 4.000,
            'unit_price' => 1450.00,
            'price_basis' => 'per_unit',
        ]);

        $grn = GoodsReceived::query()->create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-DRY-01',
            'status' => 'approved',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->admin->id,
            'received_at' => now(),
        ]);

        // Mismatched GRN item (kg instead of box)
        $grnItem = GoodsReceivedItem::query()->create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => 4.000,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $this->artisan('purchasing:repair-historical-grn-units', [
            '--dry-run' => true,
            '--grn' => $grn->id,
        ])
            ->expectsOutputToContain('SAFE')
            ->assertExitCode(0);

        $grnItem->refresh();
        $this->assertEquals('kg', $grnItem->received_unit, 'Dry run must not change received_unit');
    }

    public function test_apply_mode_repairs_safe_grn_unit_without_changing_quantities(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Cauliflower',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $po = PurchaseOrder::query()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-APPLY-01',
            'status' => POStatus::Received,
            'fulfillment_type' => 'warehouse',
            'order_date' => now(),
            'created_by' => $this->admin->id,
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'purchase_unit' => 'piece',
            'quantity' => 68.000,
            'unit_price' => 30.00,
            'price_basis' => 'per_unit',
        ]);

        $grn = GoodsReceived::query()->create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-APPLY-01',
            'status' => 'approved',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->admin->id,
            'received_at' => now(),
        ]);

        $grnItem = GoodsReceivedItem::query()->create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => 68.000,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $batch = StockBatch::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $grnItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->admin->id,
            'reference' => 'REF-001',
            'received_at' => now(),
            'total_kg' => 68.000,
            'cost_per_kg' => 30.00,
            'status' => BatchStatus::Pending,
        ]);

        $initialBatchCount = StockBatch::count();
        $initialBatchWeight = (float) StockBatch::sum('total_kg');
        $initialMovementCount = StockMovement::count();
        $initialGrnCount = GoodsReceived::count();

        $this->artisan('purchasing:repair-historical-grn-units', [
            '--apply' => true,
            '--grn' => $grn->id,
        ])
            ->expectsOutputToContain('REPAIR EXECUTION COMPLETED')
            ->assertExitCode(0);

        $grnItem->refresh();
        $this->assertEquals('piece', $grnItem->received_unit, 'GRN received_unit must be repaired to piece');
        $this->assertEquals(68.000, (float) $grnItem->received_qty, 'received_qty must remain untouched');

        $this->assertEquals($initialBatchCount, StockBatch::count());
        $this->assertEquals($initialBatchWeight, (float) StockBatch::sum('total_kg'));
        $this->assertEquals($initialMovementCount, StockMovement::count());
        $this->assertEquals($initialGrnCount, GoodsReceived::count());
    }
}
