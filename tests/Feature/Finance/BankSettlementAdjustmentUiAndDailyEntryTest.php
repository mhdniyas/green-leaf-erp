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
use App\Services\Cashbook\CompanyMoneyPositionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankSettlementAdjustmentUiAndDailyEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Shop $shop1;

    private Shop $shop2;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private CompanyAccount $hdfcAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@greenleaf.test',
        ]);
        $this->admin->assignRole('admin');

        $this->unauthorizedUser = User::factory()->create([
            'email' => 'purchaser@greenleaf.test',
        ]);
        $this->unauthorizedUser->assignRole('purchaser');

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

        $this->hdfcAccount = CompanyAccount::create([
            'name' => 'HDFC Bank',
            'bank_name' => 'HDFC',
            'account_type' => 'bank',
            'account_number' => '1234567890',
            'current_balance' => 500000.00,
            'enabled' => true,
            'is_default' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'company_account_id' => $this->hdfcAccount->id,
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
            'company_account_id' => $this->hdfcAccount->id,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_payable' => false,
        ]);
    }

    private function createTx(int $shopId, string $date, int $entryTypeId, float $amount): ShopLedgerTransaction
    {
        return ShopLedgerTransaction::create([
            'shop_id' => $shopId,
            'business_date' => $date,
            'entry_type_id' => $entryTypeId,
            'direction' => 'income',
            'amount' => $amount,
            'funding_source' => 'none',
            'status' => 'approved',
            'entered_by' => $this->admin->id,
        ]);
    }

    public function test_a_existing_shop_without_rules_renders_standard_view_and_fallback_amounts(): void
    {
        $date = '2026-08-29';
        $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => 'sana-jp', 'date' => $date]));

        $response->assertOk();
        $response->assertSee('₹20,000.00');

        $moneyService = app(CompanyMoneyPositionService::class);
        $summary = $moneyService->getShopDaySettlementOperationalSummary($this->shop1->id, $date);
        $col = collect($summary['collections'])->firstWhere('entry_type_id', $this->paytmType->id);

        $this->assertNotNull($col);
        $this->assertFalse($col['has_bank_adjustment_rules']);
        $this->assertSame(20000.0, $col['expected_bank_amount']);
        $this->assertSame(0.0, $col['adjustment_total']);
    }

    public function test_b_and_c_admin_can_create_minus_and_plus_rules(): void
    {
        // Minus rule: Rent
        $minusRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.api.shop-settings.bank-adjustment-rules.save'),
            [
                'shop_id' => $this->shop1->id,
                'entry_type_id' => $this->paytmType->id,
                'label' => 'Rent',
                'direction' => 'minus',
                'enabled' => 1,
            ]
        );

        $minusRes->assertOk();
        $minusRes->assertJson(['success' => true]);

        $this->assertDatabaseHas('shop_bank_settlement_adjustment_rules', [
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        // Plus rule: Other Addition
        $plusRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.api.shop-settings.bank-adjustment-rules.save'),
            [
                'shop_id' => $this->shop1->id,
                'entry_type_id' => $this->paytmType->id,
                'label' => 'Other Addition',
                'direction' => 'plus',
                'enabled' => 1,
            ]
        );

        $plusRes->assertOk();
        $plusRes->assertJson(['success' => true]);

        $this->assertDatabaseHas('shop_bank_settlement_adjustment_rules', [
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Other Addition',
            'direction' => 'plus',
            'enabled' => true,
        ]);
    }

    public function test_d_and_e_admin_can_save_daily_adjustments_and_derive_expected_bank(): void
    {
        $date = '2026-08-29';
        $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);

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

        // Save daily adjustments: Rent -2000, Other +500
        $res = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.api.shops.bank-settlement-adjustments.save', 'sana-jp'),
            [
                'business_date' => $date,
                'entry_type_id' => $this->paytmType->id,
                'adjustments' => [
                    ['rule_id' => $rentRule->id, 'amount' => 2000.00, 'notes' => 'August Rent portion'],
                    ['rule_id' => $otherRule->id, 'amount' => 500.00, 'notes' => 'Bonus promo addition'],
                ],
            ]
        );

        $res->assertOk();
        $res->assertJson([
            'success' => true,
            'resolved' => [
                'base_amount' => 20000.0,
                'plus_adjustments' => 500.0,
                'minus_adjustments' => 2000.0,
                'adjustment_total' => -1500.0,
                'expected_amount' => 18500.0,
            ],
        ]);

        // Check Shop Day summary output
        $moneyService = app(CompanyMoneyPositionService::class);
        $summary = $moneyService->getShopDaySettlementOperationalSummary($this->shop1->id, $date);
        $col = collect($summary['collections'])->firstWhere('entry_type_id', $this->paytmType->id);

        $this->assertTrue($col['has_bank_adjustment_rules']);
        $this->assertSame(20000.0, $col['amount']);
        $this->assertSame(18500.0, $col['expected_bank_amount']);
        $this->assertSame(-1500.0, $col['adjustment_total']);
    }

    public function test_f_admin_can_edit_daily_adjustment_and_recalculate_expected_bank(): void
    {
        $date = '2026-08-29';
        $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);

        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        // Initial entry 2000
        ShopBankSettlementAdjustment::create([
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => $rentRule->label,
            'direction' => $rentRule->direction,
            'amount' => 2000.00,
        ]);

        // Edit to 1800
        $res = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.api.shops.bank-settlement-adjustments.save', 'sana-jp'),
            [
                'business_date' => $date,
                'entry_type_id' => $this->paytmType->id,
                'adjustments' => [
                    ['rule_id' => $rentRule->id, 'amount' => 1800.00, 'notes' => 'Corrected rent'],
                ],
            ]
        );

        $res->assertOk();
        $res->assertJson([
            'success' => true,
            'resolved' => [
                'base_amount' => 20000.0,
                'minus_adjustments' => 1800.0,
                'expected_amount' => 18200.0,
            ],
        ]);

        $this->assertDatabaseHas('shop_bank_settlement_adjustments', [
            'shop_id' => $this->shop1->id,
            'business_date' => $date,
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'amount' => 1800.00,
        ]);
    }

    public function test_g_zeroing_daily_adjustment_restores_expected_to_base_amount(): void
    {
        $date = '2026-08-29';
        $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);

        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        // Zero out
        $res = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.api.shops.bank-settlement-adjustments.save', 'sana-jp'),
            [
                'business_date' => $date,
                'entry_type_id' => $this->paytmType->id,
                'adjustments' => [
                    ['rule_id' => $rentRule->id, 'amount' => 0.00],
                ],
            ]
        );

        $res->assertOk();
        $res->assertJson([
            'success' => true,
            'resolved' => [
                'base_amount' => 20000.0,
                'minus_adjustments' => 0.0,
                'adjustment_total' => 0.0,
                'expected_amount' => 20000.0,
            ],
        ]);
    }

    public function test_h_shop_date_and_category_isolation(): void
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
            'business_date' => '2026-08-29',
            'entry_type_id' => $this->paytmType->id,
            'rule_id' => $rentRule->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'amount' => 2000.00,
        ]);

        // Shop 2 on same date & category -> unaffected
        $service = app(CompanyMoneyPositionService::class);
        $resShop2 = $service->getShopDaySettlementOperationalSummary($this->shop2->id, '2026-08-29');
        $this->assertEmpty($resShop2['collections']);

        // Shop 1 on different date (2026-08-30) -> unaffected
        $this->createTx($this->shop1->id, '2026-08-30', $this->paytmType->id, 20000.00);
        $resDate2 = $service->getShopDaySettlementOperationalSummary($this->shop1->id, '2026-08-30');
        $colDate2 = collect($resDate2['collections'])->firstWhere('entry_type_id', $this->paytmType->id);
        $this->assertSame(20000.0, $colDate2['expected_bank_amount']);
        $this->assertSame(0.0, $colDate2['adjustment_total']);

        // Shop 1 Card on 2026-08-29 -> unaffected
        $this->createTx($this->shop1->id, '2026-08-29', $this->cardType->id, 15000.00);
        $resCard = $service->getShopDaySettlementOperationalSummary($this->shop1->id, '2026-08-29');
        $colCard = collect($resCard['collections'])->firstWhere('entry_type_id', $this->cardType->id);
        $this->assertSame(15000.0, $colCard['expected_bank_amount']);
        $this->assertSame(0.0, $colCard['adjustment_total']);
    }

    public function test_i_unauthorized_user_is_forbidden(): void
    {
        $res = $this->actingAs($this->unauthorizedUser)->postJson(
            route('admin.cashbook.api.shop-settings.bank-adjustment-rules.save'),
            [
                'shop_id' => $this->shop1->id,
                'entry_type_id' => $this->paytmType->id,
                'label' => 'Rent',
                'direction' => 'minus',
            ]
        );

        $res->assertForbidden();

        $dailyRes = $this->actingAs($this->unauthorizedUser)->postJson(
            route('admin.cashbook.api.shops.bank-settlement-adjustments.save', 'sana-jp'),
            [
                'business_date' => '2026-08-29',
                'entry_type_id' => $this->paytmType->id,
                'adjustments' => [],
            ]
        );

        $dailyRes->assertForbidden();
    }

    public function test_j_accounting_invariants_preserved_after_rule_and_adjustment_writes(): void
    {
        $date = '2026-08-29';
        $tx = $this->createTx($this->shop1->id, $date, $this->paytmType->id, 20000.00);

        $initialJournalCount = JournalEntry::count();
        $initialBalance = (float) $this->hdfcAccount->fresh()->current_balance;
        $initialStatementCount = CompanyAccountStatementEntry::count();
        $initialReconciliationCount = CompanyPaymentReconciliation::count();

        // 1. Create rule
        $rentRule = ShopBankSettlementAdjustmentRule::create([
            'shop_id' => $this->shop1->id,
            'entry_type_id' => $this->paytmType->id,
            'label' => 'Rent',
            'direction' => 'minus',
            'enabled' => true,
        ]);

        // 2. Save daily adjustment
        $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.api.shops.bank-settlement-adjustments.save', 'sana-jp'),
            [
                'business_date' => $date,
                'entry_type_id' => $this->paytmType->id,
                'adjustments' => [
                    ['rule_id' => $rentRule->id, 'amount' => 2000.00],
                ],
            ]
        )->assertOk();

        // Invariants verification
        $this->assertSame(20000.00, (float) $tx->fresh()->amount);
        $this->assertSame($initialJournalCount, JournalEntry::count());
        $this->assertSame($initialBalance, (float) $this->hdfcAccount->fresh()->current_balance);
        $this->assertSame($initialStatementCount, CompanyAccountStatementEntry::count());
        $this->assertSame($initialReconciliationCount, CompanyPaymentReconciliation::count());
    }
}
