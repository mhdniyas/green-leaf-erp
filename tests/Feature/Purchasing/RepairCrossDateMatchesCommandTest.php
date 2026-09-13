<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\AdvanceReceiveMatch;
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
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RepairCrossDateMatchesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Warehouse $warehouse;

    protected Supplier $supplier;

    protected Product $custardApple;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Fruit Warehouse',
            'code' => 'FRT-WH',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Fruit Supplier']);

        $this->custardApple = Product::factory()->create([
            'name' => 'Custard Apple',
            'sku' => '214',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_dry_run_identifies_cross_date_matches_without_modifying_data(): void
    {
        // 1. Advance GRN on Date 2026-09-01 (18 kg)
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'received_at' => '2026-09-01 10:00:00',
        ]);
        $advItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->custardApple->id,
            'received_qty' => 18.00,
            'received_unit' => 'kg',
        ]);
        $advBatch = StockBatch::factory()->create([
            'product_id' => $this->custardApple->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'total_kg' => 18.00,
            'received_at' => '2026-09-01',
        ]);

        // 2. Bill GRN on Date 2026-09-02 (3 kg)
        $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->custardApple->id,
            'quantity' => 3.00,
            'purchase_unit' => 'kg',
        ]);
        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_at' => '2026-09-02 10:00:00',
        ]);
        $billItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->custardApple->id,
            'received_qty' => 3.00,
            'received_unit' => 'kg',
        ]);
        $billBatch = StockBatch::factory()->create([
            'product_id' => $this->custardApple->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billItem->id,
            'total_kg' => 0.00, // Reduced by historical cross-date match
            'received_at' => '2026-09-02',
        ]);

        // 3. Cross-date match (3 kg from 01-09 to 02-09)
        $crossMatch = AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'advance_stock_batch_id' => $advBatch->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->custardApple->id,
            'matched_qty' => 3.00,
            'matched_unit' => 'kg',
            'base_qty' => 3.00,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        // Execute Dry-Run
        $this->artisan('purchasing:repair-cross-date-matches --dry-run')
            ->expectsOutputToContain('Cross-date matches found: 1')
            ->expectsOutputToContain('Safe to reverse:         1')
            ->assertSuccessful();

        // Verify no changes occurred
        $this->assertDatabaseHas('advance_receive_matches', ['id' => $crossMatch->id]);
        $this->assertEquals(0.00, $billBatch->fresh()->total_kg);
        $this->assertEquals(18.00, $advBatch->fresh()->total_kg);
    }

    public function test_apply_mode_reverses_cross_date_match_and_restores_bill_stock(): void
    {
        // 1. Advance GRN on Date 2026-09-01 (18 kg)
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'received',
            'received_at' => '2026-09-01 10:00:00',
        ]);
        $advItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->custardApple->id,
            'received_qty' => 18.00,
            'received_unit' => 'kg',
        ]);
        $advBatch = StockBatch::factory()->create([
            'product_id' => $this->custardApple->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'total_kg' => 18.00,
            'received_at' => '2026-09-01',
        ]);

        // 2. Bill GRN on Date 2026-09-02 (3 kg)
        $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->custardApple->id,
            'quantity' => 3.00,
            'purchase_unit' => 'kg',
        ]);
        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_at' => '2026-09-02 10:00:00',
        ]);
        $billItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->custardApple->id,
            'received_qty' => 3.00,
            'received_unit' => 'kg',
        ]);
        $billBatch = StockBatch::factory()->create([
            'product_id' => $this->custardApple->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billItem->id,
            'total_kg' => 0.00, // Stock had been reduced to 0 by cross-date match
            'received_at' => '2026-09-02',
        ]);

        // 3. Same-day match on 2026-09-01 (15 kg - should NOT be touched)
        $sameDayMatch = AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'advance_stock_batch_id' => $advBatch->id,
            'bill_goods_received_id' => $advGrn->id,
            'bill_goods_received_item_id' => $advItem->id,
            'product_id' => $this->custardApple->id,
            'matched_qty' => 15.00,
            'matched_unit' => 'kg',
            'base_qty' => 15.00,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        // 4. Cross-date match (3 kg from 01-09 to 02-09)
        $crossMatch = AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'advance_stock_batch_id' => $advBatch->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->custardApple->id,
            'matched_qty' => 3.00,
            'matched_unit' => 'kg',
            'base_qty' => 3.00,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        // Execute Apply
        $this->artisan('purchasing:repair-cross-date-matches --apply')
            ->expectsOutputToContain('Safe reversed:               1')
            ->expectsOutputToContain('Bill stock restored:         3')
            ->expectsOutputToContain('Advance stock changed:       0')
            ->assertSuccessful();

        // 1. Cross-date match is deleted
        $this->assertDatabaseMissing('advance_receive_matches', ['id' => $crossMatch->id]);

        // 2. Same-day match remains intact
        $this->assertDatabaseHas('advance_receive_matches', ['id' => $sameDayMatch->id]);

        // 3. Bill StockBatch is restored by 3 kg (0 -> 3)
        $this->assertEquals(3.00, $billBatch->fresh()->total_kg);

        // 4. Advance StockBatch remains untouched at 18 kg
        $this->assertEquals(18.00, $advBatch->fresh()->total_kg);

        // 5. Activity log was created
        $this->assertDatabaseHas('activity_log', [
            'description' => "Reversed cross-date match #{$crossMatch->id} (2026-09-01 -> 2026-09-02) and restored 3 kg to Bill StockBatch",
        ]);

        // 6. Running again is idempotent (finds 0 cross-date matches)
        $this->artisan('purchasing:repair-cross-date-matches --dry-run')
            ->expectsOutputToContain('Found 0 cross-date match records')
            ->assertSuccessful();
    }
}
