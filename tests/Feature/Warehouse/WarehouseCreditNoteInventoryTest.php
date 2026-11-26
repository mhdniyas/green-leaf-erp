<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Warehouse\WarehouseCreditNoteStatus;
use App\Enums\Warehouse\WarehouseCreditReason;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Inventory\StockLedgerService;
use App\Services\Warehouse\WarehouseCreditNoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WarehouseCreditNoteInventoryTest extends TestCase
{
    use RefreshDatabase;

    private WarehouseCreditNoteService $service;

    private StockLedgerService $stockLedger;

    private User $admin;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->service = app(WarehouseCreditNoteService::class);
        $this->stockLedger = app(StockLedgerService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouseA = Warehouse::factory()->create();
        $this->warehouseB = Warehouse::factory()->create();

        $this->product = Product::factory()->create([
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouseA->id,
        ]);
    }

    public function test_stock_in_creates_exact_grade_batches_for_a_b_c_and_mixed(): void
    {
        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::ExcessFound->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'in', 'unit' => 'kg', 'quantity' => 10, 'valuation_rate' => 50],
                ['product_id' => $this->product->id, 'grade' => 'B', 'inventory_action' => 'in', 'unit' => 'kg', 'quantity' => 20, 'valuation_rate' => 40],
                ['product_id' => $this->product->id, 'grade' => 'C', 'inventory_action' => 'in', 'unit' => 'kg', 'quantity' => 30, 'valuation_rate' => 30],
                ['product_id' => $this->product->id, 'grade' => 'U', 'inventory_action' => 'in', 'unit' => 'kg', 'quantity' => 40, 'valuation_rate' => 25],
            ],
        ], $this->admin);

        // Draft must have zero inventory impact
        $this->assertSame(0, StockBatch::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, JournalEntry::count());

        $finalized = $this->service->finalize($note, $this->admin);

        $this->assertSame(WarehouseCreditNoteStatus::Active, $finalized->status);
        $this->assertEqualsWithDelta(3200.00, (float) $finalized->total_amount, 0.01);
        $this->assertEqualsWithDelta(10.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA), 0.001);
        $this->assertEqualsWithDelta(20.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeB), 0.001);
        $this->assertEqualsWithDelta(30.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeC), 0.001);
        $this->assertEqualsWithDelta(40.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::Unsorted), 0.001);
        $this->assertSame(4, $finalized->itemMovements()->count());
    }

    public function test_stock_out_deducts_across_multiple_fifo_batches_using_actual_batch_costs(): void
    {
        $batch1 = $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 10.0, 40.0, '2026-11-20');
        $batch2 = $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 15.0, 60.0, '2026-11-22');

        // Client supplies bogus valuation_rate=999; OUT must override with actual FIFO cost (10*40 + 5*60 = 700)
        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::StockReturn->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 15, 'valuation_rate' => 999],
            ],
        ], $this->admin);

        $finalized = $this->service->finalize($note, $this->admin);

        $this->assertEqualsWithDelta(700.00, (float) $finalized->items->first()->fifo_cost_total, 0.01);
        $this->assertEqualsWithDelta(700.00, (float) $finalized->total_amount, 0.01);
        $this->assertEqualsWithDelta(10.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA), 0.001);

        $movements = $finalized->itemMovements()->orderBy('id')->get();
        $this->assertCount(2, $movements);
        $this->assertSame($batch1->id, $movements[0]->stock_batch_id);
        $this->assertEqualsWithDelta(10.0, (float) $movements[0]->quantity, 0.001);
        $this->assertEqualsWithDelta(40.0, (float) $movements[0]->cost_per_unit, 0.0001);
        $this->assertSame($batch2->id, $movements[1]->stock_batch_id);
        $this->assertEqualsWithDelta(5.0, (float) $movements[1]->quantity, 0.001);
        $this->assertEqualsWithDelta(60.0, (float) $movements[1]->cost_per_unit, 0.0001);
    }

    public function test_exact_grade_and_warehouse_isolation_and_pending_receive_exclusion(): void
    {
        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::Unsorted, 50.0, 30.0, '2026-11-20');
        $this->seedSortedBatch($this->warehouseB->id, ProductGrade::GradeA, 50.0, 30.0, '2026-11-20');
        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 50.0, 30.0, '2026-11-20', receivePending: true);

        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::Shortage->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 5, 'valuation_rate' => 30],
            ],
        ], $this->admin);

        $this->expectException(ValidationException::class);
        try {
            $this->service->finalize($note, $this->admin);
        } finally {
            $this->assertSame(0, StockBatch::where('reference', 'like', 'NEG-%')->count());
            $this->assertSame(0, JournalEntry::count());
        }
    }

    public function test_damage_records_wastage_once_with_single_journal_entry(): void
    {
        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeB, 20.0, 55.0, '2026-11-21');

        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::Spoilage->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'B', 'inventory_action' => 'damage', 'unit' => 'kg', 'quantity' => 4, 'valuation_rate' => 10],
            ],
        ], $this->admin);

        $finalized = $this->service->finalize($note, $this->admin);

        $this->assertEqualsWithDelta(220.00, (float) $finalized->items->first()->fifo_cost_total, 0.01);
        $this->assertEqualsWithDelta(16.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeB), 0.001);
        $this->assertSame(1, WastageEntry::count());
        $this->assertSame(1, JournalEntry::count());
        $this->assertNotNull($finalized->items->first()->wastage_entry_id);
    }

    public function test_unit_conversion_and_invalid_unit_rejection(): void
    {
        ProductUnit::create([
            'product_id' => $this->product->id,
            'unit' => 'box',
            'label' => 'Box (12.5 kg)',
            'conversion_to_base' => 12.5000,
        ]);

        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 30.0, 20.0, '2026-11-21');

        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::StockReturn->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'box', 'quantity' => 2, 'valuation_rate' => 20],
            ],
        ], $this->admin);

        $item = $note->items->first();
        $this->assertEqualsWithDelta(25.000, (float) $item->base_qty, 0.001);

        $this->service->finalize($note, $this->admin);
        $this->assertEqualsWithDelta(5.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA), 0.001);

        $this->expectException(ValidationException::class);
        $this->service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::StockReturn->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'unknown_unit', 'quantity' => 1, 'valuation_rate' => 20],
            ],
        ], $this->admin);
    }

    public function test_multiple_lines_same_stock_and_transaction_rollback_on_failure(): void
    {
        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 10.0, 50.0, '2026-11-21');

        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::StockReturn->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 6, 'valuation_rate' => 50],
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 6, 'valuation_rate' => 50],
            ],
        ], $this->admin);

        try {
            $this->service->finalize($note, $this->admin);
            $this->fail('Expected ValidationException due to cumulative shortage.');
        } catch (ValidationException) {
            $this->assertEqualsWithDelta(10.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA), 0.001);
            $this->assertSame(0, $note->fresh()->itemMovements()->count());
            $this->assertSame(WarehouseCreditNoteStatus::Draft, $note->fresh()->status);
            $this->assertSame(0, JournalEntry::count());
        }
    }

    public function test_duplicate_execution_and_exact_batch_reversal_and_consumption_block(): void
    {
        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 20.0, 50.0, '2026-11-21');

        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::DamageWastage->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'damage', 'unit' => 'kg', 'quantity' => 8, 'valuation_rate' => 50],
                ['product_id' => $this->product->id, 'grade' => 'B', 'inventory_action' => 'in', 'unit' => 'kg', 'quantity' => 10, 'valuation_rate' => 35],
            ],
        ], $this->admin);

        $finalized = $this->service->finalize($note, $this->admin);

        // Duplicate finalization must be blocked
        try {
            $this->service->finalize($finalized, $this->admin);
            $this->fail('Expected duplicate finalization to be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertEqualsWithDelta(12.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA), 0.001);
        $this->assertEqualsWithDelta(10.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeB), 0.001);

        // Exact batch reversal restores Grade A to 20, removes Grade B to 0, and soft-deletes WastageEntry
        $voided = $this->service->void($finalized, 'Reversed by audit test', $this->admin);

        $this->assertSame(WarehouseCreditNoteStatus::Voided, $voided->status);
        $this->assertEqualsWithDelta(20.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeB), 0.001);
        $this->assertSoftDeleted('wastage_entries', ['id' => $voided->items->first()->wastage_entry_id]);

        // Repeated void must be blocked
        try {
            $this->service->void($voided, 'Repeated void', $this->admin);
            $this->fail('Expected repeated void to be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Reversal of Stock IN after subsequent consumption must be blocked
        $inNote = $this->service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::ExcessFound->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'C', 'inventory_action' => 'in', 'unit' => 'kg', 'quantity' => 10, 'valuation_rate' => 25],
            ],
        ], $this->admin);

        $finalizedIn = $this->service->finalize($inNote, $this->admin);

        // Consume 3 kg from that newly created Grade C batch
        $this->stockLedger->recordWarehouseCreditOut($this->product->id, $this->warehouseA->id, ProductGrade::GradeC, 3.0, $this->admin->id, 'Consumed');

        $this->expectException(ValidationException::class);
        $this->service->void($finalizedIn, 'Attempt void after consumption', $this->admin);
    }

    public function test_admin_cannot_bypass_lifecycle_guards_to_modify_or_delete_active_or_voided_notes(): void
    {
        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 15.0, 50.0, '2026-11-21');

        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::StockReturn->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 5, 'valuation_rate' => 50],
            ],
        ], $this->admin);

        // Direct Draft -> Active without journal_entry_id must be blocked
        try {
            $note->update(['status' => WarehouseCreditNoteStatus::Active]);
            $this->fail('Expected RuntimeException on direct Draft -> Active transition.');
        } catch (\RuntimeException) {
            $this->assertTrue(true);
        }

        $activeNote = $this->service->finalize($note->fresh(), $this->admin);

        try {
            $this->service->deleteDraft($activeNote, $this->admin);
            $this->fail('Expected ValidationException on deleteDraft of active note.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            $activeNote->update(['total_amount' => 9999.00]);
            $this->fail('Expected RuntimeException on direct update of active note.');
        } catch (\RuntimeException) {
            $this->assertTrue(true);
        }

        $this->expectException(\RuntimeException::class);
        $activeNote->forceDelete();
    }

    private function seedSortedBatch(
        int $warehouseId,
        ProductGrade $grade,
        float $quantity,
        float $costPerKg,
        string $receivedAt,
        bool $receivePending = false,
    ): StockBatch {
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouseId,
            'purchase_grade' => $grade->value,
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => $receivePending,
            'warehouse_confirmed_at' => $receivePending ? null : now(),
            'warehouse_confirmed_by' => $receivePending ? null : $this->admin->id,
            'sorted_at' => now(),
            'created_by' => $this->admin->id,
            'received_at' => $receivedAt,
            'total_kg' => $quantity,
            'cost_per_kg' => $costPerKg,
            'reference' => 'SEED-'.strtoupper(bin2hex(random_bytes(2))),
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $warehouseId,
            'grade' => $grade->value,
            'type' => StockMovementType::In->value,
            'quantity' => $quantity,
            'cost_per_unit' => $costPerKg,
            'created_by' => $this->admin->id,
            'notes' => 'Seeded batch',
        ]);

        return $batch;
    }
}
