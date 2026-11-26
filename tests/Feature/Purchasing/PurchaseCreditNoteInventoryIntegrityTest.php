<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Enums\Purchasing\PurchaseCreditStatus;
use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseCreditNote;
use App\Models\PurchaseCreditNoteItem;
use App\Models\PurchaseInvoice;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementAllocation;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\PurchaseCreditNoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseCreditNoteInventoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseCreditNoteService $pcnService;

    private StockLedgerService $stockLedger;

    private User $admin;

    private Supplier $supplier;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Product $product;

    private Product $secondProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1200'], ['name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1400'], ['name' => 'Vendor Advances', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4200'], ['name' => 'Vendor Settlement Discounts', 'type' => 'revenue', 'is_active' => true]);
        Account::firstOrCreate(['code' => '5200'], ['name' => 'Wastage Expense', 'type' => 'expense', 'is_active' => true]);

        $this->pcnService = app(PurchaseCreditNoteService::class);
        $this->stockLedger = app(StockLedgerService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->supplier = Supplier::factory()->create([
            'name' => 'Inventory Integrity Vendor',
            'credit_approved' => true,
        ]);

        $this->warehouseA = Warehouse::factory()->create(['name' => 'Main Warehouse A']);
        $this->warehouseB = Warehouse::factory()->create(['name' => 'Secondary Warehouse B']);

        $this->product = Product::factory()->create(['unit' => 'kg']);
        $this->secondProduct = Product::factory()->create(['unit' => 'kg']);
        $this->supplier->products()->attach([$this->product->id, $this->secondProduct->id]);

        CompanyAccount::create([
            'name' => 'Main Cash Till',
            'account_type' => 'cash',
            'opening_balance' => 50000.00,
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);
    }

    private function createSortedStockBatch(
        Warehouse $warehouse,
        Product $product,
        ProductGrade $grade,
        float $quantity,
        float $costPerKg,
        string $receivedAt = '2026-11-20',
    ): StockBatch {
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'purchase_grade' => $grade->value,
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
            'sorted_at' => now(),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'received_at' => $receivedAt,
            'total_kg' => $quantity,
            'cost_per_kg' => $costPerKg,
            'reference' => 'TEST-BATCH-'.uniqid(),
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'grade' => $grade->value,
            'type' => StockMovementType::In->value,
            'quantity' => $quantity,
            'cost_per_unit' => $costPerKg,
            'created_by' => $this->admin->id,
            'notes' => 'Test initial stock',
        ]);

        return $batch;
    }

    public function test_multiple_stock_in_lines_and_repeated_notes_generate_unique_batch_references(): void
    {
        $note1 = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::In->value,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 15,
                    'unit_price' => 100,
                ],
                [
                    'product_id' => $this->secondProduct->id,
                    'grade' => 'A',
                    'quantity' => 25,
                    'unit_price' => 80,
                ],
            ],
        ], $this->admin);

        $note2 = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::In->value,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 10,
                    'unit_price' => 110,
                ],
            ],
        ], $this->admin);

        $batches = StockBatch::query()->where('reference', 'like', 'PCN-IN-%')->get();
        $this->assertCount(3, $batches);
        $this->assertCount(3, $batches->pluck('reference')->unique());
        foreach ($batches as $batch) {
            $this->assertMatchesRegularExpression('/^PCN-IN-\d{8}-[A-Z0-9]{6}$/', $batch->reference);
        }

        $this->assertCount(2, $note1->itemMovements()->get());
        $this->assertCount(1, $note2->itemMovements()->get());
    }

    public function test_stock_out_enforces_exact_grade_warehouse_isolation_and_fifo_costing(): void
    {
        $batchOld = $this->createSortedStockBatch($this->warehouseA, $this->product, ProductGrade::GradeA, 40, 100.0, '2026-11-10');
        $batchNew = $this->createSortedStockBatch($this->warehouseA, $this->product, ProductGrade::GradeA, 60, 150.0, '2026-11-15');
        $this->createSortedStockBatch($this->warehouseA, $this->product, ProductGrade::GradeB, 500, 90.0, '2026-11-10');
        $this->createSortedStockBatch($this->warehouseB, $this->product, ProductGrade::GradeA, 500, 95.0, '2026-11-10');

        $note = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::Return->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::Out->value,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 55,
                    'unit_price' => 120,
                ],
            ],
        ], $this->admin);

        $itemMovements = $note->itemMovements()->orderBy('id')->get();
        $this->assertCount(2, $itemMovements);
        $this->assertEquals($batchOld->id, $itemMovements[0]->stock_batch_id);
        $this->assertEquals(40.0, (float) $itemMovements[0]->quantity);
        $this->assertEquals(100.0, (float) $itemMovements[0]->cost_per_unit);

        $this->assertEquals($batchNew->id, $itemMovements[1]->stock_batch_id);
        $this->assertEquals(15.0, (float) $itemMovements[1]->quantity);
        $this->assertEquals(150.0, (float) $itemMovements[1]->cost_per_unit);

        $this->assertEquals(45.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA));
        $this->assertEquals(500.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeB));
        $this->assertEquals(500.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseB->id, ProductGrade::GradeA));

        // Requesting more Grade A in Warehouse A than 45kg must fail without touching Grade B or Warehouse B
        $this->expectException(ValidationException::class);
        $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::Return->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::Out->value,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 50,
                    'unit_price' => 120,
                ],
            ],
        ], $this->admin);
    }

    public function test_unit_conversion_computes_base_qty_preserves_line_total_and_rejects_invalid_units(): void
    {
        ProductUnit::create([
            'product_id' => $this->product->id,
            'unit' => 'box',
            'label' => 'Box (10kg)',
            'conversion_to_base' => 10.0,
            'is_default' => false,
        ]);

        // Availability check converts 3 boxes -> 30 kg
        $this->createSortedStockBatch($this->warehouseA, $this->product, ProductGrade::GradeA, 25.0, 100.0);
        $availability = $this->pcnService->checkStockAvailability(
            $this->warehouseA->id,
            [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 3, 'unit' => 'box']],
            PurchaseCreditInventoryAction::Out
        );
        $this->assertTrue($availability['has_shortage']);
        $this->assertEquals(30.0, $availability['shortages'][0]['requested_qty']);

        // Stock IN with 2 boxes @ 1000 per box -> line_total = 2000, base_qty = 20 kg, base_unit_cost = 100/kg
        $note = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::In->value,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 2,
                    'unit' => 'box',
                    'unit_price' => 1000,
                ],
            ],
        ], $this->admin);

        $item = $note->items()->firstOrFail();
        $this->assertEquals('box', $item->unit);
        $this->assertEquals(10.0, (float) $item->conversion_to_base);
        $this->assertEquals(20.0, (float) $item->base_qty);
        $this->assertEquals(2000.0, (float) $item->line_total);

        $movement = $item->movements()->firstOrFail()->stockMovement;
        $this->assertEquals(20.0, (float) $movement->quantity);
        $this->assertEquals(100.0, (float) $movement->cost_per_unit);

        $this->expectException(ValidationException::class);
        $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::In->value,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 2,
                    'unit' => 'invalid_crate',
                    'unit_price' => 1000,
                ],
            ],
        ], $this->admin);
    }

    public function test_financial_only_and_damage_workflows_and_exact_batch_reversal(): void
    {
        // Financial-only note creates zero stock movements
        $financialNote = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => null,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 10,
                    'unit_price' => 50,
                ],
            ],
        ], $this->admin);

        $this->assertCount(0, $financialNote->itemMovements()->get());

        // Damage note deducts stock once, creates one wastage entry, and restores on void
        $batch = $this->createSortedStockBatch($this->warehouseA, $this->product, ProductGrade::GradeA, 50, 100.0);
        $damageNote = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::DamageWastage->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::Damage->value,
            'deduct_stock_for_damage' => true,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 12,
                    'unit_price' => 100,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(1, WastageEntry::count());
        $this->assertEquals(38.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA));

        $voided = $this->pcnService->void($damageNote, 'Reversed damage', $this->admin);
        $this->assertEquals(PurchaseCreditStatus::Voided, $voided->status);
        $this->assertEquals(0, WastageEntry::count());
        $this->assertEquals(50.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA));

        $itemMovement = $damageNote->itemMovements()->firstOrFail();
        $this->assertNotNull($itemMovement->reversal_stock_movement_id);
        $this->assertNotNull($itemMovement->reversed_at);
        $this->assertEquals($batch->id, $itemMovement->stock_batch_id);
    }

    public function test_unsafe_stock_in_reversal_is_rejected_and_duplicate_reversal_is_blocked(): void
    {
        $stockInNote = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::In->value,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 20,
                    'unit_price' => 100,
                ],
            ],
        ], $this->admin);

        $batchId = $stockInNote->itemMovements()->firstOrFail()->stock_batch_id;

        // Consume 5 kg from the Stock IN batch
        StockMovement::create([
            'product_id' => $this->product->id,
            'batch_id' => $batchId,
            'warehouse_id' => $this->warehouseA->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 5,
            'cost_per_unit' => 100,
            'created_by' => $this->admin->id,
            'notes' => 'Consumed part of PCN IN batch',
        ]);

        try {
            $this->pcnService->void($stockInNote, 'Attempt unsafe void', $this->admin);
            $this->fail('Expected ValidationException when voiding partially consumed Stock IN.');
        } catch (ValidationException) {
            $stockInNote->refresh();
            $this->assertEquals(PurchaseCreditStatus::Active, $stockInNote->status);
        }

        // Duplicate reversal test on a clean Stock OUT note
        $this->createSortedStockBatch($this->warehouseA, $this->secondProduct, ProductGrade::GradeA, 30, 80.0);
        $outNote = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::Return->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::Out->value,
            'items' => [
                [
                    'product_id' => $this->secondProduct->id,
                    'grade' => 'A',
                    'quantity' => 10,
                    'unit_price' => 80,
                ],
            ],
        ], $this->admin);

        $this->pcnService->void($outNote, 'First void', $this->admin);

        $this->expectException(ValidationException::class);
        $this->stockLedger->reversePurchaseCreditNoteMovements($outNote->fresh(), $this->admin->id, 'Duplicate reversal');
    }

    public function test_historical_compatibility_reversal_works_without_item_movements(): void
    {
        $batch = $this->createSortedStockBatch($this->warehouseA, $this->product, ProductGrade::GradeA, 50, 100.0);

        $note = PurchaseCreditNote::create([
            'credit_note_number' => 'PCN-20261126-HIST01',
            'supplier_id' => $this->supplier->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::Return->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::Out->value,
            'status' => PurchaseCreditStatus::Active->value,
            'warehouse_id' => $this->warehouseA->id,
            'total_amount' => 1000.00,
            'applied_to_invoice_amount' => 0.00,
            'created_by' => $this->admin->id,
        ]);

        $movement = StockMovement::create([
            'product_id' => $this->product->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouseA->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 10,
            'cost_per_unit' => 100,
            'created_by' => $this->admin->id,
            'notes' => "Purchase Credit Note — PCN: {$note->credit_note_number}; NoteId: {$note->id};",
        ]);

        PurchaseCreditNoteItem::create([
            'purchase_credit_note_id' => $note->id,
            'product_id' => $this->product->id,
            'grade' => 'A',
            'quantity' => 10,
            'unit_price' => 100,
            'line_total' => 1000,
            'inventory_action' => PurchaseCreditInventoryAction::Out->value,
            'stock_movement_id' => $movement->id,
        ]);

        $this->assertEquals(40.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA));

        $this->stockLedger->reversePurchaseCreditNoteMovements($note, $this->admin->id, 'Historical void');

        $this->assertEquals(50.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA));
    }

    public function test_rollback_on_mid_transaction_stock_failure_leaves_no_partial_records(): void
    {
        $this->createSortedStockBatch($this->warehouseA, $this->product, ProductGrade::GradeA, 50, 100.0);
        // Second product has 0 stock

        $initialMovements = StockMovement::count();
        $initialNotes = PurchaseCreditNote::count();

        try {
            $this->pcnService->create([
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouseA->id,
                'credit_date' => '2026-11-26',
                'reason' => PurchaseCreditReason::Return->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::Out->value,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 10,
                        'unit_price' => 100,
                    ],
                    [
                        'product_id' => $this->secondProduct->id,
                        'grade' => 'A',
                        'quantity' => 5,
                        'unit_price' => 80,
                    ],
                ],
            ], $this->admin);
            $this->fail('Expected ValidationException on second line shortage.');
        } catch (ValidationException) {
            $this->assertEquals($initialMovements, StockMovement::count());
            $this->assertEquals($initialNotes, PurchaseCreditNote::count());
            $this->assertEquals(50.0, $this->stockLedger->availableStockForProduct($this->product->id, $this->warehouseA->id, ProductGrade::GradeA));
        }
    }

    public function test_legacy_cash_purchase_invoice_settlement_outstanding_does_not_double_count(): void
    {
        $grn = GoodsReceived::factory()->create();

        // Legacy cash invoice with paid_amount=400 and no VendorSettlementAllocation rows
        $legacyInvoice = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'goods_received_id' => $grn->id,
            'amount' => 1000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 400.00,
            'status' => 'approved',
        ]);

        $this->assertEquals(600.0, $legacyInvoice->settlementOutstanding());

        // Invoice with active VendorSettlementAllocation must NOT subtract paid_amount again
        $allocatedInvoice = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'goods_received_id' => $grn->id,
            'amount' => 1000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 400.00,
            'status' => 'approved',
        ]);

        $settlement = VendorSettlement::create([
            'supplier_id' => $this->supplier->id,
            'payment_date' => '2026-11-26',
            'payment_method' => 'cash',
            'actual_payment_amount' => 400.00,
            'created_by' => $this->admin->id,
        ]);

        VendorSettlementAllocation::create([
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $allocatedInvoice->id,
            'cash_paid' => 400.00,
            'advance_applied' => 0.00,
            'discount_given' => 0.00,
            'total_settled' => 400.00,
            'is_reversed' => false,
        ]);

        $this->assertEquals(600.0, $allocatedInvoice->fresh()->settlementOutstanding());
    }
}
