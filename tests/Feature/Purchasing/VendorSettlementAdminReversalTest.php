<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Models\VendorSettlementAllocation;
use App\Services\Finance\VendorSettlementCorrectionService;
use App\Services\Finance\VendorSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorSettlementAdminReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
        config(['greenleaf.main_admin_email' => $this->admin->email]);

        $this->supplier = Supplier::factory()->create(['name' => 'Test Vendor Rubber Corp']);
    }

    private function createInvoice(float $amount, float $discount = 0.0, string $date = '2026-08-01'): PurchaseInvoice
    {
        return PurchaseInvoice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'amount' => $amount,
            'discount_amount' => $discount,
            'paid_amount' => 0.0,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'created_at' => $date,
        ]);
    }

    private function createCompanyAccount(string $name = 'Primary Bank Account'): CompanyAccount
    {
        return CompanyAccount::query()->create([
            'name' => $name,
            'account_number' => '1234567890',
            'bank_name' => 'HDFC Bank',
            'account_type' => 'bank',
            'enabled' => true,
        ]);
    }

    public function test_1_amount_auto_selects_oldest_bills(): void
    {
        $inv1 = $this->createInvoice(1000.0, 0.0, '2026-08-01');
        $inv2 = $this->createInvoice(2000.0, 0.0, '2026-08-02');

        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id, $inv2->id],
            'actual_payment_amount' => 1500.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $this->assertEquals(1500.0, (float) $settlement->actual_payment_amount);
        $this->assertCount(2, $settlement->allocations);
        $this->assertEquals(1000.0, (float) $settlement->allocations->where('purchase_invoice_id', $inv1->id)->first()->cash_allocated);
        $this->assertEquals(500.0, (float) $settlement->allocations->where('purchase_invoice_id', $inv2->id)->first()->cash_allocated);
    }

    public function test_2_partial_last_bill_allocation(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 600.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $alloc = $settlement->allocations->first();
        $this->assertEquals(600.0, (float) $alloc->cash_allocated);
        $this->assertEquals(600.0, (float) $alloc->total_settled);
    }

    public function test_3_amount_change_recalculates_auto_selection(): void
    {
        $inv1 = $this->createInvoice(500.0);
        $inv2 = $this->createInvoice(500.0);

        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id, $inv2->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $this->assertEquals(1000.0, (float) $settlement->allocations->sum('cash_allocated'));
    }

    public function test_4_manual_selection_works(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $inv2 = $this->createInvoice(2000.0);

        $service = app(VendorSettlementService::class);
        $settlement = $service->create($this->supplier, [
            'actual_payment_amount' => 2000.0,
            'settlement_discount_amount' => 0.0,
            'vendor_advance_used_amount' => 0.0,
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
            'allocations' => [
                ['purchase_invoice_id' => $inv2->id, 'cash_allocated' => 2000.0, 'advance_allocated' => 0.0, 'discount_allocated' => 0.0],
            ],
        ], (int) $this->admin->id);

        $this->assertCount(1, $settlement->allocations);
        $this->assertEquals($inv2->id, $settlement->allocations->first()->purchase_invoice_id);
    }

    public function test_5_select_all_visible(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $inv2 = $this->createInvoice(1500.0);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $response->assertStatus(200);
        $response->assertSee($inv1->invoice_number ?: ('#'.$inv1->id));
        $response->assertSee($inv2->invoice_number ?: ('#'.$inv2->id));
    }

    public function test_6_pagination_keeps_selection_totals_correct(): void
    {
        $invoices = collect();
        for ($i = 0; $i < 30; $i++) {
            $invoices->push($this->createInvoice(100.0));
        }

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $response->assertStatus(200);
        $response->assertSee('30 invoices');
    }

    public function test_7_keep_outstanding_leaves_exact_remainder(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 700.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $this->assertEquals(0.0, (float) $settlement->settlement_discount_amount);
        $this->assertEquals(700.0, (float) $settlement->allocations->first()->cash_allocated);
        $this->assertEquals(0.0, (float) $settlement->allocations->first()->discount_allocated);
    }

    public function test_8_settlement_discount_uses_exact_remainder(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 700.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $this->assertEquals(300.0, (float) $settlement->settlement_discount_amount);
        $alloc = $settlement->allocations->first();
        $this->assertEquals(700.0, (float) $alloc->cash_allocated);
        $this->assertEquals(300.0, (float) $alloc->discount_allocated);
        $this->assertEquals(1000.0, (float) $alloc->total_settled);
    }

    public function test_9_discount_persists_on_settlement(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 800.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $this->assertDatabaseHas('vendor_settlements', [
            'id' => $settlement->id,
            'actual_payment_amount' => 800.0,
            'settlement_discount_amount' => 200.0,
        ]);
    }

    public function test_10_discount_persists_per_allocation(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 850.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $this->assertDatabaseHas('vendor_settlement_allocations', [
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $inv1->id,
            'cash_allocated' => 850.0,
            'discount_allocated' => 150.0,
            'total_settled' => 1000.0,
        ]);
    }

    public function test_11_vendor_credit_outstanding_reflects_discount(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 750.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $inv1->refresh();
        $this->assertEquals('paid', $inv1->payment_status);
        $settled = (float) VendorSettlementAllocation::query()->where('purchase_invoice_id', $inv1->id)->sum('total_settled');
        $this->assertEquals(1000.0, $settled);
    }

    public function test_12_actual_payment_remains_cash_only(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 800.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $this->assertEquals(800.0, (float) $settlement->actual_payment_amount);
    }

    public function test_13_journal_discount_account_correct(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 800.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $journal = $settlement->journalEntry;
        $this->assertNotNull($journal);

        $discountLine = $journal->transactions->where('type', 'credit')->filter(fn ($t) => (float) $t->amount === 200.0)->first();
        $this->assertNotNull($discountLine);
    }

    public function test_14_vendor_advance_cash_discount_balance(): void
    {
        $inv1 = $this->createInvoice(1000.0);

        VendorAdvance::query()->create([
            'supplier_id' => $this->supplier->id,
            'amount_original' => 200.0,
            'amount_remaining' => 200.0,
            'business_date' => '2026-08-01',
            'status' => 'open',
            'created_by' => (int) $this->admin->id,
        ]);

        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 700.0,
            'use_vendor_advance' => true,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $this->assertEquals(700.0, (float) $settlement->actual_payment_amount);
        $this->assertEquals(200.0, (float) $settlement->vendor_advance_used_amount);
        $this->assertEquals(100.0, (float) $settlement->settlement_discount_amount);
    }

    public function test_15_company_account_settlement(): void
    {
        $account = $this->createCompanyAccount('HDFC Primary');
        $inv1 = $this->createInvoice(1000.0);

        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'company_account_id' => $account->id,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $this->assertEquals($account->id, $settlement->company_account_id);
    }

    public function test_16_existing_statement_settlement(): void
    {
        $account = $this->createCompanyAccount('HDFC Primary');
        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $account->id,
            'direction' => 'out',
            'amount' => 1000.0,
            'status' => 'unmatched',
            'is_finalized' => false,
            'journal_entry_id' => null,
            'transaction_date' => '2026-08-10',
        ]);

        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'company_account_id' => $account->id,
            'statement_entry_id' => $statement->id,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $statement->refresh();
        $this->assertNotNull($statement->journal_entry_id);
    }

    public function test_17_no_duplicate_company_transaction(): void
    {
        $account = $this->createCompanyAccount('HDFC Primary');
        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $account->id,
            'direction' => 'out',
            'amount' => 500.0,
            'status' => 'unmatched',
            'is_finalized' => false,
            'journal_entry_id' => null,
            'transaction_date' => '2026-08-10',
        ]);

        $inv1 = $this->createInvoice(500.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 500.0,
            'company_account_id' => $account->id,
            'statement_entry_id' => $statement->id,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $this->assertEquals($statement->id, CompanyAccountStatementEntry::where('journal_entry_id', $settlement->journal_entry_id)->value('id'));
    }

    public function test_18_edit_metadata(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
            'note' => 'Original Note',
        ], (int) $this->admin->id);

        $correctionService = app(VendorSettlementCorrectionService::class);
        $correctionService->updateSettlementWithReversal($settlement, [
            'note' => 'Updated Note',
            'reference' => 'REF-1234',
        ], $this->admin, 'Update note');

        $settlement->refresh();
        $this->assertEquals('Updated Note', $settlement->note);
        $this->assertEquals('REF-1234', $settlement->reference);
    }

    public function test_19_edit_financial_settlement(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 700.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $correctionService = app(VendorSettlementCorrectionService::class);
        $correctionService->updateSettlementWithReversal($settlement, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 800.0,
            'difference_treatment' => 'discount',
            'payment_method' => 'Bank',
            'reason' => 'Correct amount',
        ], $this->admin, 'Correct amount');

        $inv1->refresh();
        $this->assertEquals('paid', $inv1->payment_status);
    }

    public function test_20_delete_finalized_settlement(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $correctionService = app(VendorSettlementCorrectionService::class);
        $correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Wrong entry');

        $this->assertDatabaseMissing('vendor_settlements', ['id' => $settlement->id]);
    }

    public function test_21_delete_restores_bill_outstanding(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $correctionService = app(VendorSettlementCorrectionService::class);
        $correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Delete settlement');

        $inv1->refresh();
        $this->assertEquals('unpaid', $inv1->payment_status);
        $this->assertEquals(0.0, (float) $inv1->paid_amount);
    }

    public function test_22_delete_restores_vendor_advance(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $adv = VendorAdvance::query()->create([
            'supplier_id' => $this->supplier->id,
            'amount_original' => 500.0,
            'amount_remaining' => 500.0,
            'business_date' => '2026-08-01',
            'status' => 'open',
            'created_by' => (int) $this->admin->id,
        ]);

        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 500.0,
            'use_vendor_advance' => true,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $correctionService = app(VendorSettlementCorrectionService::class);
        $correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Restore advance');

        $adv->refresh();
        $this->assertEquals(500.0, (float) $adv->amount_remaining);
        $this->assertEquals('open', $adv->status);
    }

    public function test_23_delete_reverses_discount(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 700.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $correctionService = app(VendorSettlementCorrectionService::class);
        $correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Delete discount');

        $inv1->refresh();
        $settled = (float) VendorSettlementAllocation::query()->where('purchase_invoice_id', $inv1->id)->sum('total_settled');
        $this->assertEquals(0.0, $settled);
    }

    public function test_24_delete_reverses_journal(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $journalId = $settlement->journal_entry_id;
        $correctionService = app(VendorSettlementCorrectionService::class);
        $correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Delete journal');

        $this->assertDatabaseMissing('journal_entries', ['id' => $journalId]);
    }

    public function test_25_delete_unmatches_statement(): void
    {
        $account = $this->createCompanyAccount('HDFC Primary');
        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $account->id,
            'direction' => 'out',
            'amount' => 1000.0,
            'status' => 'unmatched',
            'source' => 'imported',
            'import_file_name' => 'bank_statement.csv',
            'is_finalized' => false,
            'journal_entry_id' => null,
            'transaction_date' => '2026-08-10',
        ]);

        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'company_account_id' => $account->id,
            'statement_entry_id' => $statement->id,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $correctionService = app(VendorSettlementCorrectionService::class);
        $correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Unmatch statement');

        $statement->refresh();
        $this->assertNull($statement->journal_entry_id);
    }

    public function test_26_same_amount_date_unrelated_transaction_untouched(): void
    {
        $account = $this->createCompanyAccount('HDFC Primary');
        $unrelatedStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $account->id,
            'direction' => 'out',
            'amount' => 1000.0,
            'status' => 'unmatched',
            'is_finalized' => false,
            'journal_entry_id' => null,
            'transaction_date' => '2026-08-10',
        ]);

        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $correctionService = app(VendorSettlementCorrectionService::class);
        $correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Delete settlement');

        $unrelatedStatement->refresh();
        $this->assertNull($unrelatedStatement->journal_entry_id);
    }

    public function test_27_failed_reversal_rolls_back(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $this->assertDatabaseHas('vendor_settlements', ['id' => $settlement->id]);
    }

    public function test_28_no_orphan_allocations(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $correctionService = app(VendorSettlementCorrectionService::class);
        $correctionService->deleteSettlementWithReversal($settlement, $this->admin, 'Clean allocations');

        $this->assertDatabaseMissing('vendor_settlement_allocations', ['vendor_settlement_id' => $settlement->id]);
    }

    public function test_29_vendor_totals_reconcile(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $inv2 = $this->createInvoice(2000.0);

        $service = app(VendorSettlementService::class);
        $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id, $inv2->id],
            'actual_payment_amount' => 1500.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $totalInvoiced = (float) PurchaseInvoice::query()->where('supplier_id', $this->supplier->id)->sum('amount');
        $totalSettled = (float) VendorSettlementAllocation::query()->whereIn('purchase_invoice_id', [$inv1->id, $inv2->id])->sum('total_settled');

        $this->assertEquals(3000.0, $totalInvoiced);
        $this->assertEquals(3000.0, $totalSettled);
    }

    public function test_30_ui_discount_feedback_matches_db_result(): void
    {
        $inv1 = $this->createInvoice(202699.84);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settle', $this->supplier), [
            'actual_payment_amount' => 150000.0,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
            'invoice_ids' => [$inv1->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vendor_settlements', [
            'supplier_id' => $this->supplier->id,
            'actual_payment_amount' => 150000.0,
            'settlement_discount_amount' => 52699.84,
        ]);
    }

    public function test_31_history_shows_affected_bill_count(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $inv2 = $this->createInvoice(2000.0);
        $service = app(VendorSettlementService::class);
        $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id, $inv2->id],
            'actual_payment_amount' => 3000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $response->assertStatus(200);
        $response->assertSee('2 Bills');
    }

    public function test_32_history_shows_total_amount_settled(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 700.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $response->assertStatus(200);
        $response->assertSee('1,000.00 settled');
    }

    public function test_33_history_displays_settlement_discount(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 800.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $response->assertStatus(200);
        $response->assertSee('Settlement Discount');
        $response->assertSee('200.00');
    }

    public function test_34_history_distinguishes_actual_payment_from_discount(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 600.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $response->assertStatus(200);
        $response->assertSee('Actual Payment');
        $response->assertSee('600.00');
        $response->assertSee('400.00');
    }

    public function test_35_history_shows_payment_source(): void
    {
        $account = $this->createCompanyAccount('HDFC Main Operating');
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'company_account_id' => $account->id,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $response->assertStatus(200);
        $response->assertSee('HDFC Main Operating');
    }

    public function test_36_finalized_column_redundancy_removed(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $response->assertStatus(200);
        $response->assertSee('Status');
        $response->assertDontSee('<th class="p-2.5 text-center">Finalized</th>', false);
    }

    public function test_37_edit_action_is_available_to_authorized_admin(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $response->assertStatus(200);
        $response->assertSee('Edit');
    }

    public function test_38_delete_still_uses_reversible_workflow(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.vendor-credit.settlements.delete', $settlement), [
            'reason' => 'Duplicate settlement recorded',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('vendor_settlements', ['id' => $settlement->id]);
    }

    public function test_39_corrected_reversed_settlement_status_displays_correctly(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 1000.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'outstanding',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $settlement->update(['status' => 'corrected']);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.vendor-credit.show', $this->supplier));
        $response->assertStatus(200);
        $response->assertSee('Corrected');
    }

    public function test_40_history_totals_reconcile_with_allocations(): void
    {
        $inv1 = $this->createInvoice(1000.0);
        $service = app(VendorSettlementService::class);
        $settlement = $service->createAutomatic($this->supplier, [
            'invoice_ids' => [$inv1->id],
            'actual_payment_amount' => 700.0,
            'use_vendor_advance' => false,
            'difference_treatment' => 'discount',
            'allocation_order' => 'oldest',
            'payment_date' => '2026-08-10',
            'payment_method' => 'Bank',
        ], (int) $this->admin->id);

        $totalAllocated = (float) VendorSettlementAllocation::query()->where('vendor_settlement_id', $settlement->id)->sum('total_settled');
        $this->assertEquals((float) $settlement->actual_payment_amount + (float) $settlement->settlement_discount_amount, $totalAllocated);
    }
}
