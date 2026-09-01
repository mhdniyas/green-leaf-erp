<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookTransactionSafeEditAndReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopUser;

    private User $purchaserUser;

    private Shop $sana;

    private ShopLedgerProfile $sanaProfile;

    private CompanyAccount $cashBox;

    private CompanyAccount $hdfcBank;

    private LedgerEntryType $cashSalesType;

    private LedgerEntryType $shopPaidCompanyType;

    private DailyLedgerService $dailyLedgerService;

    private CompanyPaymentReconciliationService $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@greenleaf.test',
        ]);
        $this->admin->assignRole('admin');

        $this->shopUser = User::factory()->create([
            'email' => 'shopuser@greenleaf.test',
        ]);
        $this->shopUser->assignRole('shop');

        $this->purchaserUser = User::factory()->create([
            'email' => 'purchaser@greenleaf.test',
        ]);
        $this->purchaserUser->assignRole('purchaser');

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash Box Account', 'type' => 'asset', 'group' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'HDFC Bank Account', 'type' => 'asset', 'group' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'group' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'group' => 'revenue', 'is_active' => true]);

        $this->cashBox = CompanyAccount::create([
            'name' => 'Main Cash Box',
            'account_type' => 'cash',
            'current_balance' => 10000.00,
            'enabled' => true,
        ]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC Current Account',
            'account_type' => 'bank',
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);

        $this->sana = Shop::factory()->create([
            'name' => 'Sana Collections',
            'code' => 'SANA',
            'status' => 'active',
        ]);

        $this->sanaProfile = ShopLedgerProfile::create([
            'shop_id' => $this->sana->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'sana-collections',
            'code' => $this->sana->code,
            'name' => $this->sana->name,
            'enabled' => true,
        ]);

        $this->cashSalesType = LedgerEntryType::firstOrCreate(
            ['code' => 'cash_sales'],
            ['name' => 'Cash Sales', 'category' => 'income', 'display_order' => 1, 'active' => true, 'is_system' => true]
        );

        $this->shopPaidCompanyType = LedgerEntryType::firstOrCreate(
            ['code' => 'shop_paid_company'],
            ['name' => 'Shop Remittance to Company', 'category' => 'expense', 'display_order' => 99, 'active' => true, 'is_system' => true]
        );

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->cashSalesType->id,
            'company_account_id' => $this->cashBox->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'cash',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
        ]);

        $this->dailyLedgerService = app(DailyLedgerService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
    }

    /**
     * Test Accidental Reconciled Cash Transaction Reversal ("Cash given to Purchaser").
     */
    public function test_reversing_accidental_reconciled_cash_transaction_rolls_back_all_effects_and_preserves_audit(): void
    {
        $businessDate = '2026-08-28';
        $initialCashBalance = (float) $this->cashBox->current_balance;

        // 1. Record transaction: "Cash given to Purchaser"
        $recordResult = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => 'cash_sales',
            'entry_type_id' => $this->cashSalesType->id,
            'amount' => 5000.00,
            'funding_source' => 'cash',
            'company_account_id' => $this->cashBox->id,
            'notes' => 'Cash given to Purchaser by mistake',
            'entered_by' => $this->shopUser->id,
        ]);

        /** @var ShopLedgerTransaction $tx */
        $tx = $recordResult['transaction'];

        // 2. Approve transaction
        $this->dailyLedgerService->approveEntry($tx, $this->admin->id);

        // Find linked pending statement
        $statement = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $tx->id)
            ->firstOrFail();

        // 3. Verify / Reconcile transaction into company cash box
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);

        $this->cashBox->refresh();
        $this->assertEquals($initialCashBalance + 5000.00, (float) $this->cashBox->current_balance);

        // Prove settlement transaction exists
        $settlementTx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->sana->id)
            ->where('reference_type', CompanyAccountStatementEntry::class)
            ->where('reference_id', $statement->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'shop_paid_company'))
            ->first();
        $this->assertNotNull($settlementTx);
        $this->assertNotEquals('void', $settlementTx->status);

        // Prove journal entry exists
        $statement->refresh();
        $this->assertNotNull($statement->journal_entry_id);
        $originalJournalId = $statement->journal_entry_id;

        // 4. Reverse transaction via HTTP as Admin
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.reverse', $tx->id), [
            'reason' => 'Accidental cash given to purchaser entry in production',
            'confirm' => 'REVERSE',
        ]);

        $response->assertRedirect(route('admin.cashbook.transaction.show', $tx->id));
        $response->assertSessionHas('success');

        // Verify:
        // A. Original ShopLedgerTransaction remains in DB with status REVERSED
        $tx->refresh();
        $this->assertEquals('reversed', $tx->status);
        $this->assertStringContainsString('Reversed by admin', (string) $tx->notes);
        $this->assertEquals($this->admin->id, $tx->voided_by);
        $this->assertEquals('Accidental cash given to purchaser entry in production', $tx->void_reason);

        // B. Company account balance restored back to initial
        $this->cashBox->refresh();
        $this->assertEquals($initialCashBalance, (float) $this->cashBox->current_balance);

        // C. Original journal entry retained and reversing journal entry created
        $this->assertDatabaseHas('journal_entries', ['id' => $originalJournalId]);
        $reversalJournal = JournalEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $tx->id)
            ->where('source_event', 'reversal:'.$tx->id)
            ->first();
        $this->assertNotNull($reversalJournal);
        $this->assertStringContainsString('Reversal:', $reversalJournal->description);

        // D. Settlement reduction voided
        $settlementTx->refresh();
        $this->assertEquals('void', $settlementTx->status);

        // E. Statement entry voided / unfinalized
        $statement->refresh();
        $this->assertFalse((bool) $statement->is_finalized);
        $this->assertEquals('void', $statement->status);
    }

    /**
     * Test Idempotency: Double reverse does not duplicate balance changes or journals.
     */
    public function test_double_reverse_is_idempotent(): void
    {
        $businessDate = '2026-08-28';
        $initialBalance = (float) $this->cashBox->current_balance;

        $recordResult = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => 'cash_sales',
            'entry_type_id' => $this->cashSalesType->id,
            'amount' => 2000.00,
            'funding_source' => 'cash',
            'company_account_id' => $this->cashBox->id,
            'entered_by' => $this->shopUser->id,
        ]);

        $tx = $recordResult['transaction'];
        $this->dailyLedgerService->approveEntry($tx, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)->where('source_id', $tx->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);

        // First reverse
        $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.reverse', $tx->id), [
            'reason' => 'First attempt',
            'confirm' => 'REVERSE',
        ]);

        $this->cashBox->refresh();
        $this->assertEquals($initialBalance, (float) $this->cashBox->current_balance);

        $journalCountAfterFirst = JournalEntry::where('source_type', ShopLedgerTransaction::class)->where('source_id', $tx->id)->count();

        // Second reverse
        $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.reverse', $tx->id), [
            'reason' => 'Duplicate second attempt',
            'confirm' => 'REVERSE',
        ]);

        $this->cashBox->refresh();
        // Balance remains unchanged
        $this->assertEquals($initialBalance, (float) $this->cashBox->current_balance);

        $journalCountAfterSecond = JournalEntry::where('source_type', ShopLedgerTransaction::class)->where('source_id', $tx->id)->count();
        $this->assertEquals($journalCountAfterFirst, $journalCountAfterSecond);
    }

    /**
     * Test Correct Reconciled Transaction Workflow.
     */
    public function test_correcting_reconciled_transaction_reverses_effects_and_resets_to_posted(): void
    {
        $businessDate = '2026-08-28';
        $initialBalance = (float) $this->cashBox->current_balance;

        $recordResult = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => 'cash_sales',
            'entry_type_id' => $this->cashSalesType->id,
            'amount' => 4000.00,
            'funding_source' => 'cash',
            'company_account_id' => $this->cashBox->id,
            'entered_by' => $this->shopUser->id,
        ]);

        $tx = $recordResult['transaction'];
        $this->dailyLedgerService->approveEntry($tx, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)->where('source_id', $tx->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);

        // Put correction via PUT request
        $response = $this->actingAs($this->admin)->put(route('admin.cashbook.transaction.update', $tx->id), [
            'amount' => 3500.00,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->cashSalesType->id,
            'notes' => 'Corrected collection amount',
            'reversal_reason' => 'Admin correcting overstated amount',
        ]);

        $response->assertRedirect(route('admin.cashbook.transaction.show', $tx->id));

        $tx->refresh();
        $this->assertEquals(3500.00, (float) $tx->amount);
        $this->assertEquals('posted', $tx->status);

        // Company balance restored back to initial (prior to re-verification)
        $this->cashBox->refresh();
        $this->assertEquals($initialBalance, (float) $this->cashBox->current_balance);
    }

    /**
     * Test Unreconciled Transaction Edit and Delete.
     */
    public function test_unreconciled_transaction_can_be_edited_and_deleted(): void
    {
        $recordResult = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-28',
            'entry_type_code' => 'cash_sales',
            'entry_type_id' => $this->cashSalesType->id,
            'amount' => 1500.00,
            'funding_source' => 'cash',
            'notes' => 'Draft unverified note',
            'entered_by' => $this->shopUser->id,
        ]);

        $tx = $recordResult['transaction'];

        // 1. Edit unreconciled
        $response = $this->actingAs($this->admin)->put(route('admin.cashbook.transaction.update', $tx->id), [
            'amount' => 1800.00,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->cashSalesType->id,
            'notes' => 'Updated unverified note',
        ]);

        $response->assertRedirect(route('admin.cashbook.transaction.show', $tx->id));
        $tx->refresh();
        $this->assertEquals(1800.00, (float) $tx->amount);
        $this->assertEquals('Updated unverified note', $tx->notes);

        // 2. Delete unreconciled
        $delResponse = $this->actingAs($this->admin)->delete(route('admin.cashbook.transaction.delete', $tx->id));
        $delResponse->assertRedirect(route('admin.cashbook.money-flow'));

        $this->assertDatabaseMissing('shop_ledger_transactions', ['id' => $tx->id]);
    }

    public function test_admin_can_revert_approved_unverified_transaction_to_posted_state_only(): void
    {
        $recordResult = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-28',
            'entry_type_code' => 'cash_sales',
            'entry_type_id' => $this->cashSalesType->id,
            'amount' => 2200.00,
            'funding_source' => 'cash',
            'company_account_id' => $this->cashBox->id,
            'entered_by' => $this->shopUser->id,
        ]);

        /** @var ShopLedgerTransaction $tx */
        $tx = $recordResult['transaction'];

        $this->dailyLedgerService->approveEntry($tx, $this->admin->id);

        $statement = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $tx->id)
            ->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.transaction.revert-approval', $tx->id));

        $response->assertRedirect(route('admin.cashbook.transaction.show', $tx->id));
        $response->assertSessionHas('success');

        $tx->refresh();
        $statement->refresh();

        $this->assertSame('posted', $tx->status);
        $this->assertNull($tx->approved_by);
        $this->assertSame('unmatched', $statement->status);
        $this->assertFalse((bool) $statement->is_finalized);
    }

    /**
     * Test Authorization: Non-admin users cannot reverse or correct transactions.
     */
    public function test_non_admin_users_are_forbidden_from_reversal(): void
    {
        $recordResult = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-28',
            'entry_type_code' => 'cash_sales',
            'entry_type_id' => $this->cashSalesType->id,
            'amount' => 1000.00,
            'funding_source' => 'cash',
            'entered_by' => $this->shopUser->id,
        ]);

        $tx = $recordResult['transaction'];

        // Shop user tries to reverse
        $response = $this->actingAs($this->shopUser)->post(route('admin.cashbook.transaction.reverse', $tx->id), [
            'reason' => 'Unauthorized attempt',
            'confirm' => 'REVERSE',
        ]);
        $response->assertStatus(403);

        // Purchaser user tries to reverse
        $responsePurchaser = $this->actingAs($this->purchaserUser)->post(route('admin.cashbook.transaction.reverse', $tx->id), [
            'reason' => 'Unauthorized attempt',
            'confirm' => 'REVERSE',
        ]);
        $responsePurchaser->assertStatus(403);
    }

    /**
     * Test Complete Full Correction Cycle: Verify 5,000 -> Correct to 4,000 -> Re-approve -> Re-verify.
     */
    public function test_full_correction_cycle_end_to_end_maintains_exact_net_financial_invariants(): void
    {
        $businessDate = '2026-08-28';
        $initialBalance = (float) $this->cashBox->current_balance;

        // Step 1: Record 5,000
        $recordResult = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => 'cash_sales',
            'entry_type_id' => $this->cashSalesType->id,
            'amount' => 5000.00,
            'funding_source' => 'cash',
            'company_account_id' => $this->cashBox->id,
            'entered_by' => $this->shopUser->id,
        ]);

        $tx = $recordResult['transaction'];
        $this->dailyLedgerService->approveEntry($tx, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)->where('source_id', $tx->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);

        $this->cashBox->refresh();
        $this->assertEquals($initialBalance + 5000.00, (float) $this->cashBox->current_balance);

        // Step 2: Correct to 4,000
        $this->actingAs($this->admin)->put(route('admin.cashbook.transaction.update', $tx->id), [
            'amount' => 4000.00,
            'business_date' => $businessDate,
            'entry_type_id' => $this->cashSalesType->id,
            'notes' => 'Corrected amount to 4000',
            'reversal_reason' => 'Fixing collection amount',
        ]);

        $tx->refresh();
        $this->assertEquals('posted', $tx->status);
        $this->assertEquals(4000.00, (float) $tx->amount);
        $this->cashBox->refresh();
        $this->assertEquals($initialBalance, (float) $this->cashBox->current_balance);

        // Step 3: Re-approve corrected transaction
        $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.approve', $tx->id));
        $tx->refresh();
        $this->assertEquals('approved', $tx->status);

        $statement->refresh();
        $this->assertEquals('unmatched', $statement->status);
        $this->assertFalse((bool) $statement->is_finalized);
        $this->assertEquals(4000.00, (float) $statement->amount);

        // Step 4: Re-verify corrected transaction
        $verifyRes = $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.verify', $tx->id));
        $verifyRes->assertSessionHas('success');

        $statement->refresh();
        $this->assertTrue((bool) $statement->is_finalized);
        $this->assertEquals('reconciled', $statement->status);
        $this->assertEquals(4000.00, (float) $statement->matched_amount);

        // Assert Final Financial State:
        // A. Company balance is exactly +4,000 from initial
        $this->cashBox->refresh();
        $this->assertEquals($initialBalance + 4000.00, (float) $this->cashBox->current_balance);

        // B. Exactly ONE active settlement reduction of 4,000
        $activeSettlement = ShopLedgerTransaction::where('shop_id', $this->sana->id)
            ->where('reference_type', CompanyAccountStatementEntry::class)
            ->where('reference_id', $statement->id)
            ->where('status', '!=', 'void')
            ->first();
        $this->assertNotNull($activeSettlement);
        $this->assertEquals(4000.00, (float) $activeSettlement->amount);

        // C. Net journal lines sum to exactly +4,000 debit to cash and -4,000 credit to AR
        $cashJournals = JournalEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $tx->id)
            ->with('transactions')
            ->get();
        $netCashDebit = 0.0;
        foreach ($cashJournals as $j) {
            foreach ($j->transactions as $t) {
                if ($t->account_id === (Account::where('code', '1010')->value('id'))) {
                    $netCashDebit += $t->type === 'debit' ? (float) $t->amount : -(float) $t->amount;
                }
            }
        }
        $this->assertEquals(4000.00, $netCashDebit);
    }

    /**
     * Test Bank Collection Reversal inverts CompanyAccount balance accurately.
     */
    public function test_bank_collection_reversal_inverts_balance_properly(): void
    {
        $initialBankBalance = (float) $this->hdfcBank->current_balance;

        $bankSalesType = LedgerEntryType::firstOrCreate(
            ['code' => 'hdfc_sales'],
            ['name' => 'HDFC Sales', 'category' => 'income', 'display_order' => 2, 'active' => true, 'is_system' => true]
        );

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $bankSalesType->id,
            'company_account_id' => $this->hdfcBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'bank',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
        ]);

        $recordResult = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-28',
            'entry_type_code' => 'hdfc_sales',
            'entry_type_id' => $bankSalesType->id,
            'amount' => 7500.00,
            'funding_source' => 'bank',
            'company_account_id' => $this->hdfcBank->id,
            'entered_by' => $this->shopUser->id,
        ]);

        $tx = $recordResult['transaction'];
        $this->dailyLedgerService->approveEntry($tx, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)->where('source_id', $tx->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);

        $this->hdfcBank->refresh();
        $this->assertEquals($initialBankBalance + 7500.00, (float) $this->hdfcBank->current_balance);

        // Reverse bank collection
        $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.reverse', $tx->id), [
            'reason' => 'Bank payment failed to settle',
            'confirm' => 'REVERSE',
        ]);

        $this->hdfcBank->refresh();
        $this->assertEquals($initialBankBalance, (float) $this->hdfcBank->current_balance);
    }
}
