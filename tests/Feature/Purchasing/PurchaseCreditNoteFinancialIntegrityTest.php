<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Services\Finance\VendorSettlementCorrectionService;
use App\Services\Finance\VendorSettlementService;
use App\Services\Purchasing\PurchaseCreditNoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseCreditNoteFinancialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseCreditNoteService $pcnService;

    private VendorSettlementService $settlementService;

    private VendorSettlementCorrectionService $correctionService;

    private User $admin;

    private Supplier $supplier;

    private Product $product;

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

        $this->supplier = Supplier::factory()->create([
            'name' => 'Financial Test Vendor',
            'credit_approved' => true,
        ]);

        $this->product = Product::factory()->create(['unit' => 'kg']);
        $this->supplier->products()->attach($this->product->id);

        $this->cashAccount = CompanyAccount::create([
            'name' => 'Main Cash Till',
            'account_type' => 'cash',
            'opening_balance' => 50000.00,
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);

        $this->bankAccount = CompanyAccount::create([
            'name' => 'HDFC Bank',
            'account_type' => 'bank',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);
    }

    public function test_example_scenario_prevents_manual_and_automatic_over_settlement_and_syncs_status(): void
    {
        $invoice = $this->createCreditInvoice(10000.00);

        // Apply ₹2,000 Purchase Credit Note -> outstanding ₹8,000, status 'partial'
        $pcn = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'purchase_invoice_id' => $invoice->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 10, 'unit_price' => 200]],
        ], $this->admin);

        $invoice->refresh();
        $this->assertEqualsWithDelta(8000.00, $invoice->remainingBalance(), 0.01);
        $this->assertSame('partial', $invoice->payment_status);

        // Manual settlement of ₹10,000 must be rejected
        try {
            $this->settlementService->create($this->supplier, [
                'actual_payment_amount' => 10000.00,
                'settlement_discount_amount' => 0,
                'vendor_advance_used_amount' => 0,
                'payment_date' => '2026-11-26',
                'payment_method' => 'Bank',
                'company_account_id' => $this->bankAccount->id,
                'reference' => 'OVER-MANUAL',
                'note' => null,
                'allocations' => [
                    ['purchase_invoice_id' => $invoice->id, 'cash_allocated' => 10000.00, 'advance_allocated' => 0, 'discount_allocated' => 0],
                ],
            ], $this->admin->id);
            $this->fail('Expected ValidationException on manual over-settlement.');
        } catch (ValidationException) {
            $this->assertEqualsWithDelta(8000.00, $invoice->fresh()->remainingBalance(), 0.01);
        }

        // Automatic settlement of ₹10,000 caps invoice allocation at ₹8,000 and puts ₹2,000 into VendorAdvance
        $settlement = $this->settlementService->createAutomatic($this->supplier, [
            'invoice_ids' => [$invoice->id],
            'actual_payment_amount' => 10000.00,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-11-26',
            'payment_method' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'AUTO-SETTLE',
            'note' => null,
        ], $this->admin->id);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertEqualsWithDelta(0.00, $invoice->remainingBalance(), 0.01);
        $this->assertEqualsWithDelta(8000.00, (float) $settlement->allocations->first()->cash_allocated, 0.01);
        $this->assertEqualsWithDelta(2000.00, (float) $settlement->new_vendor_advance_amount, 0.01);

        // Credit note cancellation is blocked while downstream settlement is active
        try {
            $this->pcnService->void($pcn, 'Attempt void with active settlement', $this->admin);
            $this->fail('Expected ValidationException blocking void after downstream settlement.');
        } catch (ValidationException) {
            $this->assertTrue($pcn->fresh()->isActive());
        }

        // Reversing the settlement restores outstanding to ₹8,000 (not ₹10,000) and status to 'partial'
        $this->correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Reverse test');

        $invoice->refresh();
        $this->assertEqualsWithDelta(8000.00, $invoice->remainingBalance(), 0.01);
        $this->assertSame('partial', $invoice->payment_status);

        // Now PCN void succeeds and restores invoice to ₹10,000 'unpaid'
        $this->pcnService->void($pcn->fresh(), 'Safe void after settlement reversed', $this->admin);
        $invoice->refresh();
        $this->assertEqualsWithDelta(10000.00, $invoice->remainingBalance(), 0.01);
        $this->assertSame('unpaid', $invoice->payment_status);
    }

    public function test_full_credit_note_marks_invoice_paid_and_vendor_advance_restores_on_settlement_reversal(): void
    {
        $invoice = $this->createCreditInvoice(5000.00);

        // ₹7,000 PCN on ₹5,000 invoice -> ₹5,000 applied (invoice 'paid'), ₹2,000 VendorAdvance
        $pcn = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'purchase_invoice_id' => $invoice->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 10, 'unit_price' => 700]],
        ], $this->admin);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertEqualsWithDelta(0.00, $invoice->remainingBalance(), 0.01);

        $advance = VendorAdvance::findOrFail($pcn->vendor_advance_id);
        $this->assertEqualsWithDelta(2000.00, (float) $advance->amount_remaining, 0.01);

        // Use ₹1,200 of that ₹2,000 VendorAdvance (leaving ₹800 'open') on a second invoice
        $invoice2 = $this->createCreditInvoice(3000.00);
        $settlement = $this->settlementService->create($this->supplier, [
            'actual_payment_amount' => 0,
            'settlement_discount_amount' => 0,
            'vendor_advance_used_amount' => 1200.00,
            'payment_date' => '2026-11-26',
            'payment_method' => 'Bank',
            'company_account_id' => null,
            'reference' => 'ADV-USE',
            'note' => null,
            'allocations' => [
                ['purchase_invoice_id' => $invoice2->id, 'cash_allocated' => 0, 'advance_allocated' => 1200.00, 'discount_allocated' => 0],
            ],
        ], $this->admin->id);

        $this->assertEqualsWithDelta(800.00, (float) $advance->fresh()->amount_remaining, 0.01);

        // Reversing settlement must restore partially-used 'open' advance back to ₹2,000
        $this->correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Restore partial advance');
        $this->assertEqualsWithDelta(2000.00, (float) $advance->fresh()->amount_remaining, 0.01);
    }

    public function test_money_in_and_money_out_cash_and_bank_journals_and_exact_once_balance_reversal(): void
    {
        $cashAccountId = Account::where('code', '1010')->value('id');
        $bankAccountId = Account::where('code', '1020')->value('id');

        // Mismatched payment_method on bank account is rejected
        try {
            $this->pcnService->create([
                'supplier_id' => $this->supplier->id,
                'credit_date' => '2026-11-26',
                'reason' => PurchaseCreditReason::PriceAdjustment->value,
                'money_effect' => PurchaseCreditMoneyEffect::MoneyIn->value,
                'inventory_action' => PurchaseCreditInventoryAction::None->value,
                'company_account_id' => $this->bankAccount->id,
                'payment_method' => 'cash',
                'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 1, 'unit_price' => 500]],
            ], $this->admin);
            $this->fail('Expected ValidationException for mismatched payment_method.');
        } catch (ValidationException) {
            $this->assertEqualsWithDelta(100000.00, (float) $this->bankAccount->fresh()->current_balance, 0.01);
        }

        // 1. Money IN (Cash) -> +1000, posts 1010 debit, void reverses once back to 50,000
        $inCash = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::MoneyIn->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'company_account_id' => $this->cashAccount->id,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 1, 'unit_price' => 1000]],
        ], $this->admin);
        $this->assertSame('cash', $inCash->payment_method);
        $this->assertTrue($inCash->journalEntry->transactions->contains(fn ($t) => (int) $t->account_id === $cashAccountId && $t->type === 'debit'));
        $this->assertEqualsWithDelta(51000.00, (float) $this->cashAccount->fresh()->current_balance, 0.01);

        $this->pcnService->void($inCash, 'Void Money IN Cash', $this->admin);
        $this->assertEqualsWithDelta(50000.00, (float) $this->cashAccount->fresh()->current_balance, 0.01);

        // 2. Money OUT (Cash) -> -400, posts 1010 credit, void reverses once back to 50,000
        $outCash = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::Other->value,
            'money_effect' => PurchaseCreditMoneyEffect::MoneyOut->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'company_account_id' => $this->cashAccount->id,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 1, 'unit_price' => 400]],
        ], $this->admin);
        $this->assertTrue($outCash->journalEntry->transactions->contains(fn ($t) => (int) $t->account_id === $cashAccountId && $t->type === 'credit'));
        $this->assertEqualsWithDelta(49600.00, (float) $this->cashAccount->fresh()->current_balance, 0.01);

        $this->pcnService->void($outCash, 'Void Money OUT Cash', $this->admin);
        $this->assertEqualsWithDelta(50000.00, (float) $this->cashAccount->fresh()->current_balance, 0.01);

        // 3. Money IN (Bank) -> +2500, posts 1020 debit, void reverses once back to 100,000
        $inBank = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::PriceAdjustment->value,
            'money_effect' => PurchaseCreditMoneyEffect::MoneyIn->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'company_account_id' => $this->bankAccount->id,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 1, 'unit_price' => 2500]],
        ], $this->admin);
        $this->assertSame('bank', $inBank->payment_method);
        $this->assertTrue($inBank->journalEntry->transactions->contains(fn ($t) => (int) $t->account_id === $bankAccountId && $t->type === 'debit'));
        $this->assertEqualsWithDelta(102500.00, (float) $this->bankAccount->fresh()->current_balance, 0.01);

        $this->pcnService->void($inBank, 'Void Money IN Bank', $this->admin);
        $this->assertEqualsWithDelta(100000.00, (float) $this->bankAccount->fresh()->current_balance, 0.01);

        // 4. Money OUT (Bank) -> -1500, posts 1020 credit, void reverses once back to 100,000
        $outBank = $this->pcnService->create([
            'supplier_id' => $this->supplier->id,
            'credit_date' => '2026-11-26',
            'reason' => PurchaseCreditReason::Other->value,
            'money_effect' => PurchaseCreditMoneyEffect::MoneyOut->value,
            'inventory_action' => PurchaseCreditInventoryAction::None->value,
            'company_account_id' => $this->bankAccount->id,
            'items' => [['product_id' => $this->product->id, 'grade' => 'A', 'quantity' => 1, 'unit_price' => 1500]],
        ], $this->admin);
        $this->assertTrue($outBank->journalEntry->transactions->contains(fn ($t) => (int) $t->account_id === $bankAccountId && $t->type === 'credit'));
        $this->assertEqualsWithDelta(98500.00, (float) $this->bankAccount->fresh()->current_balance, 0.01);

        $this->pcnService->void($outBank, 'Void Money OUT Bank', $this->admin);
        $this->assertEqualsWithDelta(100000.00, (float) $this->bankAccount->fresh()->current_balance, 0.01);
    }

    private function createCreditInvoice(float $amount): PurchaseInvoice
    {
        $grn = GoodsReceived::factory()->create();

        return PurchaseInvoice::create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'GLPB26-1126-'.strtoupper(bin2hex(random_bytes(2))),
            'amount' => $amount,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_paid_by' => 'vendor_credit',
            'payment_status' => 'unpaid',
            'status' => 'approved',
        ]);
    }
}
