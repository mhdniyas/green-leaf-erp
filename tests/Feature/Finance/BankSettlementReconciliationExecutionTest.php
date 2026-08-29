<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopBankSettlementAdjustment;
use App\Models\Cashbook\ShopBankSettlementAdjustmentRule;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Cashbook\ShopCollectionAutoMatchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class BankSettlementReconciliationExecutionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop1;

    private LedgerEntryType $paytmType;

    private CompanyAccount $hdfcBank;

    private DailyLedgerService $dailyLedgerService;

    private CompanyPaymentReconciliationService $reconciliationService;

    private ShopCollectionAutoMatchService $autoMatchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        Account::firstOrCreate(['code' => '1020'], ['name' => 'HDFC Bank Account', 'type' => 'asset', 'group' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'group' => 'asset', 'is_active' => true]);

        $this->shop1 = Shop::factory()->create(['name' => 'Sana JP', 'code' => 'SANA_JP', 'status' => 'active']);

        ShopLedgerProfile::create([
            'shop_id' => $this->shop1->id,
            'name' => $this->shop1->name,
            'code' => $this->shop1->code,
            'slug' => 'sana-jp',
            'uuid' => (string) str()->uuid(),
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->paytmType = LedgerEntryType::create([
            'name' => 'Paytm',
            'code' => 'paytm',
            'category' => 'income',
            'active' => true,
            'display_order' => 1,
        ]);

        LedgerEntryType::create([
            'name' => 'Shop Paid Company',
            'code' => 'shop_paid_company',
            'category' => 'expense',
            'active' => true,
            'display_order' => 99,
        ]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC Current Account',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'current_balance' => 500000.00,
            'enabled' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'company_account_id' => $this->hdfcBank->id,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_payable' => false,
        ]);

        $this->dailyLedgerService = app(DailyLedgerService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
        $this->autoMatchService = app(ShopCollectionAutoMatchService::class);
    }

    public function test_adjusted_collection_approval_creates_pending_statement_at_expected_payment_amount(): void
    {
        $date = '2026-08-29';

        // 1. Configure minus adjustment: Rent -2000
        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        // 2. Record and Approve Shop Transaction of ₹20,000
        $res = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_code' => 'paytm',
            'amount' => 20000.00,
            'funding_source' => 'none',
            'status' => 'draft',
            'entered_by' => $this->admin->id,
        ]);

        $tx = $res['transaction'];
        $approvedTx = $this->dailyLedgerService->approveEntry($tx, $this->admin->id);

        // Verification:
        // ShopLedgerTransaction.amount MUST stay ₹20,000
        $this->assertSame(20000.00, (float) $approvedTx->fresh()->amount);

        // Pending statement MUST be created for expected payment ₹18,000
        $pendingStmt = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $tx->id)
            ->first();

        $this->assertNotNull($pendingStmt);
        $this->assertSame(18000.00, (float) $pendingStmt->amount);
        $this->assertSame($this->hdfcBank->id, (int) $pendingStmt->company_account_id);
        $this->assertFalse($pendingStmt->is_finalized);
    }

    public function test_auto_match_execution_reconciles_expected_amount_without_mutating_collection(): void
    {
        $date = '2026-08-29';

        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 20000.00,
            'direction' => 'income',
            'funding_source' => 'none',
            'company_account_id' => $this->hdfcBank->id,
            'status' => 'posted',
            'entered_by' => $this->admin->id,
        ]);

        // Real bank statement imported with ₹18,000
        $realStmt = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcBank->id,
            'transaction_date' => $date,
            'amount' => 18000.00,
            'direction' => 'in',
            'reference' => 'BANK-PAYTM-18K',
            'narration' => 'Paytm settlement',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        // Execute Auto-Match
        $execRes = $this->autoMatchService->execute('2026-08-01', '2026-08-31', $this->hdfcBank->id, $this->admin->id);

        $this->assertSame(1, $execRes['reconciled_count']);
        $this->assertSame(0, $execRes['skipped_count']);

        // Assert statement is finalized and reconciled
        $this->assertTrue($realStmt->fresh()->is_finalized);
        $this->assertSame('reconciled', $realStmt->fresh()->status);
        $this->assertSame($tx->id, (int) $realStmt->fresh()->source_id);

        // Assert original collection amount remains ₹20,000
        $this->assertSame(20000.00, (float) $tx->fresh()->amount);
    }

    public function test_verify_pending_shop_collection_uses_verified_amount_and_prevents_inflation(): void
    {
        $date = '2026-08-29';
        $initialBankBalance = (float) $this->hdfcBank->current_balance;

        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        $res = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_code' => 'paytm',
            'amount' => 20000.00,
            'funding_source' => 'none',
            'status' => 'draft',
            'entered_by' => $this->admin->id,
        ]);
        $tx = $this->dailyLedgerService->approveEntry($res['transaction'], $this->admin->id);

        $pendingStmt = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $tx->id)
            ->firstOrFail();

        $this->assertSame(18000.00, (float) $pendingStmt->amount);

        // Verify the pending statement
        $finalizedStmt = $this->reconciliationService->verifyPendingShopCollection($pendingStmt, $this->admin->id);

        $this->assertTrue($finalizedStmt->is_finalized);
        $this->assertSame('reconciled', $finalizedStmt->status);

        // 1. Bank Balance movement must be +₹18,000 (NOT ₹20,000)
        $this->assertSame($initialBankBalance + 18000.00, (float) $this->hdfcBank->fresh()->current_balance);

        // 2. Journal Entry must be for ₹18,000
        $journal = JournalEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $tx->id)
            ->firstOrFail();
        $this->assertSame(18000.00, (float) $journal->primary_amount);

        // 3. Original collection remains ₹20,000
        $this->assertSame(20000.00, (float) $tx->fresh()->amount);
    }

    public function test_manual_confirm_suggestion_succeeds_for_expected_payment_amount(): void
    {
        $date = '2026-08-29';

        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        $res = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_code' => 'paytm',
            'amount' => 20000.00,
            'funding_source' => 'none',
            'status' => 'draft',
            'entered_by' => $this->admin->id,
        ]);
        $tx = $this->dailyLedgerService->approveEntry($res['transaction'], $this->admin->id);

        $realStmt = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcBank->id,
            'transaction_date' => $date,
            'amount' => 18000.00,
            'direction' => 'in',
            'reference' => 'REAL-PAYTM-18K',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $candidateRef = rtrim(strtr(base64_encode(Crypt::encryptString('shop-ledger:'.$tx->id)), '+/', '-_'), '=');

        // Controller manual confirmation action
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.finance.reconciliation.confirm-suggestion', $realStmt),
            [
                'candidate_ref' => $candidateRef,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue($realStmt->fresh()->is_finalized);
        $this->assertSame('reconciled', $realStmt->fresh()->status);
        $this->assertSame(20000.00, (float) $tx->fresh()->amount);
    }

    public function test_normal_unadjusted_flow_remains_completely_unchanged(): void
    {
        $date = '2026-08-29';
        $initialBalance = (float) $this->hdfcBank->current_balance;

        $res = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_code' => 'paytm',
            'amount' => 20000.00,
            'funding_source' => 'none',
            'status' => 'draft',
            'entered_by' => $this->admin->id,
        ]);
        $tx = $this->dailyLedgerService->approveEntry($res['transaction'], $this->admin->id);

        $pendingStmt = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $tx->id)
            ->firstOrFail();

        $this->assertSame(20000.00, (float) $pendingStmt->amount);

        $finalizedStmt = $this->reconciliationService->verifyPendingShopCollection($pendingStmt, $this->admin->id);

        $this->assertTrue($finalizedStmt->is_finalized);
        $this->assertSame($initialBalance + 20000.00, (float) $this->hdfcBank->fresh()->current_balance);

        $journal = JournalEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $tx->id)
            ->firstOrFail();
        $this->assertSame(20000.00, (float) $journal->primary_amount);
        $this->assertSame(20000.00, (float) $tx->fresh()->amount);
    }
}
