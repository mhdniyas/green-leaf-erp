<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Warehouse\WarehouseCreditInventoryAction;
use App\Enums\Warehouse\WarehouseCreditNoteStatus;
use App\Enums\Warehouse\WarehouseCreditReason;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseCreditNote;
use App\Models\WarehouseCreditNoteItem;
use App\Models\WarehouseCreditNoteItemMovement;
use App\Policies\WarehouseCreditNotePolicy;
use App\Support\CashbookAccess;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class WarehouseCreditNoteFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_warehouse_credit_note_defaults_to_draft_and_generates_unique_identifiers(): void
    {
        $warehouse = Warehouse::factory()->create();
        $user = User::factory()->create();

        $note = WarehouseCreditNote::create([
            'warehouse_id' => $warehouse->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::ExcessFound,
            'created_by' => $user->id,
        ]);

        $this->assertNotEmpty($note->public_uuid);
        $this->assertSame(WarehouseCreditNoteStatus::Draft, $note->status);
        $this->assertTrue($note->isDraft());
        $this->assertFalse($note->isActive());
        $this->assertFalse($note->isVoided());
        $this->assertNull($note->credit_note_number);

        $number1 = WarehouseCreditNote::generateCreditNoteNumber('2026-11-26');
        $number2 = WarehouseCreditNote::generateCreditNoteNumber('2026-11-26');

        $this->assertMatchesRegularExpression('/^WCN26-1126-[A-F0-9]{4}$/', $number1);
        $this->assertNotSame($number1, $number2);

        $this->expectException(QueryException::class);
        WarehouseCreditNote::create([
            'public_uuid' => $note->public_uuid,
            'warehouse_id' => $warehouse->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::Other,
            'created_by' => $user->id,
        ]);
    }

    public function test_relationships_grades_inventory_actions_and_decimal_precision(): void
    {
        $warehouse = Warehouse::factory()->create();
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $batch = StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'purchase_grade' => ProductGrade::Unsorted->value,
            'grading_mode' => 'sort_required',
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'created_by' => $user->id,
            'reference' => 'TEST-BATCH-001',
            'received_at' => '2026-11-25',
            'total_kg' => 100.000,
            'cost_per_kg' => 45.1250,
        ]);

        $movement = StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'grade' => ProductGrade::Unsorted,
            'type' => StockMovementType::Out,
            'quantity' => 12.345,
            'cost_per_unit' => 45.1250,
            'notes' => 'Test warehouse credit movement',
        ]);

        $creditNote = WarehouseCreditNote::create([
            'credit_note_number' => WarehouseCreditNote::generateCreditNoteNumber('2026-11-26'),
            'warehouse_id' => $warehouse->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::DamageWastage,
            'status' => WarehouseCreditNoteStatus::Active,
            'total_amount' => 557.07,
            'created_by' => $user->id,
            'finalized_by' => $user->id,
            'finalized_at' => now(),
        ]);

        $gradesAndActions = [
            [ProductGrade::GradeA, WarehouseCreditInventoryAction::In, '10.125', '2.5000', '25.313', '12.3456', '312.50'],
            [ProductGrade::GradeB, WarehouseCreditInventoryAction::Out, '5.500', '1.0000', '5.500', '40.0000', '220.00'],
            [ProductGrade::GradeC, WarehouseCreditInventoryAction::Damage, '2.250', '1.0000', '2.250', '10.0000', '22.50'],
            [ProductGrade::Unsorted, WarehouseCreditInventoryAction::Out, '12.345', '1.0000', '12.345', '45.1250', '557.07'],
        ];

        $createdItems = [];
        foreach ($gradesAndActions as [$grade, $action, $qty, $conv, $baseQty, $rate, $lineTotal]) {
            $createdItems[] = WarehouseCreditNoteItem::create([
                'warehouse_credit_note_id' => $creditNote->id,
                'product_id' => $product->id,
                'grade' => $grade,
                'inventory_action' => $action,
                'unit' => 'kg',
                'conversion_to_base' => $conv,
                'quantity' => $qty,
                'base_qty' => $baseQty,
                'valuation_rate' => $rate,
                'line_total' => $lineTotal,
                'fifo_cost_total' => $lineTotal,
            ]);
        }

        $itemMovement = WarehouseCreditNoteItemMovement::create([
            'warehouse_credit_note_item_id' => $createdItems[3]->id,
            'stock_batch_id' => $batch->id,
            'stock_movement_id' => $movement->id,
            'quantity' => '12.345',
            'cost_per_unit' => '45.1250',
        ]);

        $creditNote->refresh();

        $this->assertCount(4, $creditNote->items);
        $this->assertCount(1, $creditNote->itemMovements);
        $this->assertSame($warehouse->id, $creditNote->warehouse->id);
        $this->assertSame($user->id, $creditNote->createdBy->id);
        $this->assertSame($user->id, $creditNote->finalizedBy->id);
        $this->assertSame(ProductGrade::Unsorted, $createdItems[3]->grade);
        $this->assertSame(WarehouseCreditInventoryAction::Out, $createdItems[3]->inventory_action);
        $this->assertSame('12.345', (string) $itemMovement->quantity);
        $this->assertSame('45.1250', (string) $itemMovement->cost_per_unit);
        $this->assertSame($batch->id, $itemMovement->stockBatch->id);
        $this->assertSame($movement->id, $itemMovement->stockMovement->id);
        $this->assertSame('2.5000', (string) $createdItems[0]->conversion_to_base);
        $this->assertSame('10.125', (string) $createdItems[0]->quantity);
        $this->assertSame('25.313', (string) $createdItems[0]->base_qty);
        $this->assertSame('12.3456', (string) $createdItems[0]->valuation_rate);
        $this->assertSame('312.50', (string) $createdItems[0]->line_total);
    }

    public function test_active_credit_notes_cannot_be_soft_deleted_and_scopes_filter_correctly(): void
    {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $user = User::factory()->create();

        $draft = WarehouseCreditNote::create([
            'warehouse_id' => $warehouse1->id,
            'business_date' => '2026-11-25',
            'reason' => WarehouseCreditReason::StockReturn,
            'status' => WarehouseCreditNoteStatus::Draft,
            'created_by' => $user->id,
        ]);

        $active = WarehouseCreditNote::create([
            'credit_note_number' => WarehouseCreditNote::generateCreditNoteNumber('2026-11-26'),
            'warehouse_id' => $warehouse2->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::Shortage,
            'status' => WarehouseCreditNoteStatus::Active,
            'created_by' => $user->id,
        ]);

        $this->assertCount(1, WarehouseCreditNote::draft()->get());
        $this->assertCount(1, WarehouseCreditNote::active()->get());
        $this->assertCount(1, WarehouseCreditNote::forWarehouse($warehouse1)->get());
        $this->assertCount(1, WarehouseCreditNote::forPeriod('2026-11-26', '2026-11-26')->get());

        $draft->delete();
        $this->assertSoftDeleted($draft);

        $this->expectException(\RuntimeException::class);
        $active->delete();
    }

    public function test_permissions_and_warehouse_scoping_enforce_admin_and_authorized_accounts_only(): void
    {
        $warehouseA = Warehouse::factory()->create();
        $warehouseB = Warehouse::factory()->create();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $accountsAssigned = User::factory()->create();
        $accountsAssigned->assignRole('accounts');
        $accountsAssigned->warehouses()->attach($warehouseA->id, ['is_default' => true]);

        $warehouseReceiver = User::factory()->create();
        $warehouseReceiver->assignRole('warehouse_receiver');
        $warehouseReceiver->warehouses()->attach($warehouseA->id, ['is_default' => true]);

        $draftInA = WarehouseCreditNote::create([
            'warehouse_id' => $warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::Other,
            'status' => WarehouseCreditNoteStatus::Draft,
            'created_by' => $admin->id,
        ]);

        $activeInB = WarehouseCreditNote::create([
            'credit_note_number' => WarehouseCreditNote::generateCreditNoteNumber('2026-11-26'),
            'warehouse_id' => $warehouseB->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::DamageWastage,
            'status' => WarehouseCreditNoteStatus::Active,
            'created_by' => $admin->id,
        ]);

        // Accounts user has the 3 permissions and access to warehouseA, not warehouseB
        $this->assertTrue($accountsAssigned->can(CashbookAccess::WarehouseCreditNotesView));
        $this->assertTrue($accountsAssigned->can(CashbookAccess::WarehouseCreditNotesManage));
        $this->assertTrue($accountsAssigned->can(CashbookAccess::WarehouseCreditNotesVoid));

        $this->assertTrue(Gate::forUser($accountsAssigned)->allows('view', $draftInA));
        $this->assertTrue(Gate::forUser($accountsAssigned)->allows('update', $draftInA));
        $this->assertTrue(Gate::forUser($accountsAssigned)->allows('finalize', $draftInA));
        $this->assertFalse(Gate::forUser($accountsAssigned)->allows('void', $draftInA));
        $this->assertFalse(Gate::forUser($accountsAssigned)->allows('view', $activeInB));
        $this->assertFalse(Gate::forUser($accountsAssigned)->allows('void', $activeInB));

        // Admin has full Gate access (AppServiceProvider Gate::before), while policy methods enforce state guards
        $policy = new WarehouseCreditNotePolicy;
        $this->assertTrue(Gate::forUser($admin)->allows('finalize', $draftInA));
        $this->assertFalse($policy->void($admin, $draftInA));
        $this->assertTrue(Gate::forUser($admin)->allows('void', $activeInB));
        $this->assertFalse($policy->update($admin, $activeInB));
        $this->assertFalse($policy->finalize($admin, $activeInB));

        // Warehouse receiver is denied
        $this->assertFalse(Gate::forUser($warehouseReceiver)->allows('viewAny', WarehouseCreditNote::class));
        $this->assertFalse(Gate::forUser($warehouseReceiver)->allows('view', $draftInA));
        $this->assertFalse(Gate::forUser($warehouseReceiver)->allows('create', [WarehouseCreditNote::class, $warehouseA->id]));
    }
}
