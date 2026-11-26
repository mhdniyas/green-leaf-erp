<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Warehouse\WarehouseCreditNoteStatus;
use App\Enums\Warehouse\WarehouseCreditReason;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseFinanceAccountingOpening;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Finance\JournalService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Warehouse\WarehouseCreditNoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class WarehouseCreditNoteAccountingTest extends TestCase
{
    use RefreshDatabase;

    private WarehouseCreditNoteService $service;

    private StockLedgerService $stockLedger;

    private User $admin;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->service = app(WarehouseCreditNoteService::class);
        $this->stockLedger = app(StockLedgerService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create([
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_stock_in_out_damage_and_mixed_action_journal_entries_are_balanced(): void
    {
        $this->seedSortedBatch($this->warehouse->id, ProductGrade::GradeA, 10.0, 40.0, '2026-11-20');
        $this->seedSortedBatch($this->warehouse->id, ProductGrade::GradeA, 10.0, 60.0, '2026-11-21');
        $this->seedSortedBatch($this->warehouse->id, ProductGrade::GradeB, 10.0, 35.0, '2026-11-21');

        // Mixed-action note:
        // IN:     Grade C, 5 kg @ 22.50 = 112.50 -> Dr 1200 / Cr 5210
        // OUT:    Grade A, 12 kg (10*40 + 2*60 = 520.00, client rate ignored) -> Dr 5210 / Cr 1200
        // Damage: Grade B, 3 kg (3*35 = 105.00, client rate ignored) -> Dr 5200 / Cr 1200
        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouse->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::DamageWastage->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'C', 'inventory_action' => 'in', 'unit' => 'kg', 'quantity' => 5, 'valuation_rate' => 22.50],
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 12, 'valuation_rate' => 999],
                ['product_id' => $this->product->id, 'grade' => 'B', 'inventory_action' => 'damage', 'unit' => 'kg', 'quantity' => 3, 'valuation_rate' => 999],
            ],
        ], $this->admin);

        // Draft has zero GL & stock impact
        $this->assertSame(0, JournalEntry::count());

        $finalized = $this->service->finalize($note, $this->admin);
        $this->assertSame(WarehouseCreditNoteStatus::Active, $finalized->status);
        $this->assertNotNull($finalized->journal_entry_id);
        $this->assertEqualsWithDelta(737.50, (float) $finalized->total_amount, 0.01);

        // Only 1 JournalEntry (no duplicate wastage journal)
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(1, WastageEntry::count());
        $this->assertSame(1, Account::where('code', '5210')->count());

        $entry = $finalized->journalEntry;
        $this->assertSame('2026-11-26', $entry->entry_date->toDateString());
        $this->assertSame($finalized->credit_note_number, $entry->reference);

        $debits = round((float) $entry->transactions->where('type', 'debit')->sum('amount'), 2);
        $credits = round((float) $entry->transactions->where('type', 'credit')->sum('amount'), 2);
        $this->assertEqualsWithDelta($debits, $credits, 0.001);
        $this->assertEqualsWithDelta(737.50, $debits, 0.01);

        // Verify exact account postings
        $invId = Account::where('code', '1200')->value('id');
        $adjId = Account::where('code', '5210')->value('id');
        $wasteId = Account::where('code', '5200')->value('id');

        $this->assertEqualsWithDelta(112.50, (float) $entry->transactions->where('account_id', $invId)->where('type', 'debit')->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(625.00, (float) $entry->transactions->where('account_id', $invId)->where('type', 'credit')->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(112.50, (float) $entry->transactions->where('account_id', $adjId)->where('type', 'credit')->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(520.00, (float) $entry->transactions->where('account_id', $adjId)->where('type', 'debit')->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(105.00, (float) $entry->transactions->where('account_id', $wasteId)->where('type', 'debit')->sum('amount'), 0.01);

        // Duplicate journal posting via JournalService directly must be rejected
        $this->expectException(\RuntimeException::class);
        app(JournalService::class)->recordWarehouseCreditNote($finalized, $this->admin);
    }

    public function test_journal_failure_rolls_back_inventory_and_keeps_note_in_draft(): void
    {
        $this->seedSortedBatch($this->warehouse->id, ProductGrade::GradeA, 10.0, 50.0, '2026-11-21');

        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouse->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::DamageWastage->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'damage', 'unit' => 'kg', 'quantity' => 4, 'valuation_rate' => 50],
            ],
        ], $this->admin);

        $mockJournal = Mockery::mock(JournalService::class);
        $mockJournal->shouldReceive('recordWarehouseCreditNote')
            ->once()
            ->andThrow(new \RuntimeException('Simulated journal failure'));

        $failingService = new WarehouseCreditNoteService($this->stockLedger, $mockJournal);

        try {
            $failingService->finalize($note, $this->admin);
            $this->fail('Expected RuntimeException from mocked JournalService.');
        } catch (\RuntimeException) {
            $fresh = $note->fresh();
            $this->assertSame(WarehouseCreditNoteStatus::Draft, $fresh->status);
            $this->assertNull($fresh->journal_entry_id);
            $this->assertNull($fresh->finalized_at);
            $this->assertSame(0, $fresh->itemMovements()->count());
            $this->assertSame(0, WastageEntry::count());
            $this->assertEqualsWithDelta(10.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA), 0.001);
        }
    }

    public function test_atomic_void_reverses_journal_entry_stock_and_wastage(): void
    {
        $this->seedSortedBatch($this->warehouse->id, ProductGrade::GradeA, 10.0, 45.0, '2026-11-21');

        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouse->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::DamageWastage->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'damage', 'unit' => 'kg', 'quantity' => 4, 'valuation_rate' => 45],
            ],
        ], $this->admin);

        $finalized = $this->service->finalize($note, $this->admin);
        $voided = $this->service->void($finalized, 'Posted in error', $this->admin);

        $this->assertSame(WarehouseCreditNoteStatus::Voided, $voided->status);
        $this->assertSame($this->admin->id, $voided->voided_by);
        $this->assertNotNull($voided->voided_at);
        $this->assertSame('Posted in error', $voided->void_reason);

        // Reversal journal exists and balances original journal
        $reversalEntry = JournalEntry::where('source_event', "reversal:{$finalized->journal_entry_id}")->first();
        $this->assertNotNull($reversalEntry);

        $origDebits = round((float) $finalized->journalEntry->transactions->where('type', 'debit')->sum('amount'), 2);
        $revCredits = round((float) $reversalEntry->transactions->where('type', 'credit')->sum('amount'), 2);
        $this->assertEqualsWithDelta($origDebits, $revCredits, 0.001);

        // Stock and wastage reversed
        $this->assertEqualsWithDelta(10.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA), 0.001);
        $this->assertSoftDeleted('wastage_entries', ['id' => $voided->items->first()->wastage_entry_id]);
    }

    public function test_closed_accounting_period_and_unauthorized_warehouse_are_blocked(): void
    {
        $this->seedSortedBatch($this->warehouse->id, ProductGrade::GradeA, 10.0, 50.0, '2026-11-21');

        // 1. Pre-opening business date (< 2026-09-01) is blocked on finalize
        $preOpeningNote = $this->service->createDraft([
            'warehouse_id' => $this->warehouse->id,
            'business_date' => '2026-08-15',
            'reason' => WarehouseCreditReason::StockReturn->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 2, 'valuation_rate' => 50],
            ],
        ], $this->admin);

        try {
            $this->service->finalize($preOpeningNote, $this->admin);
            $this->fail('Expected ValidationException for pre-opening date.');
        } catch (ValidationException) {
            $this->assertSame(WarehouseCreditNoteStatus::Draft, $preOpeningNote->fresh()->status);
        }

        // 2. Configured warehouse accounting opening date blocks finalize for earlier dates
        $note = $this->service->createDraft([
            'warehouse_id' => $this->warehouse->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::StockReturn->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 2, 'valuation_rate' => 50],
            ],
        ], $this->admin);

        PurchaseFinanceAccountingOpening::create([
            'account_scope' => 'warehouse',
            'accounting_start_date' => '2026-12-01',
            'created_by' => $this->admin->id,
        ]);

        try {
            $this->service->finalize($note, $this->admin);
            $this->fail('Expected ValidationException for closed accounting period.');
        } catch (ValidationException) {
            $this->assertSame(0, JournalEntry::count());
        }

        // 3. Accounts user without warehouse assignment is blocked
        $otherWarehouse = Warehouse::factory()->create();
        $accounts = User::factory()->create();
        $accounts->assignRole('accounts');
        $accounts->warehouses()->attach($otherWarehouse->id, ['is_default' => true]);

        $this->expectException(ValidationException::class);
        $this->service->finalize($note, $accounts);
    }

    private function seedSortedBatch(
        int $warehouseId,
        ProductGrade $grade,
        float $quantity,
        float $costPerKg,
        string $receivedAt,
    ): StockBatch {
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouseId,
            'purchase_grade' => $grade->value,
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
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
