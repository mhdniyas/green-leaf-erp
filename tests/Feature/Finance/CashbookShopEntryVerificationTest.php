<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashbookShopEntryVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $sana;

    private Shop $casio;

    private CompanyAccount $kotakBank;

    private CompanyAccount $cashBox;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashSalesType;

    private LedgerEntryType $shopPaidCompanyType;

    private DailyLedgerService $dailyLedgerService;

    private CompanyPaymentReconciliationService $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        // Financial accounts
        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        $this->sana = Shop::factory()->create(['name' => 'Sana', 'code' => 'SANA', 'status' => 'active']);
        $this->casio = Shop::factory()->create(['name' => 'Casio', 'code' => 'CASIO', 'status' => 'active']);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => '9988776655',
            'account_type' => 'bank',
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);

        $this->cashBox = CompanyAccount::create([
            'name' => 'Main Cash Box',
            'bank_name' => 'Main Company Cash Box',
            'account_number' => 'CASH-MAIN',
            'account_type' => 'cash',
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);

        $this->paytmType = LedgerEntryType::firstOrCreate(
            ['code' => 'paytm_sales'],
            [
                'name' => 'Paytm / UPI Collection',
                'category' => 'income',
                'display_order' => 10,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->cardType = LedgerEntryType::firstOrCreate(
            ['code' => 'card_sales'],
            [
                'name' => 'Card / POS Collection',
                'category' => 'income',
                'display_order' => 11,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->cashSalesType = LedgerEntryType::firstOrCreate(
            ['code' => 'cash_sales'],
            [
                'name' => 'Cash Sales',
                'category' => 'income',
                'display_order' => 1,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->shopPaidCompanyType = LedgerEntryType::firstOrCreate(
            ['code' => 'shop_paid_company'],
            [
                'name' => 'Shop Remittance to Company',
                'category' => 'expense',
                'display_order' => 99,
                'active' => true,
                'is_system' => true,
            ]
        );

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->kotakBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'settlement_behavior' => 'increase',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->shopPaidCompanyType->id,
            'company_account_id' => $this->kotakBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => false,
            'include_in_income' => false,
            'include_in_expense' => true,
            'include_in_pl' => false,
            'settlement_behavior' => 'decrease',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->cashSalesType->id,
            'company_account_id' => $this->cashBox->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'settlement_behavior' => 'increase',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        $this->dailyLedgerService = app(DailyLedgerService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
    }

    public function test_approved_pending_shop_collection_can_be_verified(): void
    {
        $record = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'notes' => 'Paytm batch #882',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $record['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->firstOrFail();

        $this->assertFalse((bool) $statement->is_finalized);
        $this->assertSame(100000.00, (float) $this->kotakBank->fresh()->current_balance);

        // Perform Verify
        $verifiedStatement = $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);

        // 1. Same statement is finalized
        $this->assertSame($statement->id, $verifiedStatement->id);
        $this->assertTrue((bool) $verifiedStatement->is_finalized);
        $this->assertSame('reconciled', $verifiedStatement->status);
        $this->assertSame(48962.00, (float) $verifiedStatement->matched_amount);
        $this->assertSame($this->admin->id, (int) $verifiedStatement->reconciled_by);
        $this->assertNotNull($verifiedStatement->reconciled_at);

        // 2. Exactly one statement entry exists
        $this->assertSame(1, CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->count());

        // 3. Bank balance increases by exact amount
        $this->assertSame(148962.00, (float) $this->kotakBank->fresh()->current_balance);

        // 4. Double entry Journal is created
        $this->assertNotNull($verifiedStatement->journal_entry_id);
        $journal = JournalEntry::with('transactions.account')->find($verifiedStatement->journal_entry_id);
        $this->assertNotNull($journal);
        $this->assertSame(48962.00, (float) $journal->total_debit);
        $this->assertTrue($journal->is_balanced);

        // 5. shop_paid_company settlement transaction is created
        $settlementTx = ShopLedgerTransaction::where('shop_id', $this->sana->id)
            ->where('reference_type', CompanyAccountStatementEntry::class)
            ->where('reference_id', $statement->id)
            ->firstOrFail();

        $this->assertSame('shop_paid_company', $settlementTx->entryType?->code);
        $this->assertSame(48962.00, (float) $settlementTx->amount);
        $this->assertSame(-48962.00, (float) $settlementTx->settlement_delta);

        // 6. Shop Daily Snapshot closing payable position is reduced
        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->sana->id)
            ->whereDate('business_date', '2026-08-22')
            ->firstOrFail();

        // 48962 collection (increase) - 48962 verified settlement (decrease) = 0.00 net closing position
        $this->assertSame(0.00, (float) $snapshot->closing_shop_position);
    }

    public function test_unapproved_collection_cannot_be_verified(): void
    {
        $record = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $record['transaction'];

        // Statement created manually or before approval
        $statement = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->kotakBank->id,
            'transaction_date' => '2026-08-22',
            'value_date' => '2026-08-22',
            'direction' => 'in',
            'amount' => 15000.00,
            'reference' => 'TEST-UNAPPROVED',
            'source' => 'shop_collection',
            'source_type' => ShopLedgerTransaction::class,
            'source_id' => $transaction->id,
            'status' => 'unmatched',
            'is_finalized' => false,
            'matched_amount' => 0.00,
        ]);

        $this->expectException(ValidationException::class);
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);
    }

    public function test_repeated_verification_is_idempotent(): void
    {
        $record = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $record['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->firstOrFail();

        // First verification
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);
        $balanceAfterFirst = (float) $this->kotakBank->fresh()->current_balance;
        $this->assertSame(120000.00, $balanceAfterFirst);

        // Second verification
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);
        $balanceAfterSecond = (float) $this->kotakBank->fresh()->current_balance;

        // Balance NOT incremented twice
        $this->assertSame(120000.00, $balanceAfterSecond);

        // Only one shop_paid_company created
        $this->assertSame(1, ShopLedgerTransaction::where('shop_id', $this->sana->id)
            ->where('reference_type', CompanyAccountStatementEntry::class)
            ->where('reference_id', $statement->id)
            ->count());
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $record = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $record['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->firstOrFail();

        // Corrupt statement amount to test mismatch guard
        $statement->update(['amount' => 12000.00]);

        $this->expectException(ValidationException::class);
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);
    }

    public function test_voided_pending_entry_cannot_be_verified(): void
    {
        $record = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $record['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->firstOrFail();

        // Void transaction
        $this->dailyLedgerService->voidEntry((int) $transaction->id, $this->admin->id, 'Customer payment returned');

        $this->expectException(ValidationException::class);
        $this->reconciliationService->verifyPendingShopCollection($statement->fresh(), $this->admin->id);
    }

    public function test_cash_handover_verify_received_settles_cash_box(): void
    {
        $record = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 16550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $record['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->firstOrFail();

        // Before verify: Cash Box balance unchanged at 50,000.00
        $this->assertSame(50000.00, (float) $this->cashBox->fresh()->current_balance);

        // Verify physical cash handover
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);

        // After verify: Cash Box received cash (+16,550)
        $this->assertSame(66550.00, (float) $this->cashBox->fresh()->current_balance);

        // Shop closing position settled
        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->sana->id)
            ->whereDate('business_date', '2026-08-22')
            ->firstOrFail();
        $this->assertSame(0.00, (float) $snapshot->closing_shop_position);
    }

    public function test_web_route_bank_account_statement_verify(): void
    {
        $record = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 7500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $record['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->firstOrFail();

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.bank-accounts.statement.verify', [
                'account' => $this->kotakBank,
                'statementRef' => $statement->secureRouteKey(),
            ])
        );

        $response->assertRedirect();
        $this->assertTrue((bool) $statement->fresh()->is_finalized);
        $this->assertSame(107500.00, (float) $this->kotakBank->fresh()->current_balance);
    }

    public function test_api_route_verify_shop_collection_statement(): void
    {
        $record = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 9200.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $record['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->firstOrFail();

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.finance.reconciliation.verify-shop-collection', [
                'statement' => $statement->public_uuid,
            ])
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Shop collection verified and reconciled.',
            ]);

        $this->assertTrue((bool) $statement->fresh()->is_finalized);
        $this->assertSame(109200.00, (float) $this->kotakBank->fresh()->current_balance);
    }

    public function test_category_isolation_verifies_only_exact_source_transaction(): void
    {
        // Sana records Gross Sales composed of Paytm (₹48,962) and Card (₹12,000)
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->cardType->id,
            'company_account_id' => $this->cashBox->id, // Card mapped to Cash Box
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'settlement_behavior' => 'increase',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        $paytmRecord = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $cardRecord = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cardType->code,
            'amount' => 12000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->dailyLedgerService->approveEntry($paytmRecord['transaction'], $this->admin->id);
        $this->dailyLedgerService->approveEntry($cardRecord['transaction'], $this->admin->id);

        $paytmStmt = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $paytmRecord['transaction']->id)
            ->firstOrFail();

        $cardStmt = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $cardRecord['transaction']->id)
            ->firstOrFail();

        // Verify only Paytm
        $this->reconciliationService->verifyPendingShopCollection($paytmStmt, $this->admin->id);

        // Paytm statement is finalized, Card statement remains pending
        $this->assertTrue((bool) $paytmStmt->fresh()->is_finalized);
        $this->assertFalse((bool) $cardStmt->fresh()->is_finalized);

        // Kotak Bank received Paytm (100,000 + 48,962), Cash Box unchanged at 50,000
        $this->assertSame(148962.00, (float) $this->kotakBank->fresh()->current_balance);
        $this->assertSame(50000.00, (float) $this->cashBox->fresh()->current_balance);

        // Shop closing position: 48,962 + 12,000 - 48,962 (Paytm settled) = 12,000 remaining payable
        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->sana->id)
            ->whereDate('business_date', '2026-08-22')
            ->firstOrFail();
        $this->assertSame(12000.00, (float) $snapshot->closing_shop_position);
    }
}
