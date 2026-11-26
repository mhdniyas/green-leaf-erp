<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\GoodsReceived;
use App\Models\JournalTransaction;
use App\Models\Product;
use App\Models\PurchaseCreditNote;
use App\Models\PurchaseInvoice;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementCreditAllocation;
use App\Models\Warehouse;
use App\Services\Finance\VendorSettlementCorrectionService;
use App\Services\Finance\VendorSettlementService;
use App\Services\Purchasing\PurchaseCreditNoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseCreditNoteV2EngineTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseCreditNoteService $pcnService;

    private VendorSettlementService $settlementService;

    private VendorSettlementCorrectionService $correctionService;

    private User $admin;

    private Supplier $supplier;

    private Product $product;

    private Warehouse $warehouse;

    private CompanyAccount $cashAccount;

    private CompanyAccount $bankAccount;

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
        $this->settlementService = app(VendorSettlementService::class);
        $this->correctionService = app(VendorSettlementCorrectionService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create(['unit' => 'kg']);
        $this->supplier->products()->attach($this->product->id);

        $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
        $this->cashAccount = CompanyAccount::create([
            'name' => 'Main Cash Till',
            'account_type' => 'cash',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);
        $this->bankAccount = CompanyAccount::create([
            'name' => 'HDFC Bank',
            'account_type' => 'bank',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);

        config(['greenleaf.purchase_credit_notes_v2_enabled' => true]);
    }

    private function createInvoice(float $amount, ?Supplier $supplier = null): PurchaseInvoice
    {
        $sup = $supplier ?? $this->supplier;
        $grn = GoodsReceived::factory()->create();

        return PurchaseInvoice::factory()->create([
            'supplier_id' => $sup->id,
            'goods_received_id' => $grn->id,
            'amount' => $amount,
            'discount_amount' => 0,
            'paid_amount' => 0,
            'status' => 'approved',
            'payment_method' => 'Credit',
            'payment_status' => 'unpaid',
            'payment_paid_by' => 'vendor_credit',
        ]);
    }

    private function createV2CreditNote(float $qty, float $rate, ?PurchaseInvoice $refInvoice = null, array $overrides = []): PurchaseCreditNote
    {
        return $this->pcnService->create(array_merge([
            'supplier_id' => $this->supplier->id,
            'purchase_invoice_id' => $refInvoice?->id,
            'vendor_credit_note_ref' => 'SUP-CN-001',
            'credit_date' => now()->toDateString(),
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [
                ['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => $qty, 'unit_price' => $rate],
            ],
        ], $overrides), $this->admin);
    }

    public function test_required_v2_scenario_post_partial_allocate_and_reverse(): void
    {
        $inv1 = $this->createInvoice(10000.00);
        $inv2 = $this->createInvoice(15000.00);
        $inv3 = $this->createInvoice(5000.00);

        $pcn = $this->createV2CreditNote(100.0, 30.0, $inv1); // ₹3,000 referencing INV-001

        $this->assertSame('v2', $pcn->accounting_version);
        $this->assertEquals(0.0, (float) $pcn->applied_to_invoice_amount);
        $this->assertEquals(0.0, (float) $pcn->utilized_amount);
        $this->assertEquals(3000.0, $pcn->available_credit);
        $this->assertSame('open', $pcn->utilization_status);
        $this->assertNull($pcn->vendor_advance_id);
        $this->assertSame('SUP-CN-001', $pcn->vendor_credit_note_ref);
        $this->assertSame(0, VendorAdvance::where('supplier_id', $this->supplier->id)->count());

        // Invoice balances remain unchanged after posting PCN
        $inv1->refresh();
        $this->assertEquals(10000.0, $inv1->remainingBalance());
        $this->assertSame('unpaid', $inv1->payment_status);

        // Journal: Debit 1400 ₹3,000 / Credit 4200 ₹3,000
        $txns = JournalTransaction::with('account')->where('journal_entry_id', $pcn->journal_entry_id)->get();
        $this->assertEquals(3000.0, (float) $txns->firstWhere('account.code', '1400')?->amount);
        $this->assertSame('debit', $txns->firstWhere('account.code', '1400')?->type);
        $this->assertEquals(3000.0, (float) $txns->firstWhere('account.code', '4200')?->amount);
        $this->assertSame('credit', $txns->firstWhere('account.code', '4200')?->type);

        // Canonical summary immediately after posting
        $summary = $this->pcnService->getVendorFinancialSummary($this->supplier);
        $this->assertEquals(30000.0, $summary['outstanding_balance']);
        $this->assertEquals(3000.0, $summary['available_v2_credit_notes']);
        $this->assertEquals(3000.0, $summary['available_purchase_credits']);
        $this->assertEquals(0.0, $summary['actual_cash_vendor_advances']);
        $this->assertEquals(27000.0, $summary['net_vendor_payable']);

        // Apply ₹2,000 credit to INV-001 (credit-only settlement)
        $settlement = $this->settlementService->create($this->supplier, [
            'actual_payment_amount' => 0.0,
            'settlement_discount_amount' => 0.0,
            'vendor_advance_used_amount' => 0.0,
            'purchase_credit_used_amount' => 2000.0,
            'credit_notes' => [
                ['purchase_credit_note_id' => $pcn->id, 'purchase_invoice_id' => $inv1->id, 'amount' => 2000.0],
            ],
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['purchase_invoice_id' => $inv1->id, 'cash_allocated' => 0.0, 'advance_allocated' => 0.0, 'credit_allocated' => 2000.0, 'discount_allocated' => 0.0],
            ],
        ], $this->admin->id);

        $this->assertTrue($settlement->is_finalized);
        $this->assertEquals(2000.0, (float) $settlement->purchase_credit_used_amount);

        // Settlement journal: Debit 2100 ₹2,000 / Credit 1400 ₹2,000 (no duplicate 4200 recognition)
        $sTxns = JournalTransaction::with('account')->where('journal_entry_id', $settlement->journal_entry_id)->get();
        $this->assertCount(2, $sTxns);
        $this->assertEquals(2000.0, (float) $sTxns->firstWhere('account.code', '2100')?->amount);
        $this->assertSame('debit', $sTxns->firstWhere('account.code', '2100')?->type);
        $this->assertEquals(2000.0, (float) $sTxns->firstWhere('account.code', '1400')?->amount);
        $this->assertSame('credit', $sTxns->firstWhere('account.code', '1400')?->type);

        $pcn->refresh();
        $inv1->refresh();
        $this->assertEquals(2000.0, (float) $pcn->utilized_amount);
        $this->assertEquals(1000.0, $pcn->available_credit);
        $this->assertSame('partially_utilized', $pcn->utilization_status);
        $this->assertEquals(8000.0, $inv1->remainingBalance());
        $this->assertSame('partial', $inv1->payment_status);

        // Voiding utilized credit note must be blocked
        try {
            $this->pcnService->void($pcn, 'Try void while utilized', $this->admin);
            $this->fail('Expected ValidationException when voiding utilized V2 credit note');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // Reverse settlement and verify full restoration
        $this->correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Test reversal');

        $pcn->refresh();
        $inv1->refresh();
        $this->assertEquals(0.0, (float) $pcn->utilized_amount);
        $this->assertEquals(3000.0, $pcn->available_credit);
        $this->assertSame('open', $pcn->utilization_status);
        $this->assertEquals(10000.0, $inv1->remainingBalance());
        $this->assertSame('unpaid', $inv1->payment_status);

        // Original allocation records preserved & marked reversed
        $this->assertDatabaseHas('vendor_settlement_credit_allocations', [
            'vendor_settlement_id' => $settlement->id,
            'purchase_credit_note_id' => $pcn->id,
            'is_reversed' => true,
        ]);
        $this->assertDatabaseHas('vendor_settlement_allocations', [
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $inv1->id,
            'is_reversed' => true,
        ]);

        // Repeated reversal must be blocked
        try {
            $this->correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Second reversal');
            $this->fail('Expected ValidationException on repeated reversal');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // Now voiding PCN succeeds
        $voided = $this->pcnService->void($pcn, 'Now unutilized', $this->admin);
        $this->assertTrue($voided->isVoided());
        $this->assertSame('voided', $voided->utilization_status);
    }

    public function test_multiple_credit_notes_across_multiple_invoices_and_combined_cash_advance_settlement(): void
    {
        $inv1 = $this->createInvoice(5000.00);
        $inv2 = $this->createInvoice(4000.00);

        $pcn1 = $this->createV2CreditNote(100.0, 20.0); // ₹2,000
        $pcn2 = $this->createV2CreditNote(50.0, 30.0);  // ₹1,500

        $cashAdvance = VendorAdvance::query()->create([
            'supplier_id' => $this->supplier->id,
            'amount_original' => 1000.00,
            'amount_remaining' => 1000.00,
            'business_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        // Settle INV-1 (5,000) & INV-2 (4,000) using:
        // PCN1 (2,000) + PCN2 (1,500) + Cash Advance (1,000) + Bank Cash (4,000) + Discount (500) = 9,000
        $settlement = $this->settlementService->create($this->supplier, [
            'actual_payment_amount' => 4000.0,
            'settlement_discount_amount' => 500.0,
            'vendor_advance_used_amount' => 1000.0,
            'purchase_credit_used_amount' => 3500.0,
            'company_account_id' => $this->bankAccount->id,
            'payment_method' => 'Bank',
            'payment_date' => now()->toDateString(),
            'credit_notes' => [
                ['purchase_credit_note_id' => $pcn1->id, 'amount' => 2000.0],
                ['purchase_credit_note_id' => $pcn2->id, 'amount' => 1500.0],
            ],
            'allocations' => [
                ['purchase_invoice_id' => $inv1->id, 'cash_allocated' => 2500.0, 'advance_allocated' => 0.0, 'credit_allocated' => 2500.0, 'discount_allocated' => 0.0],
                ['purchase_invoice_id' => $inv2->id, 'cash_allocated' => 1500.0, 'advance_allocated' => 1000.0, 'credit_allocated' => 1000.0, 'discount_allocated' => 500.0],
            ],
        ], $this->admin->id);

        $inv1->refresh();
        $inv2->refresh();
        $pcn1->refresh();
        $pcn2->refresh();
        $cashAdvance->refresh();

        $this->assertEquals(0.0, $inv1->remainingBalance());
        $this->assertEquals(0.0, $inv2->remainingBalance());
        $this->assertSame('paid', $inv1->payment_status);
        $this->assertSame('paid', $inv2->payment_status);
        $this->assertSame('fully_utilized', $pcn1->utilization_status);
        $this->assertSame('fully_utilized', $pcn2->utilization_status);
        $this->assertEquals(0.0, (float) $cashAdvance->amount_remaining);

        // Verify exact source-to-invoice rows
        $this->assertEquals(3500.0, (float) VendorSettlementCreditAllocation::where('vendor_settlement_id', $settlement->id)->sum('amount'));
    }

    public function test_legacy_v1_compatibility_and_no_double_counting_in_summary(): void
    {
        config(['greenleaf.purchase_credit_notes_v2_enabled' => false]);

        $inv = $this->createInvoice(1000.00);

        // V1 PCN for 1,500 linked to 1,000 invoice -> 1,000 applied to invoice, 500 VendorAdvance
        $v1Note = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'purchase_invoice_id' => $inv->id,
            'credit_date' => now()->toDateString(),
            'reason' => PurchaseCreditReason::Return->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 15, 'unit_price' => 100]],
        ], $this->admin);

        $this->assertSame('v1', $v1Note->accounting_version);
        $this->assertEquals(1000.0, (float) $v1Note->applied_to_invoice_amount);
        $this->assertNotNull($v1Note->vendor_advance_id);
        $this->assertEquals(0.0, $inv->fresh()->remainingBalance());

        // Add cash advance of 200
        VendorAdvance::query()->create([
            'supplier_id' => $this->supplier->id,
            'amount_original' => 200.00,
            'amount_remaining' => 200.00,
            'business_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        // Now create a V2 note for 300 with an unpaid 400 invoice
        config(['greenleaf.purchase_credit_notes_v2_enabled' => true]);
        $this->createInvoice(400.00);
        $this->createV2CreditNote(10.0, 30.0); // 300

        $summary = $this->pcnService->getVendorFinancialSummary($this->supplier);
        // Unpaid: 400. Available V2: 300. Available Legacy: 500. Cash Advance: 200.
        // Net Payable = 400 - (300 + 500) - 200 = -600
        $this->assertEquals(400.0, $summary['outstanding_balance']);
        $this->assertEquals(300.0, $summary['available_v2_credit_notes']);
        $this->assertEquals(500.0, $summary['available_legacy_credit_notes']);
        $this->assertEquals(800.0, $summary['available_purchase_credits']);
        $this->assertEquals(200.0, $summary['actual_cash_vendor_advances']);
        $this->assertEquals(-600.0, $summary['net_vendor_payable']);
    }

    public function test_stock_out_fifo_cost_vs_credit_value_difference_and_money_in_out_regression(): void
    {
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
            'cost_per_kg' => 20.0,
            'reference' => 'BATCH-V2-'.uniqid(),
        ]);
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 100.0,
            'cost_per_unit' => 20.0,
            'created_by' => $this->admin->id,
        ]);

        // Return 10 kg at vendor credit rate ₹25/kg (₹250 credit) while FIFO cost is ₹20/kg (₹200 inventory)
        $pcnOut = $this->createV2CreditNote(10.0, 25.0, null, [
            'reason' => PurchaseCreditReason::Return->value,
            'inventory_action' => PurchaseCreditInventoryAction::Out->value,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $txns = JournalTransaction::with('account')->where('journal_entry_id', $pcnOut->journal_entry_id)->get();
        $debits = round((float) $txns->where('type', 'debit')->sum('amount'), 2);
        $credits = round((float) $txns->where('type', 'credit')->sum('amount'), 2);
        $this->assertEquals($debits, $credits);
        $this->assertEquals(250.0, (float) $txns->firstWhere('account.code', '1400')?->amount);
        $this->assertEquals(200.0, (float) $txns->firstWhere('account.code', '1200')?->amount);
        $this->assertEquals(50.0, (float) $txns->firstWhere('account.code', '4200')?->amount);

        // Money IN regression under V2 flag
        $pcnMoneyIn = $this->createV2CreditNote(5.0, 40.0, null, [
            'money_effect' => PurchaseCreditMoneyEffect::MoneyIn->value,
            'company_account_id' => $this->cashAccount->id,
        ]);
        $this->assertEquals(0.0, $pcnMoneyIn->available_credit);
        $this->assertEquals(100200.0, (float) $this->cashAccount->fresh()->current_balance);
    }

    public function test_durable_submission_token_idempotency_and_over_allocation_guards(): void
    {
        $inv = $this->createInvoice(500.00);
        $token = 'pcn-token-durable-001';

        $first = $this->createV2CreditNote(10.0, 30.0, $inv, ['submission_token' => $token]);
        $second = $this->createV2CreditNote(10.0, 30.0, $inv, ['submission_token' => $token]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PurchaseCreditNote::where('submission_token', $token)->count());

        // Settlement token idempotency & over-allocation guard
        $sToken = 'settle-token-durable-001';
        $s1 = $this->settlementService->create($this->supplier, [
            'submission_token' => $sToken,
            'actual_payment_amount' => 0.0,
            'settlement_discount_amount' => 0.0,
            'vendor_advance_used_amount' => 0.0,
            'purchase_credit_used_amount' => 200.0,
            'credit_notes' => [['purchase_credit_note_id' => $first->id, 'amount' => 200.0]],
            'payment_date' => now()->toDateString(),
            'allocations' => [['purchase_invoice_id' => $inv->id, 'cash_allocated' => 0.0, 'advance_allocated' => 0.0, 'credit_allocated' => 200.0, 'discount_allocated' => 0.0]],
        ], $this->admin->id);

        $s2 = $this->settlementService->create($this->supplier, [
            'submission_token' => $sToken,
            'actual_payment_amount' => 0.0,
            'settlement_discount_amount' => 0.0,
            'vendor_advance_used_amount' => 0.0,
            'purchase_credit_used_amount' => 200.0,
            'credit_notes' => [['purchase_credit_note_id' => $first->id, 'amount' => 200.0]],
            'payment_date' => now()->toDateString(),
            'allocations' => [['purchase_invoice_id' => $inv->id, 'cash_allocated' => 0.0, 'advance_allocated' => 0.0, 'credit_allocated' => 200.0, 'discount_allocated' => 0.0]],
        ], $this->admin->id);

        $this->assertSame($s1->id, $s2->id);
        $this->assertEquals(100.0, $first->fresh()->available_credit);

        // Attempting to over-allocate remaining 100 with 150 fails & rolls back cleanly
        try {
            $this->settlementService->create($this->supplier, [
                'actual_payment_amount' => 0.0,
                'settlement_discount_amount' => 0.0,
                'vendor_advance_used_amount' => 0.0,
                'purchase_credit_used_amount' => 150.0,
                'credit_notes' => [['purchase_credit_note_id' => $first->id, 'amount' => 150.0]],
                'payment_date' => now()->toDateString(),
                'allocations' => [['purchase_invoice_id' => $inv->id, 'cash_allocated' => 0.0, 'advance_allocated' => 0.0, 'credit_allocated' => 150.0, 'discount_allocated' => 0.0]],
            ], $this->admin->id);
            $this->fail('Expected ValidationException for over-allocation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('credit_notes', $e->errors());
        }

        $this->assertEquals(100.0, $first->fresh()->available_credit);
        $this->assertSame(1, VendorSettlement::where('supplier_id', $this->supplier->id)->count());
    }
}
