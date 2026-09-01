<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Finance\JournalService;
use App\Services\Finance\PurchaserFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class JournalEntryAdminEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private User $otherPurchaser;

    private User $staff;

    private Supplier $supplier;

    private CompanyAccount $bankAccount;

    private CompanyAccount $cashAccount;

    private PurchaserFinanceService $purchaserFinanceService;

    private CompanyPaymentReconciliationService $reconciliationService;

    private JournalService $journalService;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'purchaser', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['registration_status' => 'approved']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->purchaser = User::factory()->create(['name' => 'Purchaser Alpha', 'registration_status' => 'approved']);
        $this->purchaser->assignRole('purchaser');

        $this->otherPurchaser = User::factory()->create(['name' => 'Purchaser Beta', 'registration_status' => 'approved']);
        $this->otherPurchaser->assignRole('purchaser');

        $this->staff = User::factory()->create(['name' => 'Staff User', 'registration_status' => 'approved']);
        $this->staff->assignRole('staff');

        $this->supplier = Supplier::factory()->create(['name' => 'Fresh Farm Supplier']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bankAccount = CompanyAccount::query()->create([
            'name' => 'HDFC Current Account',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'enabled' => true,
            'current_balance' => 100000.00,
        ]);

        $this->cashAccount = CompanyAccount::query()->create([
            'name' => 'Main Office Cash Vault',
            'account_type' => 'cash',
            'enabled' => true,
            'current_balance' => 50000.00,
        ]);

        Account::query()->firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1200'], ['name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1300'], ['name' => 'Purchaser Advances', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        $this->purchaserFinanceService = app(PurchaserFinanceService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
        $this->journalService = app(JournalService::class);
    }

    /**
     * 1. Admin can correct journal entry with balanced reversal and replacement.
     */
    public function test_admin_can_correct_journal_entry_with_balanced_reversal_and_replacement(): void
    {
        $this->withoutExceptionHandling();
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 20000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-ORIG-20K',
            'description' => 'Original funding',
        ]);

        $originalJournal = JournalEntry::query()
            ->where('reference', 'UTR-ORIG-20K')
            ->firstOrFail();

        $this->assertEquals(20000.00, $originalJournal->primary_amount);

        // 2. Admin edits amount to 35,000 and changes purchaser to Purchaser Beta
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 35000.00,
            'entry_date' => '2026-08-26',
            'purchaser_id' => $this->otherPurchaser->id,
            'company_account_id' => $this->bankAccount->id,
            'payment_source' => 'Bank',
            'reference' => 'UTR-CORRECTED-35K',
            'description' => 'Corrected funding to Purchaser Beta',
            'reason' => 'Wrong amount and purchaser entered accidentally',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 3. Verify original journal entry is immutable and marked reversed
        $originalJournal->refresh();
        $this->assertEquals(20000.00, $originalJournal->primary_amount);
        $this->assertStringContainsString('[REVERSED by JE #', (string) $originalJournal->description);

        // 4. Verify balanced reversal journal entry was created
        $reversalJournal = JournalEntry::query()
            ->where('source_event', "reversal:{$originalJournal->id}")
            ->firstOrFail();

        $this->assertEquals(20000.00, $reversalJournal->primary_amount);
        $this->assertTrue($reversalJournal->is_balanced);
        $this->assertEquals('REV-JE-'.$originalJournal->id, $reversalJournal->reference);

        // Verify reversal debit/credit lines invert the original
        $revDebit = $reversalJournal->transactions()->where('type', 'debit')->first();
        $revCredit = $reversalJournal->transactions()->where('type', 'credit')->first();
        $this->assertEquals('1020', $revDebit->account->code);
        $this->assertEquals('1300', $revCredit->account->code);

        // 5. Verify replacement journal entry was created with new values
        $replacementJournal = JournalEntry::query()
            ->where('reference', 'UTR-CORRECTED-35K')
            ->firstOrFail();

        $this->assertEquals(35000.00, $replacementJournal->primary_amount);
        $this->assertTrue($replacementJournal->is_balanced);
        $this->assertStringContainsString('Replacement for JE #'.$originalJournal->id, (string) $replacementJournal->description);

        // 6. Verify purchaser balances
        $alphaSummary = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $betaSummary = $this->purchaserFinanceService->summaryFor($this->otherPurchaser->id);

        // Alpha has 20k in + 20k reversal offset (type out) = 0 net advance
        $this->assertEquals(0.00, $alphaSummary['remaining_advance']);
        // Beta has 35k replacement advance
        $this->assertEquals(35000.00, $betaSummary['remaining_advance']);
    }

    /**
     * 2. Setting amount to 0 cancels the journal entry without replacement.
     */
    public function test_admin_cancelling_journal_entry_with_zero_amount_reverses_and_does_not_create_replacement(): void
    {
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 15000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Cash',
            'company_account_id' => $this->cashAccount->id,
            'reference' => 'CASH-ADV-15K',
            'description' => 'Accidental cash entry',
        ]);

        $originalJournal = JournalEntry::query()->where('reference', 'CASH-ADV-15K')->firstOrFail();

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 0.00,
            'entry_date' => '2026-08-25',
            'reason' => 'Entered by mistake, cancelling completely',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify reversal created
        $reversalJournal = JournalEntry::query()->where('source_event', "reversal:{$originalJournal->id}")->firstOrFail();
        $this->assertEquals(15000.00, $reversalJournal->primary_amount);

        // Verify NO replacement journal entry was created
        $this->assertEquals(2, JournalEntry::query()->count()); // 1 original + 1 reversal

        // Verify purchaser balance is 0
        $summary = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $this->assertEquals(0.00, $summary['remaining_advance']);
    }

    /**
     * 3. Cannot edit or reverse an already reversed journal entry.
     */
    public function test_admin_cannot_edit_already_reversed_journal_entry(): void
    {
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 10000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-TEST-10K',
            'description' => 'Test',
        ]);

        $originalJournal = JournalEntry::query()->where('reference', 'UTR-TEST-10K')->firstOrFail();

        // First edit / cancellation
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 0.00,
            'entry_date' => '2026-08-25',
            'reason' => 'First cancellation',
        ]);

        // Attempt second edit on original entry
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 5000.00,
            'entry_date' => '2026-08-25',
            'reason' => 'Second edit attempt',
        ]);

        $response->assertSessionHasErrors('journal_entry');
    }

    /**
     * 4. Reversal requires valid reason with minimum characters.
     */
    public function test_reversal_requires_valid_reason_with_min_characters(): void
    {
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 10000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-REASON-TEST',
            'description' => 'Test',
        ]);

        $originalJournal = JournalEntry::query()->where('reference', 'UTR-REASON-TEST')->firstOrFail();

        // Empty reason
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 5000.00,
            'entry_date' => '2026-08-25',
            'reason' => '',
        ]);
        $response->assertSessionHasErrors('reason');

        // Short reason (< 3 chars)
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 5000.00,
            'entry_date' => '2026-08-25',
            'reason' => 'ok',
        ]);
        $response->assertSessionHasErrors('reason');
    }

    /**
     * 5. Utilized funding fixture: Original ₹1,000, Utilized ₹700, Corrected to ₹800 -> Final advance ₹100.
     */
    public function test_admin_can_correct_utilized_funding_with_valid_final_position_fixture(): void
    {
        // 1. Give 1,000 advance
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 1000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-FIX-1000',
            'description' => 'Original funding 1000',
        ]);

        $originalJournal = JournalEntry::query()->where('reference', 'UTR-FIX-1000')->firstOrFail();

        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create(['amount' => 700.00]);

        // 2. Purchaser utilizes 700 on purchase bills
        $purchaseCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 700.00,
            'business_date' => '2026-08-26',
            'description' => 'Tomato purchase bill #INV-001',
            'payment_source' => 'Advance',
            'purchase_invoice_id' => $invoice->id,
            'created_by' => $this->admin->id,
        ]);

        // Current available advance is ₹300 (1,000 in - 700 out)
        $initialSummary = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $this->assertEquals(300.00, $initialSummary['remaining_advance']);

        // 3. Admin corrects funding to ₹800 (valid final position = 300 - 1000 + 800 = 100)
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 800.00,
            'entry_date' => '2026-08-25',
            'reference' => 'UTR-FIX-800',
            'description' => 'Corrected funding to 800',
            'reason' => 'Entry typo was 1000 instead of 800',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 4. Verify legitimate ₹700 purchase bill usage is preserved untouched
        $this->assertDatabaseHas('purchaser_credits', [
            'id' => $purchaseCredit->id,
            'amount' => 700.00,
            'purchase_invoice_id' => $invoice->id,
        ]);

        // 5. Verify final available advance is exactly ₹100
        $finalSummary = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $this->assertEquals(100.00, $finalSummary['remaining_advance']);
    }

    /**
     * 6. Increasing utilized advance and correcting description/reference works without error.
     */
    public function test_admin_can_increase_utilized_advance_and_update_description_reference(): void
    {
        // 1. Give 1,000 advance
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 1000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-INC-1000',
            'description' => 'Advance 1000',
        ]);

        $originalJournal = JournalEntry::query()->where('reference', 'UTR-INC-1000')->firstOrFail();

        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create(['amount' => 700.00]);

        // 2. Purchaser spends 700 on purchase bills
        PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 700.00,
            'business_date' => '2026-08-26',
            'description' => 'Vegetable purchase bill',
            'payment_source' => 'Advance',
            'purchase_invoice_id' => $invoice->id,
            'created_by' => $this->admin->id,
        ]);

        // 3. Admin increases funding to 1,500 and updates reference
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 1500.00,
            'entry_date' => '2026-08-25',
            'reference' => 'UTR-INC-1500',
            'description' => 'Updated narration with increased funding',
            'reason' => 'Increased funding after purchaser requested more advance',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Final available advance = 300 - 1000 + 1500 = 800
        $finalSummary = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $this->assertEquals(800.00, $finalSummary['remaining_advance']);
    }

    /**
     * 7. Reduction below usage requires explicit confirmation and preserves bills.
     */
    public function test_reduction_below_usage_requires_explicit_confirmation_and_preserves_bills(): void
    {
        // 1. Give 1,000 advance
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 1000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-DEFICIT-1000',
            'description' => 'Advance 1000',
        ]);

        $originalJournal = JournalEntry::query()->where('reference', 'UTR-DEFICIT-1000')->firstOrFail();

        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create(['amount' => 700.00]);

        // 2. Purchaser utilizes 700 on purchase bills
        $purchaseCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 700.00,
            'business_date' => '2026-08-26',
            'description' => 'Potato purchase bill #INV-002',
            'payment_source' => 'Advance',
            'purchase_invoice_id' => $invoice->id,
            'created_by' => $this->admin->id,
        ]);

        // 3. First attempt: Reduce funding to 500 without confirm_shortfall -> rejected with warning
        $unconfirmedResponse = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 500.00,
            'entry_date' => '2026-08-25',
            'reason' => 'Typo correction to 500',
            'confirm_shortfall' => false,
        ]);

        $unconfirmedResponse->assertSessionHasErrors('amount');

        // 4. Second attempt: Submit with explicit confirm_shortfall = 1 -> succeeds!
        $confirmedResponse = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 500.00,
            'entry_date' => '2026-08-25',
            'reference' => 'UTR-DEFICIT-500',
            'reason' => 'Typo correction to 500 with purchaser shortfall acknowledged',
            'confirm_shortfall' => true,
        ]);

        $confirmedResponse->assertRedirect();
        $confirmedResponse->assertSessionHas('success');

        // 5. Legitimate ₹700 purchase bill remains active
        $this->assertDatabaseHas('purchaser_credits', [
            'id' => $purchaseCredit->id,
            'amount' => 700.00,
            'purchase_invoice_id' => $invoice->id,
        ]);

        // 6. Resulting purchaser advance accurately reflects deficit (-200.00)
        $summary = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $this->assertEquals(-200.00, $summary['remaining_advance']);
    }

    /**
     * 8. Zero cancellation with spent advance requires confirmation and preserves bills.
     */
    public function test_zero_cancellation_with_spent_advance_requires_confirmation_and_preserves_bills(): void
    {
        // 1. Give 1,000 advance
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 1000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-CANCEL-1000',
            'description' => 'Advance 1000',
        ]);

        $originalJournal = JournalEntry::query()->where('reference', 'UTR-CANCEL-1000')->firstOrFail();

        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create(['amount' => 700.00]);

        // 2. Purchaser utilizes 700 on purchase bills
        $purchaseCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 700.00,
            'business_date' => '2026-08-26',
            'description' => 'Onion purchase bill #INV-003',
            'payment_source' => 'Advance',
            'purchase_invoice_id' => $invoice->id,
            'created_by' => $this->admin->id,
        ]);

        // 3. First attempt without confirm_shortfall -> rejected
        $unconfirmed = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 0.00,
            'entry_date' => '2026-08-25',
            'reason' => 'Accidental duplicate entry',
            'confirm_shortfall' => false,
        ]);
        $unconfirmed->assertSessionHasErrors('amount');

        // 4. Second attempt with confirm_shortfall = 1 -> succeeds!
        $confirmed = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 0.00,
            'entry_date' => '2026-08-25',
            'reason' => 'Accidental duplicate entry confirmed',
            'confirm_shortfall' => true,
        ]);
        $confirmed->assertRedirect();
        $confirmed->assertSessionHas('success');

        // 5. Purchase bill preserved
        $this->assertDatabaseHas('purchaser_credits', [
            'id' => $purchaseCredit->id,
            'amount' => 700.00,
            'purchase_invoice_id' => $invoice->id,
        ]);

        // 6. Net advance deficit = -700.00
        $summary = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $this->assertEquals(-700.00, $summary['remaining_advance']);
    }

    /**
     * 9. Reassigning purchaser for utilized funding requires confirmation and does not move bills.
     */
    public function test_reassigning_purchaser_for_utilized_funding_requires_confirmation_and_does_not_move_bills(): void
    {
        // 1. Give 1,000 advance to Purchaser Alpha
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 1000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-REASSIGN-1000',
            'description' => 'Advance to Alpha',
        ]);

        $originalJournal = JournalEntry::query()->where('reference', 'UTR-REASSIGN-1000')->firstOrFail();

        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create(['amount' => 700.00]);

        // 2. Purchaser Alpha spends 700 on purchase bills
        $purchaseCredit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 700.00,
            'business_date' => '2026-08-26',
            'description' => 'Garlic purchase bill #INV-004',
            'payment_source' => 'Advance',
            'purchase_invoice_id' => $invoice->id,
            'created_by' => $this->admin->id,
        ]);

        // 3. First attempt to reassign to Purchaser Beta without confirm_shortfall -> rejected
        $unconfirmed = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 800.00,
            'purchaser_id' => $this->otherPurchaser->id,
            'entry_date' => '2026-08-25',
            'reason' => 'Should have been Purchaser Beta',
            'confirm_shortfall' => false,
        ]);
        $unconfirmed->assertSessionHasErrors('purchaser_id');

        // 4. Second attempt with confirm_shortfall = 1 -> succeeds!
        $confirmed = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $originalJournal->id), [
            'amount' => 800.00,
            'purchaser_id' => $this->otherPurchaser->id,
            'entry_date' => '2026-08-25',
            'reason' => 'Reassigned to Purchaser Beta with deficit on Alpha acknowledged',
            'confirm_shortfall' => true,
        ]);
        $confirmed->assertRedirect();
        $confirmed->assertSessionHas('success');

        // 5. Purchaser Alpha retains the ₹700 purchase bill and has -700.00 advance deficit
        $this->assertDatabaseHas('purchaser_credits', [
            'id' => $purchaseCredit->id,
            'purchaser_id' => $this->purchaser->id,
            'amount' => 700.00,
        ]);
        $alphaSummary = $this->purchaserFinanceService->summaryFor($this->purchaser->id);
        $this->assertEquals(-700.00, $alphaSummary['remaining_advance']);

        // 6. Purchaser Beta receives ₹800 replacement advance with 0 bills
        $betaSummary = $this->purchaserFinanceService->summaryFor($this->otherPurchaser->id);
        $this->assertEquals(800.00, $betaSummary['remaining_advance']);
    }

    /**
     * 6. Imported statement entries are un-matched and preserved when journal is reversed.
     */
    public function test_imported_statement_unmatched_and_preserved_when_journal_reversed(): void
    {
        // 1. Create imported statement entry
        $importedStmt = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankAccount->id,
            'transaction_date' => '2026-08-25',
            'direction' => 'out',
            'amount' => 30000.00,
            'reference' => 'HDFC-IMP-30K',
            'narration' => 'NEFT TO PURCHASER ALPHA',
            'source' => 'imported',
            'import_file_name' => 'hdfc_august.csv',
            'import_fingerprint' => 'FINGERPRINT-30K',
            'status' => 'unmatched',
            'matched_amount' => 0.00,
            'imported_by' => $this->admin->id,
        ]);

        // 2. Create funding and match statement
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 30000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'HDFC-IMP-30K',
            'description' => 'Matched funding',
        ]);

        $journal = JournalEntry::query()->where('reference', 'HDFC-IMP-30K')->firstOrFail();
        $this->reconciliationService->reconcileStatementJournal($importedStmt, $journal, 30000.00, $this->admin->id);

        $importedStmt->refresh();
        $this->assertEquals('reconciled', $importedStmt->status);
        $this->assertEquals(30000.00, $importedStmt->matched_amount);

        // 3. Admin edits/cancels journal entry
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $journal->id), [
            'amount' => 0.00,
            'entry_date' => '2026-08-25',
            'reason' => 'Wrong funding match reversed',
        ]);

        // 4. Verify imported statement is UNMATCHED, preserved in database, not deleted
        $importedStmt->refresh();
        $this->assertEquals('unmatched', $importedStmt->status);
        $this->assertEquals(0.00, $importedStmt->matched_amount);
        $this->assertNull($importedStmt->journal_entry_id);
        $this->assertFalse($importedStmt->is_finalized);
    }

    /**
     * 7. Manual counterpart statement entry is marked reversed and balance restored.
     */
    public function test_manual_counterpart_marked_reversed_and_account_balance_restored(): void
    {
        $initialBalance = (float) $this->cashAccount->current_balance;

        // 1. Create cash funding of 12,000 (creates manual statement counterpart)
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 12000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Cash',
            'company_account_id' => $this->cashAccount->id,
            'reference' => 'CASH-12K-MANUAL',
            'description' => 'Cash handed to purchaser',
            'created_by' => $this->admin->id,
        ]);

        $journal = $this->journalService->recordPurchaserCredit($credit);

        $manualStmt = $this->reconciliationService->createStatementEntry([
            'company_account_id' => $this->cashAccount->id,
            'transaction_date' => '2026-08-25',
            'direction' => 'out',
            'amount' => 12000.00,
            'reference' => 'CASH-12K-MANUAL',
            'narration' => 'Cash handed to purchaser',
            'source' => 'manual',
            'source_type' => PurchaserCredit::class,
            'source_id' => $credit->id,
        ], $this->admin->id);

        $this->reconciliationService->reconcileStatementJournal($manualStmt, $journal, 12000.00, $this->admin->id);

        // Account balance decreased by 12,000
        $this->assertEquals($initialBalance - 12000.00, (float) $this->cashAccount->fresh()->current_balance);

        // 2. Admin cancels journal entry
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $journal->id), [
            'amount' => 0.00,
            'entry_date' => '2026-08-25',
            'reason' => 'Cash was not handed over, reversing',
        ]);

        // 3. Verify manual statement is marked reversed and balance restored
        $manualStmt->refresh();
        $this->assertEquals('reversed', $manualStmt->status);
        $this->assertFalse($manualStmt->is_finalized);
        $this->assertEquals($initialBalance, (float) $this->cashAccount->fresh()->current_balance);
    }

    /**
     * 8. Non-admin cannot edit or reverse journal entries.
     */
    public function test_non_admin_cannot_edit_or_reverse_journal_entry(): void
    {
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 10000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-AUTH-TEST',
            'description' => 'Test',
        ]);

        $journal = JournalEntry::query()->where('reference', 'UTR-AUTH-TEST')->firstOrFail();

        // Attempt update as staff user
        $response = $this->actingAs($this->staff)->post(route('admin.cashbook.finance.journal.entry-update', $journal->id), [
            'amount' => 5000.00,
            'entry_date' => '2026-08-25',
            'reason' => 'Unauthorized attempt',
        ]);

        $response->assertForbidden();
    }

    /**
     * 9. Activity audit log records actor, reason, and previous values.
     */
    public function test_audit_log_records_actor_reason_and_previous_values(): void
    {
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 18000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-AUDIT-LOG',
            'description' => 'Audit log test',
        ]);

        $journal = JournalEntry::query()->where('reference', 'UTR-AUDIT-LOG')->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.journal.entry-update', $journal->id), [
            'amount' => 22000.00,
            'entry_date' => '2026-08-25',
            'reason' => 'Increased advance after vendor phone call',
        ]);

        $activity = Activity::query()
            ->where('log_name', 'finance_journal')
            ->where('subject_id', $journal->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals($this->admin->id, $activity->causer_id);
        $this->assertEquals('Increased advance after vendor phone call', $activity->properties['reason']);
        $this->assertEquals(18000.00, $activity->properties['old_values']['primary_amount']);
        $this->assertEquals(22000.00, $activity->properties['new_values']['amount']);
    }

    /**
     * 10. UI edit action and view rendering.
     */
    public function test_ui_edit_action_route_and_view_rendering(): void
    {
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 10000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-VIEW-TEST',
            'description' => 'View test',
        ]);

        $journal = JournalEntry::query()->where('reference', 'UTR-VIEW-TEST')->firstOrFail();

        // 1. List view contains Edit Entry action and modal
        $listResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'));
        $listResponse->assertOk();
        $listResponse->assertSee('Edit Journal Entry');
        $listResponse->assertSee('editJournalModal');

        // 2. Show view contains Edit Entry button and modal
        $showResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal.entry-show', $journal->id));
        $showResponse->assertOk();
        $showResponse->assertSee('Edit Entry');
        $showResponse->assertSee('editJournalModal');
    }

    /**
     * 11. Verifies journal page query execution across all category tabs and reconciliation statuses.
     */
    public function test_journal_page_loads_cleanly_across_all_filter_tabs_and_reconciliation_statuses(): void
    {
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.store', $this->purchaser->public_uuid), [
            'amount' => 10000.00,
            'business_date' => '2026-08-25',
            'payment_source' => 'Bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'UTR-TABS-TEST',
            'description' => 'Tabs test',
        ]);

        $tabs = ['all', 'bank', 'cash', 'income', 'expense', 'purchaser_funding', 'vendor_payment', 'customer_receipt', 'transfer', 'adjustment'];
        foreach ($tabs as $tab) {
            $res = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal', ['tab' => $tab]));
            $res->assertOk();
        }

        $statuses = ['all', 'unreconciled', 'partially_reconciled', 'reconciled', 'finalized'];
        foreach ($statuses as $status) {
            $res = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal', ['status' => $status]));
            $res->assertOk();
        }
    }
}
