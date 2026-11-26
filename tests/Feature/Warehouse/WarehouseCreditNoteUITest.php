<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Warehouse\WarehouseCreditInventoryAction;
use App\Enums\Warehouse\WarehouseCreditNoteStatus;
use App\Enums\Warehouse\WarehouseCreditReason;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseCreditNote;
use App\Services\Inventory\StockLedgerService;
use App\Services\Warehouse\WarehouseCreditNoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseCreditNoteUITest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountsUser;

    private User $unauthorizedUser;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->warehouseA = Warehouse::factory()->create(['name' => 'Alpha Hub']);
        $this->warehouseB = Warehouse::factory()->create(['name' => 'Beta Hub']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->accountsUser = User::factory()->create();
        $this->accountsUser->assignRole('accounts');
        $this->accountsUser->givePermissionTo('cashbook.access');
        $this->accountsUser->warehouses()->attach($this->warehouseA->id, ['is_default' => true]);

        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->assignRole('warehouse_receiver');
        $this->unauthorizedUser->warehouses()->attach($this->warehouseA->id, ['is_default' => true]);

        $this->product = Product::factory()->create([
            'name' => 'Organic Banana',
            'sku' => 'ORG-BAN-01',
            'unit' => 'kg',
            'is_active' => true,
            'default_warehouse_id' => $this->warehouseA->id,
        ]);
    }

    public function test_navigation_permissions_and_warehouse_scoped_index_and_summary(): void
    {
        $service = app(WarehouseCreditNoteService::class);

        // Seed 20 kg Grade A @ ₹50 in Alpha Hub, 10 kg Grade A @ ₹30 in Beta Hub
        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 20.0, 50.0, '2026-11-20');
        $this->seedSortedBatch($this->warehouseB->id, ProductGrade::GradeA, 10.0, 30.0, '2026-11-20');

        // Active note in Alpha Hub: OUT 5 kg @ 50 = 250
        $activeA = $service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::StockReturn->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 5, 'valuation_rate' => 50]],
        ], $this->admin);
        $service->finalize($activeA, $this->admin);

        // Draft note in Alpha Hub (must be excluded from summary)
        $service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::ExcessFound->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'in', 'unit' => 'kg', 'quantity' => 10, 'valuation_rate' => 100]],
        ], $this->admin);

        // Active note in Beta Hub (must not be visible to accountsUser)
        $activeB = $service->createDraft([
            'warehouse_id' => $this->warehouseB->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::StockReturn->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 2, 'valuation_rate' => 30]],
        ], $this->admin);
        $service->finalize($activeB, $this->admin);

        // Unauthorized role is denied (redirected by outer cashbook middleware or 403)
        $deniedStatus = $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.cashbook.warehouse-credit-notes.index'))
            ->getStatusCode();
        $this->assertTrue(in_array($deniedStatus, [302, 403], true));

        // User with cashbook.access but without warehouse-credit-notes.view is denied
        $noPermUser = User::factory()->create();
        $noPermUser->assignRole('purchaser');
        $noPermUser->givePermissionTo('cashbook.access');
        $noPermStatus = $this->actingAs($noPermUser)
            ->get(route('admin.cashbook.warehouse-credit-notes.index'))
            ->getStatusCode();
        $this->assertTrue(in_array($noPermStatus, [302, 403], true));

        // Authorized accounts user sees Alpha Hub notes only, with sidebar link and accurate summary (₹250.00 OUT, ₹0 IN)
        $response = $this->actingAs($this->accountsUser)
            ->get(route('admin.cashbook.warehouse-credit-notes.index'));

        $response->assertOk()
            ->assertSee('Warehouse Credit Notes')
            ->assertSee($activeA->credit_note_number)
            ->assertDontSee($activeB->credit_note_number)
            ->assertViewHas('summary', fn (array $s): bool => $s['stock_in_total'] === 0.0
                && $s['stock_out_total'] === 250.0
                && $s['damage_total'] === 0.0
                && $s['net_change'] === -250.0);

        // Cross-warehouse filter or show is blocked (302 web redirect with error or 403)
        $crossFilterStatus = $this->actingAs($this->accountsUser)
            ->get(route('admin.cashbook.warehouse-credit-notes.index', ['warehouse_id' => $this->warehouseB->id]))
            ->getStatusCode();
        $this->assertTrue(in_array($crossFilterStatus, [302, 403], true));

        $crossShowStatus = $this->actingAs($this->accountsUser)
            ->get(route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $activeB->public_uuid]))
            ->getStatusCode();
        $this->assertTrue(in_array($crossShowStatus, [302, 403], true));
    }

    public function test_product_search_and_exact_grade_stock_availability_endpoints(): void
    {
        ProductUnit::create([
            'product_id' => $this->product->id,
            'unit' => 'box',
            'label' => 'Box (10 kg)',
            'conversion_to_base' => 10.0,
        ]);

        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 15.0, 42.5, '2026-11-21');
        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::Unsorted, 50.0, 20.0, '2026-11-21');

        $this->actingAs($this->accountsUser)
            ->getJson(route('admin.cashbook.warehouse-credit-notes.search-products', ['q' => 'Banana']))
            ->assertOk()
            ->assertJsonPath('items.0.id', $this->product->id)
            ->assertJsonCount(2, 'items.0.units');

        $this->actingAs($this->accountsUser)
            ->getJson(route('admin.cashbook.warehouse-credit-notes.stock-availability', [
                'warehouse_id' => $this->warehouseA->id,
                'product_id' => $this->product->id,
                'grade' => 'A',
            ]))
            ->assertOk()
            ->assertJsonPath('available_qty', 15)
            ->assertJsonPath('estimated_fifo_rate', 42.5);

        // Cross-warehouse stock check is forbidden
        $this->actingAs($this->accountsUser)
            ->getJson(route('admin.cashbook.warehouse-credit-notes.stock-availability', [
                'warehouse_id' => $this->warehouseB->id,
                'product_id' => $this->product->id,
                'grade' => 'A',
            ]))
            ->assertForbidden();
    }

    public function test_draft_create_edit_delete_and_save_and_finalize_with_server_side_valuation(): void
    {
        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 10.0, 50.0, '2026-11-21');

        // 1. Create Draft
        $createRes = $this->actingAs($this->accountsUser)
            ->post(route('admin.cashbook.warehouse-credit-notes.store'), [
                'warehouse_id' => $this->warehouseA->id,
                'business_date' => '2026-11-26',
                'reason' => WarehouseCreditReason::StockReturn->value,
                'remarks' => 'Initial draft',
                'save_action' => 'draft',
                'items' => [
                    ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 4, 'valuation_rate' => 9999],
                ],
            ]);

        $draft = WarehouseCreditNote::firstOrFail();
        $createRes->assertRedirect(route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $draft->public_uuid]));
        $this->assertTrue($draft->isDraft());
        $this->assertSame(0, StockMovement::where('notes', 'like', '%WCN%')->count());
        $this->assertSame(0, JournalEntry::count());

        // 2. Edit Draft & Save & Finalize with multi-product + bogus OUT rate
        $updateRes = $this->actingAs($this->accountsUser)
            ->put(route('admin.cashbook.warehouse-credit-notes.update', ['creditNote' => $draft->public_uuid]), [
                'warehouse_id' => $this->warehouseA->id,
                'business_date' => '2026-11-26',
                'reason' => WarehouseCreditReason::DamageWastage->value,
                'remarks' => 'Updated & finalized',
                'save_action' => 'finalize',
                'items' => [
                    ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 4, 'valuation_rate' => 9999],
                    ['product_id' => $this->product->id, 'grade' => 'B', 'inventory_action' => 'in', 'unit' => 'kg', 'quantity' => 3, 'valuation_rate' => 30],
                ],
            ]);

        $finalized = $draft->fresh();
        $updateRes->assertRedirect(route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $finalized->public_uuid]));
        $this->assertSame(WarehouseCreditNoteStatus::Active, $finalized->status);
        // 4*50 (actual FIFO, not 9999) + 3*30 = 290.00
        $this->assertEqualsWithDelta(290.00, (float) $finalized->total_amount, 0.01);
        $this->assertSame(1, JournalEntry::count());

        // 3. Finalized note edit redirects & destroy fails
        $this->actingAs($this->accountsUser)
            ->get(route('admin.cashbook.warehouse-credit-notes.edit', ['creditNote' => $finalized->public_uuid]))
            ->assertRedirect(route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $finalized->public_uuid]));

        // 4. Detail page shows journal for admin & accounts (with accounting.ledger.view)
        $this->actingAs($this->accountsUser)
            ->get(route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $finalized->public_uuid]))
            ->assertOk()
            ->assertSee($finalized->credit_note_number)
            ->assertSee('Journal Entry #');

        // 5. Duplicate finalize fails
        $this->actingAs($this->accountsUser)
            ->post(route('admin.cashbook.warehouse-credit-notes.finalize', ['creditNote' => $finalized->public_uuid]))
            ->assertSessionHasErrors('status');

        // 6. Delete separate draft works
        $draft2Res = $this->actingAs($this->accountsUser)
            ->post(route('admin.cashbook.warehouse-credit-notes.store'), [
                'warehouse_id' => $this->warehouseA->id,
                'business_date' => '2026-11-26',
                'reason' => WarehouseCreditReason::Other->value,
                'save_action' => 'draft',
                'items' => [
                    ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'in', 'unit' => 'kg', 'quantity' => 1, 'valuation_rate' => 10],
                ],
            ]);
        $draft2 = WarehouseCreditNote::latest('id')->firstOrFail();
        $this->actingAs($this->accountsUser)
            ->delete(route('admin.cashbook.warehouse-credit-notes.destroy', ['creditNote' => $draft2->public_uuid]))
            ->assertRedirect(route('admin.cashbook.warehouse-credit-notes.index'));
        $this->assertSoftDeleted($draft2);
    }

    public function test_failed_direct_save_and_finalize_leaves_no_orphan_draft_and_void_workflow_works(): void
    {
        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 5.0, 40.0, '2026-11-21');

        // Direct store with save_action=finalize exceeding stock rolls back completely (no draft created)
        $failRes = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.warehouse-credit-notes.store'), [
                'warehouse_id' => $this->warehouseA->id,
                'business_date' => '2026-11-26',
                'reason' => WarehouseCreditReason::StockReturn->value,
                'save_action' => 'finalize',
                'items' => [
                    ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 10, 'valuation_rate' => 40],
                ],
            ]);

        $failRes->assertSessionHasErrors('items');
        $this->assertSame(0, WarehouseCreditNote::count());
        $this->assertSame(0, JournalEntry::count());

        // Valid finalize then void workflow
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.warehouse-credit-notes.store'), [
                'warehouse_id' => $this->warehouseA->id,
                'business_date' => '2026-11-26',
                'reason' => WarehouseCreditReason::StockReturn->value,
                'save_action' => 'finalize',
                'items' => [
                    ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 3, 'valuation_rate' => 40],
                ],
            ]);

        $note = WarehouseCreditNote::firstOrFail();

        // Missing void_reason rejected
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.warehouse-credit-notes.void', ['creditNote' => $note->public_uuid]), [
                'void_reason' => '',
            ])
            ->assertSessionHasErrors('void_reason');

        // Valid void succeeds
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.warehouse-credit-notes.void', ['creditNote' => $note->public_uuid]), [
                'void_reason' => 'Incorrect quantity entered',
            ])
            ->assertRedirect(route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $note->public_uuid]));

        $this->assertSame(WarehouseCreditNoteStatus::Voided, $note->fresh()->status);
        $this->assertEqualsWithDelta(5.0, app(StockLedgerService::class)->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA), 0.001);
    }

    public function test_primary_stock_direction_defaults_to_out_maps_damage_and_preserves_mixed_drafts(): void
    {
        $this->seedSortedBatch($this->warehouseA->id, ProductGrade::GradeA, 20.0, 45.0, '2026-11-21');

        // 1. Create page defaults to Stock OUT and renders package-plus / package-minus icons
        $this->actingAs($this->accountsUser)
            ->get(route('admin.cashbook.warehouse-credit-notes.create'))
            ->assertOk()
            ->assertSee('package-plus')
            ->assertSee('package-minus')
            ->assertSee("movementDirection: 'out'", false);

        // 2. Incompatible direction + reason rejected server-side
        $this->actingAs($this->accountsUser)
            ->post(route('admin.cashbook.warehouse-credit-notes.store'), [
                'warehouse_id' => $this->warehouseA->id,
                'business_date' => '2026-11-26',
                'movement_direction' => 'in',
                'reason' => WarehouseCreditReason::DamageWastage->value,
                'save_action' => 'draft',
                'items' => [
                    ['product_id' => $this->product->id, 'grade' => 'A', 'unit' => 'kg', 'quantity' => 2, 'valuation_rate' => 45],
                ],
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->accountsUser)
            ->post(route('admin.cashbook.warehouse-credit-notes.store'), [
                'warehouse_id' => $this->warehouseA->id,
                'business_date' => '2026-11-26',
                'movement_direction' => 'out',
                'reason' => WarehouseCreditReason::ExcessFound->value,
                'save_action' => 'draft',
                'items' => [
                    ['product_id' => $this->product->id, 'grade' => 'A', 'unit' => 'kg', 'quantity' => 2, 'valuation_rate' => 45],
                ],
            ])
            ->assertSessionHasErrors('reason');

        // 3. Stock OUT + Damage/Wastage maps to 'damage' action & creates WastageEntry at FIFO cost
        $this->actingAs($this->accountsUser)
            ->post(route('admin.cashbook.warehouse-credit-notes.store'), [
                'warehouse_id' => $this->warehouseA->id,
                'business_date' => '2026-11-26',
                'movement_direction' => 'out',
                'reason' => WarehouseCreditReason::DamageWastage->value,
                'save_action' => 'finalize',
                'items' => [
                    ['product_id' => $this->product->id, 'grade' => 'A', 'unit' => 'kg', 'quantity' => 4, 'valuation_rate' => 999],
                ],
            ])
            ->assertRedirect();

        $damageNote = WarehouseCreditNote::latest('id')->firstOrFail();
        $this->assertSame(WarehouseCreditInventoryAction::Damage, $damageNote->items->first()->inventory_action);
        $this->assertNotNull($damageNote->items->first()->wastage_entry_id);
        $this->assertEqualsWithDelta(180.00, (float) $damageNote->total_amount, 0.01);

        // 4. Existing mixed-action draft preserves 'mixed' mode without silently converting actions
        $service = app(WarehouseCreditNoteService::class);
        $mixedDraft = $service->createDraft([
            'warehouse_id' => $this->warehouseA->id,
            'business_date' => '2026-11-26',
            'reason' => WarehouseCreditReason::StockReturn->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'in', 'unit' => 'kg', 'quantity' => 2, 'valuation_rate' => 30],
                ['product_id' => $this->product->id, 'grade' => 'A', 'inventory_action' => 'out', 'unit' => 'kg', 'quantity' => 1, 'valuation_rate' => 45],
            ],
        ], $this->admin);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.warehouse-credit-notes.edit', ['creditNote' => $mixedDraft->public_uuid]))
            ->assertOk()
            ->assertSee("movementDirection: 'mixed'", false)
            ->assertSee('isMixedDraft: true', false);
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
