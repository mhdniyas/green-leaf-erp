<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopBankSettlementAdjustment;
use App\Models\Cashbook\ShopBankSettlementAdjustmentRule;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\BankSettlementExpectedAmountService;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Cashbook\HistoricalBankCollectionFetchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankSettlementExpectedAmountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop1;

    private Shop $shop2;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private CompanyAccount $hdfcBank;

    private BankSettlementExpectedAmountService $expectedAmountService;

    private HistoricalBankCollectionFetchService $fetchService;

    private DailyLedgerService $dailyLedgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        Account::firstOrCreate(['code' => '1020'], ['name' => 'HDFC Bank Account', 'type' => 'asset', 'group' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'group' => 'asset', 'is_active' => true]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC Current Account',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);

        $this->shop1 = Shop::factory()->create(['name' => 'Shop One', 'code' => 'SH1', 'status' => 'active']);
        $this->shop2 = Shop::factory()->create(['name' => 'Shop Two', 'code' => 'SH2', 'status' => 'active']);

        ShopLedgerProfile::create([
            'shop_id' => $this->shop1->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'shop-one',
            'code' => $this->shop1->code,
            'name' => $this->shop1->name,
            'status' => 'active',
            'is_active' => true,
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $this->shop2->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'shop-two',
            'code' => $this->shop2->code,
            'name' => $this->shop2->name,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->paytmType = LedgerEntryType::firstOrCreate(
            ['code' => 'paytm'],
            ['name' => 'Paytm', 'category' => 'income', 'display_order' => 1, 'active' => true, 'is_system' => true]
        );

        $this->cardType = LedgerEntryType::firstOrCreate(
            ['code' => 'card'],
            ['name' => 'Card', 'category' => 'income', 'display_order' => 2, 'active' => true, 'is_system' => true]
        );

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
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

        $this->expectedAmountService = new BankSettlementExpectedAmountService;
        $this->fetchService = new HistoricalBankCollectionFetchService($this->expectedAmountService);
        $this->dailyLedgerService = app(DailyLedgerService::class);
    }

    /**
     * Test A: No rules configured -> fallback expected === base amount.
     */
    public function test_no_rules_returns_base_amount_exactly(): void
    {
        $res = $this->expectedAmountService->resolve(
            $this->shop1->id,
            '2026-08-28',
            $this->paytmType->id,
            20000.00
        );

        $this->assertEquals(20000.00, $res['base_amount']);
        $this->assertEquals(0.00, $res['plus_adjustments']);
        $this->assertEquals(0.00, $res['minus_adjustments']);
        $this->assertEquals(0.00, $res['adjustment_total']);
        $this->assertEquals(20000.00, $res['expected_amount']);
        $this->assertEmpty($res['adjustments']);
    }

    /**
     * Test B: Rule exists but disabled -> fallback expected === base amount.
     */
    public function test_disabled_rule_is_ignored_and_returns_base_amount(): void
    {
        $rule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => false,
            'created_by' => $this->admin->id,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
            'created_by' => $this->admin->id,
        ]);

        $res = $this->expectedAmountService->resolve(
            $this->shop1->id,
            '2026-08-28',
            $this->paytmType->id,
            20000.00
        );

        $this->assertEquals(20000.00, $res['expected_amount']);
        $this->assertEquals(0.00, $res['adjustment_total']);
    }

    /**
     * Test C: Minus adjustment reduces expected bank amount.
     */
    public function test_minus_adjustment_calculates_correct_expected_amount(): void
    {
        $rule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
            'created_by' => $this->admin->id,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
            'created_by' => $this->admin->id,
        ]);

        $res = $this->expectedAmountService->resolve(
            $this->shop1->id,
            '2026-08-28',
            $this->paytmType->id,
            20000.00
        );

        $this->assertEquals(20000.00, $res['base_amount']);
        $this->assertEquals(0.00, $res['plus_adjustments']);
        $this->assertEquals(2000.00, $res['minus_adjustments']);
        $this->assertEquals(-2000.00, $res['adjustment_total']);
        $this->assertEquals(18000.00, $res['expected_amount']);
        $this->assertCount(1, $res['adjustments']);
    }

    /**
     * Test D: Plus adjustment increases expected bank amount.
     */
    public function test_plus_adjustment_calculates_correct_expected_amount(): void
    {
        $rule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Reimbursement',
            'direction' => 'plus',
            'enabled' => true,
            'created_by' => $this->admin->id,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rule->id,
            'label' => 'Reimbursement',
            'direction' => 'plus',
            'amount' => 500.00,
            'created_by' => $this->admin->id,
        ]);

        $res = $this->expectedAmountService->resolve(
            $this->shop1->id,
            '2026-08-28',
            $this->paytmType->id,
            20000.00
        );

        $this->assertEquals(20000.00, $res['base_amount']);
        $this->assertEquals(500.00, $res['plus_adjustments']);
        $this->assertEquals(0.00, $res['minus_adjustments']);
        $this->assertEquals(500.00, $res['adjustment_total']);
        $this->assertEquals(20500.00, $res['expected_amount']);
    }

    /**
     * Test E: Mixed plus and minus adjustments.
     */
    public function test_mixed_plus_and_minus_adjustments(): void
    {
        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        $otherRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Other Addition',
            'direction' => 'plus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $otherRule->id,
            'label' => 'Other Addition',
            'direction' => 'plus',
            'amount' => 500.00,
        ]);

        $res = $this->expectedAmountService->resolve(
            $this->shop1->id,
            '2026-08-28',
            $this->paytmType->id,
            20000.00
        );

        $this->assertEquals(20000.00, $res['base_amount']);
        $this->assertEquals(500.00, $res['plus_adjustments']);
        $this->assertEquals(2000.00, $res['minus_adjustments']);
        $this->assertEquals(-1500.00, $res['adjustment_total']);
        $this->assertEquals(18500.00, $res['expected_amount']);
    }

    /**
     * Test F: Shop Isolation (same category and date for shop2 is unaffected).
     */
    public function test_shop_isolation(): void
    {
        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        // Shop 1 has adjustment -> 18,000
        $resShop1 = $this->expectedAmountService->resolve($this->shop1->id, '2026-08-28', $this->paytmType->id, 20000.00);
        $this->assertEquals(18000.00, $resShop1['expected_amount']);

        // Shop 2 has no adjustments -> exactly 20,000
        $resShop2 = $this->expectedAmountService->resolve($this->shop2->id, '2026-08-28', $this->paytmType->id, 20000.00);
        $this->assertEquals(20000.00, $resShop2['expected_amount']);
    }

    /**
     * Test G: Date Isolation (same shop and category for a different date is unaffected).
     */
    public function test_date_isolation(): void
    {
        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        // Aug 28 -> 18,000
        $resAug28 = $this->expectedAmountService->resolve($this->shop1->id, '2026-08-28', $this->paytmType->id, 20000.00);
        $this->assertEquals(18000.00, $resAug28['expected_amount']);

        // Aug 29 -> 20,000
        $resAug29 = $this->expectedAmountService->resolve($this->shop1->id, '2026-08-29', $this->paytmType->id, 20000.00);
        $this->assertEquals(20000.00, $resAug29['expected_amount']);
    }

    /**
     * Test H: Payment Category Isolation (Paytm adjustment does not affect Card).
     */
    public function test_category_isolation(): void
    {
        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        // Paytm -> 18,000
        $resPaytm = $this->expectedAmountService->resolve($this->shop1->id, '2026-08-28', $this->paytmType->id, 20000.00);
        $this->assertEquals(18000.00, $resPaytm['expected_amount']);

        // Card -> 5,000
        $resCard = $this->expectedAmountService->resolve($this->shop1->id, '2026-08-28', $this->cardType->id, 5000.00);
        $this->assertEquals(5000.00, $resCard['expected_amount']);
    }

    /**
     * Test I & J: Accounting invariants & original transaction amount preserved.
     */
    public function test_accounting_invariants_and_source_amount_untouched(): void
    {
        $recordResult = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_code' => 'paytm',
            'entry_type_id' => $this->paytmType->id,
            'amount' => 20000.00,
            'funding_source' => 'bank',
            'company_account_id' => $this->hdfcBank->id,
            'entered_by' => $this->admin->id,
        ]);

        $tx = $recordResult['transaction'];
        $initialBalance = (float) $this->hdfcBank->current_balance;
        $initialJournalsCount = JournalEntry::count();

        // Create adjustment
        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        // Assert:
        // 1. ShopLedgerTransaction.amount is still 20,000
        $tx->refresh();
        $this->assertEquals(20000.00, (float) $tx->amount);

        // 2. CompanyAccount balance untouched
        $this->hdfcBank->refresh();
        $this->assertEquals($initialBalance, (float) $this->hdfcBank->current_balance);

        // 3. Journal count unchanged
        $this->assertEquals($initialJournalsCount, JournalEntry::count());

        // 4. Resolved expected amount is 18,000
        $res = $this->expectedAmountService->resolve($this->shop1->id, '2026-08-28', $this->paytmType->id, (float) $tx->amount);
        $this->assertEquals(18000.00, $res['expected_amount']);
    }

    /**
     * Test K: Bulk resolution works with single grouped query.
     */
    public function test_bulk_resolution_with_grouped_query(): void
    {
        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        $items = [
            ['shop_id' => $this->shop1->id, 'business_date' => '2026-08-28', 'entry_type_id' => $this->paytmType->id, 'base_amount' => 20000.00],
            ['shop_id' => $this->shop1->id, 'business_date' => '2026-08-29', 'entry_type_id' => $this->paytmType->id, 'base_amount' => 15000.00],
            ['shop_id' => $this->shop2->id, 'business_date' => '2026-08-28', 'entry_type_id' => $this->paytmType->id, 'base_amount' => 12000.00],
        ];

        $bulkRes = $this->expectedAmountService->resolveBulk($items);

        $key1 = "{$this->shop1->id}_2026-08-28_{$this->paytmType->id}";
        $key2 = "{$this->shop1->id}_2026-08-29_{$this->paytmType->id}";
        $key3 = "{$this->shop2->id}_2026-08-28_{$this->paytmType->id}";

        $this->assertEquals(18000.00, $bulkRes->get($key1)['expected_amount']);
        $this->assertEquals(15000.00, $bulkRes->get($key2)['expected_amount']);
        $this->assertEquals(12000.00, $bulkRes->get($key3)['expected_amount']);
    }

    /**
     * Test L: HistoricalBankCollectionFetchService preview uses expected amount.
     */
    public function test_historical_fetch_preview_exposes_expected_and_base_amounts(): void
    {
        $recordResult = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_code' => 'paytm',
            'entry_type_id' => $this->paytmType->id,
            'amount' => 20000.00,
            'funding_source' => 'bank',
            'entered_by' => $this->admin->id,
        ]);

        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-08-28',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        $recordResult['transaction']->update(['company_account_id' => null]);

        $preview = $this->fetchService->preview(
            $this->shop1->id,
            $this->paytmType->id,
            $this->hdfcBank->id,
            '2026-08-28',
            '2026-08-28'
        );

        $this->assertEquals(1, $preview['source_count']);
        $this->assertEquals(20000.00, $preview['source_base_amount']);
        $this->assertEquals(-2000.00, $preview['source_adjustment_amount']);
        $this->assertEquals(18000.00, $preview['source_expected_amount']);
        $this->assertEquals(18000.00, $preview['source_amount']);

        $this->assertEquals(1, $preview['eligible_count']);
        $this->assertEquals(20000.00, $preview['eligible_base_amount']);
        $this->assertEquals(-2000.00, $preview['eligible_adjustment_amount']);
        $this->assertEquals(18000.00, $preview['eligible_expected_amount']);
        $this->assertEquals(18000.00, $preview['eligible_amount']);
    }
}
