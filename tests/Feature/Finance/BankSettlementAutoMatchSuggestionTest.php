<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopBankSettlementAdjustment;
use App\Models\Cashbook\ShopBankSettlementAdjustmentRule;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\ReconciliationAutoMatchSuggestionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankSettlementAutoMatchSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop1;

    private Shop $shop2;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private CompanyAccount $hdfcBank;

    private CompanyAccount $iciciBank;

    private ReconciliationAutoMatchSuggestionService $suggestionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->shop1 = Shop::factory()->create(['name' => 'Sana JP', 'code' => 'SANA_JP', 'status' => 'active']);
        $this->shop2 = Shop::factory()->create(['name' => 'Grandcity', 'code' => 'GRANDCITY', 'status' => 'active']);

        ShopLedgerProfile::create([
            'shop_id' => $this->shop1->id,
            'name' => $this->shop1->name,
            'code' => $this->shop1->code,
            'slug' => 'sana-jp',
            'uuid' => (string) str()->uuid(),
            'status' => 'active',
            'is_active' => true,
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $this->shop2->id,
            'name' => $this->shop2->name,
            'code' => $this->shop2->code,
            'slug' => 'grandcity',
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

        $this->cardType = LedgerEntryType::create([
            'name' => 'Card',
            'code' => 'card',
            'category' => 'income',
            'active' => true,
            'display_order' => 2,
        ]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC Current Account',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'current_balance' => 500000.00,
            'enabled' => true,
        ]);

        $this->iciciBank = CompanyAccount::create([
            'name' => 'ICICI Current Account',
            'account_type' => 'bank',
            'bank_name' => 'ICICI Bank',
            'current_balance' => 200000.00,
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

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->cardType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'company_account_id' => $this->hdfcBank->id,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_payable' => false,
        ]);

        $this->suggestionService = app(ReconciliationAutoMatchSuggestionService::class);
    }

    private function createTx(int $shopId, string $date, int $entryTypeId, float $amount, ?int $companyAccountId = null): ShopLedgerTransaction
    {
        return ShopLedgerTransaction::create([
            'shop_id' => $shopId,
            'business_date' => $date,
            'entry_type_id' => $entryTypeId,
            'direction' => 'income',
            'amount' => $amount,
            'company_account_id' => $companyAccountId ?? $this->hdfcBank->id,
            'funding_source' => 'none',
            'status' => 'approved',
            'entered_by' => $this->admin->id,
        ]);
    }

    private function createStatement(int $accountId, string $date, float $amount, string $direction = 'in', ?string $reference = null): CompanyAccountStatementEntry
    {
        return CompanyAccountStatementEntry::create([
            'company_account_id' => $accountId,
            'transaction_date' => $date,
            'amount' => $amount,
            'direction' => $direction,
            'reference' => $reference ?? 'STMT-'.uniqid(),
            'narration' => 'Statement Deposit',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);
    }

    public function test_a_existing_flow_unchanged_without_adjustments(): void
    {
        $date = '2026-08-29';
        $tx = $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);
        $stmt = $this->createStatement($this->hdfcBank->id, $date, 20000.00);

        $results = $this->suggestionService->suggest(collect([$tx]), 2);
        $res = $results->first();

        $this->assertSame('SUGGESTED', $res->reconciliation_status);
        $this->assertSame('SUGGESTED', $res->suggestion['status']);
        $this->assertSame('high', $res->suggestion['confidence']);
        $this->assertSame($stmt->id, $res->suggestion['statement_entry_id']);
        $this->assertSame(20000.00, (float) $res->suggestion['statement_amount']);
    }

    public function test_b_minus_adjustment_exact_match(): void
    {
        $date = '2026-08-29';
        $tx = $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);

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

        $stmt = $this->createStatement($this->hdfcBank->id, $date, 18000.00);

        $results = $this->suggestionService->suggest(collect([$tx]), 2);
        $res = $results->first();

        $this->assertSame('SUGGESTED', $res->reconciliation_status);
        $this->assertSame('SUGGESTED', $res->suggestion['status']);
        $this->assertSame('high', $res->suggestion['confidence']);
        $this->assertSame($stmt->id, $res->suggestion['statement_entry_id']);
        $this->assertSame(18000.00, (float) $res->suggestion['statement_amount']);
        $this->assertSame(20000.00, (float) $res->suggestion['base_collection_amount']);
        $this->assertSame(-2000.00, (float) $res->suggestion['adjustment_total']);
        $this->assertSame(18000.00, (float) $res->suggestion['expected_bank_amount']);
        $this->assertSame(20000.00, (float) $tx->fresh()->amount); // raw amount preserved!
    }

    public function test_c_raw_amount_must_not_win_when_adjustment_exists(): void
    {
        $date = '2026-08-29';
        $tx = $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);

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

        // Statement is 20,000 (raw base amount), but expected is 18,000
        $stmt20k = $this->createStatement($this->hdfcBank->id, $date, 20000.00);

        $results = $this->suggestionService->suggest(collect([$tx]), 2);
        $res = $results->first();

        // Must NOT match 20,000
        $this->assertSame('NEEDS_REVIEW', $res->reconciliation_status);
        $this->assertSame('NO_MATCH', $res->suggestion['status']);
    }

    public function test_d_plus_adjustment_exact_match(): void
    {
        $date = '2026-08-29';
        $tx = $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);

        $bonusRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Bonus',
            'direction' => 'plus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $bonusRule->id,
            'label' => 'Bonus',
            'direction' => 'plus',
            'amount' => 500.00,
        ]);

        $stmt = $this->createStatement($this->hdfcBank->id, $date, 20500.00);

        $results = $this->suggestionService->suggest(collect([$tx]), 2);
        $res = $results->first();

        $this->assertSame('SUGGESTED', $res->reconciliation_status);
        $this->assertSame($stmt->id, $res->suggestion['statement_entry_id']);
        $this->assertSame(20500.00, (float) $res->suggestion['statement_amount']);
        $this->assertSame(20500.00, (float) $res->suggestion['expected_bank_amount']);
    }

    public function test_e_multiple_adjustments_exact_match(): void
    {
        $date = '2026-08-29';
        $tx = $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);

        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        $bonusRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Bonus',
            'direction' => 'plus',
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

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $bonusRule->id,
            'label' => 'Bonus',
            'direction' => 'plus',
            'amount' => 500.00,
        ]);

        // Expected = 20000 - 2000 + 500 = 18500
        $stmt = $this->createStatement($this->hdfcBank->id, $date, 18500.00);

        $results = $this->suggestionService->suggest(collect([$tx]), 2);
        $res = $results->first();

        $this->assertSame('SUGGESTED', $res->reconciliation_status);
        $this->assertSame($stmt->id, $res->suggestion['statement_entry_id']);
        $this->assertSame(18500.00, (float) $res->suggestion['statement_amount']);
        $this->assertSame(-1500.00, (float) $res->suggestion['adjustment_total']);
        $this->assertSame(18500.00, (float) $res->suggestion['expected_bank_amount']);
    }

    public function test_f_other_shop_unaffected(): void
    {
        $date = '2026-08-29';
        // Shop 1 has adjustment
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

        // Shop 2 transaction with 20000 on same date
        $txShop2 = $this->createTx($this->shop2->id, $date, $this->paytmType->id, 20000.00);
        $stmt20k = $this->createStatement($this->hdfcBank->id, $date, 20000.00);

        $results = $this->suggestionService->suggest(collect([$txShop2]), 2);
        $res = $results->first();

        $this->assertSame('SUGGESTED', $res->reconciliation_status);
        $this->assertSame($stmt20k->id, $res->suggestion['statement_entry_id']);
        $this->assertSame(20000.00, (float) $res->suggestion['statement_amount']);
    }

    public function test_g_other_category_unaffected(): void
    {
        $date = '2026-08-29';
        // Adjustment on Paytm only
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

        // Card transaction for 20000
        $txCard = $this->createTx($this->shop1->id, $date, $this->cardType->id, 20000.00);
        $stmt20k = $this->createStatement($this->hdfcBank->id, $date, 20000.00);

        $results = $this->suggestionService->suggest(collect([$txCard]), 2);
        $res = $results->first();

        $this->assertSame('SUGGESTED', $res->reconciliation_status);
        $this->assertSame($stmt20k->id, $res->suggestion['statement_entry_id']);
        $this->assertSame(20000.00, (float) $res->suggestion['statement_amount']);
    }

    public function test_h_other_date_unaffected(): void
    {
        // Adjustment on 2026-08-29
        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-29',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        // Transaction on 2026-08-30
        $txDate2 = $this->createTx($this->shop1->id, '2026-08-30', $this->paytmType->id, 20000.00);
        $stmt20k = $this->createStatement($this->hdfcBank->id, '2026-08-30', 20000.00);

        $results = $this->suggestionService->suggest(collect([$txDate2]), 2);
        $res = $results->first();

        $this->assertSame('SUGGESTED', $res->reconciliation_status);
        $this->assertSame($stmt20k->id, $res->suggestion['statement_entry_id']);
        $this->assertSame(20000.00, (float) $res->suggestion['statement_amount']);
    }

    public function test_i_wrong_account_no_match(): void
    {
        $date = '2026-08-29';
        $tx = $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00, $this->hdfcBank->id);

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

        // 18,000 deposited into ICICI Bank (different bank account)
        $this->createStatement($this->iciciBank->id, $date, 18000.00);

        $results = $this->suggestionService->suggest(collect([$tx]), 2);
        $res = $results->first();

        $this->assertSame('NEEDS_REVIEW', $res->reconciliation_status);
        $this->assertSame('NO_MATCH', $res->suggestion['status']);
    }

    public function test_j_ambiguous_candidates_preserved(): void
    {
        $date = '2026-08-29';
        $tx = $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);

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

        // Two statements with 18,000 on the same date -> ambiguous
        $stmt1 = $this->createStatement($this->hdfcBank->id, $date, 18000.00);
        $stmt2 = $this->createStatement($this->hdfcBank->id, $date, 18000.00);

        $results = $this->suggestionService->suggest(collect([$tx]), 2);
        $res = $results->first();

        $this->assertSame('NEEDS_REVIEW', $res->reconciliation_status);
        $this->assertSame('NEEDS_REVIEW', $res->suggestion['status']);
        $this->assertStringContainsString('possible exact-date matches', $res->suggestion['reason']);
    }

    public function test_k_accounting_invariants_preserved(): void
    {
        $date = '2026-08-29';
        $tx = $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);

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

        $stmt = $this->createStatement($this->hdfcBank->id, $date, 18000.00);

        $initialJournalCount = JournalEntry::count();
        $initialBalance = (float) $this->hdfcBank->fresh()->current_balance;
        $initialStatementCount = CompanyAccountStatementEntry::count();
        $initialReconciliationCount = CompanyPaymentReconciliation::count();

        // Run suggestions
        $results = $this->suggestionService->suggest(collect([$tx]), 2);

        // Verification of invariants
        $this->assertSame(20000.00, (float) $tx->fresh()->amount);
        $this->assertSame(18000.00, (float) $stmt->fresh()->amount);
        $this->assertSame($initialJournalCount, JournalEntry::count());
        $this->assertSame($initialBalance, (float) $this->hdfcBank->fresh()->current_balance);
        $this->assertSame($initialStatementCount, CompanyAccountStatementEntry::count());
        $this->assertSame($initialReconciliationCount, CompanyPaymentReconciliation::count());
    }
}
