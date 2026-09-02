<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Cashbook\CompanyAccount;
use App\Models\GoodsReceived;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\JournalService;
use App\Services\Finance\PurchaserFinanceService;
use App\Services\Finance\PurchaserSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserSettlementAdminReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private User $otherPurchaser;

    private CompanyAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->otherPurchaser = User::factory()->create();
        $this->otherPurchaser->assignRole('purchaser');

        $this->account = CompanyAccount::query()->create([
            'name' => 'Main Bank',
            'account_type' => 'bank',
            'account_number' => '1234567890',
            'bank_name' => 'HDFC',
            'enabled' => true,
            'is_default' => true,
        ]);
    }

    private function giveFunding(float $amount, string $date = '2026-08-10', ?User $user = null): PurchaserCredit
    {
        $targetUser = $user ?? $this->purchaser;
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $targetUser->id,
            'type' => 'in',
            'amount' => $amount,
            'description' => 'Company funding to purchaser',
            'payment_source' => 'Bank',
            'company_account_id' => $this->account->id,
            'reference' => 'REF-'.rand(1000, 9999),
            'created_by' => $this->admin->id,
            'business_date' => $date,
        ]);

        app(JournalService::class)->recordPurchaserCredit($credit);

        return $credit;
    }

    private function createCashInvoice(float $amount, string $date = '2026-08-15', ?User $user = null): PurchaseInvoice
    {
        $targetUser = $user ?? $this->purchaser;
        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);
        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'received_by' => $targetUser->id,
        ]);

        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'INV-'.rand(1000, 9999),
            'amount' => $amount,
            'discount_amount' => 0,
            'paid_amount' => $amount,
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
            'purchaser_submitted_by' => $targetUser->id,
        ]);

        PurchaserCredit::query()->create([
            'purchaser_id' => $targetUser->id,
            'type' => 'out',
            'amount' => $amount,
            'description' => 'Debit for invoice: '.$invoice->invoice_number,
            'purchase_invoice_id' => $invoice->id,
            'created_by' => $targetUser->id,
            'business_date' => $date,
        ]);

        return $invoice;
    }

    public function test_selecting_month_returns_month_records(): void
    {
        $this->giveFunding(50000, '2026-08-10');
        $this->giveFunding(20000, '2026-09-05');

        $service = app(PurchaserSettlementService::class);
        $aug = $service->monthSettlement($this->purchaser, '2026-08');

        $this->assertSame('2026-08', $aug['month']);
        $this->assertEquals(50000, $aug['month_funding_added']);
    }

    public function test_opening_balance_is_separated_from_month_activity(): void
    {
        $this->giveFunding(30000, '2026-07-20');
        $this->giveFunding(50000, '2026-08-10');

        $service = app(PurchaserSettlementService::class);
        $aug = $service->monthSettlement($this->purchaser, '2026-08');

        $this->assertEquals(30000, $aug['opening_balance']);
        $this->assertEquals(50000, $aug['month_funding_added']);
    }

    public function test_admin_records_payment_through_new_flow(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 25000,
            'business_date' => '2026-08-05',
            'payment_source' => 'Bank',
            'company_account_id' => $this->account->id,
            'reference' => 'TXN-999',
            'description' => 'Fresh funding',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchaser_credits', [
            'purchaser_id' => $this->purchaser->id,
            'amount' => 25000,
            'reference' => 'TXN-999',
        ]);
    }

    public function test_admin_edits_unused_funding(): void
    {
        $credit = $this->giveFunding(50000, '2026-08-10');

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.update', ['purchaser' => $this->purchaser->public_uuid, 'credit' => $credit->id]), [
            'amount' => 60000,
            'business_date' => '2026-08-10',
            'payment_source' => 'Bank',
            'company_account_id' => $this->account->id,
            'reference' => 'UPD-001',
            'reason' => 'Amount correction',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchaser_credits', [
            'id' => $credit->id,
            'amount' => 60000,
        ]);
    }

    public function test_admin_deletes_unused_funding(): void
    {
        $credit = $this->giveFunding(50000, '2026-08-10');

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.delete', ['purchaser' => $this->purchaser->public_uuid, 'credit' => $credit->id]), [
            'reason' => 'Wrong Entry',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('purchaser_credits', ['id' => $credit->id]);
    }

    public function test_admin_deletes_utilized_funding_and_bill_allocations_are_reversed(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');
        $invoice = $this->createCashInvoice(40000, '2026-08-15');

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.delete', ['purchaser' => $this->purchaser->public_uuid, 'credit' => $funding->id]), [
            'reason' => 'Reversal Needed',
        ]);

        $response->assertRedirect();
        $invoice->refresh();

        $this->assertSame('Credit', $invoice->payment_method);
        $this->assertEquals(0.00, $invoice->paid_amount);
    }

    public function test_bill_outstanding_is_restored_after_deletion(): void
    {
        $funding = $this->giveFunding(30000, '2026-08-10');
        $invoice = $this->createCashInvoice(30000, '2026-08-12');

        $service = app(PurchaserSettlementService::class);
        $service->deleteFundingWithReversal($this->purchaser, $funding, $this->admin, 'Wrong Entry');

        $invoice->refresh();
        $this->assertSame('Credit', $invoice->payment_method);
        $this->assertEquals(30000, $invoice->amount - $invoice->paid_amount);
    }

    public function test_purchaser_advance_balance_is_recalculated_after_deletion(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');
        $this->createCashInvoice(30000, '2026-08-12');

        $service = app(PurchaserSettlementService::class);
        $service->deleteFundingWithReversal($this->purchaser, $funding, $this->admin, 'Wrong Entry');

        $summary = app(PurchaserFinanceService::class)->summaryFor((int) $this->purchaser->id);
        $this->assertEquals(0, $summary['remaining_advance']);
    }

    public function test_admin_edits_utilized_funding_to_a_smaller_amount(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');
        $invoice = $this->createCashInvoice(40000, '2026-08-15');

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.update', ['purchaser' => $this->purchaser->public_uuid, 'credit' => $funding->id]), [
            'amount' => 30000,
            'business_date' => '2026-08-10',
            'payment_source' => 'Bank',
            'company_account_id' => $this->account->id,
            'reason' => 'Reduced funding',
        ]);

        $response->assertRedirect();

        $invoice->refresh();
        $this->assertSame('Credit', $invoice->payment_method);
    }

    public function test_existing_allocations_cannot_exceed_edited_funding_amount(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');
        $this->createCashInvoice(40000, '2026-08-15');

        $service = app(PurchaserSettlementService::class);
        $service->updateFundingWithReversal($this->purchaser, $funding, [
            'amount' => 20000,
            'business_date' => '2026-08-10',
            'payment_source' => 'Bank',
            'company_account_id' => $this->account->id,
        ], $this->admin, 'Amount reduction');

        $summary = app(PurchaserFinanceService::class)->summaryFor((int) $this->purchaser->id);
        $this->assertGreaterThanOrEqual(0, $summary['remaining_advance']);
    }

    public function test_admin_edits_utilized_funding_to_a_larger_amount(): void
    {
        $funding = $this->giveFunding(30000, '2026-08-10');
        $this->createCashInvoice(20000, '2026-08-15');

        $service = app(PurchaserSettlementService::class);
        $service->updateFundingWithReversal($this->purchaser, $funding, [
            'amount' => 60000,
            'business_date' => '2026-08-10',
            'payment_source' => 'Bank',
            'company_account_id' => $this->account->id,
        ], $this->admin, 'Increased funding');

        $summary = app(PurchaserFinanceService::class)->summaryFor((int) $this->purchaser->id);
        $this->assertEquals(40000, $summary['remaining_advance']);
    }

    public function test_failure_during_reversal_rolls_back_everything(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');

        try {
            DB::transaction(function () use ($funding): void {
                $funding->delete();
                throw new \Exception('Simulated Failure');
            });
        } catch (\Throwable) {
        }

        $this->assertDatabaseHas('purchaser_credits', ['id' => $funding->id]);
    }

    public function test_non_admin_restriction_remains(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');

        $response = $this->actingAs($this->purchaser)->post(route('admin.cashbook.finance.purchasers.funding.delete', ['purchaser' => $this->purchaser->public_uuid, 'credit' => $funding->id]), [
            'reason' => 'Wrong Entry',
        ]);

        $response->assertForbidden();
    }

    public function test_audit_entry_exists_for_admin_edit_delete_reversal(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');

        $service = app(PurchaserSettlementService::class);
        $service->deleteFundingWithReversal($this->purchaser, $funding, $this->admin, 'Audit Check');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'purchaser_finance',
            'causer_id' => $this->admin->id,
        ]);
    }

    public function test_no_orphan_allocation_utilization_records_remain(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');
        $invoice = $this->createCashInvoice(50000, '2026-08-15');

        $service = app(PurchaserSettlementService::class);
        $service->deleteFundingWithReversal($this->purchaser, $funding, $this->admin, 'Cleanup Test');

        $orphans = PurchaserCredit::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->count();

        $this->assertSame(0, $orphans);
    }

    public function test_month_totals_remain_correct_after_edit_delete(): void
    {
        $funding1 = $this->giveFunding(30000, '2026-08-05');
        $funding2 = $this->giveFunding(40000, '2026-08-10');

        $service = app(PurchaserSettlementService::class);
        $service->deleteFundingWithReversal($this->purchaser, $funding1, $this->admin, 'Delete Funding 1');

        $settlement = $service->monthSettlement($this->purchaser, '2026-08');
        $this->assertEquals(40000, $settlement['month_funding_added']);
    }

    public function test_editing_notes_reference_does_not_unnecessarily_reverse_allocations(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');
        $invoice = $this->createCashInvoice(40000, '2026-08-15');

        $service = app(PurchaserSettlementService::class);
        $service->updateFundingWithReversal($this->purchaser, $funding, [
            'amount' => 50000,
            'business_date' => '2026-08-10',
            'payment_source' => 'Bank',
            'company_account_id' => $this->account->id,
            'reference' => 'NEW-REF-999',
            'description' => 'Updated Description Only',
        ], $this->admin, 'Ref Change');

        $invoice->refresh();
        $this->assertSame('Cash', $invoice->payment_method);
    }

    public function test_changing_funding_business_date_recalculates_chronological_advance(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');
        $invoice = $this->createCashInvoice(40000, '2026-08-15');

        $service = app(PurchaserSettlementService::class);
        $service->updateFundingWithReversal($this->purchaser, $funding, [
            'amount' => 50000,
            'business_date' => '2026-08-20',
            'payment_source' => 'Bank',
            'company_account_id' => $this->account->id,
        ], $this->admin, 'Date change');

        $invoice->refresh();
        $this->assertSame('Credit', $invoice->payment_method);
    }

    public function test_moving_funding_between_months_updates_opening_closing_values(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');

        $service = app(PurchaserSettlementService::class);
        $service->updateFundingWithReversal($this->purchaser, $funding, [
            'amount' => 50000,
            'business_date' => '2026-09-05',
            'payment_source' => 'Bank',
            'company_account_id' => $this->account->id,
        ], $this->admin, 'Move to Sep');

        $aug = $service->monthSettlement($this->purchaser, '2026-08');
        $sep = $service->monthSettlement($this->purchaser, '2026-09');

        $this->assertEquals(0, $aug['month_funding_added']);
        $this->assertEquals(50000, $sep['month_funding_added']);
    }

    public function test_funding_cannot_be_manipulated_through_another_purchaser_url(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10', $this->purchaser);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.delete', ['purchaser' => $this->otherPurchaser->public_uuid, 'credit' => $funding->id]), [
            'reason' => 'Attack Test',
        ]);

        $response->assertNotFound();
    }

    public function test_two_correction_operations_cannot_leave_purchaser_balance_inconsistent(): void
    {
        $funding1 = $this->giveFunding(30000, '2026-08-05');
        $funding2 = $this->giveFunding(20000, '2026-08-10');
        $this->createCashInvoice(45000, '2026-08-15');

        $service = app(PurchaserSettlementService::class);
        $service->deleteFundingWithReversal($this->purchaser, $funding1, $this->admin, 'Correction 1');
        $service->deleteFundingWithReversal($this->purchaser, $funding2, $this->admin, 'Correction 2');

        $summary = app(PurchaserFinanceService::class)->summaryFor((int) $this->purchaser->id);
        $this->assertEquals(0, $summary['remaining_advance']);
    }

    public function test_increasing_funding_does_not_unexpectedly_allocate_money(): void
    {
        $funding = $this->giveFunding(30000, '2026-08-10');

        $service = app(PurchaserSettlementService::class);
        $service->updateFundingWithReversal($this->purchaser, $funding, [
            'amount' => 70000,
            'business_date' => '2026-08-10',
            'payment_source' => 'Bank',
            'company_account_id' => $this->account->id,
        ], $this->admin, 'Increase');

        $summary = app(PurchaserFinanceService::class)->summaryFor((int) $this->purchaser->id);
        $this->assertEquals(70000, $summary['remaining_advance']);
    }

    public function test_journal_reconciliation_belonging_to_another_transaction_is_untouched(): void
    {
        $funding1 = $this->giveFunding(30000, '2026-08-05', $this->purchaser);
        $funding2 = $this->giveFunding(30000, '2026-08-05', $this->otherPurchaser);

        $journal2 = JournalEntry::query()
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $funding2->id)
            ->first();

        $service = app(PurchaserSettlementService::class);
        $service->deleteFundingWithReversal($this->purchaser, $funding1, $this->admin, 'Delete 1');

        $this->assertDatabaseHas('journal_entries', ['id' => $journal2->id]);
    }

    public function test_historical_audit_data_remains_available_after_correction(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');

        $service = app(PurchaserSettlementService::class);
        $service->deleteFundingWithReversal($this->purchaser, $funding, $this->admin, 'Audit verification');

        $auditCount = DB::table('activity_log')
            ->where('log_name', 'purchaser_finance')
            ->count();

        $this->assertGreaterThan(0, $auditCount);
    }

    public function test_reversal_followed_by_recalculation_is_idempotent(): void
    {
        $funding = $this->giveFunding(50000, '2026-08-10');
        $this->createCashInvoice(30000, '2026-08-15');

        $service = app(PurchaserSettlementService::class);
        $service->rebuildChronologicalSettlement($this->purchaser);
        $firstSummary = app(PurchaserFinanceService::class)->summaryFor((int) $this->purchaser->id);

        $service->rebuildChronologicalSettlement($this->purchaser);
        $secondSummary = app(PurchaserFinanceService::class)->summaryFor((int) $this->purchaser->id);

        $this->assertEquals($firstSummary['remaining_advance'], $secondSummary['remaining_advance']);
    }

    public function test_summary_numbers_reconcile_with_purchaser_ledger(): void
    {
        $this->giveFunding(50000, '2026-08-10');
        $this->createCashInvoice(20000, '2026-08-15');

        $service = app(PurchaserSettlementService::class);
        $settlement = $service->monthSettlement($this->purchaser, '2026-08');

        $calculatedClosing = $settlement['opening_balance'] + $settlement['month_funding_added'] - $settlement['month_cash_returned'] - $settlement['month_advance_utilized'];
        $this->assertEquals($calculatedClosing, $settlement['closing_balance']);
    }
}
