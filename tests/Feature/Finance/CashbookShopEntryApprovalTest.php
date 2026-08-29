<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookShopEntryApprovalTest extends TestCase
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

    private DailyLedgerService $dailyLedgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->sana = Shop::factory()->create(['name' => 'Sana', 'code' => 'SANA', 'status' => 'active']);
        $this->casio = Shop::factory()->create(['name' => 'Casio', 'code' => 'CASIO', 'status' => 'active']);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank',
            'account_type' => 'bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => 'KOTAK-11111111',
            'opening_balance' => 50000.00,
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);

        $this->cashBox = CompanyAccount::create([
            'name' => 'Company Cash Box',
            'account_type' => 'cash',
            'opening_balance' => 10000.00,
            'current_balance' => 10000.00,
            'enabled' => true,
        ]);

        $this->paytmType = LedgerEntryType::create([
            'name' => 'Paytm Online',
            'code' => 'paytm_online',
            'category' => 'income',
            'display_order' => 1,
            'active' => true,
        ]);

        $this->cardType = LedgerEntryType::create([
            'name' => 'Card Swap',
            'code' => 'card_swap',
            'category' => 'income',
            'display_order' => 2,
            'active' => true,
        ]);

        $this->cashSalesType = LedgerEntryType::create([
            'name' => 'Daily Cash Sales',
            'code' => 'daily_cash_sales',
            'category' => 'income',
            'display_order' => 3,
            'active' => true,
        ]);

        // Configure Sana settings: Paytm -> Kotak Bank, Cash -> No company account (held at shop)
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
            'entry_type_id' => $this->cashSalesType->id,
            'company_account_id' => null,
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
    }

    public function test_approval_changes_shop_transaction_status_to_approved(): void
    {
        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'notes' => 'Paytm collection #123',
            'entered_by' => $this->admin->id,
        ]);

        /** @var ShopLedgerTransaction $transaction */
        $transaction = $result['transaction'];
        $this->assertSame(TransactionStatus::Posted->value, $transaction->status);
        $this->assertNull($transaction->approved_by);

        $approvedTx = $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $this->assertSame(TransactionStatus::Approved->value, $approvedTx->status);
        $this->assertSame($this->admin->id, $approvedTx->approved_by);
    }

    public function test_approval_resolves_destination_and_creates_one_pending_statement(): void
    {
        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $result['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $statements = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->get();

        $this->assertCount(1, $statements);
        $statement = $statements->first();

        $this->assertSame($this->kotakBank->id, $statement->company_account_id);
        $this->assertSame('48962.00', (string) $statement->amount);
        $this->assertSame('2026-08-22', $statement->transaction_date->toDateString());
        $this->assertSame('in', $statement->direction);
        $this->assertFalse($statement->is_finalized);
        $this->assertSame('unmatched', $statement->status);
        $this->assertSame('0.00', (string) $statement->matched_amount);
        $this->assertFalse($transaction->fresh()->isReconciled());
    }

    public function test_approval_does_not_mutate_verified_current_balance(): void
    {
        $openingBalance = (float) $this->kotakBank->current_balance;

        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $result['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $this->kotakBank->refresh();
        $this->assertSame($openingBalance, (float) $this->kotakBank->current_balance);
    }

    public function test_approval_does_not_reduce_shop_payable_or_create_shop_paid_company(): void
    {
        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $result['transaction'];
        /** @var ShopDailyLedgerSnapshot $snapshotBefore */
        $snapshotBefore = $result['snapshot'];
        $payableBefore = (float) $snapshotBefore->closing_shop_position;
        $this->assertSame(15000.00, $payableBefore);

        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $snapshotAfter = ShopDailyLedgerSnapshot::where('shop_id', $this->sana->id)
            ->where('business_date', '2026-08-22')
            ->firstOrFail();

        $this->assertSame(15000.00, (float) $snapshotAfter->closing_shop_position);

        // Confirm no 'shop_paid_company' was created
        $paidCompanyCount = ShopLedgerTransaction::where('shop_id', $this->sana->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'shop_paid_company'))
            ->count();
        $this->assertSame(0, $paidCompanyCount);
    }

    public function test_repeated_approval_is_idempotent(): void
    {
        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $result['transaction'];

        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $statements = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->get();

        $this->assertCount(1, $statements);
    }

    public function test_entry_without_destination_account_approves_without_creating_statement(): void
    {
        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $result['transaction'];
        $approvedTx = $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $this->assertSame(TransactionStatus::Approved->value, $approvedTx->status);
        $this->assertNull($approvedTx->company_account_id);

        $statements = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->count();

        $this->assertSame(0, $statements);
    }

    public function test_updating_transaction_amount_synchronizes_pending_statement(): void
    {
        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $result['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->firstOrFail();
        $this->assertSame('10000.00', (string) $statement->amount);

        $this->dailyLedgerService->updateEntryAmount((int) $transaction->id, 12500.00, $this->admin->id);

        $statement->refresh();
        $this->assertSame('12500.00', (string) $statement->amount);
    }

    public function test_voiding_transaction_supersedes_pending_statement(): void
    {
        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 8000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $result['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        $this->dailyLedgerService->voidEntry((int) $transaction->id, $this->admin->id, 'Customer payment cancelled');

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->firstOrFail();

        $this->assertSame('superseded', $statement->status);
        $this->assertStringContainsString('Voided: Customer payment cancelled', (string) $statement->notes);
    }

    public function test_admin_api_approve_entry_endpoint(): void
    {
        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 7770.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $result['transaction'];

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.approve-entry'), [
            'transaction_id' => $transaction->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Entry approved.',
            ]);

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'id' => $transaction->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'company_account_id' => $this->kotakBank->id,
        ]);

        $this->assertDatabaseHas('cashbook_company_account_statement_entries', [
            'source_type' => ShopLedgerTransaction::class,
            'source_id' => $transaction->id,
            'company_account_id' => $this->kotakBank->id,
            'amount' => 7770.00,
            'is_finalized' => false,
            'status' => 'unmatched',
        ]);
    }

    public function test_cross_shop_setting_isolation(): void
    {
        // Casio has Card mapped to cashBox, Sana has Card mapped to Kotak
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cardType->id,
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

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->cardType->id,
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

        $casioResult = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cardType->code,
            'amount' => 3200.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $casioTx = $this->dailyLedgerService->approveEntry($casioResult['transaction'], $this->admin->id);

        $casioStmt = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $casioTx->id)
            ->firstOrFail();

        $this->assertSame($this->cashBox->id, $casioStmt->company_account_id);
    }

    public function test_statement_ui_shows_needs_verification_badge_for_pending_shop_entry(): void
    {
        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->dailyLedgerService->approveEntry($result['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.bank-accounts.statement', [
            'account' => $this->kotakBank,
            'month' => '2026-08',
        ]));

        $response->assertOk()
            ->assertSee('Needs Verification')
            ->assertSee('48,962.00');
    }

    public function test_reconciled_transaction_cannot_be_modified(): void
    {
        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 6000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $transaction = $result['transaction'];
        $this->dailyLedgerService->approveEntry($transaction, $this->admin->id);

        // Mark statement as finalized
        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->firstOrFail();
        $statement->update(['is_finalized' => true, 'status' => 'reconciled']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Reconciled transactions cannot be modified.');

        $this->dailyLedgerService->updateEntryAmount((int) $transaction->id, 9000.00, $this->admin->id);
    }
}
