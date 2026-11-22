<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Enums\Purchasing\PurchaseCreditStatus;
use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaseCreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseCreditNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Warehouse $warehouse;

    private Product $product;

    private PurchaseCreditNoteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1200'], ['name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1400'], ['name' => 'Vendor Advances', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4200'], ['name' => 'Purchase Discounts', 'type' => 'revenue', 'is_active' => true]);
        Account::firstOrCreate(['code' => '5100'], ['name' => 'Cost of Goods Sold', 'type' => 'expense', 'is_active' => true]);
        Account::firstOrCreate(['code' => '5200'], ['name' => 'Wastage Expense', 'type' => 'expense', 'is_active' => true]);

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'super_admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->supplier = Supplier::factory()->create([
            'name' => 'Fresh Produce Supplier Ltd',
            'credit_approved' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MW01',
            'address' => 'Industrial Area',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Tomato Local',
            'unit' => 'kg',
        ]);
        $this->supplier->products()->attach($this->product->id);

        $this->service = app(PurchaseCreditNoteService::class);
    }

    public function test_can_create_credit_note_with_vendor_credit_and_stock_return(): void
    {
        // 1. Setup available stock
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
            'sorted_at' => now(),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 100.0,
            'cost_per_kg' => 20.00,
            'reference' => 'BATCH-TEST-01',
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 20.00,
            'created_by' => $this->admin->id,
            'notes' => 'Initial stock receipt',
        ]);

        // 2. Setup an invoice with outstanding balance of ₹1500
        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create([
            'amount' => 1500.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'invoice_number' => 'PINV-TEST-001',
            'status' => 'pending',
            'payment_method' => 'Credit',
        ]);

        $this->assertEquals(1500.00, $invoice->remainingBalance());

        // 3. Create Purchase Credit Note for 25 kg @ ₹20 = ₹500
        $creditNote = $this->service->create([
            'supplier_id' => $this->supplier->id,
            'purchase_invoice_id' => $invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'credit_date' => now()->toDateString(),
            'reason' => PurchaseCreditReason::Return,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit,
            'inventory_action' => PurchaseCreditInventoryAction::Out,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 25.0,
                    'unit_price' => 20.00,
                ],
            ],
        ], $this->admin);

        $this->assertNotNull($creditNote->id);
        $this->assertEquals(500.00, (float) $creditNote->total_amount);
        $this->assertEquals(500.00, (float) $creditNote->applied_to_invoice_amount);
        $this->assertNull($creditNote->vendor_advance_id);
        $this->assertEquals(PurchaseCreditStatus::Active, $creditNote->status);

        // Verify remaining balance on invoice reduced by credit note
        $this->assertEquals(1000.00, $invoice->fresh()->remainingBalance());

        // Verify stock movement deduction
        $outMovement = StockMovement::query()
            ->where('product_id', $this->product->id)
            ->where('type', StockMovementType::Out->value)
            ->where('notes', 'like', "%PCN: {$creditNote->credit_note_number};%")
            ->first();

        $this->assertNotNull($outMovement);
        $this->assertEquals(25.0, (float) $outMovement->quantity);

        // Verify balanced journal entry
        $this->assertNotNull($creditNote->journal_entry_id);
        $journal = $creditNote->journalEntry;
        $this->assertNotNull($journal);

        $debits = (float) $journal->transactions()->where('type', 'debit')->sum('amount');
        $credits = (float) $journal->transactions()->where('type', 'credit')->sum('amount');
        $this->assertEquals(500.00, $debits);
        $this->assertEquals(500.00, $credits);
    }

    public function test_creates_vendor_advance_when_credit_exceeds_invoice_balance(): void
    {
        // Invoice with ₹200 outstanding balance
        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create([
            'amount' => 200.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'invoice_number' => 'PINV-TEST-002',
            'status' => 'pending',
            'payment_method' => 'Credit',
        ]);

        // Credit note for ₹600 (without stock movement, e.g. Price Adjustment / Commercial Rebate)
        $creditNote = $this->service->create([
            'supplier_id' => $this->supplier->id,
            'purchase_invoice_id' => $invoice->id,
            'credit_date' => now()->toDateString(),
            'reason' => PurchaseCreditReason::PriceAdjustment,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit,
            'inventory_action' => PurchaseCreditInventoryAction::None,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 10.0,
                    'unit_price' => 60.00,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(600.00, (float) $creditNote->total_amount);
        $this->assertEquals(200.00, (float) $creditNote->applied_to_invoice_amount);
        $this->assertNotNull($creditNote->vendor_advance_id);

        $advance = $creditNote->vendorAdvance;
        $this->assertNotNull($advance);
        $this->assertEquals(400.00, (float) $advance->amount_original);
        $this->assertEquals(400.00, (float) $advance->amount_remaining);
        $this->assertEquals('open', $advance->status);

        // Verify journal entry balances across 2100 (Payables: 200), 1400 (Advance: 400), and 4200 (Rebate: 600)
        $journal = $creditNote->journalEntry;
        $debits = (float) $journal->transactions()->where('type', 'debit')->sum('amount');
        $credits = (float) $journal->transactions()->where('type', 'credit')->sum('amount');
        $this->assertEquals(600.00, $debits);
        $this->assertEquals(600.00, $credits);
    }

    public function test_can_create_credit_note_with_money_in_refund(): void
    {
        $companyAccount = CompanyAccount::create([
            'name' => 'Main Bank Account',
            'account_type' => 'bank',
            'current_balance' => 5000.00,
            'enabled' => true,
        ]);

        $creditNote = $this->service->create([
            'supplier_id' => $this->supplier->id,
            'company_account_id' => $companyAccount->id,
            'credit_date' => now()->toDateString(),
            'reason' => PurchaseCreditReason::Shortage,
            'money_effect' => PurchaseCreditMoneyEffect::MoneyIn,
            'inventory_action' => PurchaseCreditInventoryAction::None,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 5.0,
                    'unit_price' => 100.00,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(500.00, (float) $creditNote->total_amount);

        // Check company account balance incremented
        $this->assertEquals(5500.00, (float) $companyAccount->fresh()->current_balance);

        // Check journal balance
        $journal = $creditNote->journalEntry;
        $debits = (float) $journal->transactions()->where('type', 'debit')->sum('amount');
        $credits = (float) $journal->transactions()->where('type', 'credit')->sum('amount');
        $this->assertEquals(500.00, $debits);
        $this->assertEquals(500.00, $credits);
    }

    public function test_can_void_credit_note_and_reverse_all_effects(): void
    {
        // 1. Setup stock
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
            'sorted_at' => now(),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => 50.0,
            'cost_per_kg' => 30.00,
            'reference' => 'BATCH-TEST-03',
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 50.0,
            'cost_per_unit' => 30.00,
            'created_by' => $this->admin->id,
            'notes' => 'Batch setup',
        ]);

        // 2. Create Damage Wastage credit note (stock deducted)
        $creditNote = $this->service->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'credit_date' => now()->toDateString(),
            'reason' => PurchaseCreditReason::DamageWastage,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit,
            'inventory_action' => PurchaseCreditInventoryAction::Damage,
            'deduct_stock_for_damage' => true,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 10.0,
                    'unit_price' => 30.00,
                    'wastage_reason' => WastageReason::TransitDamage,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(PurchaseCreditStatus::Active, $creditNote->status);
        $this->assertDatabaseHas('wastage_entries', [
            'product_id' => $this->product->id,
            'quantity' => 10.0,
        ]);

        // 3. Void the credit note
        $voidedNote = $this->service->void($creditNote, 'Cancelled due to vendor dispute', $this->admin);

        $this->assertEquals(PurchaseCreditStatus::Voided, $voidedNote->status);
        $this->assertEquals('Cancelled due to vendor dispute', $voidedNote->void_reason);
        $this->assertEquals($this->admin->id, $voidedNote->voided_by);

        // Verify compensating stock movement added stock back
        $reversalMovement = StockMovement::query()
            ->where('product_id', $this->product->id)
            ->where('type', StockMovementType::In->value)
            ->where('notes', 'like', '%Void Reversal%')
            ->first();

        $this->assertNotNull($reversalMovement);
        $this->assertEquals(10.0, (float) $reversalMovement->quantity);

        // Verify wastage entry is soft-deleted
        $item = $voidedNote->items->first();
        $this->assertSoftDeleted('wastage_entries', ['id' => $item->wastage_entry_id]);
    }

    public function test_blocks_voiding_if_vendor_advance_was_partially_utilized(): void
    {
        $creditNote = $this->service->create([
            'supplier_id' => $this->supplier->id,
            'credit_date' => now()->toDateString(),
            'reason' => PurchaseCreditReason::Other,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit,
            'inventory_action' => PurchaseCreditInventoryAction::None,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'quantity' => 10.0,
                    'unit_price' => 50.00,
                ],
            ],
        ], $this->admin);

        $advance = $creditNote->vendorAdvance;
        $this->assertNotNull($advance);

        // Simulate utilization in settlement
        $advance->update(['amount_remaining' => 200.00]);

        $this->expectException(ValidationException::class);
        $this->service->void($creditNote, 'Attempting void after advance usage', $this->admin);
    }
}
