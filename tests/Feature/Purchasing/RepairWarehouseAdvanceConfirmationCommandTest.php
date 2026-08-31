<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairWarehouseAdvanceConfirmationCommandTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse1;

    private Warehouse $warehouse2;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse1 = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH1',
            'is_active' => true,
        ]);

        $this->warehouse2 = Warehouse::create([
            'name' => 'Secondary Warehouse',
            'code' => 'WH2',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();

        $this->product = Product::factory()->create([
            'name' => 'Red Apple',
            'sku' => 'APPLE-001',
            'unit' => 'kg',
            'base_price' => 50.00,
            'is_active' => true,
        ]);
    }

    private function createAdvanceWithPendingBatch(Warehouse $wh, float $qty, string $receiptType = 'warehouse_advance', string $status = 'approved', string $billStatus = 'bill_pending'): array
    {
        $grn = GoodsReceived::create([
            'warehouse_id' => $wh->id,
            'grn_number' => 'GRN-'.uniqid(),
            'receipt_type' => $receiptType,
            'status' => $status,
            'bill_status' => $billStatus,
            'received_at' => '2026-08-30 10:00:00',
            'approved_at' => '2026-08-30 10:05:00',
            'received_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => $qty,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $wh->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->user->id,
            'reference' => 'BATCH-'.uniqid(),
            'received_at' => '2026-08-30 10:00:00',
            'total_kg' => $qty,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
        ]);

        return [$grn, $batch];
    }

    public function test_repair_command_requires_warehouse_option(): void
    {
        $this->artisan('purchasing:repair-warehouse-advance-confirmation')
            ->expectsOutput('The --warehouse=<id> option is required and must be a positive integer.')
            ->assertExitCode(1);
    }

    public function test_repair_command_dry_run_changes_nothing(): void
    {
        [$grn, $batch] = $this->createAdvanceWithPendingBatch($this->warehouse1, 100.0);

        // Before run: batch is pending confirmation and excluded from openWarehouseAdvance
        $this->assertTrue((bool) $batch->fresh()->warehouse_receive_pending);
        $this->assertFalse(GoodsReceived::openWarehouseAdvance($this->warehouse1->id)->whereKey($grn->id)->exists());

        $initialBatchTotal = StockBatch::sum('total_kg');
        $initialMovementCount = StockMovement::count();

        // Run dry-run (without --execute)
        $this->artisan('purchasing:repair-warehouse-advance-confirmation', [
            '--warehouse' => $this->warehouse1->id,
        ])
            ->expectsOutputToContain('DRY RUN: No database changes were made.')
            ->assertExitCode(0);

        // Verify no changes were made
        $this->assertTrue((bool) $batch->fresh()->warehouse_receive_pending);
        $this->assertFalse(GoodsReceived::openWarehouseAdvance($this->warehouse1->id)->whereKey($grn->id)->exists());
        $this->assertEquals($initialBatchTotal, StockBatch::sum('total_kg'));
        $this->assertEquals($initialMovementCount, StockMovement::count());
    }

    public function test_repair_command_execution_updates_only_confirmation_fields_and_is_idempotent(): void
    {
        [$grn1, $batch1] = $this->createAdvanceWithPendingBatch($this->warehouse1, 100.0);
        [$grn2, $batch2] = $this->createAdvanceWithPendingBatch($this->warehouse1, 50.0);
        // Advance for warehouse 2 (should NOT be touched when repairing warehouse 1)
        [$grnWh2, $batchWh2] = $this->createAdvanceWithPendingBatch($this->warehouse2, 75.0);

        $initialBatchCount = StockBatch::count();
        $initialBatchTotal = StockBatch::sum('total_kg');
        $initialMovementCount = StockMovement::count();

        // Run with --execute for warehouse 1
        $this->artisan('purchasing:repair-warehouse-advance-confirmation', [
            '--warehouse' => $this->warehouse1->id,
            '--execute' => true,
        ])
            ->expectsOutputToContain('SUCCESS: Successfully confirmed 2 stock batches across 2 warehouse advances.')
            ->assertExitCode(0);

        // Verify warehouse 1 batches were confirmed
        $freshBatch1 = $batch1->fresh();
        $this->assertFalse((bool) $freshBatch1->warehouse_receive_pending);
        $this->assertNotNull($freshBatch1->warehouse_confirmed_at);
        $this->assertEquals($this->user->id, $freshBatch1->warehouse_confirmed_by);

        $freshBatch2 = $batch2->fresh();
        $this->assertFalse((bool) $freshBatch2->warehouse_receive_pending);

        // Verify warehouse 2 batch was NOT modified
        $this->assertTrue((bool) $batchWh2->fresh()->warehouse_receive_pending);

        // Verify both warehouse 1 advances are now in openWarehouseAdvance
        $this->assertTrue(GoodsReceived::openWarehouseAdvance($this->warehouse1->id)->whereKey($grn1->id)->exists());
        $this->assertTrue(GoodsReceived::openWarehouseAdvance($this->warehouse1->id)->whereKey($grn2->id)->exists());
        $this->assertFalse(GoodsReceived::openWarehouseAdvance($this->warehouse2->id)->whereKey($grnWh2->id)->exists());

        // Inventory integrity: NO extra batches, NO extra movements, quantities unchanged
        $this->assertEquals($initialBatchCount, StockBatch::count());
        $this->assertEquals($initialBatchTotal, StockBatch::sum('total_kg'));
        $this->assertEquals($initialMovementCount, StockMovement::count());

        // IDEMPOTENCY: Rerunning the command finds 0 eligible batches and makes 0 changes
        $this->artisan('purchasing:repair-warehouse-advance-confirmation', [
            '--warehouse' => $this->warehouse1->id,
            '--execute' => true,
        ])
            ->expectsOutputToContain('No unconfirmed stock batches found for approved warehouse advances in warehouse')
            ->assertExitCode(0);
    }

    public function test_repair_command_ignores_non_advance_or_unapproved_receipts(): void
    {
        // 1. Normal purchase receipt (not warehouse_advance) with pending batch
        [$grnNormal, $batchNormal] = $this->createAdvanceWithPendingBatch($this->warehouse1, 40.0, 'normal_purchase');
        // 2. Draft/pending approval advance
        [$grnDraft, $batchDraft] = $this->createAdvanceWithPendingBatch($this->warehouse1, 60.0, 'warehouse_advance', 'draft');

        $this->artisan('purchasing:repair-warehouse-advance-confirmation', [
            '--warehouse' => $this->warehouse1->id,
            '--execute' => true,
        ])
            ->expectsOutputToContain('No unconfirmed stock batches found')
            ->assertExitCode(0);

        // Neither batch was confirmed
        $this->assertTrue((bool) $batchNormal->fresh()->warehouse_receive_pending);
        $this->assertTrue((bool) $batchDraft->fresh()->warehouse_receive_pending);
    }
}
