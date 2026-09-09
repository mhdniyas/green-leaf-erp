<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearOldAdvancesCommandTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private User $user;

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

        $this->product = Product::factory()->create([
            'name' => 'Carrot',
            'sku' => 'CAR-001',
            'unit' => 'kg',
            'base_price' => 20.00,
            'is_active' => true,
        ]);
    }

    public function test_dry_run_identifies_old_advances_without_modifying(): void
    {
        $oldGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-ADV-OLD',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => '2026-09-02 10:00:00',
            'approved_at' => '2026-09-02 10:05:00',
            'received_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $oldGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $oldGrn->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 20.0,
            'created_by' => $this->user->id,
            'reference' => 'BATCH-OLD',
            'received_at' => '2026-09-02',
            'warehouse_receive_pending' => false,
        ]);

        $newGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-ADV-NEW',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => '2026-09-05 10:00:00',
            'approved_at' => '2026-09-05 10:05:00',
            'received_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);

        $this->artisan('inventory:clear-old-advances --through=2026-09-03')
            ->expectsOutputToContain('Old open advances to clear: 1')
            ->expectsOutputToContain('No changes made. Run with --apply to execute.')
            ->assertExitCode(0);

        // State remains untouched
        $this->assertEquals('bill_pending', $oldGrn->fresh()->bill_status);
        $this->assertEquals(50.0, (float) $batch->fresh()->total_kg);
    }

    public function test_apply_mode_clears_old_advances_and_preserves_stock(): void
    {
        $oldGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-ADV-OLD-2',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => '2026-09-03 18:00:00',
            'approved_at' => '2026-09-03 18:05:00',
            'received_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);
        GoodsReceivedItem::create([
            'goods_received_id' => $oldGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 75.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $oldGrn->id,
            'total_kg' => 75.0,
            'cost_per_kg' => 20.0,
            'created_by' => $this->user->id,
            'reference' => 'BATCH-OLD-2',
            'received_at' => '2026-09-03',
            'warehouse_receive_pending' => false,
        ]);

        $this->artisan('inventory:clear-old-advances --through=2026-09-03 --apply')
            ->expectsOutputToContain('Old open advances to clear: 1')
            ->expectsOutputToContain('Successfully cleared 1 historical advance(s).')
            ->assertExitCode(0);

        // Bill status updated to bill_available
        $this->assertEquals('bill_available', $oldGrn->fresh()->bill_status);

        // Stock batch quantity is completely untouched
        $this->assertEquals(75.0, (float) $batch->fresh()->total_kg);

        // Subsequent run finds 0 eligible
        $this->artisan('inventory:clear-old-advances --through=2026-09-03')
            ->expectsOutputToContain('Old open advances to clear: 0')
            ->assertExitCode(0);
    }
}
